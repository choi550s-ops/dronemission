<?php
// Front controller + JSON API (PHP 5.x compatible).

require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Auth.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($base !== '' && strpos($path, $base) === 0) {
    $path = substr($path, strlen($base));
}
$path   = '/' . ltrim($path, '/');
$method = $_SERVER['REQUEST_METHOD'];

if ($path === '/' || $path === '') {
    header('Content-Type: text/html; charset=utf-8');
    $dash = htmlspecialchars($base . '/public/dashboard/', ENT_QUOTES, 'UTF-8');
    $oper = htmlspecialchars($base . '/public/operator/', ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>IUCCS · 접속 선택</title>
<style>
  :root{--bg:#0A111E;--surface:#152439;--surface2:#0b1626;--line:#24384F;--navy:#1F3A5F;--steel:#2E5A88;
    --ink:#EAF2FC;--text:#C4D4E7;--muted:#7C90A8;--amber:#E8A33D;--amberD:#C9871B;}
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:radial-gradient(1100px 650px at 50% -8%, #16283f 0%, #0A111E 60%);color:var(--text);
    font-family:-apple-system,BlinkMacSystemFont,"Malgun Gothic","Apple SD Gothic Neo","Noto Sans KR",sans-serif;
    min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
  .wrap{width:100%;max-width:420px;text-align:center}
  .logo{width:60px;height:60px;border-radius:16px;margin:0 auto 16px;background:linear-gradient(160deg,#2e5a88,#16273f);
    display:flex;align-items:center;justify-content:center;font-size:28px}
  h1{color:var(--ink);font-size:19px;font-weight:800;letter-spacing:-.01em}
  .mono{font-family:"SF Mono",ui-monospace,Consolas,monospace}
  .build{color:#F3D26B;font-weight:800;font-size:12px;margin-top:6px;letter-spacing:.02em}
  .cards{display:flex;flex-direction:column;gap:14px;margin-top:32px}
  a.card{display:flex;align-items:center;gap:16px;background:var(--surface);border:1px solid var(--line);
    border-radius:14px;padding:18px 20px;text-decoration:none;color:inherit;text-align:left;
    transition:border-color .15s, transform .15s}
  a.card:hover{border-color:var(--steel);transform:translateY(-1px)}
  a.card .ic{width:46px;height:46px;border-radius:12px;background:var(--surface2);display:flex;align-items:center;
    justify-content:center;font-size:22px;flex:none}
  a.card .lbl{color:var(--ink);font-weight:800;font-size:16px}
  a.card .sub{color:var(--muted);font-size:12.5px;margin-top:2px}
  a.card.admin .ic{background:rgba(232,163,61,.15)}
  a.card.operator .ic{background:rgba(59,130,214,.15)}
  .foot{margin-top:28px;color:var(--muted);font-size:11.5px}
</style>
</head>
<body>
  <div class="wrap">
    <div class="logo">🛰️</div>
    <h1>통합무인전투 통제체계</h1>
    <div class="build mono">[시범운용]</div>
    <div class="cards">
      <a class="card admin" href="' . $dash . '">
        <div class="ic">🖥️</div>
        <div><div class="lbl">관리자</div><div class="sub">관제 대시보드 · 임무 지시 · COP</div></div>
      </a>
      <a class="card operator" href="' . $oper . '">
        <div class="ic">📡</div>
        <div><div class="lbl">조종자</div><div class="sub">현장 운용자 앱 · 임무 수신 · 보고</div></div>
      </a>
    </div>
    <div class="foot">역할에 맞는 항목을 선택해 접속하세요</div>
  </div>
</body>
</html>';
    exit;
}

if (strpos($path, '/api/') !== 0) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Token');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    route($path, $method);
} catch (Exception $e) {
    $cfg = config();
    $msg = ($cfg['app']['env'] === 'production') ? 'internal error' : $e->getMessage();
    json_out(array('error' => 'server_error', 'message' => $msg), 500);
}

function route($path, $method)
{
    $pdo = Database::pdo();

    if ($path === '/api/time') {
        json_out(array('now' => date('c'), 'ts' => time()));
    }
    if ($path === '/api/login' && $method === 'POST') {
        $b = body_json();
        $loginId = trim(pval($b, 'team_no', pval($b, 'login_id', '')));
        $r = Auth::login($loginId, pval($b, 'password', ''), pval($b, 'mode', 'real'));
        if (!$r) {
            json_out(array('error' => 'invalid_credentials'), 401);
        }
        json_out($r);
    }

    if ($path === '/api/session') {
        $s = Auth::require_auth();
        json_out(array('role' => $s['role'], 'team_id' => $s['team_id'], 'mode' => $s['mode']));
    }
    if ($path === '/api/logout' && $method === 'POST') {
        Auth::logout();
        json_out(array('ok' => true));
    }
    if ($path === '/api/dashboard') {
        Auth::require_auth();
        $teams = $pdo->query(
            "SELECT t.id, t.team_no, t.name, t.status, t.coord, t.lat, t.lng, t.last_login_at,
                    (SELECT COUNT(*) FROM iuccs_drones d WHERE d.team_id = t.id) AS drone_count
               FROM iuccs_teams t ORDER BY t.team_no"
        )->fetchAll();
        $counts = array(
            'missions_pending' => (int) $pdo->query(
                "SELECT COUNT(*) FROM iuccs_missions WHERE status IN ('issued','pending_approval')"
            )->fetchColumn(),
            'reports_unack'    => (int) $pdo->query(
                "SELECT COUNT(*) FROM iuccs_reports WHERE acknowledged = 0"
            )->fetchColumn(),
            'fire_pending'     => (int) $pdo->query(
                "SELECT COUNT(*) FROM iuccs_fire_requests WHERE status IN ('received','reviewing')"
            )->fetchColumn(),
        );
        json_out(array('teams' => $teams, 'counts' => $counts, 'server_time' => date('c')));
    }

    if ($path === '/api/map') {
        Auth::require_auth();
        $teams = $pdo->query(
            "SELECT id, team_no, name, status, coord, lat, lng
               FROM iuccs_teams WHERE lat IS NOT NULL ORDER BY team_no"
        )->fetchAll();
        $reports = $pdo->query(
            "SELECT id, team_id, kind, mode, coord, lat, lng, troops, trucks, vehicles, hostile, action_taken, note, created_at
               FROM iuccs_reports WHERE lat IS NOT NULL ORDER BY created_at DESC LIMIT 50"
        )->fetchAll();
        $fires = $pdo->query(
            "SELECT id, team_id, coord, target, scale, status, lat, lng, created_at
               FROM iuccs_fire_requests WHERE lat IS NOT NULL ORDER BY created_at DESC LIMIT 50"
        )->fetchAll();
        $missionRoutes = $pdo->query(
            "SELECT id, team_id, type, status, coord, route_points
               FROM iuccs_missions
              WHERE route_points IS NOT NULL AND status NOT IN ('completed','cancelled')
              ORDER BY issued_at DESC LIMIT 30"
        )->fetchAll();
        json_out(array('teams' => $teams, 'reports' => $reports, 'fire_requests' => $fires, 'mission_routes' => $missionRoutes));
    }

    if ($path === '/api/teams') {
        Auth::require_auth();
        json_out(array('teams' => $pdo->query("SELECT * FROM iuccs_teams ORDER BY team_no")->fetchAll()));
    }
    if ($path === '/api/drones') {
        Auth::require_auth();
        $tid = pval($_GET, 'team_id');
        if ($tid) {
            $st = $pdo->prepare("SELECT * FROM iuccs_drones WHERE team_id = ? ORDER BY code");
            $st->execute(array($tid));
        } else {
            $st = $pdo->query("SELECT * FROM iuccs_drones ORDER BY code");
        }
        json_out(array('drones' => $st->fetchAll()));
    }

    if ($path === '/api/drones/update' && $method === 'POST') {
        $s  = Auth::require_auth();
        $b  = body_json();
        $id = pval($b, 'id');
        if ($s['role'] !== 'admin') {
            $chk = $pdo->prepare("SELECT team_id FROM iuccs_drones WHERE id = ?");
            $chk->execute(array($id));
            $d = $chk->fetch();
            if (!$d || $d['team_id'] != $s['team_id']) { json_out(array('error' => 'forbidden'), 403); }
        }
        $enum = array(
            'comm_status' => array('good','weak','lost'),
            'gps_status'  => array('fixed','searching','none'),
            'status'      => array('available','in_use','maintenance','down'),
        );
        $sets = array(); $vals = array();
        foreach (array('model','max_flight_min','battery_count','range_km','battery_pct','comm_status','gps_status','status') as $f) {
            if (!array_key_exists($f, $b)) { continue; }
            if (isset($enum[$f]) && !in_array($b[$f], $enum[$f], true)) { continue; }
            $sets[] = "$f = ?"; $vals[] = $b[$f];
        }
        if (!$sets) { json_out(array('error' => 'no_fields'), 400); }
        $vals[] = $id;
        $pdo->prepare("UPDATE iuccs_drones SET " . implode(', ', $sets) . " WHERE id = ?")->execute($vals);
        log_activity('team#' . pval($s, 'team_id', '?'), 'drone_update', 'drone', $id, null);
        json_out(array('ok' => true));
    }
    if ($path === '/api/drones/create' && $method === 'POST') {
        $s    = Auth::require_auth();
        $b    = body_json();
        $team = ($s['role'] === 'admin') ? pval($b, 'team_id', $s['team_id']) : $s['team_id'];
        $code = trim(pval($b, 'code', ''));
        if ($code === '') { json_out(array('error' => 'code_required'), 400); }
        try {
            $st = $pdo->prepare(
                "INSERT INTO iuccs_drones(team_id, code, model, max_flight_min, battery_count, range_km, battery_pct, comm_status, gps_status, status)
                 VALUES (?,?,?,?,?,?,?,'good','fixed','available')"
            );
            $st->execute(array($team, $code, pval($b, 'model'), pval($b, 'max_flight_min'),
                pval($b, 'battery_count'), pval($b, 'range_km'), pval($b, 'battery_pct', 100)));
        } catch (Exception $e) {
            json_out(array('error' => 'duplicate_or_invalid'), 400);
        }
        json_out(array('id' => $pdo->lastInsertId()), 201);
    }

    if ($path === '/api/missions/board') {
        Auth::require_auth();
        $missions = $pdo->query("SELECT * FROM iuccs_missions ORDER BY issued_at DESC LIMIT 100")->fetchAll();
        $reps = $pdo->query(
            "SELECT id, mission_id, team_id, kind, mode, coord, lat, lng, troops, trucks, vehicles,
                    armed, unarmed, kia, serious, minor, failed, hostile, acknowledged, created_at
               FROM iuccs_reports WHERE mission_id IS NOT NULL ORDER BY created_at DESC"
        )->fetchAll();
        $byM = array();
        foreach ($reps as $r) {
            $mid = $r['mission_id'];
            if (!isset($byM[$mid])) { $byM[$mid] = array(); }
            $byM[$mid][] = $r;
        }
        foreach ($missions as $i => $m) {
            $missions[$i]['reports'] = isset($byM[$m['id']]) ? $byM[$m['id']] : array();
        }
        json_out(array('missions' => $missions));
    }

    if ($path === '/api/missions' && $method === 'GET') {
        $s   = Auth::require_auth();
        $tid = pval($_GET, 'team_id', $s['team_id']);
        $st  = $pdo->prepare("SELECT * FROM iuccs_missions WHERE team_id = ? ORDER BY issued_at DESC LIMIT 50");
        $st->execute(array($tid));
        json_out(array('missions' => $st->fetchAll()));
    }
    if ($path === '/api/missions' && $method === 'POST') {
        $s    = Auth::require_auth(array('admin', 'leader'));
        $b    = body_json();
        $type = (pval($b, 'type', 'recon') === 'attack') ? 'attack' : 'recon';
        $status = ($type === 'attack') ? 'pending_approval' : 'issued';
        $mode   = (pval($b, 'mode', 'real') === 'sim') ? 'sim' : 'real';
        $rp = pval($b, 'route_points');
        if (is_array($rp)) { $rp = json_encode($rp); } elseif (!is_string($rp)) { $rp = null; }
        $st = $pdo->prepare(
            "INSERT INTO iuccs_missions(team_id, type, coord, lat, lng, route, method, mode, status, issued_by, route_points)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)"
        );
        $st->execute(array(
            pval($b, 'team_id', $s['team_id']), $type, pval($b, 'coord'), pval($b, 'lat'), pval($b, 'lng'),
            pval($b, 'route'), pval($b, 'method'), $mode, $status, $s['user_id'], $rp,
        ));
        $id = $pdo->lastInsertId();
        log_activity('user#' . $s['user_id'], 'mission_issue', 'mission', $id, $type);
        json_out(array('id' => $id, 'status' => $status), 201);
    }
    if ($path === '/api/missions/ack' && $method === 'POST') {
        Auth::require_auth();
        $b = body_json();
        $pdo->prepare("UPDATE iuccs_missions SET status = 'acknowledged' WHERE id = ? AND status = 'issued'")
            ->execute(array(pval($b, 'id', 0)));
        json_out(array('ok' => true));
    }
    if ($path === '/api/missions/respond' && $method === 'POST') {
        $s    = Auth::require_auth();
        $b    = body_json();
        $id   = pval($b, 'id');
        $resp = pval($b, 'response', '');
        if (!in_array($resp, array('ack', 'unable', 'delay'), true)) {
            json_out(array('error' => 'invalid_response'), 400);
        }
        if ($s['role'] !== 'admin') {
            $chk = $pdo->prepare("SELECT team_id FROM iuccs_missions WHERE id = ?");
            $chk->execute(array($id));
            $row = $chk->fetch();
            if (!$row || $row['team_id'] != $s['team_id']) { json_out(array('error' => 'forbidden'), 403); }
        }
        $reason = pval($b, 'reason');
        $delay  = pval($b, 'delay_min');
        $sql    = "UPDATE iuccs_missions SET response_type = ?, response_reason = ?, response_delay_min = ?, responded_at = NOW()";
        $params = array($resp, $reason, $delay);
        if ($resp === 'ack') { $sql .= ", status = 'acknowledged'"; }
        $sql   .= " WHERE id = ?";
        $params[] = $id;
        $pdo->prepare($sql)->execute($params);
        log_activity('team#' . pval($s, 'team_id', '?'), 'mission_respond', 'mission', $id, $resp . ($reason ? (':' . $reason) : ''));
        json_out(array('ok' => true));
    }
    if ($path === '/api/missions/complete' && $method === 'POST') {
        $s  = Auth::require_auth();
        $b  = body_json();
        $id = pval($b, 'id');
        if ($s['role'] !== 'admin') {
            $chk = $pdo->prepare("SELECT team_id FROM iuccs_missions WHERE id = ?");
            $chk->execute(array($id));
            $row = $chk->fetch();
            if (!$row || $row['team_id'] != $s['team_id']) { json_out(array('error' => 'forbidden'), 403); }
        }
        $note = pval($b, 'note');
        $pdo->prepare("UPDATE iuccs_missions SET status = 'completed', completion_note = ?, completed_at = NOW() WHERE id = ?")
            ->execute(array($note, $id));
        log_activity('team#' . pval($s, 'team_id', '?'), 'mission_complete', 'mission', $id, $note);
        json_out(array('ok' => true));
    }
    if ($path === '/api/missions/cancel' && $method === 'POST') {
        $s  = Auth::require_auth(array('admin'));
        $b  = body_json();
        $id = pval($b, 'id');
        $pdo->prepare("UPDATE iuccs_missions SET status = 'cancelled' WHERE id = ? AND status NOT IN ('completed','cancelled')")
            ->execute(array($id));
        log_activity('user#' . $s['user_id'], 'mission_cancel', 'mission', $id, null);
        json_out(array('ok' => true));
    }
    if ($path === '/api/missions/approve' && $method === 'POST') {
        $s = Auth::require_auth(array('admin'));
        $b = body_json();
        if (!pval($b, 'roe_confirmed')) {
            json_out(array('error' => 'roe_required', 'message' => '교전규칙(ROE) 확인이 필요합니다.'), 422);
        }
        $pdo->prepare(
            "UPDATE iuccs_missions SET approved = 1, roe_confirmed = 1, status = 'issued'
              WHERE id = ? AND type = 'attack'"
        )->execute(array(pval($b, 'id', 0)));
        log_activity('user#' . $s['user_id'], 'mission_approve', 'mission', pval($b, 'id', 0), 'roe_confirmed');
        json_out(array('ok' => true));
    }

    if ($path === '/api/reports' && $method === 'GET') {
        Auth::require_auth();
        json_out(array('reports' => $pdo->query("SELECT * FROM iuccs_reports ORDER BY created_at DESC LIMIT 100")->fetchAll()));
    }
    if ($path === '/api/reports' && $method === 'POST') {
        $s   = Auth::require_auth();
        $b   = body_json();
        $kind = in_array(pval($b, 'kind', ''), array('recon', 'attack', 'photo'), true) ? $b['kind'] : 'recon';
        $st  = $pdo->prepare(
            "INSERT INTO iuccs_reports
                (mission_id, team_id, kind, mode, coord, lat, lng, troops, trucks, vehicles,
                 armed, unarmed, scale, kia, serious, minor, failed, note, hostile)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $st->execute(array(
            pval($b, 'mission_id'), pval($b, 'team_id', $s['team_id']), $kind,
            (pval($b, 'mode', 'real') === 'sim') ? 'sim' : 'real', pval($b, 'coord'),
            pval($b, 'lat'), pval($b, 'lng'),
            pval($b, 'troops'), pval($b, 'trucks'), pval($b, 'vehicles'),
            pval($b, 'armed'), pval($b, 'unarmed'), pval($b, 'scale'),
            pval($b, 'kia'), pval($b, 'serious'), pval($b, 'minor'),
            pval($b, 'failed') ? 1 : 0, pval($b, 'note'), pval($b, 'hostile') ? 1 : 0,
        ));
        $id = $pdo->lastInsertId();
        log_activity('team#' . pval($s, 'team_id', '?'), 'report_submit', 'report', $id, $kind);
        json_out(array('id' => $id), 201);
    }
    if ($path === '/api/reports/ack' && $method === 'POST') {
        Auth::require_auth(array('admin', 'leader', 'fire_coord'));
        $b = body_json();
        $pdo->prepare("UPDATE iuccs_reports SET acknowledged = 1 WHERE id = ?")->execute(array(pval($b, 'id', 0)));
        json_out(array('ok' => true));
    }
    if ($path === '/api/reports/action' && $method === 'POST') {
        $s      = Auth::require_auth(array('admin'));
        $b      = body_json();
        $id     = pval($b, 'id');
        $action = pval($b, 'action', '');
        if ($action === 'reset') {
            $pdo->prepare("UPDATE iuccs_reports SET action_taken = 'none', action_at = NULL, acknowledged = 0 WHERE id = ?")
                ->execute(array($id));
            log_activity('user#' . $s['user_id'], 'report_action_reset', 'report', $id, null);
            json_out(array('ok' => true));
        }
        if (!in_array($action, array('attack', 'hold', 'friendly', 'ack'), true)) {
            json_out(array('error' => 'invalid_action'), 400);
        }
        $pdo->prepare("UPDATE iuccs_reports SET action_taken = ?, action_at = NOW(), acknowledged = 1 WHERE id = ?")
            ->execute(array($action, $id));
        log_activity('user#' . $s['user_id'], 'report_action', 'report', $id, $action);
        json_out(array('ok' => true));
    }

    if ($path === '/api/fire-requests' && $method === 'GET') {
        Auth::require_auth();
        json_out(array('fire_requests' => $pdo->query("SELECT * FROM iuccs_fire_requests ORDER BY created_at DESC LIMIT 50")->fetchAll()));
    }
    if ($path === '/api/fire-requests' && $method === 'POST') {
        $s  = Auth::require_auth();
        $b  = body_json();
        $st = $pdo->prepare(
            "INSERT INTO iuccs_fire_requests(team_id, coord, lat, lng, target, scale, support_from, mode)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        $st->execute(array(
            pval($b, 'team_id', $s['team_id']), pval($b, 'coord'), pval($b, 'lat'), pval($b, 'lng'),
            pval($b, 'target'), pval($b, 'scale'),
            (pval($b, 'support_from', 'higher') === 'adjacent') ? 'adjacent' : 'higher',
            (pval($b, 'mode', 'real') === 'sim') ? 'sim' : 'real',
        ));
        $id = $pdo->lastInsertId();
        log_activity('team#' . pval($s, 'team_id', '?'), 'fire_request', 'fire_request', $id, pval($b, 'target', ''));
        json_out(array('id' => $id, 'status' => 'received'), 201);
    }
    if ($path === '/api/fire-requests/status' && $method === 'POST') {
        $s       = Auth::require_auth(array('admin', 'fire_coord'));
        $b       = body_json();
        $allowed = array('received', 'reviewing', 'approved', 'held', 'executed', 'rejected');
        $stt     = in_array(pval($b, 'status', ''), $allowed, true) ? $b['status'] : 'received';
        $pdo->prepare("UPDATE iuccs_fire_requests SET status = ? WHERE id = ?")->execute(array($stt, pval($b, 'id', 0)));
        log_activity('user#' . $s['user_id'], 'fire_status', 'fire_request', pval($b, 'id', 0), $stt);
        json_out(array('ok' => true));
    }

    json_out(array('error' => 'not_found', 'path' => $path), 404);
}
