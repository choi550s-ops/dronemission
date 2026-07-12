-- Catalog of drone models an admin can pick when registering a drone.
-- Each model has a fixed category (attack/recon) that the drone inherits.
CREATE TABLE IF NOT EXISTS iuccs_drone_models (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(64) NOT NULL,
  category   ENUM('attack','recon') NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO iuccs_drone_models (name, category)
SELECT * FROM (SELECT 'FPV자폭드론' AS name, 'attack' AS category) x
WHERE NOT EXISTS (SELECT 1 FROM iuccs_drone_models WHERE name='FPV자폭드론');

INSERT INTO iuccs_drone_models (name, category)
SELECT * FROM (SELECT '근거리정찰드론' AS name, 'recon' AS category) x
WHERE NOT EXISTS (SELECT 1 FROM iuccs_drone_models WHERE name='근거리정찰드론');

INSERT INTO iuccs_drone_models (name, category)
SELECT * FROM (SELECT '양자VTOL' AS name, 'recon' AS category) x
WHERE NOT EXISTS (SELECT 1 FROM iuccs_drone_models WHERE name='양자VTOL');

INSERT INTO iuccs_drone_models (name, category)
SELECT * FROM (SELECT '워메이트' AS name, 'attack' AS category) x
WHERE NOT EXISTS (SELECT 1 FROM iuccs_drone_models WHERE name='워메이트');
