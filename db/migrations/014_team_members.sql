-- Team roster: member name + role/성질 (특기), editable from both the
-- admin dashboard (team expand panel) and the operator app (팀관리 tab).
CREATE TABLE IF NOT EXISTS iuccs_team_members (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  team_id    INT UNSIGNED NOT NULL,
  name       VARCHAR(64) NOT NULL,
  role       ENUM('공격','로봇','정찰','지원','통신','사이버') NOT NULL DEFAULT '지원',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (team_id) REFERENCES iuccs_teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
