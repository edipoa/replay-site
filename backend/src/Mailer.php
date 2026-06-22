<?php

declare(strict_types=1);

namespace App;

class Mailer
{
    public static function send(string $to, string $subject, string $htmlBody): bool
    {
        $apiKey = $_ENV['RESEND_API_KEY'] ?? '';
        $from   = $_ENV['MAIL_FROM']      ?? 'noreply@example.com';
        $venue  = $_ENV['VENUE_NAME']     ?? 'Replay';

        if ($apiKey === '') {
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: =?UTF-8?B?" . base64_encode($venue) . "?= <{$from}>\r\n";
            return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlBody, $headers);
        }

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'from'    => "{$venue} <{$from}>",
                'to'      => [$to],
                'subject' => $subject,
                'html'    => $htmlBody,
            ]),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 10,
        ]);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_exec($ch);
        curl_close($ch);

        return $status >= 200 && $status < 300;
    }

    public static function passwordReset(string $to, string $resetUrl): bool
    {
        $venue   = $_ENV['VENUE_NAME'] ?? 'Replay';
        $subject = "Redefinição de senha — {$venue}";
        $html    = <<<HTML
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
        <body style="margin:0;padding:0;background:#f5f0e8;font-family:Arial,sans-serif;">
          <table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f0e8;padding:32px 16px;">
            <tr><td align="center">
              <table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;max-width:560px;width:100%;">
                <tr><td style="background:#0b132b;padding:28px 32px;">
                  <p style="margin:0;font-size:22px;font-weight:900;color:#fff;text-transform:uppercase;letter-spacing:-0.03em;">{$venue}</p>
                </td></tr>
                <tr><td style="padding:32px;">
                  <h1 style="margin:0 0 8px;font-size:24px;font-weight:900;color:#0b132b;text-transform:uppercase;">Redefinir senha</h1>
                  <p style="margin:0 0 24px;color:#666;font-size:15px;">Clique no botão abaixo para definir uma nova senha. O link expira em 1 hora.</p>
                  <a href="{$resetUrl}" style="display:block;background:#c8991a;color:#0b132b;text-decoration:none;text-align:center;font-weight:900;font-size:15px;text-transform:uppercase;letter-spacing:.05em;padding:16px 24px;border-radius:8px;margin-bottom:24px;">Redefinir senha →</a>
                  <p style="margin:0;font-size:12px;color:#999;line-height:1.6;">Se você não solicitou a redefinição, ignore este e-mail. O link é válido por 1 hora.</p>
                </td></tr>
              </table>
            </td></tr>
          </table>
        </body>
        </html>
        HTML;
        return self::send($to, $subject, $html);
    }

    public static function subscriptionConfirmed(string $to, string $userName, string $slotLabel): bool
    {
        $venue   = $_ENV['VENUE_NAME'] ?? 'Replay';
        $appUrl  = rtrim($_ENV['APP_URL'] ?? '', '/');
        $subject = "Assinatura confirmada — {$venue}";
        $html    = <<<HTML
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
        <body style="margin:0;padding:0;background:#f5f0e8;font-family:Arial,sans-serif;">
          <table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f0e8;padding:32px 16px;">
            <tr><td align="center">
              <table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;max-width:560px;width:100%;">
                <tr><td style="background:#0b132b;padding:28px 32px;">
                  <p style="margin:0;font-size:22px;font-weight:900;color:#fff;text-transform:uppercase;letter-spacing:-0.03em;">{$venue}</p>
                </td></tr>
                <tr><td style="padding:32px;">
                  <h1 style="margin:0 0 8px;font-size:24px;font-weight:900;color:#0b132b;text-transform:uppercase;">Assinatura ativada!</h1>
                  <p style="margin:0 0 16px;color:#666;font-size:15px;">Olá, <strong>{$userName}</strong>! Sua assinatura está ativa.</p>
                  <p style="margin:0 0 24px;color:#666;font-size:15px;">Agora você tem acesso a todos os replays do horário <strong>{$slotLabel}</strong>.</p>
                  <a href="{$appUrl}/grupo" style="display:block;background:#c8991a;color:#0b132b;text-decoration:none;text-align:center;font-weight:900;font-size:15px;text-transform:uppercase;letter-spacing:.05em;padding:16px 24px;border-radius:8px;margin-bottom:24px;">Ver meus replays →</a>
                </td></tr>
              </table>
            </td></tr>
          </table>
        </body>
        </html>
        HTML;
        return self::send($to, $subject, $html);
    }

    public static function paymentFailed(string $to, string $userName): bool
    {
        $venue   = $_ENV['VENUE_NAME'] ?? 'Replay';
        $appUrl  = rtrim($_ENV['APP_URL'] ?? '', '/');
        $subject = "Falha no pagamento — {$venue}";
        $html    = <<<HTML
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
        <body style="margin:0;padding:0;background:#f5f0e8;font-family:Arial,sans-serif;">
          <table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f0e8;padding:32px 16px;">
            <tr><td align="center">
              <table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;max-width:560px;width:100%;">
                <tr><td style="background:#0b132b;padding:28px 32px;">
                  <p style="margin:0;font-size:22px;font-weight:900;color:#fff;text-transform:uppercase;letter-spacing:-0.03em;">{$venue}</p>
                </td></tr>
                <tr><td style="padding:32px;">
                  <h1 style="margin:0 0 8px;font-size:24px;font-weight:900;color:#0b132b;text-transform:uppercase;">Falha no pagamento</h1>
                  <p style="margin:0 0 24px;color:#666;font-size:15px;">Olá, <strong>{$userName}</strong>. Houve uma falha no pagamento da sua assinatura. Acesse o site para renovar.</p>
                  <a href="{$appUrl}/grupo" style="display:block;background:#c8991a;color:#0b132b;text-decoration:none;text-align:center;font-weight:900;font-size:15px;text-transform:uppercase;letter-spacing:.05em;padding:16px 24px;border-radius:8px;margin-bottom:24px;">Renovar assinatura →</a>
                </td></tr>
              </table>
            </td></tr>
          </table>
        </body>
        </html>
        HTML;
        return self::send($to, $subject, $html);
    }
}
