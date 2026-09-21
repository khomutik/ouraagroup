ALTER TABLE sessions ADD COLUMN telegram_user_hash TEXT;

ALTER TABLE sessions ADD COLUMN telegram_chat_id_encrypted TEXT;

CREATE INDEX IF NOT EXISTS idx_sessions_telegram_user
  ON sessions(telegram_user_hash);

CREATE UNIQUE INDEX IF NOT EXISTS idx_sessions_telegram_open_user
  ON sessions(telegram_user_hash)
  WHERE telegram_user_hash IS NOT NULL AND status = 'open';

CREATE UNIQUE INDEX IF NOT EXISTS idx_messages_telegram_client_unique
  ON messages(client_message_id)
  WHERE client_message_id LIKE 'telegram:%';
