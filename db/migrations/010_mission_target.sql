-- Target description for a mission (manual entry, or auto-filled from a
-- hostile-identification report when the admin issues an attack from it).
ALTER TABLE iuccs_missions
  ADD COLUMN IF NOT EXISTS target VARCHAR(255) NULL;
