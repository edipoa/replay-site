-- Contas individuais (capitão + jogadores convidados)
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Sessões de usuário logado (token opaco, mesmo padrão dos outros tokens)
CREATE TABLE IF NOT EXISTS user_sessions (
    token      CHAR(64) PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Recuperação de senha
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    token      CHAR(64) PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at    DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Vínculo entre usuários e grupos (capitão = quem paga; player = convidado)
CREATE TABLE IF NOT EXISTS user_group_memberships (
    user_id   INT UNSIGNED NOT NULL,
    group_id  INT UNSIGNED NOT NULL,
    role      ENUM('captain','player') NOT NULL DEFAULT 'player',
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, group_id),
    FOREIGN KEY (user_id)  REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE
);

-- Tokens de convite gerados pelo capitão (link que jogadores usam para criar conta)
CREATE TABLE IF NOT EXISTS invite_tokens (
    token      CHAR(32) PRIMARY KEY,
    group_id   INT UNSIGNED NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE
);

-- captain_user_id: quem é o dono self-service do grupo
-- login/password_hash: tornados nullable para grupos self-service (acesso via user_sessions)
ALTER TABLE `groups`
    ADD COLUMN captain_user_id INT UNSIGNED NULL AFTER id,
    ADD CONSTRAINT fk_group_captain FOREIGN KEY (captain_user_id) REFERENCES users(id) ON DELETE SET NULL,
    MODIFY COLUMN login         VARCHAR(64)  NULL,
    MODIFY COLUMN password_hash VARCHAR(255) NULL;
