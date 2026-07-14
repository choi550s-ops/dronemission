-- Completes the mission<->report linkage. iuccs_reports.mission_id
-- already existed (a result report points forward to its mission).
-- This adds the reverse pointer: a mission points back to the report
-- that triggered its creation (e.g. a hostile recon report -> the
-- 공격/피해평가 mission issued from it).
ALTER TABLE iuccs_missions
  ADD COLUMN IF NOT EXISTS source_report_id INT UNSIGNED NULL;
