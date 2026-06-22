<?php

declare(strict_types=1);

use App\Database;
use App\TokenAuth;
use App\R2Client;

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
    $minutes = (int) $now->format('H') * 60 + (int) $now->format('i');
    $today   = $now->format('Y-m-d');

    $db = Database::getInstance();

    // Jogo ativo agora para esta câmera (games já tem camera_id, slot_hour, duration_m)
    $stmt = $db->prepare(
        'SELECT * FROM games
         WHERE camera_id = :camera_id
           AND slot_date = :today
           AND (slot_hour * 60 + slot_minute) <= :minutes
           AND (slot_hour * 60 + slot_minute + duration_m) > :minutes
         LIMIT 1'
    );
    $stmt->execute([':camera_id' => $cameraId, ':today' => $today, ':minutes' => $minutes]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$game) {
        // Verifica se há um slot ativo agora — se houver, cria o game automaticamente
        $slotNowStmt = $db->prepare(
            'SELECT * FROM slots
             WHERE weekday = :weekday
               AND (start_hour * 60 + start_minute) <= :minutes
               AND (start_hour * 60 + start_minute + duration_m) > :minutes
             LIMIT 1'
        );
        $slotNowStmt->execute([':weekday' => $weekday, ':minutes' => $minutes]);
        $activeSlot = $slotNowStmt->fetch(PDO::FETCH_ASSOC);

        if ($activeSlot) {
            $slotHour   = (int) $activeSlot['start_hour'];
            $slotMinute = (int) $activeSlot['start_minute'];

            // Garante que não existe game para esta câmera+slot antes de inserir
            $existCheck = $db->prepare(
                'SELECT id FROM games
                 WHERE camera_id = :camera_id AND slot_date = :date
                   AND slot_hour = :hour AND slot_minute = :minute
                 LIMIT 1'
            );
            $existCheck->execute([
                ':camera_id' => $cameraId,
                ':date'      => $today,
                ':hour'      => $slotHour,
                ':minute'    => $slotMinute,
            ]);

            if (!$existCheck->fetchColumn()) {
                $gameId  = bin2hex(random_bytes(16));
                $qrToken = bin2hex(random_bytes(16));
                $db->prepare(
                    'INSERT IGNORE INTO games (id, camera_id, slot_date, slot_hour, slot_minute, duration_m, qr_token)
                     VALUES (:id, :camera_id, :date, :hour, :minute, :duration_m, :qr_token)'
                )->execute([
                    ':id'         => $gameId,
                    ':camera_id'  => $cameraId,
                    ':date'       => $today,
                    ':hour'       => $slotHour,
                    ':minute'     => $slotMinute,
                    ':duration_m' => (int) $activeSlot['duration_m'],
                    ':qr_token'   => $qrToken,
                ]);
            }

            $stmt->execute([':camera_id' => $cameraId, ':today' => $today, ':minutes' => $minutes]);
            $game = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$game) {
            // Slots são câmera-agnósticos — retorna próximo slot agendado para hoje
            $nextStmt = $db->prepare(
                'SELECT weekday, start_hour, start_minute FROM slots
                 WHERE weekday = :weekday
                   AND (start_hour * 60 + start_minute) > :minutes
                 ORDER BY (start_hour * 60 + start_minute) ASC LIMIT 1'
            );
            $nextStmt->execute([':weekday' => $weekday, ':minutes' => $minutes]);
            $next = $nextStmt->fetch(PDO::FETCH_ASSOC);
            jsonResponse(404, ['error' => 'Nenhum jogo ativo', 'next_slot' => $next ?: null]);
            return;
        }
    }

    // Rótulo do slot (opcional, apenas informativo)
    $labelStmt = $db->prepare(
        'SELECT label FROM slots
         WHERE weekday = :weekday AND start_hour = :hour AND start_minute = :minute
         LIMIT 1'
    );
    $labelStmt->execute([':weekday' => $weekday, ':hour' => $game['slot_hour'], ':minute' => $game['slot_minute']]);
    $label = $labelStmt->fetchColumn() ?: null;

    jsonResponse(200, [
        'id'          => $game['id'],
        'qr_token'    => $game['qr_token'],
        'slot_date'   => $game['slot_date'],
        'slot_hour'   => (int) $game['slot_hour'],
        'slot_minute' => (int) $game['slot_minute'],
        'duration_m'  => (int) $game['duration_m'],
        'camera_id'   => $game['camera_id'],
        'label'       => $label,
        'clip_count'  => clipCount($db, $game),
    ]);
}

function getPublicGames(): void
{
    $db  = Database::getInstance();
    $tz  = new DateTimeZone($_ENV['TZ'] ?? 'America/Sao_Paulo');
    $cutoff = (new DateTimeImmutable('now', $tz))->modify('-24 hours');

    // Busca jogos dos últimos 2 dias por data (margem segura) e filtra por horário de término no PHP
    $stmt = $db->prepare(
        'SELECT g.camera_id, g.slot_date, g.slot_hour, g.slot_minute, g.duration_m, g.qr_token,
                (SELECT COUNT(*) FROM `groups` gr
                   JOIN slots s ON s.id = gr.slot_id
                   WHERE s.weekday      = WEEKDAY(g.slot_date)
                     AND s.start_hour   = g.slot_hour
                     AND s.start_minute = g.slot_minute
                ) AS group_count
         FROM games g
         WHERE g.slot_date >= :cutoff_date
         ORDER BY g.slot_date DESC, g.slot_hour DESC, g.slot_minute DESC
         LIMIT 50'
    );
    $stmt->execute([':cutoff_date' => $cutoff->format('Y-m-d')]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($rows as &$r) {
        $r['slot_hour']   = (int) $r['slot_hour'];
        $r['slot_minute'] = (int) $r['slot_minute'];
        $r['duration_m']  = (int) $r['duration_m'];

        // Exclui jogos cujo término foi há mais de 24h
        $gameEnd = (new DateTimeImmutable(
            sprintf('%s %02d:%02d:00', $r['slot_date'], $r['slot_hour'], $r['slot_minute']),
            $tz
        ))->modify("+{$r['duration_m']} minutes");
        if ($gameEnd < $cutoff) continue;

        $r['clip_count']  = clipCount($db, $r);
        $r['access_type'] = ((int) $r['group_count']) > 0 ? 'group' : 'avulso';
        unset($r['group_count']);
        $result[] = $r;
    }

    jsonResponse(200, $result);
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

    // Clipes expiram 24h após triggered_at, que fica dentro da janela do jogo.
    // O último clipe possível expira em: game_end + 24h.
    $tz      = new DateTimeZone($_ENV['TZ'] ?? 'America/Sao_Paulo');
    $gameEnd = (new DateTimeImmutable(
        sprintf('%s %02d:%02d:00', $game['slot_date'], (int)$game['slot_hour'], (int)($game['slot_minute'] ?? 0)),
        $tz
    ))->modify('+' . (int)$game['duration_m'] . ' minutes');
    $maxExpiry = $gameEnd->modify('+24 hours');

    if ($maxExpiry <= new DateTimeImmutable('now', $tz)) {
        jsonResponse(404, ['error' => 'Jogo não encontrado']);
        return;
    }

    $clipCount  = clipCount($db, $game);
    $accessType = gameAccessType($db, $game);

    jsonResponse(200, [
        'id'          => $game['id'],
        'qr_token'    => $game['qr_token'],
        'slot_date'   => $game['slot_date'],
        'slot_hour'   => (int) $game['slot_hour'],
        'slot_minute' => (int) $game['slot_minute'],
        'duration_m'  => (int) $game['duration_m'],
        'camera_id'   => $game['camera_id'],
        'clip_count'  => $clipCount,
        'access_type' => $accessType,
        'free_mode'   => ($_ENV['FREE_MODE'] ?? 'false') === 'true',
    ]);
}

function gameAccessType(PDO $db, array $game): string
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM `groups` g
         JOIN slots s ON s.id = g.slot_id
         WHERE s.weekday      = WEEKDAY(:slot_date)
           AND s.start_hour   = :slot_hour
           AND s.start_minute = :slot_minute'
    );
    $stmt->execute([
        ':slot_date'   => $game['slot_date'],
        ':slot_hour'   => (int) $game['slot_hour'],
        ':slot_minute' => (int) ($game['slot_minute'] ?? 0),
    ]);
    return ((int) $stmt->fetchColumn()) > 0 ? 'group' : 'avulso';
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

    // Aceita game_access_token (avulso/pago) OU group_token com slot compatível
    $slotWeekday = (int) (new DateTimeImmutable($game['slot_date']))->format('N') - 1;
    $hasAccess   = TokenAuth::resolveGameAccess($game['id'])
                || TokenAuth::resolveGroupAccess($slotWeekday, (int) $game['slot_hour'], (int) ($game['slot_minute'] ?? 0));
    if (!$hasAccess) {
        jsonResponse(401, ['error' => 'Acesso não autorizado']);
        return;
    }

    [$utcStart, $utcEnd] = gameUtcWindow($game);

    $vStmt = $db->prepare(
        "SELECT id, seq, camera_id, duration_s, size_bytes, triggered_at, expires_at,
                (thumbnail_key IS NOT NULL) AS has_thumbnail
         FROM videos
         WHERE triggered_at >= :start AND triggered_at < :end
         ORDER BY created_at DESC"
    );
    $vStmt->execute([':start' => $utcStart, ':end' => $utcEnd]);
    $rows = $vStmt->fetchAll(PDO::FETCH_ASSOC);

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

function getGameClips(string $qrToken): void
{
    $db   = Database::getInstance();
    $stmt = $db->prepare('SELECT * FROM games WHERE qr_token = :qr_token');
    $stmt->execute([':qr_token' => $qrToken]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$game) {
        jsonResponse(404, ['error' => 'Jogo não encontrado']);
        return;
    }

    [$utcStart, $utcEnd] = gameUtcWindow($game);

    try {
        $vStmt = $db->prepare(
            "SELECT id, seq, camera_id, duration_s, size_bytes, triggered_at, expires_at,
                    thumbnail_key, gif_key, (thumbnail_key IS NOT NULL) AS has_thumbnail
             FROM videos
             WHERE triggered_at >= :start AND triggered_at < :end
             ORDER BY created_at ASC"
        );
        $vStmt->execute([':start' => $utcStart, ':end' => $utcEnd]);
        $rows = $vStmt->fetchAll(PDO::FETCH_ASSOC);
        $hasGif = true;
    } catch (\PDOException $e) {
        // gif_key column may not exist yet — fall back
        $vStmt = $db->prepare(
            "SELECT id, seq, camera_id, duration_s, size_bytes, triggered_at, expires_at,
                    thumbnail_key, (thumbnail_key IS NOT NULL) AS has_thumbnail
             FROM videos
             WHERE triggered_at >= :start AND triggered_at < :end
             ORDER BY created_at ASC"
        );
        $vStmt->execute([':start' => $utcStart, ':end' => $utcEnd]);
        $rows   = $vStmt->fetchAll(PDO::FETCH_ASSOC);
        $hasGif = false;
    }

    $r2  = new R2Client();
    $utc = new DateTimeZone('UTC');

    foreach ($rows as &$row) {
        $row['triggered_at']  = (new DateTimeImmutable($row['triggered_at'], $utc))->format(DateTimeInterface::ATOM);
        $row['expires_at']    = (new DateTimeImmutable($row['expires_at'], $utc))->format(DateTimeInterface::ATOM);
        $row['seq']           = (int) $row['seq'];
        $row['display_id']    = sprintf('VC-%03d', $row['seq']);
        $row['duration_s']    = (int) $row['duration_s'];
        $row['size_bytes']    = (int) $row['size_bytes'];
        $row['has_thumbnail'] = (bool) $row['has_thumbnail'];
        $row['thumbnail_url'] = ($row['thumbnail_key'] ?? null) !== null
            ? $r2->presign($row['thumbnail_key'], 3600)
            : null;
        $row['gif_url']       = ($hasGif && ($row['gif_key'] ?? null) !== null)
            ? $r2->presign($row['gif_key'], 3600)
            : null;
        unset($row['thumbnail_key'], $row['gif_key']);
    }

    jsonResponse(200, $rows);
}

function getGamePreviews(string $qrToken): void
{
    $db   = Database::getInstance();
    $stmt = $db->prepare('SELECT * FROM games WHERE qr_token = :qr_token');
    $stmt->execute([':qr_token' => $qrToken]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$game) {
        jsonResponse(404, ['error' => 'Jogo não encontrado']);
        return;
    }

    [$utcStart, $utcEnd] = gameUtcWindow($game);

    try {
        $vStmt = $db->prepare(
            "SELECT seq, thumbnail_key, gif_key FROM videos
             WHERE triggered_at >= :start AND triggered_at < :end
             ORDER BY created_at ASC"
        );
        $vStmt->execute([':start' => $utcStart, ':end' => $utcEnd]);
        $rows = $vStmt->fetchAll(PDO::FETCH_ASSOC);
        $hasGif = true;
    } catch (\PDOException $e) {
        // gif_key column may not exist yet — fall back
        $vStmt = $db->prepare(
            "SELECT seq, thumbnail_key FROM videos
             WHERE triggered_at >= :start AND triggered_at < :end
             ORDER BY created_at ASC"
        );
        $vStmt->execute([':start' => $utcStart, ':end' => $utcEnd]);
        $rows = $vStmt->fetchAll(PDO::FETCH_ASSOC);
        $hasGif = false;
    }

    $r2    = new R2Client();
    $clips = array_map(function ($row) use ($r2, $hasGif) {
        return [
            'seq'           => (int) $row['seq'],
            'thumbnail_url' => ($row['thumbnail_key'] ?? null) !== null ? $r2->presign($row['thumbnail_key'], 3600) : null,
            'gif_url'       => ($hasGif && ($row['gif_key'] ?? null) !== null) ? $r2->presign($row['gif_key'], 3600) : null,
        ];
    }, $rows);

    jsonResponse(200, ['clips' => $clips]);
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
    $type   = $body['type']   ?? 'full';
    $method = $body['method'] ?? 'pix';
    $email  = isset($body['email']) ? trim((string) $body['email']) : null;

    if (!in_array($type, ['full', 'clips'], true)) {
        jsonResponse(400, ['error' => 'type deve ser full ou clips']);
        return;
    }
    if (!in_array($method, ['pix', 'credit_card'], true)) {
        jsonResponse(400, ['error' => 'method deve ser pix ou credit_card']);
        return;
    }
    if ($email === null || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(400, ['error' => 'E-mail inválido ou não informado']);
        return;
    }

    $clipIds = null;
    if ($type === 'full') {
        $amount = (float) ($_ENV['PRICE_FULL_GAME'] ?? 25.00);
    } else {
        $clipIds = $body['clip_ids'] ?? [];
        if (!is_array($clipIds) || empty($clipIds)) {
            jsonResponse(400, ['error' => 'clip_ids obrigatório para type=clips']);
            return;
        }
        // Valida formato dos IDs
        $clipIds = array_values(array_filter($clipIds, fn($id) => preg_match('/^[a-f0-9]{32}$/', (string) $id)));
        if (empty($clipIds)) {
            jsonResponse(400, ['error' => 'clip_ids inválidos']);
            return;
        }
        $amount = count($clipIds) * (float) ($_ENV['PRICE_PER_CLIP'] ?? 5.00);
    }

    $paymentId = bin2hex(random_bytes(16));

    $db->prepare(
        'INSERT INTO payments (id, game_id, qr_token, type, clip_ids, method, amount, email)
         VALUES (:id, :game_id, :qr_token, :type, :clip_ids, :method, :amount, :email)'
    )->execute([
        ':id'       => $paymentId,
        ':game_id'  => $game['id'],
        ':qr_token' => $qrToken,
        ':type'     => $type,
        ':clip_ids' => $clipIds !== null ? json_encode($clipIds) : null,
        ':method'   => $method,
        ':amount'   => $amount,
        ':email'    => $email,
    ]);

    $description = $type === 'full'
        ? sprintf('Replay – Jogo %s %02d:%02d', $game['slot_date'], (int) $game['slot_hour'], (int) ($game['slot_minute'] ?? 0))
        : sprintf('Replay – %d clipe(s) em %s', count($clipIds), $game['slot_date']);

    if ($method === 'pix') {
        // PIX_MOCK=true: aprova instantaneamente sem chamar o MP (útil em dev local)
        if (($_ENV['PIX_MOCK'] ?? 'false') === 'true') {
            $db->prepare('UPDATE payments SET status = "approved", access_granted = 1 WHERE id = :id')
               ->execute([':id' => $paymentId]);
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
            $pix = \App\Mp::createPix($amount, $description, $paymentId);
        } catch (\Exception $e) {
            $db->prepare('DELETE FROM payments WHERE id = :id')->execute([':id' => $paymentId]);
            jsonResponse(500, ['error' => 'Falha ao criar Pix: ' . $e->getMessage()]);
            return;
        }
        $db->prepare('UPDATE payments SET mp_payment_id = :mp_id WHERE id = :id')
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
                'external_reference' => $paymentId,
                'back_urls' => [
                    'success' => "{$appUrl}/jogo/{$qrToken}?payment={$paymentId}",
                    'failure' => "{$appUrl}/jogo/{$qrToken}?payment={$paymentId}&pstatus=failure",
                    'pending' => "{$appUrl}/jogo/{$qrToken}?payment={$paymentId}&pstatus=pending",
                ],
                'notification_url' => "{$apiUrl}/api/webhooks/mercadopago",
            ]);
        } catch (\Exception $e) {
            $db->prepare('DELETE FROM payments WHERE id = :id')->execute([':id' => $paymentId]);
            jsonResponse(500, ['error' => 'Falha ao criar preferência: ' . $e->getMessage()]);
            return;
        }
        $db->prepare('UPDATE payments SET mp_preference_id = :pref_id WHERE id = :id')
           ->execute([':pref_id' => $pref['preference_id'], ':id' => $paymentId]);

        jsonResponse(201, [
            'payment_id'   => $paymentId,
            'method'       => 'credit_card',
            'checkout_url' => $pref['init_point'],
            'amount'       => $amount,
        ]);
    }
}

// ── Helpers chamados também pelo postVideo ────────────────────────────────────

function maybeCreateGame(PDO $db, string $cameraId, DateTimeImmutable $triggeredAt): void
{
    $tz      = new DateTimeZone($_ENV['TZ'] ?? 'America/Sao_Paulo');
    $local   = $triggeredAt->setTimezone($tz);
    $weekday = (int) $local->format('N') - 1;
    $minutes = (int) $local->format('H') * 60 + (int) $local->format('i');
    $date    = $local->format('Y-m-d');

    $slotStmt = $db->prepare(
        'SELECT * FROM slots
         WHERE weekday = :weekday
           AND (start_hour * 60 + start_minute) <= :minutes
           AND (start_hour * 60 + start_minute + duration_m) > :minutes
         LIMIT 1'
    );
    $slotStmt->execute([':weekday' => $weekday, ':minutes' => $minutes]);
    $slot = $slotStmt->fetch(PDO::FETCH_ASSOC);

    if (!$slot) return;

    $slotHour   = (int) $slot['start_hour'];
    $slotMinute = (int) $slot['start_minute'];

    $existing = findOrNullGame($db, $cameraId, $date, $slotHour, $slotMinute);
    if ($existing) return;

    $gameId  = bin2hex(random_bytes(16));
    $qrToken = bin2hex(random_bytes(16));
    $db->prepare(
        'INSERT IGNORE INTO games (id, camera_id, slot_date, slot_hour, slot_minute, duration_m, qr_token)
         VALUES (:id, :camera_id, :date, :hour, :minute, :duration_m, :qr_token)'
    )->execute([
        ':id'         => $gameId,
        ':camera_id'  => $cameraId,
        ':date'       => $date,
        ':hour'       => $slotHour,
        ':minute'     => $slotMinute,
        ':duration_m' => (int) $slot['duration_m'],
        ':qr_token'   => $qrToken,
    ]);
}

function findOrNullGame(PDO $db, string $cameraId, string $date, int $slotHour, int $slotMinute = 0): ?array
{
    $stmt = $db->prepare(
        'SELECT * FROM games
         WHERE slot_date = :date AND slot_hour = :hour AND slot_minute = :minute'
    );
    $stmt->execute([':date' => $date, ':hour' => $slotHour, ':minute' => $slotMinute]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function gameUtcWindow(array $game): array
{
    $tz    = new DateTimeZone($_ENV['TZ'] ?? 'America/Sao_Paulo');
    $utc   = new DateTimeZone('UTC');
    $start = (new DateTimeImmutable(
        sprintf('%s %02d:%02d:00', $game['slot_date'], $game['slot_hour'], $game['slot_minute'] ?? 0),
        $tz
    ))->setTimezone($utc);
    $end   = $start->modify("+{$game['duration_m']} minutes");
    return [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
}

function getFreeToken(string $qrToken): void
{
    if (($_ENV['FREE_MODE'] ?? 'false') !== 'true') {
        jsonResponse(403, ['error' => 'Modo gratuito desativado']);
        return;
    }

    $db   = Database::getInstance();
    $stmt = $db->prepare('SELECT * FROM games WHERE qr_token = :qr_token');
    $stmt->execute([':qr_token' => $qrToken]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$game) {
        jsonResponse(404, ['error' => 'Jogo não encontrado']);
        return;
    }

    $expiresAt = (new DateTimeImmutable($game['slot_date']))
        ->modify('+48 hours')
        ->format('Y-m-d H:i:s');

    $token = TokenAuth::issueGameAccessToken($game['id'], null, $expiresAt);
    jsonResponse(200, ['token' => $token, 'expires_at' => $expiresAt, 'type' => 'full']);
}

function clipCount(PDO $db, array $game): int
{
    [$start, $end] = gameUtcWindow($game);
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM videos
         WHERE triggered_at >= :start AND triggered_at < :end
           AND expires_at > UTC_TIMESTAMP()'
    );
    $stmt->execute([':start' => $start, ':end' => $end]);
    return (int) $stmt->fetchColumn();
}
