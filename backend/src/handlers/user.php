<?php

declare(strict_types=1);

use App\Database;
use App\TokenAuth;
use App\Mp;
use App\Mailer;

// ── Slots disponíveis ──────────────────────────────────────────────────────────

function getAvailableSlots(): void
{
    $db = Database::getInstance();

    $days = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];

    $rows = $db->query(
        'SELECT s.id, s.weekday, s.start_hour, s.start_minute, s.duration_m, s.label
         FROM slots s
         WHERE NOT EXISTS (
             SELECT 1 FROM `groups` g
             WHERE g.slot_id = s.id AND g.captain_user_id IS NOT NULL
         )
         ORDER BY s.weekday, s.start_hour, s.start_minute'
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $r['id']           = (int) $r['id'];
        $r['weekday']      = (int) $r['weekday'];
        $r['start_hour']   = (int) $r['start_hour'];
        $r['start_minute'] = (int) $r['start_minute'];
        $r['duration_m']   = (int) $r['duration_m'];
        $day               = $days[$r['weekday']] ?? '';
        $time              = sprintf('%02d:%02d', $r['start_hour'], $r['start_minute']);
        $r['display']      = $r['label'] ?? "{$day} {$time}";
    }

    jsonResponse(200, $rows);
}

// ── Assinatura self-service ────────────────────────────────────────────────────

function createUserSubscription(): void
{
    $user = TokenAuth::resolveUser();
    if (!$user) {
        jsonResponse(401, ['error' => 'Não autenticado']);
        return;
    }

    $db = Database::getInstance();

    // Não pode ter grupo já
    $existing = $db->prepare(
        'SELECT group_id FROM user_group_memberships WHERE user_id = :uid AND role = "captain"'
    );
    $existing->execute([':uid' => $user['id']]);
    if ($existing->fetch()) {
        jsonResponse(409, ['error' => 'Você já possui um grupo cadastrado']);
        return;
    }

    $body   = json_decode(file_get_contents('php://input'), true);
    $slotId = (int) ($body['slot_id'] ?? 0);
    $method = $body['method'] ?? 'credit_card';
    $name   = trim((string) ($body['team_name'] ?? $user['name']));

    if (!in_array($method, ['pix', 'credit_card'], true)) {
        jsonResponse(400, ['error' => 'method deve ser pix ou credit_card']);
        return;
    }
    if ($slotId <= 0) {
        jsonResponse(400, ['error' => 'slot_id é obrigatório']);
        return;
    }
    if ($name === '') {
        jsonResponse(400, ['error' => 'team_name é obrigatório']);
        return;
    }

    // Verifica slot existe e está disponível
    $slotStmt = $db->prepare(
        'SELECT s.* FROM slots s
         WHERE s.id = :id AND NOT EXISTS (
             SELECT 1 FROM `groups` g WHERE g.slot_id = s.id AND g.captain_user_id IS NOT NULL
         )'
    );
    $slotStmt->execute([':id' => $slotId]);
    $slot = $slotStmt->fetch(PDO::FETCH_ASSOC);

    if (!$slot) {
        jsonResponse(400, ['error' => 'Slot não disponível']);
        return;
    }

    // Cria o grupo com subscription_expires_at no passado (ainda não pago)
    $db->prepare(
        'INSERT INTO `groups` (captain_user_id, slot_id, name, subscription_expires_at)
         VALUES (:captain, :slot, :name, DATE_SUB(NOW(), INTERVAL 1 DAY))'
    )->execute([
        ':captain' => $user['id'],
        ':slot'    => $slotId,
        ':name'    => $name,
    ]);
    $groupId = (int) $db->lastInsertId();

    // Vínculo capitão ↔ grupo
    $db->prepare(
        'INSERT INTO user_group_memberships (user_id, group_id, role) VALUES (:uid, :gid, "captain")'
    )->execute([':uid' => $user['id'], ':gid' => $groupId]);

    // Gera group_token para o link público (1 ano de validade; acesso real controlado por subscription_expires_at)
    $publicTokenExpiry = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+1 year')->format('Y-m-d H:i:s');
    $groupToken        = TokenAuth::issueGroupToken($groupId, $publicTokenExpiry);

    // Cria registro de pagamento
    $amount    = (float) ($_ENV['PRICE_MONTHLY_SUBSCRIPTION'] ?? 59.90);
    $paymentId = bin2hex(random_bytes(16));
    $db->prepare(
        'INSERT INTO subscription_payments (id, group_id, method, amount)
         VALUES (:id, :group_id, :method, :amount)'
    )->execute([
        ':id'       => $paymentId,
        ':group_id' => $groupId,
        ':method'   => $method,
        ':amount'   => $amount,
    ]);

    $description = sprintf('Replay – Assinatura mensal – %s', $name);

    if ($method === 'pix') {
        if (($_ENV['PIX_MOCK'] ?? 'false') === 'true') {
            grantSubscriptionAccess($db, $paymentId, $groupId);
            jsonResponse(201, [
                'payment_id'   => $paymentId,
                'group_token'  => $groupToken,
                'method'       => 'pix',
                'mock'         => true,
                'amount'       => $amount,
                'expires_in'   => 1800,
            ]);
            return;
        }

        try {
            $pix = Mp::createPix($amount, $description, 'sub_' . $paymentId);
        } catch (\Exception $e) {
            $db->prepare('DELETE FROM subscription_payments WHERE id = :id')->execute([':id' => $paymentId]);
            $db->prepare('DELETE FROM user_group_memberships WHERE group_id = :gid')->execute([':gid' => $groupId]);
            $db->prepare('DELETE FROM `groups` WHERE id = :id')->execute([':id' => $groupId]);
            jsonResponse(500, ['error' => 'Falha ao criar Pix: ' . $e->getMessage()]);
            return;
        }

        $db->prepare('UPDATE subscription_payments SET mp_payment_id = :mp_id WHERE id = :id')
           ->execute([':mp_id' => $pix['mp_id'], ':id' => $paymentId]);

        jsonResponse(201, [
            'payment_id'     => $paymentId,
            'group_token'    => $groupToken,
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
            $pref = Mp::createPreference([
                'items' => [[
                    'title'       => $description,
                    'quantity'    => 1,
                    'unit_price'  => $amount,
                    'currency_id' => 'BRL',
                ]],
                'external_reference' => 'sub_' . $paymentId,
                'back_urls' => [
                    'success' => "{$appUrl}/grupo?sub_payment={$paymentId}",
                    'failure' => "{$appUrl}/grupo?sub_payment={$paymentId}&pstatus=failure",
                    'pending' => "{$appUrl}/grupo?sub_payment={$paymentId}&pstatus=pending",
                ],
                'notification_url' => "{$apiUrl}/api/webhooks/mercadopago",
            ]);
        } catch (\Exception $e) {
            $db->prepare('DELETE FROM subscription_payments WHERE id = :id')->execute([':id' => $paymentId]);
            $db->prepare('DELETE FROM user_group_memberships WHERE group_id = :gid')->execute([':gid' => $groupId]);
            $db->prepare('DELETE FROM `groups` WHERE id = :id')->execute([':id' => $groupId]);
            jsonResponse(500, ['error' => 'Falha ao criar preferência: ' . $e->getMessage()]);
            return;
        }

        $db->prepare('UPDATE subscription_payments SET mp_preference_id = :pref_id WHERE id = :id')
           ->execute([':pref_id' => $pref['preference_id'], ':id' => $paymentId]);

        jsonResponse(201, [
            'payment_id'   => $paymentId,
            'group_token'  => $groupToken,
            'method'       => 'credit_card',
            'checkout_url' => $pref['init_point'],
            'amount'       => $amount,
        ]);
    }
}

// ── Me (usuário atual) ─────────────────────────────────────────────────────────

function getMe(): void
{
    $user = TokenAuth::resolveUser();
    if (!$user) {
        jsonResponse(401, ['error' => 'Não autenticado']);
        return;
    }

    $db = Database::getInstance();

    $gStmt = $db->prepare(
        'SELECT g.id, g.name, g.subscription_expires_at, m.role,
                s.weekday, s.start_hour, s.start_minute, s.label AS slot_label
         FROM user_group_memberships m
         JOIN `groups` g ON g.id = m.group_id
         JOIN slots    s ON s.id = g.slot_id
         WHERE m.user_id = :uid
         LIMIT 1'
    );
    $gStmt->execute([':uid' => $user['id']]);
    $group = $gStmt->fetch(PDO::FETCH_ASSOC);

    $result = [
        'id'    => (int) $user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'group' => null,
    ];

    if ($group) {
        $expiresAt  = new DateTimeImmutable($group['subscription_expires_at'], new DateTimeZone('UTC'));
        $now        = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $days       = $days = (int) $now->diff($expiresAt)->days;
        $expired    = $expiresAt <= $now;

        $days_label = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
        $day        = $days_label[$group['weekday']] ?? '';
        $time       = sprintf('%02d:%02d', $group['start_hour'], $group['start_minute']);
        $slotLabel  = $group['slot_label'] ?? "{$day} {$time}";

        $result['group'] = [
            'id'          => (int) $group['id'],
            'name'        => $group['name'],
            'role'        => $group['role'],
            'slot_label'  => $slotLabel,
            'status'      => $expired ? 'expired' : ($days <= 7 ? 'expiring' : 'active'),
            'expires_at'  => $expiresAt->format(DateTimeInterface::ATOM),
            'days_left'   => $expired ? 0 : $days,
        ];
    }

    jsonResponse(200, $result);
}

// ── Link de convite ────────────────────────────────────────────────────────────

function getUserInviteLink(): void
{
    $user = TokenAuth::resolveUser();
    if (!$user) {
        jsonResponse(401, ['error' => 'Não autenticado']);
        return;
    }

    $db = Database::getInstance();

    $gStmt = $db->prepare(
        'SELECT g.id, g.name FROM user_group_memberships m
         JOIN `groups` g ON g.id = m.group_id
         WHERE m.user_id = :uid AND m.role = "captain"
         LIMIT 1'
    );
    $gStmt->execute([':uid' => $user['id']]);
    $group = $gStmt->fetch(PDO::FETCH_ASSOC);

    if (!$group) {
        jsonResponse(403, ['error' => 'Apenas o capitão pode gerar link de convite']);
        return;
    }

    // Reutiliza convite existente não expirado
    $existing = $db->prepare(
        'SELECT token FROM invite_tokens WHERE group_id = :gid AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1'
    );
    $existing->execute([':gid' => $group['id']]);
    $row = $existing->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $inviteToken = $row['token'];
    } else {
        $inviteToken = bin2hex(random_bytes(16));
        $expiresAt   = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+7 days')->format('Y-m-d H:i:s');
        $db->prepare(
            'INSERT INTO invite_tokens (token, group_id, expires_at) VALUES (:token, :gid, :exp)'
        )->execute([':token' => $inviteToken, ':gid' => $group['id'], ':exp' => $expiresAt]);
    }

    $appUrl     = rtrim($_ENV['APP_URL'] ?? '', '/');
    $inviteUrl  = "{$appUrl}/convite/{$inviteToken}";

    jsonResponse(200, ['invite_url' => $inviteUrl, 'token' => $inviteToken]);
}

function getUserGroupMembers(): void
{
    $user = TokenAuth::resolveUser();
    if (!$user) {
        jsonResponse(401, ['error' => 'Não autenticado']);
        return;
    }

    $db = Database::getInstance();

    $gStmt = $db->prepare(
        'SELECT group_id FROM user_group_memberships WHERE user_id = :uid LIMIT 1'
    );
    $gStmt->execute([':uid' => $user['id']]);
    $row = $gStmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        jsonResponse(403, ['error' => 'Você não pertence a nenhum grupo']);
        return;
    }

    $groupId = (int) $row['group_id'];

    $stmt = $db->prepare(
        'SELECT u.id, u.name, u.email, m.role, m.joined_at
         FROM user_group_memberships m
         JOIN users u ON u.id = m.user_id
         WHERE m.group_id = :gid
         ORDER BY m.role DESC, m.joined_at ASC'
    );
    $stmt->execute([':gid' => $groupId]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($members as &$m) {
        $m['id'] = (int) $m['id'];
    }

    jsonResponse(200, $members);
}

// ── Link público (group_token) ─────────────────────────────────────────────────

function getUserPublicLink(): void
{
    $user = TokenAuth::resolveUser();
    if (!$user) {
        jsonResponse(401, ['error' => 'Não autenticado']);
        return;
    }

    $db = Database::getInstance();

    $gStmt = $db->prepare(
        'SELECT g.id FROM user_group_memberships m
         JOIN `groups` g ON g.id = m.group_id
         WHERE m.user_id = :uid AND m.role = "captain"
         LIMIT 1'
    );
    $gStmt->execute([':uid' => $user['id']]);
    $group = $gStmt->fetch(PDO::FETCH_ASSOC);

    if (!$group) {
        jsonResponse(403, ['error' => 'Apenas o capitão pode acessar o link público']);
        return;
    }

    // Busca group_token ativo (o token de longa duração criado na assinatura)
    $tStmt = $db->prepare(
        'SELECT token FROM group_tokens WHERE group_id = :gid AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1'
    );
    $tStmt->execute([':gid' => $group['id']]);
    $tokenRow = $tStmt->fetch(PDO::FETCH_ASSOC);

    if (!$tokenRow) {
        jsonResponse(404, ['error' => 'Link público não disponível']);
        return;
    }

    $appUrl    = rtrim($_ENV['APP_URL'] ?? '', '/');
    $publicUrl = "{$appUrl}/group?t={$tokenRow['token']}";

    jsonResponse(200, ['public_url' => $publicUrl, 'token' => $tokenRow['token']]);
}
