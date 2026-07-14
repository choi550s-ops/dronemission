-- When acknowledging (수신확인) a mission, the operator now records a
-- planned start time (HH:MM), shown to admin as e.g. '09:30부터 임무 시작'.
ALTER TABLE iuccs_missions
  ADD COLUMN IF NOT EXISTS response_start_time VARCHAR(5) NULL;
