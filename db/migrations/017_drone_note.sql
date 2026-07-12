-- Simplify drone management: add a free-text note (비고) field.
-- battery_pct/comm_status/gps_status columns are kept in the schema (no data loss)
-- but are no longer shown/edited in the UI, which now focuses on 모델/운용상태/비고.
ALTER TABLE iuccs_drones
  ADD COLUMN IF NOT EXISTS note VARCHAR(255) NULL;
