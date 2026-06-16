<?php

declare(strict_types=1);

use App\Database;
use App\TokenAuth;

function getCurrentGame(): void
{
    $cameraId = $_GET['camera'] ?? '';
    if ($cameraId === '') {
        jsonResponse(400, ['error' => 'Parâmetro camera obrigatório']);
        return;
    }

    $tz  = new DateTimeZone($_ENV['TZ'] ?? 'America/Sao_Paulo');
    $now = new DateTimeImmutable('now', $tz);

    // WEEKDAY(): 0=Mon…6=Sun — mesmo que nosso campo weekday
    $weekday = (int) $now->format('N') - 1;
    $hour    = (int) $now->format('H');
    $minutes = (int) $now->format('H') * 60 + (int) $now->format('i');
    $today   = $now->format('Y-m-d');

    $db   = Database::getInstance();
    $stmt = $db->prepare(
        'SELECT * FROM slots
         WHERE camera_id = :camera_id
           AND weekday = :weekday
           AND (start_hour * 60) <= :minutes
           AND (start_hour * 60 + duration_m) > :minutes'
    );
    $stmt->execute([
        ':camera_id' => $cameraId,
        ':weekday'   => $weekday,
        ':minutes'   => $minutes,
    ]);
    $slot = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$slot) {
        // Retorna próximo slot do dia para mostrar na tela
        $nextStmt = $db->prepare(
            'SELECT * FROM slots
             WHERE camera_id = :camera_id AND weekday = :weekday AND start_hour > :hour
             ORDER BY start_hour ASC LIMIT 1'
        );
        $nextStmt->execute([':camera_id' => $cameraId, ':weekday' => $weekday, ':hour' => $hour]);
        $next = $nextStmt->fetch(PDO::FETCH_ASSOC);
        jsonResponse(404, ['error' => 'Nenhum jogo ativo', 'next_slot' => $next ?: null]);
        return;
    }

    $game = findOrNullGame($db, $cameraId, $today, (int) $slot['start_hour']);
    if (!$game) {
        jsonResponse(404, ['error' => 'Jogo ainda não iniciado', 'slot' => $slot]);
        return;
    }

    jsonResponse(200, [
        'id'         => $game['id'],
        'qr_token'   => $game['qr_token'],
        'slot_date'  => $game['slot_date'],
        'slot_hour'  => (int) $game['slot_hour'],
        'duration_m' => (int) $game['duration_m'],
        'camera_id'  => $game['camera_id'],
        'label'      => $slot['label'],
    ]);
}

function getGame(string $qrToken): void
{
    $db   = Database::getInstance();
    $stmt = $db->prepare('SELECT * FROM games WHERE qr_token = :qr_token');
    $stmt->execute([':qr_token' => $qrToken]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$game) {
        jsonResponse(404, ['error' => 'Jogo não encontrado']);
        return;
    }

    $clipCount = clipCount($db, $game);

    jsonResponse(200, [
        'id'         => $game['id'],
        'qr_token'   => $game['qr_token'],
        'slot_date'  => $game['slot_date'],
        'slot_hour'  => (int) $game['slot_hour'],
        'duration_m' => (int) $game['duration_m'],
        'camera_id'  => $game['camera_id'],
        'clip_count' => $clipCount,
    ]);
}

function getGameVideos(string $qrToken): void
{
    $db   = Database::getInstance();
    $stmt = $db->prepare('SELECT * FROM games WHERE qr_token = :qr_token');
    $stmt->execute([':qr_token' => $qrToken]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$game) {
        jsonResponse(404, ['error' => 'Jogo não encontrado']);
        return;
    }

    if (!TokenAuth::resolveGameAccess($game['id'])) {
        jsonResponse(401, ['error' => 'Acesso não autorizado']);
        return;
    }

    [$utcStart, $utcEnd] = gameUtcWindow($game);

    $vStmt = $db->prepare(
        "SELECT id, seq, camera_id, duration_s, size_bytes, triggered_at, expires_at,
                (thumbnail_key IS NOT NULL) AS has_thumbnail
         FROM videos
         WHERE camera_id = :camera_id
           AND triggered_at >= :start AND triggered_at < :end
         ORDER BY triggered_at ASC"
    );
    $vStmt->execute([
        ':camera_id' => $game['camera_id'],
        ':start'     => $utcStart,
        ':end'       => $utcEnd,
    ]);
    $rows = $vStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $row['triggered_at']  = (new DateTimeImmutable($row['triggered_at']))->format(DateTimeInterface::ATOM);
        $row['expires_at']    = (new DateTimeImmutable($row['expires_at']))->format(DateTimeInterface::ATOM);
        $row['seq']           = (int) $row['seq'];
        $row['display_id']    = sprintf('VC-%03d', $row['seq']);
        $row['duration_s']    = (int) $row['duration_s'];
        $row['size_bytes']    = (int) $row['size_bytes'];
        $row['has_thumbnail'] = (bool) $row['has_thumbnail'];
    }

    jsonResponse(200, $rows);
}

function initiateGamePayment(string $qrToken): void
{
    $db   = Database::getInstance();
    $stmt = $db->prepare('SELECT * FROM games WHERE qr_token = :qr_token');
    $stmt->execute([':qr_token' => $qrToken]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$game) {
        jsonResponse(404, ['error' => 'Jogo não encontrado']);
        return;
    }

    $body   = json_decode(file_get_contents('php://input'), true);
    $type   = $body['type'] ?? 'full';
    $clipId = ($type === 'clip' && isset($body['clip_id'])) ? (string) $body['clip_id'] : null;

    if (!in_array($type, ['full', 'clip'], true)) {
        jsonResponse(400, ['error' => 'type deve ser full ou clip']);
        return;
    }

    // ── Fluxo de pagamento (gateway a integrar) ───────────────────────────────
    // TODO: criar registro em payments, chamar API do gateway e retornar payment_url.
    // Por enquanto, modo mock: concede acesso imediatamente.
    // ─────────────────────────────────────────────────────────────────────────

    $expiresAt = (new DateTimeImmutable("{$game['slot_date']}"))->modify('+48 hours')->format('Y-m-d H:i:s');
    $token     = TokenAuth::issueGameAccessToken($game['id'], $clipId, $expiresAt);

    jsonResponse(200, [
        'token'      => $token,
        'expires_at' => $expiresAt,
        'type'       => $type,
        'mock'       => true,
    ]);
}

// ── Helpers chamados também pelo postVideo ────────────────────────────────────

function maybeCreateGame(PDO $db, string $cameraId, DateTimeImmutable $triggeredAt): void
{
    $tz      = new DateTimeZone($_ENV['TZ'] ?? 'America/Sao_Paulo');
    $local   = $triggeredAt->setTimezone($tz);
    $weekday = (int) $local->format('N') - 1;
    $hour    = (int) $local->format('H');
    $minutes = $hour * 60 + (int) $local->format('i');
    $date    = $local->format('Y-m-d');

    $slotStmt = $db->prepare(
        'SELECT * FROM slots
         WHERE camera_id = :camera_id AND weekday = :weekday
           AND (start_hour * 60) <= :minutes AND (start_hour * 60 + duration_m) > :minutes'
    );
    $slotStmt->execute([':camera_id' => $cameraId, ':weekday' => $weekday, ':minutes' => $minutes]);
    $slot = $slotStmt->fetch(PDO::FETCH_ASSOC);

    if (!$slot) return;

    $existing = findOrNullGame($db, $cameraId, $date, (int) $slot['start_hour']);
    if ($existing) return;

    $gameId  = bin2hex(random_bytes(16));
    $qrToken = bin2hex(random_bytes(16));
    $db->prepare(
        'INSERT IGNORE INTO games (id, camera_id, slot_date, slot_hour, duration_m, qr_token)
         VALUES (:id, :camera_id, :date, :hour, :duration_m, :qr_token)'
    )->execute([
        ':id'         => $gameId,
        ':camera_id'  => $cameraId,
        ':date'       => $date,
        ':hour'       => (int) $slot['start_hour'],
        ':duration_m' => (int) $slot['duration_m'],
        ':qr_token'   => $qrToken,
    ]);
}

function findOrNullGame(PDO $db, string $cameraId, string $date, int $slotHour): ?array
{
    $stmt = $db->prepare(
        'SELECT * FROM games WHERE camera_id = :camera_id AND slot_date = :date AND slot_hour = :hour'
    );
    $stmt->execute([':camera_id' => $cameraId, ':date' => $date, ':hour' => $slotHour]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function gameUtcWindow(array $game): array
{
    $tz    = new DateTimeZone($_ENV['TZ'] ?? 'America/Sao_Paulo');
    $utc   = new DateTimeZone('UTC');
    $start = (new DateTimeImmutable("{$game['slot_date']} {$game['slot_hour']}:00:00", $tz))->setTimezone($utc);
    $end   = $start->modify("+{$game['duration_m']} minutes");
    return [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
}

function clipCount(PDO $db, array $game): int
{
    [$start, $end] = gameUtcWindow($game);
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM videos WHERE camera_id = :camera_id AND triggered_at >= :start AND triggered_at < :end'
    );
    $stmt->execute([':camera_id' => $game['camera_id'], ':start' => $start, ':end' => $end]);
    return (int) $stmt->fetchColumn();
}
