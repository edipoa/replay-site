<?php

declare(strict_types=1);

use App\Database;
use App\TokenAuth;
use App\Mailer;

function userRegister(): void
{
    $body     = json_decode(file_get_contents('php://input'), true);
    $name     = trim((string) ($body['name']     ?? ''));
    $email    = trim((string) ($body['email']    ?? ''));
    $password = (string) ($body['password'] ?? '');

    if ($name === '' || $email === '' || $password === '') {
        jsonResponse(400, ['error' => 'name, email e password são obrigatórios']);
        return;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(400, ['error' => 'E-mail inválido']);
        return;
    }
    if (strlen($password) < 6) {
        jsonResponse(400, ['error' => 'Senha deve ter ao menos 6 caracteres']);
        return;
    }

    $db   = Database::getInstance();
    $stmt = $db->prepare('SELECT id FROM users WHERE email = :email');
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        jsonResponse(409, ['error' => 'E-mail já cadastrado']);
        return;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $db->prepare(
        'INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :hash)'
    )->execute([':name' => $name, ':email' => $email, ':hash' => $hash]);

    $userId = (int) $db->lastInsertId();
    $token  = TokenAuth::issueUserSession($userId);

    jsonResponse(201, ['token' => $token, 'name' => $name, 'email' => $email]);
}

function userLogin(): void
{
    $body     = json_decode(file_get_contents('php://input'), true);
    $email    = trim((string) ($body['email']    ?? ''));
    $password = (string) ($body['password'] ?? '');

    if ($email === '' || $password === '') {
        jsonResponse(400, ['error' => 'email e password são obrigatórios']);
        return;
    }

    $db   = Database::getInstance();
    $stmt = $db->prepare('SELECT * FROM users WHERE email = :email');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        jsonResponse(401, ['error' => 'Credenciais inválidas']);
        return;
    }

    // Verifica se tem grupo ativo
    $gStmt = $db->prepare(
        'SELECT g.id FROM user_group_memberships m
         JOIN `groups` g ON g.id = m.group_id
         WHERE m.user_id = :uid AND g.subscription_expires_at > NOW()
         LIMIT 1'
    );
    $gStmt->execute([':uid' => $user['id']]);
    $hasActiveGroup = (bool) $gStmt->fetch();

    // Verifica se tem algum grupo (mesmo expirado)
    $anyGroup = null;
    if (!$hasActiveGroup) {
        $aStmt = $db->prepare(
            'SELECT g.id FROM user_group_memberships m
             JOIN `groups` g ON g.id = m.group_id
             WHERE m.user_id = :uid LIMIT 1'
        );
        $aStmt->execute([':uid' => $user['id']]);
        $anyGroup = $aStmt->fetch(PDO::FETCH_ASSOC);
    }

    $token = TokenAuth::issueUserSession((int) $user['id']);

    jsonResponse(200, [
        'token'              => $token,
        'name'               => $user['name'],
        'email'              => $user['email'],
        'has_active_group'   => $hasActiveGroup,
        'has_group'          => $hasActiveGroup || !empty($anyGroup),
    ]);
}

function userLogout(): void
{
    TokenAuth::revokeUserSession();
    jsonResponse(200, ['ok' => true]);
}

function userForgotPassword(): void
{
    $body  = json_decode(file_get_contents('php://input'), true);
    $email = trim((string) ($body['email'] ?? ''));

    if ($email === '') {
        jsonResponse(400, ['error' => 'email é obrigatório']);
        return;
    }

    $db   = Database::getInstance();
    $stmt = $db->prepare('SELECT id FROM users WHERE email = :email');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Responde sempre 200 para não vazar quais emails existem
    if (!$user) {
        jsonResponse(200, ['ok' => true]);
        return;
    }

    // Invalida tokens anteriores não usados
    $db->prepare('DELETE FROM password_reset_tokens WHERE user_id = :uid AND used_at IS NULL')
       ->execute([':uid' => $user['id']]);

    $token     = bin2hex(random_bytes(32));
    $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+1 hour')->format('Y-m-d H:i:s');

    $db->prepare(
        'INSERT INTO password_reset_tokens (token, user_id, expires_at) VALUES (:token, :uid, :exp)'
    )->execute([':token' => $token, ':uid' => $user['id'], ':exp' => $expiresAt]);

    $appUrl   = rtrim($_ENV['APP_URL'] ?? '', '/');
    $resetUrl = "{$appUrl}/redefinir-senha?token={$token}";

    Mailer::passwordReset($email, $resetUrl);

    jsonResponse(200, ['ok' => true]);
}

function userResetPassword(): void
{
    $body     = json_decode(file_get_contents('php://input'), true);
    $token    = trim((string) ($body['token']    ?? ''));
    $password = (string) ($body['password'] ?? '');

    if ($token === '' || $password === '') {
        jsonResponse(400, ['error' => 'token e password são obrigatórios']);
        return;
    }
    if (strlen($password) < 6) {
        jsonResponse(400, ['error' => 'Senha deve ter ao menos 6 caracteres']);
        return;
    }

    $db   = Database::getInstance();
    $stmt = $db->prepare(
        'SELECT * FROM password_reset_tokens WHERE token = :token AND expires_at > NOW() AND used_at IS NULL'
    );
    $stmt->execute([':token' => $token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        jsonResponse(400, ['error' => 'Token inválido ou expirado']);
        return;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $db->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')
       ->execute([':hash' => $hash, ':id' => $row['user_id']]);

    $db->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE token = :token')
       ->execute([':token' => $token]);

    // Invalida todas as sessões ativas do usuário por segurança
    $db->prepare('DELETE FROM user_sessions WHERE user_id = :uid')
       ->execute([':uid' => $row['user_id']]);

    jsonResponse(200, ['ok' => true]);
}
