-- Recon reports can now record 미식별(unidentified) contacts —
-- something spotted but not classifiable into the other categories.
ALTER TABLE iuccs_reports
  ADD COLUMN IF NOT EXISTS unidentified INT NULL;
