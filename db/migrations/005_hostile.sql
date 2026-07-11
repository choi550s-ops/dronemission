-- Marks a reconnaissance report as a hostile (enemy) identification,
-- so it can be rendered distinctly on the map (red diamond).
ALTER TABLE iuccs_reports
  ADD COLUMN IF NOT EXISTS hostile TINYINT(1) NOT NULL DEFAULT 0;
