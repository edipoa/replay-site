#!/usr/bin/env php
<?php
declare(strict_types=1);

if ($argc !== 3) {
    fwrite(STDERR, "Uso: php create-admin.php <login> <senha>\n");
    exit(1);
}

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Database;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

[, $login, $password] = $argv;
$hash = password_hash($password, PASSWORD_BCRYPT);

$db   = Database::getInstance();
$stmt = $db->prepare(
    'INSERT INTO admin_users (login, password_hash) VALUES (:login, :hash)
     ON DUPLICATE KEY UPDATE password_hash = :hash'
);
$stmt->execute([':login' => $login, ':hash' => $hash]);

echo "Admin '{$login}' criado/atualizado com sucesso.\n";
