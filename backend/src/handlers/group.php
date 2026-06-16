<?php

declare(strict_types=1);

use App\Database;
use App\TokenAuth;

function getGroupVideos(): void
{
    $group = TokenAuth::resolveGroup();
    if (!$group) {
        jsonResponse(401, ['error' => 'Token inválido ou assinatura expirada']);
        return;
    }

    $tz = new DateTimeZone($_ENV['TZ'] ?? 'America/Sao_Paulo');

    // Calculate UTC window for all occurrences of this weekly slot still within 24h
    // weekday 0=Seg…6=Dom (WEEKDAY() in MySQL matches this)
    $db = Database::getInstance();

    $stmt = $db->prepare(
        "SELECT id, seq, camera_id, duration_s, size_bytes, triggered_at, expires_at,
                (thumbnail_key IS NOT NULL) AS has_thumbnail
         FROM videos
         WHERE camera_id    = :camera_id
           AND CONVERT_TZ(triggered_at, 'UTC', :tz_name) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
           AND WEEKDAY(CONVERT_TZ(triggered_at, 'UTC', :tz_name)) = :weekday
           AND HOUR(CONVERT_TZ(triggered_at, 'UTC', :tz_name))    = :slot_hour
           AND expires_at > NOW()
         ORDER BY triggered_at DESC"
    );
    $stmt->execute([
        ':camera_id' => $group['camera_id'],
        ':tz_name'   => $tz->getName(),
        ':weekday'   => (int) $group['slot_weekday'],
        ':slot_hour' => (int) $group['slot_hour'],
    ]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
