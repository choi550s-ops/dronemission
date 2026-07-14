-- 피해평가(damage) reports: add equipment damage level counts
-- (완파=destroyed, 중파=heavy_damage, 소파=light_damage), separate from
-- personnel casualty counts (kia/serious/minor).
ALTER TABLE iuccs_reports
  ADD COLUMN IF NOT EXISTS destroyed INT NULL,
  ADD COLUMN IF NOT EXISTS heavy_damage INT NULL,
  ADD COLUMN IF NOT EXISTS light_damage INT NULL;
