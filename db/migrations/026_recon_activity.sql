-- Recon reports: add 지휘소(command post) as a target category, and
-- 행동(activity: moving/defending/attacking) with an optional 방향
-- (direction) when the target is moving.
ALTER TABLE iuccs_reports
  ADD COLUMN IF NOT EXISTS command_post INT NULL,
  ADD COLUMN IF NOT EXISTS activity ENUM('moving','defending','attacking') NULL,
  ADD COLUMN IF NOT EXISTS direction VARCHAR(20) NULL;
