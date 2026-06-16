<?php

declare(strict_types=1);

use App\Database;
use App\TokenAuth;

function groupLogin(): void
{
    $body = json_decode(file_get_contents('php://input'), true);
    $login    = trim((string) ($body['login'] ?? ''));
    $password = (string) ($body['password'] ?? '');

    if ($login === '' || $password === '') {
        jsonResponse(400, ['error' => 'login e password obrigatórios']);
        return;
    }

    $db   = Database::getInstance();
    $stmt = $db->prepare('SELECT * FROM `groups` WHERE login = :login');
    $stmt->execute([':login' => $login]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$group || !password_verify($password, $group['password_hash'])) {
        jsonResponse(401, ['error' => 'Credenciais inválidas']);
        return;
    }

    if (new DateTimeImmutable($group['subscription_expires_at']) <= new DateTimeImmutable()) {
        jsonResponse(403, ['error' => 'Assinatura expirada']);
        return;
    }

    $token = TokenAuth::issueGroupToken((int) $group['id'], $group['subscription_expires_at']);

    jsonResponse(200, [
        'token'      => $token,
        'expires_at' => $group['subscription_expires_at'],
        'group_name' => $group['name'],
        'camera_id'  => $group['camera_id'],
    ]);
}

function groupLogout(): void
{
    TokenAuth::revokeGroupToken();
    jsonResponse(200, ['ok' => true]);
}

function adminLogin(): void
{
    $body = json_decode(file_get_contents('php://input'), true);
    $login    = trim((string) ($body['login'] ?? ''));
    $password = (string) ($body['password'] ?? '');

    if ($login === '' || $password === '') {
        jsonResponse(400, ['error' => 'login e password obrigatórios']);
        return;
    }

    $db   = Database::getInstance();
    $stmt = $db->prepare('SELECT * FROM admin_users WHERE login = :login');
    $stmt->execute([':login' => $login]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        jsonResponse(401, ['error' => 'Credenciais inválidas']);
        return;
    }

    $token = TokenAuth::issueAdminToken((int) $admin['id']);
    jsonResponse(200, ['token' => $token]);
}

function adminLogout(): void
{
    TokenAuth::revokeAdminToken();
    jsonResponse(200, ['ok' => true]);
}
