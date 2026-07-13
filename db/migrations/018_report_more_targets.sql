-- Extend recon report fields beyond 병력/트럭/일반차량 to include
-- 전차(tanks), 장갑차(armored vehicles), 포병(artillery).
ALTER TABLE iuccs_reports
  ADD COLUMN IF NOT EXISTS tanks INT NULL,
  ADD COLUMN IF NOT EXISTS armored INT NULL,
  ADD COLUMN IF NOT EXISTS artillery INT NULL;
