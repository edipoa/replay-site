CREATE TABLE IF NOT EXISTS slots (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    camera_id   VARCHAR(64)  NOT NULL,
    weekday     TINYINT      NOT NULL COMMENT '0=Seg 1=Ter 2=Qua 3=Qui 4=Sex 5=Sab 6=Dom',
    start_hour  TINYINT      NOT NULL,
    duration_m  SMALLINT     NOT NULL DEFAULT 60,
    label       VARCHAR(100) NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_slot (camera_id, weekday, start_hour)
);

CREATE TABLE IF NOT EXISTS `groups` (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                    VARCHAR(100) NOT NULL,
    camera_id               VARCHAR(64)  NOT NULL,
    slot_weekday            TINYINT      NOT NULL,
    slot_hour               TINYINT      NOT NULL,
    login                   VARCHAR(64)  NOT NULL UNIQUE,
    password_hash           VARCHAR(255) NOT NULL,
    subscription_expires_at DATETIME     NOT NULL,
    created_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS group_tokens (
    token       CHAR(64)     PRIMARY KEY,
    group_id    INT UNSIGNED NOT NULL,
    expires_at  DATETIME     NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS games (
    id          CHAR(32)     PRIMARY KEY,
    camera_id   VARCHAR(64)  NOT NULL,
    slot_date   DATE         NOT NULL,
    slot_hour   TINYINT      NOT NULL,
    duration_m  SMALLINT     NOT NULL DEFAULT 60,
    qr_token    CHAR(32)     NOT NULL UNIQUE,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_camera_date (camera_id, slot_date, slot_hour)
);

CREATE TABLE IF NOT EXISTS game_access_tokens (
    token       CHAR(64)     PRIMARY KEY,
    game_id     CHAR(32)     NOT NULL,
    clip_id     CHAR(32)     NULL COMMENT 'NULL = acesso completo ao jogo',
    expires_at  DATETIME     NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS admin_users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    login         VARCHAR(64)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS admin_tokens (
    token       CHAR(64)     PRIMARY KEY,
    admin_id    INT UNSIGNED NOT NULL,
    expires_at  DATETIME     NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE
);
