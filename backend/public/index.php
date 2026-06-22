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

set_exception_handler(function (\Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }
    echo json_encode(['error' => $e->getMessage()]);
});

require_once __DIR__ . '/../src/handlers/auth.php';
require_once __DIR__ . '/../src/handlers/user_auth.php';
require_once __DIR__ . '/../src/handlers/user.php';
require_once __DIR__ . '/../src/handlers/invites.php';
require_once __DIR__ . '/../src/handlers/group.php';
require_once __DIR__ . '/../src/handlers/games.php';
require_once __DIR__ . '/../src/handlers/admin.php';
require_once __DIR__ . '/../src/handlers/payments.php';
require_once __DIR__ . '/../src/handlers/share.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Api-Key, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// ── Auth de grupo (existente) ────────────────────────────────────────────────
if ($method === 'POST' && $uri === '/api/auth/login')         { groupLogin();  exit; }
if ($method === 'POST' && $uri === '/api/auth/logout')        { groupLogout(); exit; }
if ($method === 'POST' && $uri === '/api/admin/login')        { adminLogin();  exit; }
if ($method === 'POST' && $uri === '/api/admin/logout')       { adminLogout(); exit; }

// ── Auth de usuário (self-service) ────────────────────────────────────────────
if ($method === 'POST' && $uri === '/api/user/register')        { userRegister();       exit; }
if ($method === 'POST' && $uri === '/api/user/login')           { userLogin();          exit; }
if ($method === 'POST' && $uri === '/api/user/logout')          { userLogout();         exit; }
if ($method === 'POST' && $uri === '/api/user/forgot-password') { userForgotPassword(); exit; }
if ($method === 'POST' && $uri === '/api/user/reset-password')  { userResetPassword();  exit; }

// ── Self-service: slots e assinatura ─────────────────────────────────────────
if ($method === 'GET'  && $uri === '/api/user/slots')     { getAvailableSlots();        exit; }
if ($method === 'POST' && $uri === '/api/user/subscribe') { createUserSubscription();   exit; }
if ($method === 'GET'  && $uri === '/api/user/me')        { getMe();                    exit; }

// ── Self-service: grupo ───────────────────────────────────────────────────────
if ($method === 'GET' && $uri === '/api/user/group/invite-link') { getUserInviteLink();   exit; }
if ($method === 'GET' && $uri === '/api/user/group/members')     { getUserGroupMembers(); exit; }
if ($method === 'GET' && $uri === '/api/user/group/public-link') { getUserPublicLink();   exit; }

// ── Convites ─────────────────────────────────────────────────────────────────
if (preg_match('#^/api/invites/([a-f0-9]{32})$#', $uri, $m)) {
    if ($method === 'GET')  { getInviteInfo($m[1]); exit; }
    if ($method === 'POST') { joinViaInvite($m[1]);  exit; }
}

// ── Group ────────────────────────────────────────────────────────────────────
if ($method === 'GET'  && $uri === '/api/group/videos')       { getGroupVideos();         exit; }
if ($method === 'GET'  && $uri === '/api/group/subscription') { getGroupSubscription();   exit; }
if ($method === 'POST' && $uri === '/api/group/subscribe')    { initiateGroupSubscription(); exit; }

// ── Games ────────────────────────────────────────────────────────────────────
if ($method === 'GET' && $uri === '/api/games')               { getPublicGames(); exit; }
if ($method === 'GET' && $uri === '/api/games/current')       { getCurrentGame(); exit; }

if (preg_match('#^/api/games/([a-f0-9]{32})$#', $uri, $m)) {
    if ($method === 'GET')  { getGame($m[1]);              exit; }
}
if (preg_match('#^/api/games/([a-f0-9]{32})/videos$#', $uri, $m)) {
    if ($method === 'GET')  { getGameVideos($m[1]);        exit; }
}
if (preg_match('#^/api/games/([a-f0-9]{32})/previews$#', $uri, $m)) {
    if ($method === 'GET')  { getGamePreviews($m[1]);      exit; }
}
if (preg_match('#^/api/games/([a-f0-9]{32})/clips$#', $uri, $m)) {
    if ($method === 'GET')  { getGameClips($m[1]);         exit; }
}
if (preg_match('#^/api/games/([a-f0-9]{32})/pay$#', $uri, $m)) {
    if ($method === 'POST') { initiateGamePayment($m[1]);  exit; }
}
if (preg_match('#^/api/games/([a-f0-9]{32})/free-token$#', $uri, $m)) {
    if ($method === 'POST') { getFreeToken($m[1]); exit; }
}

// ── Share links ──────────────────────────────────────────────────────────────
if ($method === 'POST' && $uri === '/api/share')                  { createShareLink(); exit; }
if (preg_match('#^/api/share/([a-f0-9]{32})$#', $uri, $m)) {
    if ($method === 'GET') { getShareLink($m[1]); exit; }
}

// ── Payments ─────────────────────────────────────────────────────────────────
if (preg_match('#^/api/payments/([a-f0-9]{32})/status$#', $uri, $m)) {
    if ($method === 'GET') { getPaymentStatus($m[1]); exit; }
}
if (preg_match('#^/api/payments/sub/([a-f0-9]{32})/status$#', $uri, $m)) {
    if ($method === 'GET') { getSubscriptionPaymentStatus($m[1]); exit; }
}
if ($method === 'POST' && $uri === '/api/webhooks/mercadopago') { handleMpWebhook(); exit; }
if (preg_match('#^/api/recover/([a-f0-9]{32})$#', $uri, $m)) {
    if ($method === 'GET') { getRecoveryLink($m[1]); exit; }
}

// ── Admin ────────────────────────────────────────────────────────────────────
if ($method === 'GET'    && $uri === '/api/admin/slots')      { getAdminSlots();    exit; }
if ($method === 'POST'   && $uri === '/api/admin/slots')      { createAdminSlot();  exit; }
if (preg_match('#^/api/admin/slots/(\d+)$#', $uri, $m)) {
    if ($method === 'PUT')    { updateAdminSlot((int) $m[1]); exit; }
    if ($method === 'DELETE') { deleteAdminSlot((int) $m[1]); exit; }
}
if ($method === 'GET'    && $uri === '/api/admin/groups')     { getAdminGroups();   exit; }
if ($method === 'POST'   && $uri === '/api/admin/groups')     { createAdminGroup(); exit; }
if (preg_match('#^/api/admin/groups/(\d+)$#', $uri, $m)) {
    if ($method === 'PUT')    { updateAdminGroup((int) $m[1]); exit; }
    if ($method === 'DELETE') { deleteAdminGroup((int) $m[1]); exit; }
}
if ($method === 'GET'    && $uri === '/api/admin/games')           { getAdminGames();      exit; }
if ($method === 'GET'    && $uri === '/api/admin/stats')           { getAdminStats();      exit; }
if ($method === 'GET'    && $uri === '/api/admin/dashboard')       { getAdminDashboard();  exit; }
if ($method === 'GET'    && $uri === '/api/admin/orphaned-clips')  { getAdminOrphanedClips(); exit; }
if (preg_match('#^/api/admin/orphaned-clips/([a-f0-9]{32})/create-game$#', $uri, $m)) {
    if ($method === 'POST') { createGameForOrphanedClip($m[1]); exit; }
}

// ── Videos (existentes) ──────────────────────────────────────────────────────
if ($method === 'POST' && $uri === '/api/videos')                                    { postVideo();          exit; }
if ($method === 'GET'  && $uri === '/api/videos')                                    { getVideos();          exit; }
if (preg_match('#^/api/videos/([a-f0-9]{32})$#', $uri, $m)) {
    if ($method === 'GET') {
        if (!resolveVideoAuth($m[1])) { jsonResponse(401, ['error' => 'Acesso não autorizado']); exit; }
        getVideo($m[1]); exit;
    }
}
if (preg_match('#^/api/videos/([a-f0-9]{32})/download$#', $uri, $m)) {
    if ($method === 'GET') {
        if (!resolveVideoAuth($m[1])) { jsonResponse(401, ['error' => 'Acesso não autorizado']); exit; }
        servePresigned($m[1], 'r2_key'); exit;
    }
}
if (preg_match('#^/api/videos/([a-f0-9]{32})/thumbnail$#', $uri, $m)) {
    if ($method === 'GET') { servePresigned($m[1], 'thumbnail_key'); exit; } // thumbnail: público
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

    $utc = new DateTimeZone('UTC');
    $row['triggered_at']  = (new DateTimeImmutable($row['triggered_at'], $utc))->format(DateTimeInterface::ATOM);
    $row['expires_at']    = (new DateTimeImmutable($row['expires_at'], $utc))->format(DateTimeInterface::ATOM);
    $row['seq']           = (int) $row['seq'];
    $row['display_id']    = sprintf('VC-%03d', $row['seq']);
    $row['duration_s']    = (int) $row['duration_s'];
    $row['size_bytes']    = (int) $row['size_bytes'];
    $row['has_thumbnail'] = (bool) $row['has_thumbnail'];
    $row['game_id']       = findGameIdForVideo($db, $row['camera_id'], $row['triggered_at']);

    jsonResponse(200, $row);
}

function findGameIdForVideo(PDO $db, string $cameraId, string $triggeredAtAtom): ?string
{
    $tz  = new DateTimeZone($_ENV['TZ'] ?? 'America/Sao_Paulo');
    $utc = new DateTimeZone('UTC');
    $dt  = (new DateTimeImmutable($triggeredAtAtom, $utc))->setTimezone($tz);

    $stmt = $db->prepare(
        'SELECT id, slot_hour, slot_minute, duration_m FROM games
         WHERE camera_id = :camera_id AND slot_date = :date'
    );
    $stmt->execute([':camera_id' => $cameraId, ':date' => $dt->format('Y-m-d')]);
    $games = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $triggeredMinutes = (int) $dt->format('H') * 60 + (int) $dt->format('i');
    foreach ($games as $g) {
        $slotStart = (int) $g['slot_hour'] * 60 + (int) ($g['slot_minute'] ?? 0);
        if ($triggeredMinutes >= $slotStart && $triggeredMinutes < $slotStart + (int) $g['duration_m']) {
            return $g['id'];
        }
    }
    return null;
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
    $gifKey       = isset($body['gif_key'])       ? (string) $body['gif_key']       : null;

    $db = Database::getInstance();
    try {
        $stmt = $db->prepare(
            'INSERT IGNORE INTO videos
                 (id, camera_id, r2_key, thumbnail_key, gif_key, duration_s, size_bytes, triggered_at, expires_at)
             VALUES
                 (:id, :camera_id, :r2_key, :thumbnail_key, :gif_key, :duration_s, :size_bytes, :triggered_at, :expires_at)'
        );
        $stmt->execute([
            ':id'            => $body['id'],
            ':camera_id'     => $body['camera_id'],
            ':r2_key'        => $body['r2_key'],
            ':thumbnail_key' => $thumbnailKey,
            ':gif_key'       => $gifKey,
            ':duration_s'    => (int) $body['duration_s'],
            ':size_bytes'    => (int) $body['size_bytes'],
            ':triggered_at'  => $triggeredAt->format('Y-m-d H:i:s'),
            ':expires_at'    => $expiresAt->format('Y-m-d H:i:s'),
        ]);
    } catch (\PDOException $e) {
        // gif_key column may not exist yet — fall back to INSERT without it
        if (!str_contains(strtolower($e->getMessage()), 'gif_key')
            && !str_contains(strtolower($e->getMessage()), 'unknown column')) {
            throw $e;
        }
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
    }

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

    $tz = $_ENV['TZ'] ?? 'America/Sao_Paulo';
    $stmt = $db->prepare(
        "SELECT v.id, v.seq, v.camera_id, v.duration_s, v.size_bytes, v.triggered_at, v.expires_at,
                (v.thumbnail_key IS NOT NULL) AS has_thumbnail,
                g.id AS game_id
         FROM videos v
         LEFT JOIN games g ON (
             g.camera_id = v.camera_id
             AND g.slot_date = DATE(CONVERT_TZ(v.triggered_at, 'UTC', :tz))
             AND (TIME_TO_SEC(TIME(CONVERT_TZ(v.triggered_at, 'UTC', :tz2))) / 60)
                 BETWEEN (g.slot_hour * 60 + IFNULL(g.slot_minute, 0))
                 AND    (g.slot_hour * 60 + IFNULL(g.slot_minute, 0) + g.duration_m - 1)
         )
         WHERE {$where} ORDER BY {$orderBy}"
    );
    $params[':tz']  = $tz;
    $params[':tz2'] = $tz;
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $utc = new DateTimeZone('UTC');
    foreach ($rows as &$row) {
        $row['triggered_at']  = (new DateTimeImmutable($row['triggered_at'], $utc))->format(DateTimeInterface::ATOM);
        $row['expires_at']    = (new DateTimeImmutable($row['expires_at'], $utc))->format(DateTimeInterface::ATOM);
        $row['seq']           = (int) $row['seq'];
        $row['display_id']    = sprintf('VC-%03d', $row['seq']);
        $row['duration_s']    = (int) $row['duration_s'];
        $row['size_bytes']    = (int) $row['size_bytes'];
        $row['has_thumbnail'] = (bool) $row['has_thumbnail'];
        $row['game_id']       = $row['game_id'] ?? null;
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

// ── Autorização de vídeo ─────────────────────────────────────────────────────

function resolveVideoAuth(string $videoId): bool
{
    // Aceita token via header Authorization: Bearer ou query param ?t= (para links de download)
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token  = '';
    if (str_starts_with($header, 'Bearer ')) {
        $token = substr($header, 7);
    } elseif (!empty($_GET['t'])) {
        $token = (string) $_GET['t'];
    }
    if ($token === '') return false;

    $db = Database::getInstance();

    // 1. Token de clip específico
    $stmt = $db->prepare(
        'SELECT 1 FROM game_access_tokens WHERE token = :t AND clip_id = :v AND expires_at > NOW()'
    );
    $stmt->execute([':t' => $token, ':v' => $videoId]);
    if ($stmt->fetch()) return true;

    // 2. Token de jogo completo (clip_id IS NULL) — verifica se vídeo está na janela do jogo
    $stmt = $db->prepare(
        'SELECT game_id FROM game_access_tokens WHERE token = :t AND clip_id IS NULL AND expires_at > NOW()'
    );
    $stmt->execute([':t' => $token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $gStmt = $db->prepare('SELECT * FROM games WHERE id = :id');
        $gStmt->execute([':id' => $row['game_id']]);
        $game = $gStmt->fetch(PDO::FETCH_ASSOC);
        if ($game) {
            [$start, $end] = gameUtcWindow($game);
            $vStmt = $db->prepare(
                'SELECT 1 FROM videos WHERE id = :id AND triggered_at >= :s AND triggered_at < :e'
            );
            $vStmt->execute([':id' => $videoId, ':s' => $start, ':e' => $end]);
            if ($vStmt->fetch()) return true;
        }
    }

    // 3. Token de grupo — verifica se vídeo pertence ao horário semanal do grupo (via slot FK)
    $stmt = $db->prepare(
        'SELECT s.weekday, s.start_hour, s.start_minute, s.duration_m
         FROM group_tokens gt
         JOIN `groups` gr ON gr.id = gt.group_id
         JOIN slots    s  ON s.id  = gr.slot_id
         WHERE gt.token = :t AND gt.expires_at > NOW() AND gr.subscription_expires_at > NOW()'
    );
    $stmt->execute([':t' => $token]);
    $slot = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($slot) {
        $vStmt = $db->prepare('SELECT triggered_at FROM videos WHERE id = :id');
        $vStmt->execute([':id' => $videoId]);
        $video = $vStmt->fetch(PDO::FETCH_ASSOC);
        if ($video) {
            $tz      = new DateTimeZone($_ENV['TZ'] ?? 'America/Sao_Paulo');
            $utc     = new DateTimeZone('UTC');
            $dt      = (new DateTimeImmutable($video['triggered_at'], $utc))->setTimezone($tz);
            $weekday = (int) $dt->format('N') - 1;
            $minutes = (int) $dt->format('H') * 60 + (int) $dt->format('i');
            if ($weekday === (int) $slot['weekday']) {
                $slotStart = (int) $slot['start_hour'] * 60 + (int) $slot['start_minute'];
                if ($minutes >= $slotStart && $minutes < $slotStart + (int) $slot['duration_m']) {
                    return true;
                }
            }
        }
    }

    return false;
}

// ── Helper global ─────────────────────────────────────────────────────────────

function jsonResponse(int $code, mixed $data): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
}
