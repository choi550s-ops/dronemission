-- IUCCS initial schema. All statements are idempotent (IF NOT EXISTS).
-- Run automatically by bin/migrate.php on each deploy.

CREATE TABLE IF NOT EXISTS teams (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  team_no       VARCHAR(16)  NOT NULL UNIQUE,
  name          VARCHAR(64)  NOT NULL,
  status        ENUM('unavailable','preparing','ready','on_mission') NOT NULL DEFAULT 'ready',
  coord         VARCHAR(32)  NULL,
  last_login_at DATETIME     NULL,
  created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  team_id       INT UNSIGNED NULL,
  login_id      VARCHAR(32)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','leader','operator','fire_coord','observer') NOT NULL DEFAULT 'operator',
  display_name  VARCHAR(64)  NULL,
  created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS drones (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  team_id        INT UNSIGNED NULL,
  code           VARCHAR(32)  NOT NULL UNIQUE,
  model          VARCHAR(64)  NULL,
  max_flight_min INT          NULL,
  battery_count  INT          NULL,
  range_km       DECIMAL(6,2) NULL,
  battery_pct    TINYINT      NULL,
  comm_status    ENUM('good','weak','lost')        DEFAULT 'good',
  gps_status     ENUM('fixed','searching','none')  DEFAULT 'fixed',
  status         ENUM('available','in_use','maintenance','down') DEFAULT 'available',
  updated_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_drones_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS missions (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  team_id       INT UNSIGNED NOT NULL,
  type          ENUM('recon','attack') NOT NULL,
  coord         VARCHAR(32)  NULL,
  route         VARCHAR(255) NULL,
  method        ENUM('autonomous','designated') NULL,
  mode          ENUM('real','sim') NOT NULL DEFAULT 'real',
  status        ENUM('issued','acknowledged','in_progress','completed','cancelled','pending_approval')
                NOT NULL DEFAULT 'issued',
  approved      TINYINT(1)   NOT NULL DEFAULT 0,
  roe_confirmed TINYINT(1)   NOT NULL DEFAULT 0,
  issued_by     INT UNSIGNED NULL,
  issued_at     DATETIME     DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_missions_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reports (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mission_id   INT UNSIGNED NULL,
  team_id      INT UNSIGNED NOT NULL,
  kind         ENUM('recon','attack','photo') NOT NULL,
  mode         ENUM('real','sim') NOT NULL DEFAULT 'real',
  coord        VARCHAR(32)  NULL,
  troops       INT          NULL,
  trucks       INT          NULL,
  vehicles     INT          NULL,
  armed        INT          NULL,
  unarmed      INT          NULL,
  scale        VARCHAR(16)  NULL,
  kia          INT          NULL,
  serious      INT          NULL,
  minor        INT          NULL,
  failed       TINYINT(1)   NULL,
  note         TEXT         NULL,
  photo_path   VARCHAR(255) NULL,
  acknowledged TINYINT(1)   NOT NULL DEFAULT 0,
  created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reports_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fire_requests (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  team_id      INT UNSIGNED NOT NULL,
  coord        VARCHAR(32)  NULL,
  target       VARCHAR(64)  NULL,
  scale        VARCHAR(16)  NULL,
  support_from ENUM('higher','adjacent') NULL,
  status       ENUM('received','reviewing','approved','held','executed','rejected')
               NOT NULL DEFAULT 'received',
  mode         ENUM('real','sim') NOT NULL DEFAULT 'real',
  created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_fire_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS activity_log (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor      VARCHAR(64)  NULL,
  action     VARCHAR(64)  NOT NULL,
  entity     VARCHAR(64)  NULL,
  entity_id  INT UNSIGNED NULL,
  detail     TEXT         NULL,
  created_at DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sessions (
  token      CHAR(64)     PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  team_id    INT UNSIGNED NULL,
  role       VARCHAR(32)  NOT NULL,
  mode       ENUM('real','sim') NOT NULL DEFAULT 'real',
  created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME     NOT NULL,
  INDEX idx_sessions_exp (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
