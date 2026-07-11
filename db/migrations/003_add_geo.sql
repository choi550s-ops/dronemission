-- Add latitude/longitude for map (COP) visualization.
-- MariaDB supports ADD COLUMN IF NOT EXISTS (safe to re-run).

ALTER TABLE iuccs_teams
  ADD COLUMN IF NOT EXISTS lat DECIMAL(9,6) NULL,
  ADD COLUMN IF NOT EXISTS lng DECIMAL(9,6) NULL;

ALTER TABLE iuccs_reports
  ADD COLUMN IF NOT EXISTS lat DECIMAL(9,6) NULL,
  ADD COLUMN IF NOT EXISTS lng DECIMAL(9,6) NULL;

ALTER TABLE iuccs_fire_requests
  ADD COLUMN IF NOT EXISTS lat DECIMAL(9,6) NULL,
  ADD COLUMN IF NOT EXISTS lng DECIMAL(9,6) NULL;

ALTER TABLE iuccs_missions
  ADD COLUMN IF NOT EXISTS lat DECIMAL(9,6) NULL,
  ADD COLUMN IF NOT EXISTS lng DECIMAL(9,6) NULL;

-- sample coordinates so the map shows team pins immediately
UPDATE iuccs_teams SET lat = 37.552000, lng = 126.988000 WHERE team_no = '01' AND lat IS NULL;
UPDATE iuccs_teams SET lat = 37.560000, lng = 127.010000 WHERE team_no = '03' AND lat IS NULL;
UPDATE iuccs_teams SET lat = 37.545000, lng = 127.030000 WHERE team_no = '05' AND lat IS NULL;
