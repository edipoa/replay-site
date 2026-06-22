<?php

declare(strict_types=1);

use App\Database;
use App\TokenAuth;
use App\R2Client;

function createShareLink(): void
{
    $body   = json_decode(file_get_contents('php://input'), true);
    $gameId = (string) ($body['game_id'] ?? '');
    $clipId = isset($body['clip_id']) ? (string) $body['clip_id'] : null;

    if (!preg_match('/^[a-f0-9]{32}$/', $gameId)) {
        jsonResponse(400, ['error' => 'game_id inválido']);
        return;
    }
    if ($clipId !== null && !preg_match('/^[a-f0-9]{32}$/', $clipId)) {
        jsonResponse(400, ['error' => 'clip_id inválido']);
        return;
    }

    $db = Database::getInstance();

    $gStmt = $db->prepare('SELECT * FROM games WHERE id = :id');
    $gStmt->execute([':id' => $gameId]);
    $game = $gStmt->fetch(PDO::FETCH_ASSOC);

    if (!$game) {
        jsonResponse(404, ['error' => 'Jogo não encontrado']);
        return;
    }

    // Valida acesso: game_access_token (avulso) OU group_token (mensalista)
    $slotWeekday = (int) (new DateTimeImmutable($game['slot_date']))->format('N') - 1;
    $hasAccess   = TokenAuth::resolveGameAccess($gameId, $clipId)
                || TokenAuth::resolveGroupAccess($slotWeekday, (int) $game['slot_hour'], (int) ($game['slot_minute'] ?? 0));

    if (!$hasAccess) {
        jsonResponse(403, ['error' => 'Acesso não autorizado']);
        return;
    }

    // Idempotência: reutiliza token ativo para o mesmo escopo
    if ($clipId === null) {
        $existStmt = $db->prepare(
            'SELECT token, expires_at FROM share_links
             WHERE game_id = :game_id AND clip_id IS NULL AND expires_at > NOW()'
        );
        $existStmt->execute([':game_id' => $gameId]);
    } else {
        $existStmt = $db->prepare(
            'SELECT token, expires_at FROM share_links
             WHERE game_id = :game_id AND clip_id = :clip_id AND expires_at > NOW()'
        );
        $existStmt->execute([':game_id' => $gameId, ':clip_id' => $clipId]);
    }

    $existing = $existStmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        $appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
        jsonResponse(200, [
            'token'      => $existing['token'],
            'url'        => "{$appUrl}/share/{$existing['token']}",
            'expires_at' => (new DateTimeImmutable($existing['expires_at'], new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM),
        ]);
        return;
    }

    // Cria novo token — expira 24h após início do slot
    $tz        = new DateTimeZone($_ENV['TZ'] ?? 'America/Sao_Paulo');
    $slotStart = new DateTimeImmutable(
        sprintf('%s %02d:%02d:00', $game['slot_date'], (int) $game['slot_hour'], (int) ($game['slot_minute'] ?? 0)),
        $tz
    );
    $expiresAt = $slotStart->modify('+24 hours')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $token     = bin2hex(random_bytes(16));

    $db->prepare(
        'INSERT INTO share_links (token, game_id, clip_id, expires_at)
         VALUES (:token, :game_id, :clip_id, :expires_at)'
    )->execute([
        ':token'      => $token,
        ':game_id'    => $gameId,
        ':clip_id'    => $clipId,
        ':expires_at' => $expiresAt,
    ]);

    $appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
    jsonResponse(201, [
        'token'      => $token,
        'url'        => "{$appUrl}/share/{$token}",
        'expires_at' => (new DateTimeImmutable($expiresAt, new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM),
    ]);
}

function getShareLink(string $token): void
{
    $db = Database::getInstance();

    $stmt = $db->prepare('SELECT * FROM share_links WHERE token = :token AND expires_at > NOW()');
    $stmt->execute([':token' => $token]);
    $link = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$link) {
        jsonResponse(404, ['error' => 'Link expirado ou inválido']);
        return;
    }

    $gStmt = $db->prepare('SELECT * FROM games WHERE id = :id');
    $gStmt->execute([':id' => $link['game_id']]);
    $game = $gStmt->fetch(PDO::FETCH_ASSOC);

    if (!$game) {
        jsonResponse(404, ['error' => 'Jogo não encontrado']);
        return;
    }

    $r2  = new R2Client();
    $utc = new DateTimeZone('UTC');

    $linkExpiresAt = (new DateTimeImmutable($link['expires_at'], $utc))->format(DateTimeInterface::ATOM);
    $slotInfo      = [
        'slot_date'   => $game['slot_date'],
        'slot_hour'   => (int) $game['slot_hour'],
        'slot_minute' => (int) ($game['slot_minute'] ?? 0),
        'duration_m'  => (int) $game['duration_m'],
        'game_id'     => $link['game_id'],
        'expires_at'  => $linkExpiresAt,
    ];

    if ($link['clip_id'] !== null) {
        $vStmt = $db->prepare(
            'SELECT id, seq, camera_id, r2_key, duration_s, size_bytes, triggered_at, expires_at, thumbnail_key
             FROM videos WHERE id = :id AND expires_at > NOW()'
        );
        $vStmt->execute([':id' => $link['clip_id']]);
        $video = $vStmt->fetch(PDO::FETCH_ASSOC);

        if (!$video) {
            jsonResponse(404, ['error' => 'Vídeo expirado']);
            return;
        }

        $video = formatVideoRow($video, $r2, $utc, true);
        jsonResponse(200, array_merge($slotInfo, ['type' => 'clip', 'video' => $video]));
    } else {
        [$utcStart, $utcEnd] = gameUtcWindow($game);

        $vStmt = $db->prepare(
            'SELECT id, seq, camera_id, r2_key, duration_s, size_bytes, triggered_at, expires_at, thumbnail_key
             FROM videos
             WHERE triggered_at >= :start AND triggered_at < :end AND expires_at > NOW()
             ORDER BY triggered_at ASC'
        );
        $vStmt->execute([':start' => $utcStart, ':end' => $utcEnd]);
        $rows = $vStmt->fetchAll(PDO::FETCH_ASSOC);

        $videos = array_map(fn($v) => formatVideoRow($v, $r2, $utc, true), $rows);
        jsonResponse(200, array_merge($slotInfo, ['type' => 'game', 'videos' => $videos]));
    }
}

function formatVideoRow(array $video, R2Client $r2, DateTimeZone $utc, bool $includeDownload = false): array
{
    $key = $video['r2_key'];

    if (str_starts_with($key, 'http://') || str_starts_with($key, 'https://')) {
        $video['stream_url']   = $key;
        $video['download_url'] = $key;
    } else {
        $video['stream_url']   = $r2->presign($key, 3600);
        $video['download_url'] = $r2->presign($key, 3600, 'attachment; filename="' . basename($key) . '"');
    }

    $video['thumbnail_url'] = $video['thumbnail_key'] !== null
        ? $r2->presign($video['thumbnail_key'], 3600)
        : null;

    $video['triggered_at'] = (new DateTimeImmutable($video['triggered_at'], $utc))->format(DateTimeInterface::ATOM);
    $video['expires_at']   = (new DateTimeImmutable($video['expires_at'], $utc))->format(DateTimeInterface::ATOM);
    $video['seq']          = (int) $video['seq'];
    $video['display_id']   = sprintf('VC-%03d', $video['seq']);
    $video['duration_s']   = (int) $video['duration_s'];
    $video['size_bytes']   = (int) $video['size_bytes'];

    unset($video['r2_key'], $video['thumbnail_key']);
    return $video;
}
