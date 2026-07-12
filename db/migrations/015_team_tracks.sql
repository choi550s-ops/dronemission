-- Position pings from the operator app (auto every N minutes, or manual on demand).
-- Also mirrored into iuccs_teams.lat/lng so the COP map always shows the latest fix.
CREATE TABLE IF NOT EXISTS iuccs_team_tracks (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  team_id    INT UNSIGNED NOT NULL,
  lat        DECIMAL(9,6) NOT NULL,
  lng        DECIMAL(9,6) NOT NULL,
  source     ENUM('auto','manual') NOT NULL DEFAULT 'auto',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (team_id) REFERENCES iuccs_teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
