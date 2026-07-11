-- Sample teams and drones so the dashboard has data on first deploy.
-- User accounts (with hashed passwords) are seeded separately by bin/migrate.php.
-- Idempotent: INSERT ... ON DUPLICATE KEY UPDATE keeps re-runs safe.

INSERT INTO teams (team_no, name, status, coord) VALUES
  ('01', '1팀', 'ready',      'WQ 1180 5420'),
  ('03', '3팀', 'on_mission', 'WQ 1274 5583'),
  ('05', '5팀', 'preparing',  'WQ 1360 5610')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO drones (team_id, code, model, max_flight_min, battery_count, range_km, battery_pct, comm_status, gps_status, status)
SELECT t.id, x.code, x.model, x.mf, x.bc, x.rk, x.bp, x.cs, x.gs, x.st
FROM (
  SELECT '01' AS team_no, 'D-01' AS code, 'Quad-S' AS model, 30 AS mf, 4 AS bc, 8.0 AS rk, 92 AS bp, 'good' AS cs, 'fixed' AS gs, 'available' AS st
  UNION ALL SELECT '03', 'D-07', 'Quad-S', 30, 3, 8.0, 78, 'good', 'fixed', 'in_use'
  UNION ALL SELECT '05', 'D-11', 'Fixed-L', 55, 2, 15.0, 40, 'weak', 'searching', 'available'
) x
JOIN teams t ON t.team_no = x.team_no
ON DUPLICATE KEY UPDATE model = VALUES(model);
