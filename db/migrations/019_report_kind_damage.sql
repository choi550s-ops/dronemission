-- Split the old combined '공격 / 피해' report kind into two distinct
-- kinds: attack (공격 — target engaged) and damage (피해평가 — BDA/
-- casualty assessment reported separately).
ALTER TABLE iuccs_reports MODIFY COLUMN kind ENUM('recon','attack','photo','damage') NOT NULL;
