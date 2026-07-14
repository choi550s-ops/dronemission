-- 미식별(unidentified) is not a count of unidentified contacts — it's
-- a flag meaning "정찰했지만 아무것도 식별하지 못함" (patrolled but
-- could not identify/find anything), same semantics/shape as
-- hostile. Correcting the column type to match.
ALTER TABLE iuccs_reports
  MODIFY COLUMN unidentified TINYINT(1) NOT NULL DEFAULT 0;
