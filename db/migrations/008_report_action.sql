-- Tracks the admin's disposition decision on a hostile-identification report:
-- 'none' = still open (map marker blinks); otherwise the buttons are locked
-- until reset back to 'none' (재조치).
ALTER TABLE iuccs_reports
  ADD COLUMN IF NOT EXISTS action_taken ENUM('none','attack','hold','friendly','ack') NOT NULL DEFAULT 'none',
  ADD COLUMN IF NOT EXISTS action_at DATETIME NULL;
