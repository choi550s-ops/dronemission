-- Demo/simulation data: one hostile-identification recon report so the
-- "최근 보고" hostile action buttons (보류 / 공격지정) can be seen without
-- waiting for a real field report. Marked mode='sim' so it's clearly flagged
-- as simulation in the UI (amber "모의" badge), not mistaken for a real report.

INSERT INTO iuccs_reports
  (team_id, kind, mode, coord, lat, lng, troops, trucks, vehicles, hostile, note)
SELECT t.id, 'recon', 'sim', 'WQ 1320 5610', 37.557500, 127.007500, 9, 2, 1, 1,
       '모의 훈련 데이터 — 적 병력·차량 식별 시연용'
  FROM iuccs_teams t
 WHERE t.team_no = '03'
 LIMIT 1;
