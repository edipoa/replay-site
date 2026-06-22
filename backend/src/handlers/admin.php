<?php

declare(strict_types=1);

use App\Database;
use App\TokenAuth;

function requireAdmin(): void
{
    if (!TokenAuth::resolveAdmin()) {
        jsonResponse(401, ['error' => 'Não autorizado']);
        exit;
    }
}

// ── Slots ─────────────────────────────────────────────────────────────────────

function getAdminSlots(): void
{
    requireAdmin();
    $db   = Database::getInstance();
    $rows = $db->query('SELECT * FROM slots ORDER BY weekday, start_hour, start_minute')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['id']           = (int) $r['id'];
        $r['weekday']      = (int) $r['weekday'];
        $r['start_hour']   = (int) $r['start_hour'];
        $r['start_minute'] = (int) $r['start_minute'];
        $r['duration_m']   = (int) $r['duration_m'];
    }
    jsonResponse(200, $rows);
}

function createAdminSlot(): void
{
    requireAdmin();
    $body = json_decode(file_get_contents('php://input'), true);

    foreach (['weekday', 'start_hour'] as $f) {
        if (!isset($body[$f])) { jsonResponse(400, ['error' => "Campo obrigatório: $f"]); return; }
    }

    $db   = Database::getInstance();
    $stmt = $db->prepare(
        'INSERT INTO slots (weekday, start_hour, start_minute, duration_m, label)
         VALUES (:weekday, :start_hour, :start_minute, :duration_m, :label)'
    );
    $stmt->execute([
        ':weekday'      => (int) $body['weekday'],
        ':start_hour'   => (int) $body['start_hour'],
        ':start_minute' => (int) ($body['start_minute'] ?? 0),
        ':duration_m'   => (int) ($body['duration_m'] ?? 60),
        ':label'        => $body['label'] ?? null,
    ]);
    jsonResponse(201, ['id' => (int) $db->lastInsertId()]);
}

function updateAdminSlot(int $id): void
{
    requireAdmin();
    $body = json_decode(file_get_contents('php://input'), true);
    $db   = Database::getInstance();

    $sets   = [];
    $params = [':id' => $id];

    if (isset($body['weekday']))      { $sets[] = 'weekday = :weekday';           $params[':weekday']      = (int) $body['weekday']; }
    if (isset($body['start_hour']))   { $sets[] = 'start_hour = :start_hour';     $params[':start_hour']   = (int) $body['start_hour']; }
    if (isset($body['start_minute'])) { $sets[] = 'start_minute = :start_minute'; $params[':start_minute'] = (int) $body['start_minute']; }
    if (isset($body['duration_m']))   { $sets[] = 'duration_m = :duration_m';     $params[':duration_m']   = (int) $body['duration_m']; }
    if (array_key_exists('label', $body)) { $sets[] = 'label = :label';           $params[':label']        = $body['label'] ?: null; }

    if (empty($sets)) { jsonResponse(400, ['error' => 'Nada para atualizar']); return; }

    $db->prepare('UPDATE slots SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
    jsonResponse(200, ['ok' => true]);
}

function deleteAdminSlot(int $id): void
{
    requireAdmin();
    $db = Database::getInstance();
    $db->prepare('DELETE FROM slots WHERE id = :id')->execute([':id' => $id]);
    jsonResponse(200, ['ok' => true]);
}

// ── Groups ────────────────────────────────────────────────────────────────────

function getAdminGroups(): void
{
    requireAdmin();
    $db   = Database::getInstance();
    $rows = $db->query(
        'SELECT g.id, g.name, g.slot_id, g.login, g.subscription_expires_at, g.created_at,
                s.weekday, s.start_hour, s.start_minute, s.label AS slot_label
         FROM `groups` g
         JOIN slots s ON s.id = g.slot_id
         ORDER BY g.name'
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['id']           = (int) $r['id'];
        $r['slot_id']      = (int) $r['slot_id'];
        $r['weekday']      = (int) $r['weekday'];
        $r['start_hour']   = (int) $r['start_hour'];
        $r['start_minute'] = (int) $r['start_minute'];
    }
    jsonResponse(200, $rows);
}

function createAdminGroup(): void
{
    requireAdmin();
    $body = json_decode(file_get_contents('php://input'), true);

    foreach (['name', 'slot_id', 'login', 'password', 'subscription_expires_at'] as $f) {
        if (!isset($body[$f])) { jsonResponse(400, ['error' => "Campo obrigatório: $f"]); return; }
    }

    $hash = password_hash($body['password'], PASSWORD_BCRYPT);
    $db   = Database::getInstance();
    $stmt = $db->prepare(
        'INSERT INTO `groups` (name, slot_id, login, password_hash, subscription_expires_at)
         VALUES (:name, :slot_id, :login, :password_hash, :expires_at)'
    );
    try {
        $stmt->execute([
            ':name'          => $body['name'],
            ':slot_id'       => (int) $body['slot_id'],
            ':login'         => $body['login'],
            ':password_hash' => $hash,
            ':expires_at'    => $body['subscription_expires_at'],
        ]);
    } catch (\PDOException $e) {
        if ($e->getCode() === '23000') {
            jsonResponse(409, ['error' => 'Login já está em uso por outro grupo']);
        } else {
            jsonResponse(500, ['error' => 'Erro ao salvar grupo']);
        }
        return;
    }
    jsonResponse(201, ['id' => (int) $db->lastInsertId()]);
}

function updateAdminGroup(int $id): void
{
    requireAdmin();
    $body = json_decode(file_get_contents('php://input'), true);

    $sets   = [];
    $params = [':id' => $id];

    foreach (['name', 'login'] as $f) {
        if (isset($body[$f])) { $sets[] = "$f = :$f"; $params[":$f"] = $body[$f]; }
    }
    if (isset($body['slot_id'])) {
        $sets[]            = 'slot_id = :slot_id';
        $params[':slot_id'] = (int) $body['slot_id'];
    }
    if (isset($body['subscription_expires_at'])) {
        $sets[]                = 'subscription_expires_at = :expires_at';
        $params[':expires_at'] = $body['subscription_expires_at'];
    }
    if (isset($body['password'])) {
        $sets[]                   = 'password_hash = :password_hash';
        $params[':password_hash'] = password_hash($body['password'], PASSWORD_BCRYPT);
    }

    if (empty($sets)) { jsonResponse(400, ['error' => 'Nenhum campo para atualizar']); return; }

    $db   = Database::getInstance();
    $stmt = $db->prepare('UPDATE `groups` SET ' . implode(', ', $sets) . ' WHERE id = :id');
    try {
        $stmt->execute($params);
    } catch (\PDOException $e) {
        if ($e->getCode() === '23000') {
            jsonResponse(409, ['error' => 'Login já está em uso por outro grupo']);
        } else {
            jsonResponse(500, ['error' => 'Erro ao atualizar grupo']);
        }
        return;
    }
    jsonResponse(200, ['ok' => true]);
}

function deleteAdminGroup(int $id): void
{
    requireAdmin();
    $db = Database::getInstance();
    $db->prepare('DELETE FROM `groups` WHERE id = :id')->execute([':id' => $id]);
    jsonResponse(200, ['ok' => true]);
}

// ── Games ─────────────────────────────────────────────────────────────────────

function getAdminGames(): void
{
    requireAdmin();
    $db   = Database::getInstance();
    $rows = $db->query(
        'SELECT * FROM games ORDER BY slot_date DESC, slot_hour DESC, slot_minute DESC'
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['slot_hour']   = (int) $r['slot_hour'];
        $r['slot_minute'] = (int) $r['slot_minute'];
        $r['duration_m']  = (int) $r['duration_m'];
        $r['clip_count']  = clipCount($db, $r);
    }
    jsonResponse(200, $rows);
}

function getAdminStats(): void
{
    requireAdmin();
    $db = Database::getInstance();

    $stats = [
        'total_groups'  => (int) $db->query('SELECT COUNT(*) FROM `groups`')->fetchColumn(),
        'active_groups' => (int) $db->query("SELECT COUNT(*) FROM `groups` WHERE subscription_expires_at > NOW()")->fetchColumn(),
        'total_games'   => (int) $db->query('SELECT COUNT(*) FROM games')->fetchColumn(),
        'total_slots'   => (int) $db->query('SELECT COUNT(*) FROM slots')->fetchColumn(),
        'videos_today'  => (int) $db->query("SELECT COUNT(*) FROM videos WHERE DATE(triggered_at) = CURDATE()")->fetchColumn(),
    ];

    jsonResponse(200, $stats);
}

function getAdminDashboard(): void
{
    requireAdmin();

    $from = $_GET['from'] ?? date('Y-m-01');
    $to   = $_GET['to']   ?? date('Y-m-t');

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-t');

    $tz    = $_ENV['TZ'] ?? 'America/Sao_Paulo';
    $tzObj = new DateTimeZone($tz);
    $utc   = new DateTimeZone('UTC');

    $fromDt  = $from . ' 00:00:00';
    $toDt    = $to   . ' 23:59:59';

    // Videos são armazenados em UTC — converte o range local para UTC
    $fromUtc = (new DateTimeImmutable($from . ' 00:00:00', $tzObj))->setTimezone($utc)->format('Y-m-d H:i:s');
    $toUtc   = (new DateTimeImmutable($to   . ' 23:59:59', $tzObj))->setTimezone($utc)->format('Y-m-d H:i:s');

    $db = Database::getInstance();

    // ── Receita: assinaturas ──────────────────────────────────────────────────
    $stmt = $db->prepare(
        'SELECT status, IFNULL(SUM(amount), 0) AS total
         FROM subscription_payments
         WHERE created_at BETWEEN :from AND :to
         GROUP BY status'
    );
    $stmt->execute([':from' => $fromDt, ':to' => $toDt]);
    $subscriptions = buildRevenueByStatus($stmt->fetchAll(PDO::FETCH_ASSOC));

    // ── Receita: avulsas (pay-per-game) ───────────────────────────────────────
    $stmt = $db->prepare(
        'SELECT status, IFNULL(SUM(amount), 0) AS total
         FROM payments
         WHERE created_at BETWEEN :from AND :to
         GROUP BY status'
    );
    $stmt->execute([':from' => $fromDt, ':to' => $toDt]);
    $payPerGame = buildRevenueByStatus($stmt->fetchAll(PDO::FETCH_ASSOC));

    // ── Totais ────────────────────────────────────────────────────────────────
    $s = $db->prepare('SELECT COUNT(*) FROM videos WHERE triggered_at BETWEEN :from AND :to');
    $s->execute([':from' => $fromUtc, ':to' => $toUtc]);
    $totalClips = (int) $s->fetchColumn();

    $s = $db->prepare('SELECT COUNT(*) FROM games WHERE slot_date BETWEEN :from AND :to');
    $s->execute([':from' => $from, ':to' => $to]);
    $totalGames = (int) $s->fetchColumn();

    // ── Por grupo: jogos + clipes ─────────────────────────────────────────────
    $s = $db->prepare(
        "SELECT g.id AS group_id, g.name AS group_name,
                COUNT(DISTINCT gm.id) AS game_count,
                COUNT(v.id) AS clip_count
         FROM `groups` g
         JOIN slots s ON s.id = g.slot_id
         LEFT JOIN games gm
             ON  WEEKDAY(gm.slot_date)        = s.weekday
             AND gm.slot_hour                 = s.start_hour
             AND IFNULL(gm.slot_minute, 0)    = IFNULL(s.start_minute, 0)
             AND gm.slot_date BETWEEN :from_date AND :to_date
         LEFT JOIN videos v
             ON  v.camera_id    = gm.camera_id
             AND v.triggered_at >= CONVERT_TZ(
                     CONCAT(gm.slot_date,' ',LPAD(gm.slot_hour,2,'0'),':',LPAD(IFNULL(gm.slot_minute,0),2,'0'),':00'),
                     :tz1, 'UTC')
             AND v.triggered_at <  CONVERT_TZ(
                     CONCAT(gm.slot_date,' ',LPAD(gm.slot_hour,2,'0'),':',LPAD(IFNULL(gm.slot_minute,0),2,'0'),':00'),
                     :tz2, 'UTC') + INTERVAL gm.duration_m MINUTE
         GROUP BY g.id, g.name
         ORDER BY clip_count DESC, game_count DESC"
    );
    $s->execute([':from_date' => $from, ':to_date' => $to, ':tz1' => $tz, ':tz2' => $tz]);
    $byGroup = $s->fetchAll(PDO::FETCH_ASSOC);
    foreach ($byGroup as &$row) {
        $row['group_id']   = (int) $row['group_id'];
        $row['game_count'] = (int) $row['game_count'];
        $row['clip_count'] = (int) $row['clip_count'];
    }
    unset($row);

    // ── Por jogo: compras + receita ───────────────────────────────────────────
    $s = $db->prepare(
        'SELECT gm.id, gm.slot_date, gm.slot_hour, gm.slot_minute, gm.camera_id,
                COUNT(DISTINCT p.id) AS purchase_count,
                IFNULL(SUM(CASE WHEN p.status = \'approved\' THEN p.amount ELSE 0 END), 0) AS revenue_approved
         FROM games gm
         LEFT JOIN payments p ON p.game_id = gm.id
         WHERE gm.slot_date BETWEEN :from AND :to
         GROUP BY gm.id, gm.slot_date, gm.slot_hour, gm.slot_minute, gm.camera_id
         ORDER BY gm.slot_date DESC, gm.slot_hour DESC, gm.slot_minute DESC'
    );
    $s->execute([':from' => $from, ':to' => $to]);
    $byGame = $s->fetchAll(PDO::FETCH_ASSOC);
    foreach ($byGame as &$row) {
        $row['slot_hour']        = (int) $row['slot_hour'];
        $row['slot_minute']      = (int) ($row['slot_minute'] ?? 0);
        $row['purchase_count']   = (int) $row['purchase_count'];
        $row['revenue_approved'] = (float) $row['revenue_approved'];
    }
    unset($row);

    jsonResponse(200, [
        'period'      => ['from' => $from, 'to' => $to],
        'revenue'     => ['subscriptions' => $subscriptions, 'pay_per_game' => $payPerGame],
        'totals'      => ['clips' => $totalClips, 'games' => $totalGames],
        'by_group'    => $byGroup,
        'by_game'     => $byGame,
    ]);
}

// ── Clipes órfãos ─────────────────────────────────────────────────────────────

function getAdminOrphanedClips(): void
{
    requireAdmin();
    $tz = $_ENV['TZ'] ?? 'America/Sao_Paulo';
    $db = Database::getInstance();

    $stmt = $db->prepare(
        "SELECT v.id, v.seq, v.camera_id, v.duration_s, v.size_bytes, v.triggered_at, v.expires_at,
                (v.thumbnail_key IS NOT NULL) AS has_thumbnail,
                (v.expires_at > UTC_TIMESTAMP()) AS is_active
         FROM videos v
         LEFT JOIN games g ON (
             g.camera_id = v.camera_id
             AND g.slot_date = DATE(CONVERT_TZ(v.triggered_at, 'UTC', :tz))
             AND (TIME_TO_SEC(TIME(CONVERT_TZ(v.triggered_at, 'UTC', :tz2))) / 60)
                 BETWEEN (g.slot_hour * 60 + IFNULL(g.slot_minute, 0))
                 AND    (g.slot_hour * 60 + IFNULL(g.slot_minute, 0) + g.duration_m - 1)
         )
         WHERE g.id IS NULL
         ORDER BY v.triggered_at DESC
         LIMIT 200"
    );
    $stmt->execute([':tz' => $tz, ':tz2' => $tz]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $utc   = new DateTimeZone('UTC');
    $tzObj = new DateTimeZone($tz);
    foreach ($rows as &$row) {
        $row['triggered_at']  = (new DateTimeImmutable($row['triggered_at'], $utc))->format(DateTimeInterface::ATOM);
        $row['expires_at']    = (new DateTimeImmutable($row['expires_at'],   $utc))->format(DateTimeInterface::ATOM);
        $row['seq']           = (int)  $row['seq'];
        $row['display_id']    = sprintf('VC-%03d', $row['seq']);
        $row['duration_s']    = (int)  $row['duration_s'];
        $row['size_bytes']    = (int)  $row['size_bytes'];
        $row['has_thumbnail'] = (bool) $row['has_thumbnail'];
        $row['is_active']     = (bool) $row['is_active'];
        $local                = (new DateTimeImmutable($row['triggered_at']))->setTimezone($tzObj);
        $row['local_date']    = $local->format('Y-m-d');
        $row['local_hour']    = (int) $local->format('H');
        $row['local_minute']  = (int) $local->format('i');
    }
    jsonResponse(200, $rows);
}

function createGameForOrphanedClip(string $videoId): void
{
    requireAdmin();
    $body = json_decode(file_get_contents('php://input'), true);

    foreach (['hour', 'minute', 'duration_m'] as $f) {
        if (!isset($body[$f])) { jsonResponse(400, ['error' => "Campo obrigatório: $f"]); return; }
    }

    $db   = Database::getInstance();
    $stmt = $db->prepare('SELECT camera_id, triggered_at FROM videos WHERE id = :id');
    $stmt->execute([':id' => $videoId]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$video) { jsonResponse(404, ['error' => 'Vídeo não encontrado']); return; }

    $tz    = new DateTimeZone($_ENV['TZ'] ?? 'America/Sao_Paulo');
    $utc   = new DateTimeZone('UTC');
    $local = (new DateTimeImmutable($video['triggered_at'], $utc))->setTimezone($tz);
    $date  = $local->format('Y-m-d');
    $hour  = (int) $body['hour'];
    $min   = (int) $body['minute'];
    $dur   = (int) $body['duration_m'];

    $existing = findOrNullGame($db, $video['camera_id'], $date, $hour, $min);
    if ($existing) {
        jsonResponse(409, [
            'error'     => 'Já existe um jogo para este horário',
            'game_id'   => $existing['id'],
            'qr_token'  => $existing['qr_token'],
        ]);
        return;
    }

    $gameId  = bin2hex(random_bytes(16));
    $qrToken = bin2hex(random_bytes(16));
    $db->prepare(
        'INSERT INTO games (id, camera_id, slot_date, slot_hour, slot_minute, duration_m, qr_token)
         VALUES (:id, :camera_id, :date, :hour, :minute, :duration_m, :qr_token)'
    )->execute([
        ':id'         => $gameId,
        ':camera_id'  => $video['camera_id'],
        ':date'       => $date,
        ':hour'       => $hour,
        ':minute'     => $min,
        ':duration_m' => $dur,
        ':qr_token'   => $qrToken,
    ]);

    jsonResponse(201, ['game_id' => $gameId, 'qr_token' => $qrToken]);
}

function buildRevenueByStatus(array $rows): array
{
    $result = ['approved' => 0.0, 'pending' => 0.0, 'failed' => 0.0];
    foreach ($rows as $r) {
        if (array_key_exists($r['status'], $result)) {
            $result[$r['status']] = (float) $r['total'];
        }
    }
    return $result;
}
