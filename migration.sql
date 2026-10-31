-- ForHaz Pro v8.0 Migration Script
-- Run this in your MySQL database to support PIN and faster lookups

ALTER TABLE matches ADD COLUMN IF NOT EXISTS pin VARCHAR(10) DEFAULT NULL AFTER match_id;
ALTER TABLE matches ADD INDEX IF NOT EXISTS idx_match_id (match_id);

-- Success: Migration complete.