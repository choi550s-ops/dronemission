-- Store a drawn route (list of [lat,lng] waypoints) as JSON for missions.
ALTER TABLE iuccs_missions
  ADD COLUMN IF NOT EXISTS route_points TEXT NULL;
