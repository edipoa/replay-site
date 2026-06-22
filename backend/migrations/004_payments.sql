CREATE TABLE IF NOT EXISTS payments (
    id               CHAR(32)      NOT NULL PRIMARY KEY,
    game_id          CHAR(32)      NOT NULL,
    qr_token         CHAR(32)      NOT NULL,
    type             VARCHAR(10)   NOT NULL,
    clip_ids         JSON          NULL,
    method           VARCHAR(20)   NOT NULL,
    amount           DECIMAL(10,2) NOT NULL,
    status           VARCHAR(20)   NOT NULL DEFAULT 'pending',
    mp_payment_id    BIGINT        NULL,
    mp_preference_id VARCHAR(128)  NULL,
    access_granted   TINYINT(1)    NOT NULL DEFAULT 0,
    created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mp_payment (mp_payment_id),
    INDEX idx_game (game_id),
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
);
