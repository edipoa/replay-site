<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Database;
use App\R2Client;
use App\TokenAuth;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

date_default_timezone_set($_ENV['TZ'] ?? 'America/Sao_Paulo');

require_once __DIR__ . '/../src/handlers/auth.php';
require_once __DIR__ . '/../src/handlers/group.php';
require_once __DIR__ . '/../src/handlers/games.php';
require_once __DIR__ . '/../src/handlers/admin.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Api-Key, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// ── Auth ─────────────────────────────────────────────────────────────────────
if ($method === 'POST' && $uri === '/api/auth/login')         { groupLogin();  exit; }
if ($method === 'POST' && $uri === '/api/auth/logout')        { groupLogout(); exit; }
if ($method === 'POST' && $uri === '/api/admin/login')        { adminLogin();  exit; }
if ($method === 'POST' && $uri === '/api/admin/logout')       { adminLogout(); exit; }

// ── Group ────────────────────────────────────────────────────────────────────
if ($method === 'GET' && $uri === '/api/group/videos')        { getGroupVideos(); exit; }

// ── Games ────────────────────────────────────────────────────────────────────
if ($method === 'GET' && $uri === '/api/games/current')       { getCurrentGame(); exit; }

if (preg_match('#^/api/games/([a-f0-9]{32})$#', $uri, $m)) {
    if ($method === 'GET')  { getGame($m[1]);              exit; }
}
if (preg_match('#^/api/games/([a-f0-9]{32})/videos$#', $uri, $m)) {
    if ($method === 'GET')  { getGameVideos($m[1]);        exit; }
}
if (preg_match('#^/api/games/([a-f0-9]{32})/pay$#', $uri, $m)) {
    if ($method === 'POST') { initiateGamePayment($m[1]);  exit; }
}

// ── Admin ────────────────────────────────────────────────────────────────────
if ($method === 'GET'    && $uri === '/api/admin/slots')      { getAdminSlots();    exit; }
if ($method === 'POST'   && $uri === '/api/admin/slots')      { createAdminSlot();  exit; }
if (preg_match('#^/api/admin/slots/(\d+)$#', $uri, $m)) {
    if ($method === 'DELETE') { deleteAdminSlot((int) $m[1]); exit; }
}
if ($method === 'GET'    && $uri === '/api/admin/groups')     { getAdminGroups();   exit; }
if ($method === 'POST'   && $uri === '/api/admin/groups')     { createAdminGroup(); exit; }
if (preg_match('#^/api/admin/groups/(\d+)$#', $uri, $m)) {
    if ($method === 'PUT')    { updateAdminGroup((int) $m[1]); exit; }
    if ($method === 'DELETE') { deleteAdminGroup((int) $m[1]); exit; }
}
if ($method === 'GET'    && $uri === '/api/admin/games')      { getAdminGames();    exit; }
if ($method === 'GET'    && $uri === '/api/admin/stats')      { getAdminStats();    exit; }

// ── Videos (existentes) ──────────────────────────────────────────────────────
if ($method === 'POST' && $uri === '/api/videos')                                    { postVideo();          exit; }
if ($method === 'GET'  && $uri === '/api/videos')                                    { getVideos();          exit; }
if (preg_match('#^/api/videos/([a-f0-9]{32})$#', $uri, $m)) {
    if ($method === 'GET') { getVideo($m[1]); exit; }
}
if (preg_match('#^/api/videos/([a-f0-9]{32})/download$#', $uri, $m)) {
    if ($method === 'GET') { servePresigned($m[1], 'r2_key'); exit; }
}
if (preg_match('#^/api/videos/([a-f0-9]{32})/thumbnail$#', $uri, $m)) {
    if ($method === 'GET') { servePresigned($m[1], 'thumbnail_key'); exit; }
}

jsonResponse(404, ['error' => 'Not Found']);

// ── Handlers de vídeo ────────────────────────────────────────────────────────

function getVideo(string $id): void
{
    $db   = Database::getInstance();
    $stmt = $db->prepare(
        'SELECT id, seq, camera_id, r2_key, duration_s, size_bytes, triggered_at, expires_at,
                (thumbnail_key IS NOT NULL) AS has_thumbnail
         FROM videos
         WHERE id = :id AND expires_at > NOW()'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) { jsonResponse(404, ['error' => 'Not Found']); return; }

    $key = $row['r2_key'];
    if (str_starts_with($key, 'http://') || str_starts_with($key, 'https://')) {
        $row['stream_url'] = $key;
    } else {
        $row['stream_url'] = (new R2Client())->presign($key, 3600);
    }
    unset($row['r2_key']);

    $row['triggered_at']  = (new DateTimeImmutable($row['triggered_at']))->format(DateTimeInterface::ATOM);
    $row['expires_at']    = (new DateTimeImmutable($row['expires_at']))->format(DateTimeInterface::ATOM);
    $row['seq']           = (int) $row['seq'];
    $row['display_id']    = sprintf('VC-%03d', $row['seq']);
    $row['duration_s']    = (int) $row['duration_s'];
    $row['size_bytes']    = (int) $row['size_bytes'];
    $row['has_thumbnail'] = (bool) $row['has_thumbnail'];

    jsonResponse(200, $row);
}

function postVideo(): void
{
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (!hash_equals($_ENV['API_KEY'], $apiKey)) {
        jsonResponse(401, ['error' => 'Unauthorized']);
        return;
    }

    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) { jsonResponse(400, ['error' => 'Invalid JSON']); return; }

    foreach (['id', 'camera_id', 'r2_key', 'duration_s', 'size_bytes', 'triggered_at'] as $field) {
        if (!isset($body[$field])) { jsonResponse(400, ['error' => "Missing field: $field"]); return; }
    }

    if (!preg_match('/^[a-f0-9]{32}$/', (string) $body['id'])) {
        jsonResponse(400, ['error' => 'Invalid id format']);
        return;
    }

    try {
        $triggeredAt = (new DateTimeImmutable($body['triggered_at']))->setTimezone(new DateTimeZone('UTC'));
    } catch (Exception) {
        jsonResponse(400, ['error' => 'Invalid triggered_at']);
        return;
    }

    $expiresAt    = $triggeredAt->modify('+24 hours');
    $thumbnailKey = isset($body['thumbnail_key']) ? (string) $body['thumbnail_key'] : null;

    $db   = Database::getInstance();
    $stmt = $db->prepare(
        'INSERT IGNORE INTO videos
             (id, camera_id, r2_key, thumbnail_key, duration_s, size_bytes, triggered_at, expires_at)
         VALUES
             (:id, :camera_id, :r2_key, :thumbnail_key, :duration_s, :size_bytes, :triggered_at, :expires_at)'
    );
    $stmt->execute([
        ':id'            => $body['id'],
        ':camera_id'     => $body['camera_id'],
        ':r2_key'        => $body['r2_key'],
        ':thumbnail_key' => $thumbnailKey,
        ':duration_s'    => (int) $body['duration_s'],
        ':size_bytes'    => (int) $body['size_bytes'],
        ':triggered_at'  => $triggeredAt->format('Y-m-d H:i:s'),
        ':expires_at'    => $expiresAt->format('Y-m-d H:i:s'),
    ]);

    // Cria o game automaticamente se este clip pertence a um slot cadastrado
    maybeCreateGame($db, (string) $body['camera_id'], $triggeredAt);

    jsonResponse(201, ['ok' => true]);
}

function getVideos(): void
{
    $db = Database::getInstance();

    $conditions = ['expires_at > NOW()'];
    $params     = [];

    $date = $_GET['date'] ?? '';
    if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $conditions[] = 'DATE(triggered_at) = :date';
        $params[':date'] = $date;
    } else {
        $periodHours = match ($_GET['period'] ?? '24h') {
            '1h'  => 1, '6h'  => 6, '12h' => 12, default => 24,
        };
        $conditions[]     = 'triggered_at >= NOW() - INTERVAL :hours HOUR';
        $params[':hours'] = $periodHours;
    }

    $orderBy = ($_GET['sort'] ?? 'recent') === 'expiring' ? 'expires_at ASC' : 'triggered_at DESC';
    $where   = implode(' AND ', $conditions);

    $stmt = $db->prepare(
        "SELECT id, seq, camera_id, duration_s, size_bytes, triggered_at, expires_at,
                (thumbnail_key IS NOT NULL) AS has_thumbnail
         FROM videos WHERE {$where} ORDER BY {$orderBy}"
    );
    $stmt->execute($params);
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

function servePresigned(string $id, string $column): void
{
    $db   = Database::getInstance();
    $stmt = $db->prepare(
        "SELECT {$column} FROM videos WHERE id = :id AND expires_at > NOW()"
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || $row[$column] === null) { jsonResponse(404, ['error' => 'Not Found']); return; }

    $key = $row[$column];
    if (str_starts_with($key, 'http://') || str_starts_with($key, 'https://')) {
        header('Location: ' . $key, true, 302);
        exit;
    }

    $disposition = $column === 'r2_key' ? 'attachment; filename="' . basename($key) . '"' : '';
    $url = (new R2Client())->presign($key, 3600, $disposition);
    header('Location: ' . $url, true, 302);
    exit;
}

// ── Helper global ─────────────────────────────────────────────────────────────

function jsonResponse(int $code, mixed $data): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
}
