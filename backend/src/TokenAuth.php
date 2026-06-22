<?php

declare(strict_types=1);

namespace App;

use PDO;

class TokenAuth
{
    private static function db(): PDO
    {
        return Database::getInstance();
    }

    private static function bearer(): string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }
        return '';
    }

    public static function resolveGroup(): ?array
    {
        $token = self::bearer();
        if ($token === '') return null;

        $stmt = self::db()->prepare(
            'SELECT g.*, s.weekday AS slot_weekday, s.start_hour AS slot_hour,
                    s.start_minute AS slot_minute, s.duration_m AS slot_duration_m,
                    gt.expires_at AS token_expires_at
             FROM group_tokens gt
             JOIN `groups` g ON g.id  = gt.group_id
             JOIN slots    s ON s.id  = g.slot_id
             WHERE gt.token = :token
               AND gt.expires_at > NOW()
               AND g.subscription_expires_at > NOW()'
        );
        $stmt->execute([':token' => $token]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Igual a resolveGroup() mas sem verificar subscription_expires_at (para renovação). */
    public static function resolveGroupAllowExpired(): ?array
    {
        $token = self::bearer();
        if ($token === '') return null;

        $stmt = self::db()->prepare(
            'SELECT g.*, s.weekday AS slot_weekday, s.start_hour AS slot_hour,
                    s.start_minute AS slot_minute, s.duration_m AS slot_duration_m,
                    gt.expires_at AS token_expires_at
             FROM group_tokens gt
             JOIN `groups` g ON g.id = gt.group_id
             JOIN slots    s ON s.id = g.slot_id
             WHERE gt.token = :token
               AND gt.expires_at > NOW()'
        );
        $stmt->execute([':token' => $token]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function resolveAdmin(): bool
    {
        $token = self::bearer();
        if ($token === '') return false;

        $stmt = self::db()->prepare(
            'SELECT 1 FROM admin_tokens WHERE token = :token AND expires_at > NOW()'
        );
        $stmt->execute([':token' => $token]);
        return (bool) $stmt->fetch();
    }

    public static function resolveGameAccess(string $gameId, ?string $clipId = null): bool
    {
        $token = self::bearer();
        if ($token === '') return false;

        $stmt = self::db()->prepare(
            'SELECT clip_id FROM game_access_tokens
             WHERE token = :token AND game_id = :game_id AND expires_at > NOW()'
        );
        $stmt->execute([':token' => $token, ':game_id' => $gameId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return false;
        // NULL clip_id = acesso completo; clip_id preenchido = acesso a clip específico
        if ($row['clip_id'] === null) return true;
        return $clipId !== null && $row['clip_id'] === $clipId;
    }

    /** Verifica se o token Bearer é um group_token com slot compatível (via FK). */
    public static function resolveGroupAccess(int $weekday, int $hour, int $minute): bool
    {
        $token = self::bearer();
        if ($token === '') return false;

        $stmt = self::db()->prepare(
            'SELECT 1 FROM group_tokens gt
             JOIN `groups` gr ON gr.id = gt.group_id
             JOIN slots    s  ON s.id  = gr.slot_id
             WHERE gt.token = :token
               AND gt.expires_at > NOW()
               AND gr.subscription_expires_at > NOW()
               AND s.weekday      = :weekday
               AND s.start_hour   = :hour
               AND s.start_minute = :minute'
        );
        $stmt->execute([':token' => $token, ':weekday' => $weekday, ':hour' => $hour, ':minute' => $minute]);
        return (bool) $stmt->fetch();
    }

    public static function issueGroupToken(int $groupId, string $expiresAt): string
    {
        $token = bin2hex(random_bytes(32));
        self::db()->prepare(
            'INSERT INTO group_tokens (token, group_id, expires_at) VALUES (:token, :group_id, :expires_at)'
        )->execute([':token' => $token, ':group_id' => $groupId, ':expires_at' => $expiresAt]);
        return $token;
    }

    public static function issueAdminToken(int $adminId): string
    {
        $token     = bin2hex(random_bytes(32));
        $expiresAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('+8 hours')->format('Y-m-d H:i:s');
        self::db()->prepare(
            'INSERT INTO admin_tokens (token, admin_id, expires_at) VALUES (:token, :admin_id, :expires_at)'
        )->execute([':token' => $token, ':admin_id' => $adminId, ':expires_at' => $expiresAt]);
        return $token;
    }

    public static function issueGameAccessToken(string $gameId, ?string $clipId, string $expiresAt): string
    {
        $token = bin2hex(random_bytes(32));
        self::db()->prepare(
            'INSERT INTO game_access_tokens (token, game_id, clip_id, expires_at)
             VALUES (:token, :game_id, :clip_id, :expires_at)'
        )->execute([
            ':token'      => $token,
            ':game_id'    => $gameId,
            ':clip_id'    => $clipId,
            ':expires_at' => $expiresAt,
        ]);
        return $token;
    }

    public static function revokeGroupToken(): void
    {
        $token = self::bearer();
        if ($token === '') return;
        self::db()->prepare('DELETE FROM group_tokens WHERE token = :token')
                  ->execute([':token' => $token]);
    }

    public static function revokeAdminToken(): void
    {
        $token = self::bearer();
        if ($token === '') return;
        self::db()->prepare('DELETE FROM admin_tokens WHERE token = :token')
                  ->execute([':token' => $token]);
    }

    public static function resolveUser(): ?array
    {
        $token = self::bearer();
        if ($token === '') return null;

        $stmt = self::db()->prepare(
            'SELECT u.* FROM user_sessions s
             JOIN users u ON u.id = s.user_id
             WHERE s.token = :token AND s.expires_at > NOW()'
        );
        $stmt->execute([':token' => $token]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Resolve o grupo do usuário autenticado via user_session (captain ou player). */
    public static function resolveUserGroup(): ?array
    {
        $user = self::resolveUser();
        if (!$user) return null;

        $stmt = self::db()->prepare(
            'SELECT g.*, s.weekday AS slot_weekday, s.start_hour AS slot_hour,
                    s.start_minute AS slot_minute, s.duration_m AS slot_duration_m,
                    s.label AS slot_label
             FROM user_group_memberships m
             JOIN `groups` g ON g.id = m.group_id
             JOIN slots    s ON s.id = g.slot_id
             WHERE m.user_id = :user_id
               AND g.subscription_expires_at > NOW()'
        );
        $stmt->execute([':user_id' => $user['id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function issueUserSession(int $userId): string
    {
        $token     = bin2hex(random_bytes(32));
        $expiresAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('+30 days')->format('Y-m-d H:i:s');
        self::db()->prepare(
            'INSERT INTO user_sessions (token, user_id, expires_at) VALUES (:token, :user_id, :expires_at)'
        )->execute([':token' => $token, ':user_id' => $userId, ':expires_at' => $expiresAt]);
        return $token;
    }

    public static function revokeUserSession(): void
    {
        $token = self::bearer();
        if ($token === '') return;
        self::db()->prepare('DELETE FROM user_sessions WHERE token = :token')
                  ->execute([':token' => $token]);
    }
}
