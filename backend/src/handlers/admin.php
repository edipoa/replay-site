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
    $rows = $db->query('SELECT * FROM slots ORDER BY weekday, start_hour')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['id']         = (int) $r['id'];
        $r['weekday']    = (int) $r['weekday'];
        $r['start_hour'] = (int) $r['start_hour'];
        $r['duration_m'] = (int) $r['duration_m'];
    }
    jsonResponse(200, $rows);
}

function createAdminSlot(): void
{
    requireAdmin();
    $body = json_decode(file_get_contents('php://input'), true);

    foreach (['camera_id', 'weekday', 'start_hour'] as $f) {
        if (!isset($body[$f])) { jsonResponse(400, ['error' => "Campo obrigatório: $f"]); return; }
    }

    $db   = Database::getInstance();
    $stmt = $db->prepare(
        'INSERT INTO slots (camera_id, weekday, start_hour, duration_m, label)
         VALUES (:camera_id, :weekday, :start_hour, :duration_m, :label)'
    );
    $stmt->execute([
        ':camera_id'  => $body['camera_id'],
        ':weekday'    => (int) $body['weekday'],
        ':start_hour' => (int) $body['start_hour'],
        ':duration_m' => (int) ($body['duration_m'] ?? 60),
        ':label'      => $body['label'] ?? null,
    ]);
    jsonResponse(201, ['id' => (int) $db->lastInsertId()]);
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
        'SELECT id, name, camera_id, slot_weekday, slot_hour, login, subscription_expires_at, created_at
         FROM `groups` ORDER BY name'
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['id']           = (int) $r['id'];
        $r['slot_weekday'] = (int) $r['slot_weekday'];
        $r['slot_hour']    = (int) $r['slot_hour'];
    }
    jsonResponse(200, $rows);
}

function createAdminGroup(): void
{
    requireAdmin();
    $body = json_decode(file_get_contents('php://input'), true);

    foreach (['name', 'camera_id', 'slot_weekday', 'slot_hour', 'login', 'password', 'subscription_expires_at'] as $f) {
        if (!isset($body[$f])) { jsonResponse(400, ['error' => "Campo obrigatório: $f"]); return; }
    }

    $hash = password_hash($body['password'], PASSWORD_BCRYPT);
    $db   = Database::getInstance();
    $stmt = $db->prepare(
        'INSERT INTO `groups` (name, camera_id, slot_weekday, slot_hour, login, password_hash, subscription_expires_at)
         VALUES (:name, :camera_id, :slot_weekday, :slot_hour, :login, :password_hash, :expires_at)'
    );
    $stmt->execute([
        ':name'         => $body['name'],
        ':camera_id'    => $body['camera_id'],
        ':slot_weekday' => (int) $body['slot_weekday'],
        ':slot_hour'    => (int) $body['slot_hour'],
        ':login'        => $body['login'],
        ':password_hash'=> $hash,
        ':expires_at'   => $body['subscription_expires_at'],
    ]);
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
    if (isset($body['subscription_expires_at'])) {
        $sets[]                          = 'subscription_expires_at = :expires_at';
        $params[':expires_at']           = $body['subscription_expires_at'];
    }
    if (isset($body['password'])) {
        $sets[]                 = 'password_hash = :password_hash';
        $params[':password_hash'] = password_hash($body['password'], PASSWORD_BCRYPT);
    }

    if (empty($sets)) { jsonResponse(400, ['error' => 'Nenhum campo para atualizar']); return; }

    $db = Database::getInstance();
    $db->prepare('UPDATE `groups` SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
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
        'SELECT g.*, (SELECT COUNT(*) FROM videos v
           WHERE v.camera_id = g.camera_id
             AND v.triggered_at >= CONCAT(g.slot_date, " ", LPAD(g.slot_hour, 2, "0"), ":00:00")
             AND v.triggered_at < DATE_ADD(CONCAT(g.slot_date, " ", LPAD(g.slot_hour, 2, "0"), ":00:00"), INTERVAL g.duration_m MINUTE)
         ) AS clip_count
         FROM games g ORDER BY g.slot_date DESC, g.slot_hour DESC'
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['slot_hour']  = (int) $r['slot_hour'];
        $r['duration_m'] = (int) $r['duration_m'];
        $r['clip_count'] = (int) $r['clip_count'];
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
