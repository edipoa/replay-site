<?php

declare(strict_types=1);

use App\Database;
use App\TokenAuth;

function getInviteInfo(string $token): void
{
    $db   = Database::getInstance();
    $stmt = $db->prepare(
        'SELECT it.group_id, g.name AS group_name, s.weekday, s.start_hour, s.start_minute, s.label AS slot_label
         FROM invite_tokens it
         JOIN `groups` g ON g.id = it.group_id
         JOIN slots    s ON s.id = g.slot_id
         WHERE it.token = :token AND it.expires_at > NOW()'
    );
    $stmt->execute([':token' => $token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        jsonResponse(404, ['error' => 'Convite inválido ou expirado']);
        return;
    }

    $days     = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
    $day      = $days[$row['weekday']] ?? '';
    $time     = sprintf('%02d:%02d', $row['start_hour'], $row['start_minute']);
    $slotLabel = $row['slot_label'] ?? "{$day} {$time}";

    jsonResponse(200, [
        'group_id'   => (int) $row['group_id'],
        'group_name' => $row['group_name'],
        'slot_label' => $slotLabel,
    ]);
}

function joinViaInvite(string $token): void
{
    $db   = Database::getInstance();
    $stmt = $db->prepare(
        'SELECT it.group_id FROM invite_tokens it
         JOIN `groups` g ON g.id = it.group_id
         WHERE it.token = :token AND it.expires_at > NOW()'
    );
    $stmt->execute([':token' => $token]);
    $invite = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invite) {
        jsonResponse(404, ['error' => 'Convite inválido ou expirado']);
        return;
    }

    $groupId = (int) $invite['group_id'];

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

    // Verifica se e-mail já tem conta
    $userStmt = $db->prepare('SELECT * FROM users WHERE email = :email');
    $userStmt->execute([':email' => $email]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Usuário já existe — verifica se já está no grupo
        $memberStmt = $db->prepare(
            'SELECT 1 FROM user_group_memberships WHERE user_id = :uid AND group_id = :gid'
        );
        $memberStmt->execute([':uid' => $user['id'], ':gid' => $groupId]);
        if ($memberStmt->fetch()) {
            $sessionToken = TokenAuth::issueUserSession((int) $user['id']);
            jsonResponse(200, ['token' => $sessionToken, 'already_member' => true]);
            return;
        }
    } else {
        // Cria conta nova
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $db->prepare(
            'INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :hash)'
        )->execute([':name' => $name, ':email' => $email, ':hash' => $hash]);
        $userId = (int) $db->lastInsertId();

        $userStmt->execute([':email' => $email]);
        $user = ['id' => $userId, 'name' => $name, 'email' => $email];
    }

    // Verifica se usuário já pertence a outro grupo
    $anyGroup = $db->prepare('SELECT 1 FROM user_group_memberships WHERE user_id = :uid');
    $anyGroup->execute([':uid' => $user['id']]);
    if ($anyGroup->fetch()) {
        jsonResponse(409, ['error' => 'Você já pertence a um grupo']);
        return;
    }

    // Vincula ao grupo como player
    $db->prepare(
        'INSERT INTO user_group_memberships (user_id, group_id, role) VALUES (:uid, :gid, "player")'
    )->execute([':uid' => $user['id'], ':gid' => $groupId]);

    $sessionToken = TokenAuth::issueUserSession((int) $user['id']);

    jsonResponse(201, ['token' => $sessionToken, 'name' => $user['name']]);
}
