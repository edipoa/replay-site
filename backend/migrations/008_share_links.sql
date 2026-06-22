CREATE TABLE IF NOT EXISTS share_links (
  token      CHAR(32)     NOT NULL,
  game_id    CHAR(32)     NOT NULL,
  clip_id    CHAR(32)     NULL DEFAULT NULL,
  expires_at DATETIME     NOT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (token),
  INDEX idx_share_scope (game_id, clip_id),
  INDEX idx_share_expires (expires_at)
);
