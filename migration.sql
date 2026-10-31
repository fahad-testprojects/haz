-- ForHaz Pro v8.0 Migration Script
-- Run this in your MySQL database to support PIN and faster lookups

ALTER TABLE matches ADD COLUMN IF NOT EXISTS pin VARCHAR(10) DEFAULT NULL AFTER match_id;
ALTER TABLE matches ADD INDEX IF NOT EXISTS idx_match_id (match_id);

-- Success: Migration complete.
-- Optional live reactions: separate from game_data to avoid overwriting scores.
CREATE TABLE IF NOT EXISTS match_reactions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  match_id VARCHAR(100) NOT NULL,
  kind VARCHAR(16) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_reactions_match_id (match_id, id),
  INDEX idx_reactions_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
