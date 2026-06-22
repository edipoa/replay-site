CREATE TABLE IF NOT EXISTS subscription_payments (
    id               CHAR(32)      NOT NULL PRIMARY KEY,
    group_id         INT UNSIGNED  NOT NULL,
    method           VARCHAR(20)   NOT NULL,
    amount           DECIMAL(10,2) NOT NULL,
    status           VARCHAR(20)   NOT NULL DEFAULT 'pending',
    mp_payment_id    BIGINT        NULL,
    mp_preference_id VARCHAR(128)  NULL,
    access_granted   TINYINT(1)    NOT NULL DEFAULT 0,
    created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mp_payment (mp_payment_id),
    INDEX idx_group (group_id),
    FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE
);
