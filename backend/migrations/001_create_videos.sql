CREATE TABLE IF NOT EXISTS videos (
    id            CHAR(32)          PRIMARY KEY,
    seq           INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    camera_id     VARCHAR(64)       NOT NULL,
    r2_key        VARCHAR(255)      NOT NULL,
    thumbnail_key VARCHAR(255)      NULL,
    duration_s    SMALLINT UNSIGNED NOT NULL,
    size_bytes    INT UNSIGNED      NOT NULL,
    triggered_at  DATETIME          NOT NULL,
    expires_at    DATETIME          NOT NULL,
    created_at    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_seq     (seq),
    INDEX idx_expires      (expires_at),
    INDEX idx_camera       (camera_id, triggered_at)
);

