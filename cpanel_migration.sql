-- HazariVai: non-destructive upgrade for an EXISTING database (MariaDB 10.11).
-- In phpMyAdmin select the application's existing database, then Import this file.
-- Back up the database first. This script does not delete matches or change scores.
-- Re-importing this file is safe. The existing matches table must already exist.
ALTER TABLE matches ADD COLUMN IF NOT EXISTS pin VARCHAR(10) DEFAULT NULL AFTER match_id;
ALTER TABLE matches ADD INDEX IF NOT EXISTS idx_match_id (match_id);

CREATE TABLE IF NOT EXISTS match_reactions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  match_id VARCHAR(100) NOT NULL,
  kind VARCHAR(16) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_reactions_match_id (match_id, id),
  INDEX idx_reactions_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
