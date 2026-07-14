-- Admin can now task teams with a 피해평가(damage assessment) mission,
-- in addition to 정찰(recon) and 공격(attack).
ALTER TABLE iuccs_missions MODIFY COLUMN type ENUM('recon','attack','damage') NOT NULL;
