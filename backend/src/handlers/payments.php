<?php

declare(strict_types=1);

use App\Database;
use App\TokenAuth;
use App\Mp;
use App\Mailer;

function getPaymentStatus(string $paymentId): void
{
    $db   = Database::getInstance();
    $stmt = $db->prepare('SELECT * FROM payments WHERE id = :id');
    $stmt->execute([':id' => $paymentId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        jsonResponse(404, ['error' => 'Pagamento não encontrado']);
        return;
    }

    // Já aprovado localmente — emite tokens (idempotente: tokens expiram em 48h)
    if ($payment['access_granted']) {
        $tokenData = issueTokensForPayment($db, $payment);
        jsonResponse(200, array_merge(['status' => 'approved'], $tokenData));
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
        $mpStatus = Mp::getPaymentStatus((int) $payment['mp_payment_id']);
    } catch (\Exception) {
        jsonResponse(200, ['status' => 'pending']);
        return;
    }

    if ($mpStatus === 'approved') {
        $db->prepare('UPDATE payments SET status = "approved", access_granted = 1 WHERE id = :id')
           ->execute([':id' => $paymentId]);
        $tokenData = issueTokensForPayment($db, $payment);
        jsonResponse(200, array_merge(['status' => 'approved'], $tokenData));
    } elseif (in_array($mpStatus, ['rejected', 'cancelled'], true)) {
        $db->prepare('UPDATE payments SET status = :s WHERE id = :id')
           ->execute([':s' => $mpStatus, ':id' => $paymentId]);
        jsonResponse(200, ['status' => $mpStatus]);
    } else {
        jsonResponse(200, ['status' => 'pending']);
    }
}

function issueTokensForPayment(PDO $db, array $payment): array
{
    $gStmt = $db->prepare('SELECT slot_date FROM games WHERE id = :id');
    $gStmt->execute([':id' => $payment['game_id']]);
    $game      = $gStmt->fetch(PDO::FETCH_ASSOC);
    $expiresAt = (new DateTimeImmutable($game['slot_date']))->modify('+48 hours')->format('Y-m-d H:i:s');

    if ($payment['type'] === 'full') {
        $token = TokenAuth::issueGameAccessToken($payment['game_id'], null, $expiresAt);
        $result = ['token' => $token, 'expires_at' => $expiresAt, 'type' => 'full'];
    } else {
        $clipIds = json_decode($payment['clip_ids'], true) ?? [];
        $tokens  = [];
        foreach ($clipIds as $clipId) {
            $tokens[$clipId] = TokenAuth::issueGameAccessToken($payment['game_id'], $clipId, $expiresAt);
        }
        $result = ['tokens' => $tokens, 'expires_at' => $expiresAt, 'type' => 'clips'];
    }

    if (!empty($payment['email'])) {
        $recoveryToken = createRecoveryLink($db, $payment, $expiresAt);
        sendRecoveryEmail($payment['email'], $recoveryToken, $payment, $expiresAt);
    }

    return $result;
}

function createRecoveryLink(PDO $db, array $payment, string $expiresAt): string
{
    $existing = $db->prepare('SELECT token FROM recovery_links WHERE payment_id = :id');
    $existing->execute([':id' => $payment['id']]);
    if ($row = $existing->fetch(PDO::FETCH_ASSOC)) {
        return $row['token'];
    }

    $token = bin2hex(random_bytes(16));
    $db->prepare(
        'INSERT INTO recovery_links (token, payment_id, expires_at)
         VALUES (:token, :payment_id, :expires_at)'
    )->execute([
        ':token'      => $token,
        ':payment_id' => $payment['id'],
        ':expires_at' => $expiresAt,
    ]);
    return $token;
}

function sendRecoveryEmail(string $to, string $recoveryToken, array $payment, string $expiresAt): void
{
    $appUrl   = rtrim($_ENV['APP_URL'] ?? '', '/');
    $venue    = $_ENV['VENUE_NAME'] ?? 'Replay';
    $expiresDt = new DateTimeImmutable($expiresAt, new DateTimeZone($_ENV['TZ'] ?? 'America/Sao_Paulo'));
    $expiresFormatted = $expiresDt->setTimezone(new DateTimeZone($_ENV['TZ'] ?? 'America/Sao_Paulo'))
                                  ->format('d/m/Y \à\s H:i');

    $recoveryUrl = "{$appUrl}/recuperar/{$recoveryToken}";

    if ($payment['type'] === 'full') {
        $subject = "Seu jogo está disponível — {$venue}";
        $body    = buildFullGameEmail($venue, $payment, $expiresFormatted, $recoveryUrl);
    } else {
        $subject = "Seu(s) clipe(s) estão disponíveis — {$venue}";
        $body    = buildClipsEmail($venue, $payment, $expiresFormatted, $recoveryUrl);
    }

    $fromEmail = $_ENV['MAIL_FROM'] ?? "noreply@{$_SERVER['HTTP_HOST']}";
    $fromName  = $venue;

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$fromEmail}\r\n";
    $headers .= "X-Mailer: PHP/" . PHP_VERSION;

    @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}

function buildFullGameEmail(string $venue, array $payment, string $expiresFormatted, string $recoveryUrl): string
{
    $gStmt = Database::getInstance()->prepare(
        'SELECT slot_date, slot_hour, slot_minute FROM games WHERE id = :id'
    );
    $gStmt->execute([':id' => $payment['game_id']]);
    $game = $gStmt->fetch(PDO::FETCH_ASSOC);
    $gameLabel = $game
        ? date('d/m/Y', strtotime($game['slot_date'])) . ' às ' . sprintf('%02d:%02d', $game['slot_hour'], $game['slot_minute'] ?? 0)
        : '';

    return <<<HTML
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
    <body style="margin:0;padding:0;background:#f5f0e8;font-family:Arial,sans-serif;">
      <table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f0e8;padding:32px 16px;">
        <tr><td align="center">
          <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;max-width:560px;width:100%;">
            <tr><td style="background:#0b132b;padding:28px 32px;">
              <p style="margin:0;font-size:22px;font-weight:900;color:#ffffff;text-transform:uppercase;letter-spacing:-0.03em;">{$venue}</p>
            </td></tr>
            <tr><td style="padding:32px;">
              <h1 style="margin:0 0 8px;font-size:26px;font-weight:900;color:#0b132b;text-transform:uppercase;">Compra confirmada!</h1>
              <p style="margin:0 0 24px;color:#666;font-size:15px;">Seu jogo está disponível para assistir e baixar.</p>
              <table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f0e8;border-radius:8px;padding:16px;margin-bottom:24px;">
                <tr>
                  <td style="font-size:12px;color:#888;text-transform:uppercase;letter-spacing:.06em;">Jogo</td>
                  <td style="font-size:14px;font-weight:700;color:#0b132b;text-align:right;">{$gameLabel}</td>
                </tr>
                <tr><td colspan="2" style="padding:4px 0;"></td></tr>
                <tr>
                  <td style="font-size:12px;color:#888;text-transform:uppercase;letter-spacing:.06em;">Válido até</td>
                  <td style="font-size:14px;font-weight:700;color:#c8991a;text-align:right;">{$expiresFormatted}</td>
                </tr>
              </table>
              <a href="{$recoveryUrl}" style="display:block;background:#c8991a;color:#0b132b;text-decoration:none;text-align:center;font-weight:900;font-size:15px;text-transform:uppercase;letter-spacing:.05em;padding:16px 24px;border-radius:8px;margin-bottom:24px;">Ver meu jogo →</a>
              <p style="margin:0;font-size:12px;color:#999;line-height:1.6;">Após o prazo, os vídeos são removidos automaticamente. Baixe ou compartilhe antes!<br>Guarde este e-mail para acessar seus clipes de qualquer dispositivo.</p>
            </td></tr>
          </table>
        </td></tr>
      </table>
    </body>
    </html>
    HTML;
}

function buildClipsEmail(string $venue, array $payment, string $expiresFormatted, string $recoveryUrl): string
{
    $clipIds = json_decode($payment['clip_ids'], true) ?? [];
    $count   = count($clipIds);
    $clipeWord = $count === 1 ? 'clipe' : 'clipes';

    $db   = Database::getInstance();
    $rows = [];
    foreach ($clipIds as $clipId) {
        $s = $db->prepare('SELECT triggered_at FROM videos WHERE id = :id');
        $s->execute([':id' => $clipId]);
        if ($r = $s->fetch(PDO::FETCH_ASSOC)) {
            $tz  = new DateTimeZone($_ENV['TZ'] ?? 'America/Sao_Paulo');
            $dt  = (new DateTimeImmutable($r['triggered_at'], new DateTimeZone('UTC')))->setTimezone($tz);
            $rows[] = '<tr><td style="padding:6px 0;font-size:14px;color:#0b132b;">🎬 Clipe das ' . $dt->format('H:i') . '</td></tr>';
        }
    }
    $clipsList  = implode('', $rows);
    $verboCopula = $count > 1 ? 'estão' : 'está';

    return <<<HTML
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
    <body style="margin:0;padding:0;background:#f5f0e8;font-family:Arial,sans-serif;">
      <table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f0e8;padding:32px 16px;">
        <tr><td align="center">
          <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;max-width:560px;width:100%;">
            <tr><td style="background:#0b132b;padding:28px 32px;">
              <p style="margin:0;font-size:22px;font-weight:900;color:#ffffff;text-transform:uppercase;letter-spacing:-0.03em;">{$venue}</p>
            </td></tr>
            <tr><td style="padding:32px;">
              <h1 style="margin:0 0 8px;font-size:26px;font-weight:900;color:#0b132b;text-transform:uppercase;">Compra confirmada!</h1>
              <p style="margin:0 0 24px;color:#666;font-size:15px;">Seu(s) {$clipeWord} {$verboCopula} disponível(is) para assistir e baixar.</p>
              <table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f0e8;border-radius:8px;padding:16px;margin-bottom:8px;">
                {$clipsList}
              </table>
              <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                <tr>
                  <td style="font-size:12px;color:#888;text-transform:uppercase;letter-spacing:.06em;padding-top:12px;">Válido até</td>
                  <td style="font-size:14px;font-weight:700;color:#c8991a;text-align:right;padding-top:12px;">{$expiresFormatted}</td>
                </tr>
              </table>
              <a href="{$recoveryUrl}" style="display:block;background:#c8991a;color:#0b132b;text-decoration:none;text-align:center;font-weight:900;font-size:15px;text-transform:uppercase;letter-spacing:.05em;padding:16px 24px;border-radius:8px;margin-bottom:24px;">Acessar meus clipes →</a>
              <p style="margin:0;font-size:12px;color:#999;line-height:1.6;">Após o prazo, os vídeos são removidos automaticamente. Baixe ou compartilhe antes!<br>Guarde este e-mail para acessar seus clipes de qualquer dispositivo.</p>
            </td></tr>
          </table>
        </td></tr>
      </table>
    </body>
    </html>
    HTML;
}

function getRecoveryLink(string $token): void
{
    $db   = Database::getInstance();
    $stmt = $db->prepare(
        'SELECT rl.*, p.type, p.clip_ids, p.game_id, p.qr_token
         FROM recovery_links rl
         JOIN payments p ON p.id = rl.payment_id
         WHERE rl.token = :token AND rl.expires_at > NOW()'
    );
    $stmt->execute([':token' => $token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        jsonResponse(404, ['error' => 'Link inválido ou expirado']);
        return;
    }

    $payment   = [
        'id'       => $row['payment_id'],
        'type'     => $row['type'],
        'clip_ids' => $row['clip_ids'],
        'game_id'  => $row['game_id'],
        'email'    => null,
    ];

    $gStmt = $db->prepare('SELECT slot_date FROM games WHERE id = :id');
    $gStmt->execute([':id' => $row['game_id']]);
    $game      = $gStmt->fetch(PDO::FETCH_ASSOC);
    $expiresAt = (new DateTimeImmutable($game['slot_date']))->modify('+48 hours')->format('Y-m-d H:i:s');

    if ($payment['type'] === 'full') {
        $token = TokenAuth::issueGameAccessToken($payment['game_id'], null, $expiresAt);
        $tokenData = ['token' => $token, 'expires_at' => $expiresAt, 'type' => 'full'];
    } else {
        $clipIds = json_decode($payment['clip_ids'], true) ?? [];
        $tokens  = [];
        foreach ($clipIds as $clipId) {
            $tokens[$clipId] = TokenAuth::issueGameAccessToken($payment['game_id'], $clipId, $expiresAt);
        }
        $tokenData = ['tokens' => $tokens, 'expires_at' => $expiresAt, 'type' => 'clips'];
    }

    jsonResponse(200, array_merge($tokenData, ['qr_token' => $row['qr_token']]));
}

function handleMpWebhook(): void
{
    $body = json_decode(file_get_contents('php://input'), true);

    if (($body['type'] ?? '') !== 'payment') {
        http_response_code(200);
        echo 'ok';
        return;
    }

    $mpId = (int) ($body['data']['id'] ?? 0);
    if (!$mpId) {
        http_response_code(200);
        echo 'ok';
        return;
    }

    try {
        $mpPayment = Mp::getPayment($mpId);
    } catch (\Exception) {
        http_response_code(200);
        echo 'ok';
        return;
    }

    if ($mpPayment->status !== 'approved') {
        http_response_code(200);
        echo 'ok';
        return;
    }

    // Usa external_reference (nosso payment.id) para encontrar o registro —
    // funciona tanto para Pix (mp_payment_id já gravado) quanto para cartão
    // (mp_payment_id ainda null, só temos mp_preference_id).
    $extRef = $mpPayment->external_reference ?? null;
    if (!$extRef) {
        http_response_code(200);
        echo 'ok';
        return;
    }

    $db = Database::getInstance();

    // Assinatura mensal: external_reference = "sub_{paymentId}"
    if (str_starts_with($extRef, 'sub_')) {
        $subId = substr($extRef, 4);
        $stmt  = $db->prepare('SELECT * FROM subscription_payments WHERE id = :id AND access_granted = 0');
        $stmt->execute([':id' => $subId]);
        $sub = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($sub) {
            grantSubscriptionAccess($db, $sub['id'], (int) $sub['group_id']);
            $db->prepare('UPDATE subscription_payments SET mp_payment_id = :mp_id WHERE id = :id')
               ->execute([':mp_id' => $mpId, ':id' => $sub['id']]);
            notifyCaptainSubscriptionConfirmed($db, (int) $sub['group_id']);
        }
        http_response_code(200);
        echo 'ok';
        return;
    }

    // Pagamento de jogo
    $stmt = $db->prepare('SELECT * FROM payments WHERE id = :id AND access_granted = 0');
    $stmt->execute([':id' => $extRef]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        http_response_code(200);
        echo 'ok';
        return;
    }

    $db->prepare(
        'UPDATE payments SET mp_payment_id = :mp_id, status = "approved", access_granted = 1 WHERE id = :id'
    )->execute([':mp_id' => $mpId, ':id' => $payment['id']]);

    http_response_code(200);
    echo 'ok';
}
