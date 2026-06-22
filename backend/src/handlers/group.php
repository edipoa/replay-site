<?php

declare(strict_types=1);

use App\Database;
use App\TokenAuth;
use App\Mailer;

function getGroupVideos(): void
{
    $group = TokenAuth::resolveGroup() ?? TokenAuth::resolveUserGroup();
    if (!$group) {
        jsonResponse(401, ['error' => 'Token inválido ou assinatura expirada']);
        return;
    }

    $tz         = new DateTimeZone($_ENV['TZ'] ?? 'America/Sao_Paulo');
    $db         = Database::getInstance();
    $slotHour   = (int) $group['slot_hour'];
    $slotMinute = (int) ($group['slot_minute'] ?? 0);
    $weekday    = (int) $group['slot_weekday'];
    $durationM  = (int) ($group['slot_duration_m'] ?? 60);
    $startM     = $slotHour * 60 + $slotMinute;
    $endM       = $startM + $durationM;

    $tzName  = $tz->getName();
    $localTs = "HOUR(CONVERT_TZ(triggered_at,'UTC',:tz_name))*60+MINUTE(CONVERT_TZ(triggered_at,'UTC',:tz_name))";

    if ($endM < 1440) {
        // Slot não cruza meia-noite
        $sql = "SELECT id, seq, camera_id, duration_s, size_bytes, triggered_at, expires_at,
                       (thumbnail_key IS NOT NULL) AS has_thumbnail
                FROM videos
                WHERE CONVERT_TZ(triggered_at,'UTC',:tz_name) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                  AND WEEKDAY(CONVERT_TZ(triggered_at,'UTC',:tz_name)) = :weekday
                  AND ({$localTs}) >= :start_m
                  AND ({$localTs}) < :end_m
                  AND expires_at > NOW()
                ORDER BY triggered_at DESC";
        $params = [
            ':tz_name' => $tzName,
            ':weekday' => $weekday,
            ':start_m' => $startM,
            ':end_m'   => $endM,
        ];
    } else {
        // Slot cruza meia-noite (ex: 23:30 + 60min = 00:30)
        $nextWeekday = ($weekday + 1) % 7;
        $overflowM   = $endM - 1440;
        $sql = "SELECT id, seq, camera_id, duration_s, size_bytes, triggered_at, expires_at,
                       (thumbnail_key IS NOT NULL) AS has_thumbnail
                FROM videos
                WHERE CONVERT_TZ(triggered_at,'UTC',:tz_name) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                  AND (
                    (WEEKDAY(CONVERT_TZ(triggered_at,'UTC',:tz_name)) = :weekday
                     AND ({$localTs}) >= :start_m)
                    OR
                    (WEEKDAY(CONVERT_TZ(triggered_at,'UTC',:tz_name)) = :next_weekday
                     AND ({$localTs}) < :overflow_m)
                  )
                  AND expires_at > NOW()
                ORDER BY triggered_at DESC";
        $params = [
            ':tz_name'      => $tzName,
            ':weekday'      => $weekday,
            ':next_weekday' => $nextWeekday,
            ':start_m'      => $startM,
            ':overflow_m'   => $overflowM,
        ];
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $utc = new DateTimeZone('UTC');
        $row['triggered_at']  = (new DateTimeImmutable($row['triggered_at'], $utc))->format(DateTimeInterface::ATOM);
        $row['expires_at']    = (new DateTimeImmutable($row['expires_at'], $utc))->format(DateTimeInterface::ATOM);
        $row['seq']           = (int) $row['seq'];
        $row['display_id']    = sprintf('VC-%03d', $row['seq']);
        $row['duration_s']    = (int) $row['duration_s'];
        $row['size_bytes']    = (int) $row['size_bytes'];
        $row['has_thumbnail'] = (bool) $row['has_thumbnail'];
    }

    jsonResponse(200, $rows);
}

function getGroupSubscription(): void
{
    $group = TokenAuth::resolveGroupAllowExpired();
    if (!$group) {
        jsonResponse(401, ['error' => 'Token inválido']);
        return;
    }

    $db   = Database::getInstance();
    $stmt = $db->prepare('SELECT subscription_expires_at FROM `groups` WHERE id = :id');
    $stmt->execute([':id' => $group['id']]);
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);

    $expiresAt   = new DateTimeImmutable($row['subscription_expires_at'], new DateTimeZone('UTC'));
    $now         = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $daysLeft    = (int) $now->diff($expiresAt)->days;
    $expired     = $expiresAt <= $now;

    jsonResponse(200, [
        'expires_at'   => $expiresAt->format(DateTimeInterface::ATOM),
        'days_left'    => $expired ? 0 : $daysLeft,
        'status'       => $expired ? 'expired' : ($daysLeft <= 7 ? 'expiring' : 'active'),
        'price'        => (float) ($_ENV['PRICE_MONTHLY_SUBSCRIPTION'] ?? 59.90),
    ]);
}

function initiateGroupSubscription(): void
{
    $group = TokenAuth::resolveGroupAllowExpired();
    if (!$group) {
        jsonResponse(401, ['error' => 'Token inválido']);
        return;
    }

    $body   = json_decode(file_get_contents('php://input'), true);
    $method = $body['method'] ?? 'pix';
    if (!in_array($method, ['pix', 'credit_card'], true)) {
        jsonResponse(400, ['error' => 'method deve ser pix ou credit_card']);
        return;
    }

    $amount    = (float) ($_ENV['PRICE_MONTHLY_SUBSCRIPTION'] ?? 59.90);
    $paymentId = bin2hex(random_bytes(16));

    $db = Database::getInstance();
    $db->prepare(
        'INSERT INTO subscription_payments (id, group_id, method, amount)
         VALUES (:id, :group_id, :method, :amount)'
    )->execute([
        ':id'       => $paymentId,
        ':group_id' => $group['id'],
        ':method'   => $method,
        ':amount'   => $amount,
    ]);

    $description = sprintf('Replay – Assinatura mensal – %s', $group['name']);

    if ($method === 'pix') {
        if (($_ENV['PIX_MOCK'] ?? 'false') === 'true') {
            grantSubscriptionAccess($db, $paymentId, $group['id']);
            jsonResponse(201, [
                'payment_id' => $paymentId,
                'method'     => 'pix',
                'mock'       => true,
                'amount'     => $amount,
                'expires_in' => 1800,
            ]);
            return;
        }

        try {
            $pix = \App\Mp::createPix($amount, $description, 'sub_' . $paymentId);
        } catch (\Exception $e) {
            $db->prepare('DELETE FROM subscription_payments WHERE id = :id')->execute([':id' => $paymentId]);
            jsonResponse(500, ['error' => 'Falha ao criar Pix: ' . $e->getMessage()]);
            return;
        }
        $db->prepare('UPDATE subscription_payments SET mp_payment_id = :mp_id WHERE id = :id')
           ->execute([':mp_id' => $pix['mp_id'], ':id' => $paymentId]);

        jsonResponse(201, [
            'payment_id'     => $paymentId,
            'method'         => 'pix',
            'qr_code'        => $pix['qr_code'],
            'qr_code_base64' => $pix['qr_code_base64'],
            'amount'         => $amount,
            'expires_in'     => 1800,
        ]);
    } else {
        $appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
        $apiUrl = rtrim($_ENV['API_URL'] ?? '', '/');
        try {
            $pref = \App\Mp::createPreference([
                'items' => [[
                    'title'       => $description,
                    'quantity'    => 1,
                    'unit_price'  => $amount,
                    'currency_id' => 'BRL',
                ]],
                'external_reference' => 'sub_' . $paymentId,
                'back_urls' => [
                    'success' => "{$appUrl}/group?sub_payment={$paymentId}",
                    'failure' => "{$appUrl}/group?sub_payment={$paymentId}&pstatus=failure",
                    'pending' => "{$appUrl}/group?sub_payment={$paymentId}&pstatus=pending",
                ],
                'notification_url' => "{$apiUrl}/api/webhooks/mercadopago",
            ]);
        } catch (\Exception $e) {
            $db->prepare('DELETE FROM subscription_payments WHERE id = :id')->execute([':id' => $paymentId]);
            jsonResponse(500, ['error' => 'Falha ao criar preferência: ' . $e->getMessage()]);
            return;
        }
        $db->prepare('UPDATE subscription_payments SET mp_preference_id = :pref_id WHERE id = :id')
           ->execute([':pref_id' => $pref['preference_id'], ':id' => $paymentId]);

        jsonResponse(201, [
            'payment_id'   => $paymentId,
            'method'       => 'credit_card',
            'checkout_url' => $pref['init_point'],
            'amount'       => $amount,
        ]);
    }
}

function getSubscriptionPaymentStatus(string $paymentId): void
{
    $db   = Database::getInstance();
    $stmt = $db->prepare('SELECT * FROM subscription_payments WHERE id = :id');
    $stmt->execute([':id' => $paymentId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        jsonResponse(404, ['error' => 'Pagamento não encontrado']);
        return;
    }

    if ($payment['access_granted']) {
        jsonResponse(200, ['status' => 'approved']);
        return;
    }

    if (in_array($payment['status'], ['rejected', 'cancelled'], true)) {
        jsonResponse(200, ['status' => $payment['status']]);
        return;
    }

    if (!$payment['mp_payment_id']) {
        jsonResponse(200, ['status' => 'pending']);
        return;
    }

    try {
        $mpStatus = \App\Mp::getPaymentStatus((int) $payment['mp_payment_id']);
    } catch (\Exception) {
        jsonResponse(200, ['status' => 'pending']);
        return;
    }

    if ($mpStatus === 'approved') {
        grantSubscriptionAccess($db, $paymentId, (int) $payment['group_id']);
        jsonResponse(200, ['status' => 'approved']);
    } elseif (in_array($mpStatus, ['rejected', 'cancelled'], true)) {
        $db->prepare('UPDATE subscription_payments SET status = :s WHERE id = :id')
           ->execute([':s' => $mpStatus, ':id' => $paymentId]);
        jsonResponse(200, ['status' => $mpStatus]);
    } else {
        jsonResponse(200, ['status' => 'pending']);
    }
}

function grantSubscriptionAccess(PDO $db, string $paymentId, int $groupId): void
{
    $db->prepare(
        'UPDATE subscription_payments SET status = "approved", access_granted = 1 WHERE id = :id'
    )->execute([':id' => $paymentId]);

    // Estende a partir de MAX(expires_at, agora) para não desperdiçar dias de renovação antecipada
    $db->prepare(
        'UPDATE `groups`
         SET subscription_expires_at = DATE_ADD(
             GREATEST(subscription_expires_at, NOW()),
             INTERVAL 30 DAY
         )
         WHERE id = :id'
    )->execute([':id' => $groupId]);

    // Notifica o capitão self-service (se houver)
    notifyCaptainSubscriptionConfirmed($db, $groupId);
}

function notifyCaptainSubscriptionConfirmed(PDO $db, int $groupId): void
{
    $stmt = $db->prepare(
        'SELECT u.email, u.name, s.weekday, s.start_hour, s.start_minute, s.label AS slot_label
         FROM `groups` g
         JOIN users u ON u.id = g.captain_user_id
         JOIN slots s ON s.id = g.slot_id
         WHERE g.id = :gid AND g.captain_user_id IS NOT NULL'
    );
    $stmt->execute([':gid' => $groupId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return;

    $days      = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
    $day       = $days[$row['weekday']] ?? '';
    $time      = sprintf('%02d:%02d', $row['start_hour'], $row['start_minute']);
    $slotLabel = $row['slot_label'] ?? "{$day} {$time}";

    Mailer::subscriptionConfirmed($row['email'], $row['name'], $slotLabel);
}
