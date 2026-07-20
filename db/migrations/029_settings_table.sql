-- Small key/value settings store for flags that need to be toggled at
-- runtime without a code redeploy (e.g. the login maintenance lock).
CREATE TABLE IF NOT EXISTS iuccs_settings (
  setting_key   VARCHAR(64) PRIMARY KEY,
  setting_value TEXT NULL,
  updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
