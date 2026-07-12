-- Two-way text messages between admin (dashboard) and a team (operator app).
CREATE TABLE IF NOT EXISTS iuccs_messages (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  team_id    INT UNSIGNED NOT NULL,
  sender     VARCHAR(64) NOT NULL,
  direction  ENUM('to_team','from_team') NOT NULL DEFAULT 'to_team',
  body       TEXT NOT NULL,
  read_at    DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (team_id) REFERENCES iuccs_teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
