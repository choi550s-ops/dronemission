-- 공격(attack) reports now record a simple engagement result status
-- (명중=hit / 불명=unknown / 기타=other) instead of armed/unarmed
-- target counts. armed/unarmed columns are left in place (unused by
-- new submissions) to avoid data loss on existing rows.
ALTER TABLE iuccs_reports
  ADD COLUMN IF NOT EXISTS attack_result ENUM('hit','unknown','other') NULL,
  ADD COLUMN IF NOT EXISTS attack_result_note VARCHAR(255) NULL;
