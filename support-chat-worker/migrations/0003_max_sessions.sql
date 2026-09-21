ALTER TABLE sessions ADD COLUMN source TEXT NOT NULL DEFAULT 'site'
  CHECK (source IN ('site', 'max'));

ALTER TABLE sessions ADD COLUMN max_user_id TEXT;

CREATE INDEX IF NOT EXISTS idx_sessions_max_user
  ON sessions(max_user_id);

CREATE UNIQUE INDEX IF NOT EXISTS idx_sessions_max_open_user
  ON sessions(max_user_id)
  WHERE source = 'max' AND status = 'open';
