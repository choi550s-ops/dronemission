-- Redefine fire-request workflow: 접수완료(received) -> 보류(held) -> 결정(decided).
-- 'decided' carries an optional scheduled fire time (NULL = 미정/undecided).
-- Map any old status values onto the new set before narrowing the ENUM.
UPDATE iuccs_fire_requests SET status='received' WHERE status='reviewing';
UPDATE iuccs_fire_requests SET status='decided'  WHERE status IN ('approved','executed');
UPDATE iuccs_fire_requests SET status='held'     WHERE status='rejected';

ALTER TABLE iuccs_fire_requests
  MODIFY COLUMN status ENUM('received','held','decided') NOT NULL DEFAULT 'received';

ALTER TABLE iuccs_fire_requests
  ADD COLUMN IF NOT EXISTS fire_time VARCHAR(64) NULL;
