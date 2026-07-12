-- Link drones to the model catalog (category comes from the model),
-- and simplify operating status to 가용(available)/정비(maintenance)/파괴(destroyed).
ALTER TABLE iuccs_drones
  ADD COLUMN IF NOT EXISTS model_id INT UNSIGNED NULL;

-- Best-effort: point existing drones with a matching free-text model name to the catalog row.
UPDATE iuccs_drones d
  JOIN iuccs_drone_models dm ON dm.name = d.model
   SET d.model_id = dm.id
 WHERE d.model_id IS NULL;

-- Remap old status values onto the new 3-state set before narrowing the ENUM.
UPDATE iuccs_drones SET status = 'available' WHERE status = 'in_use';
UPDATE iuccs_drones SET status = 'destroyed' WHERE status = 'down';

ALTER TABLE iuccs_drones
  MODIFY COLUMN status ENUM('available','maintenance','destroyed') NOT NULL DEFAULT 'available';
