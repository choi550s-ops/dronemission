-- Operator feedback on an issued mission: acknowledge / cannot comply / needs prep time.
ALTER TABLE iuccs_missions
  ADD COLUMN IF NOT EXISTS response_type ENUM('none','ack','unable','delay') NOT NULL DEFAULT 'none',
  ADD COLUMN IF NOT EXISTS response_reason VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS response_delay_min INT NULL,
  ADD COLUMN IF NOT EXISTS responded_at DATETIME NULL;
