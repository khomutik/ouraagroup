PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS settings (
  key TEXT PRIMARY KEY,
  value TEXT NOT NULL,
  updated_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS sessions (
  id TEXT PRIMARY KEY,
  token_hash TEXT NOT NULL UNIQUE,
  visitor_code TEXT NOT NULL,
  page_path TEXT NOT NULL DEFAULT '/',
  telegram_thread_id INTEGER UNIQUE,
  topic_lock_id TEXT,
  topic_lock_until INTEGER,
  status TEXT NOT NULL DEFAULT 'open' CHECK (status IN ('open', 'closed')),
  created_at INTEGER NOT NULL,
  last_activity_at INTEGER NOT NULL,
  closed_at INTEGER
);

CREATE INDEX IF NOT EXISTS idx_sessions_thread
  ON sessions(telegram_thread_id);
CREATE INDEX IF NOT EXISTS idx_sessions_last_activity
  ON sessions(last_activity_at);

CREATE TABLE IF NOT EXISTS messages (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  session_id TEXT NOT NULL,
  direction TEXT NOT NULL CHECK (direction IN ('visitor', 'operator', 'system')),
  text TEXT NOT NULL,
  client_message_id TEXT,
  telegram_message_id INTEGER,
  delivery_status TEXT NOT NULL DEFAULT 'delivered' CHECK (delivery_status IN ('pending', 'delivered', 'failed')),
  created_at INTEGER NOT NULL,
  FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
  UNIQUE (session_id, client_message_id)
);

CREATE INDEX IF NOT EXISTS idx_messages_session_id
  ON messages(session_id, id);
CREATE INDEX IF NOT EXISTS idx_messages_rate_limit
  ON messages(session_id, direction, created_at);
CREATE UNIQUE INDEX IF NOT EXISTS idx_messages_telegram_unique
  ON messages(telegram_message_id)
  WHERE telegram_message_id IS NOT NULL;

CREATE TABLE IF NOT EXISTS rate_limits (
  bucket_key TEXT PRIMARY KEY,
  count INTEGER NOT NULL,
  expires_at INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_rate_limits_expiry
  ON rate_limits(expires_at);
