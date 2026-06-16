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
            'SELECT g.*, gt.expires_at AS token_expires_at
             FROM group_tokens gt
             JOIN `groups` g ON g.id = gt.group_id
             WHERE gt.token = :token
               AND gt.expires_at > NOW()
               AND g.subscription_expires_at > NOW()'
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
        $expiresAt = (new \DateTimeImmutable())->modify('+8 hours')->format('Y-m-d H:i:s');
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
}
