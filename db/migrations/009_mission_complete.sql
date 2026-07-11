-- Lets the operator file a completion report for a mission; 'completed' already
-- exists as a status value, this adds the supporting detail fields.
ALTER TABLE iuccs_missions
  ADD COLUMN IF NOT EXISTS completion_note VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS completed_at DATETIME NULL;
