ALTER TABLE payments ADD COLUMN email VARCHAR(255) NULL AFTER method;

CREATE TABLE IF NOT EXISTS recovery_links (
    token       CHAR(32)     NOT NULL PRIMARY KEY,
    payment_id  CHAR(32)     NOT NULL,
    expires_at  DATETIME     NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payment (payment_id),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
);
