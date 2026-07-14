-- Recon reports: add 박격포(mortar) as a target category alongside
-- 병력/트럭/차량/전차/장갑차/포병/지휘소.
ALTER TABLE iuccs_reports
  ADD COLUMN IF NOT EXISTS mortar INT NULL;
