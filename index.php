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
    if ($path === '/api/public/team-list') {
        // No auth required — used only to populate the login screen's
        // team selector. Exposes team_no only, nothing sensitive.
        json_out(array('teams' => $pdo->query("SELECT team_no FROM iuccs_teams ORDER BY team_no")->fetchAll()));
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
                    (SELECT COUNT(*) FROM iuccs_drones d WHERE d.team_id = t.id) AS drone_count,
                    (SELECT COUNT(*) FROM iuccs_messages m WHERE m.team_id = t.id AND m.direction = 'from_team' AND m.read_at IS NULL) AS unread_messages
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
            "SELECT id, team_id, kind, mode, coord, lat, lng, troops, trucks, vehicles, tanks, armored, artillery, unidentified, command_post, activity, direction, hostile, action_taken, note, created_at
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
        $sql = "SELECT d.*, dm.name AS model_name, dm.category AS model_category
                  FROM iuccs_drones d LEFT JOIN iuccs_drone_models dm ON dm.id = d.model_id";
        if ($tid) {
            $st = $pdo->prepare($sql . " WHERE d.team_id = ? ORDER BY d.code");
            $st->execute(array($tid));
        } else {
            $st = $pdo->query($sql . " ORDER BY d.code");
        }
        json_out(array('drones' => $st->fetchAll()));
    }

    if ($path === '/api/drone-models') {
        Auth::require_auth();
        json_out(array('models' => $pdo->query("SELECT * FROM iuccs_drone_models ORDER BY category, name")->fetchAll()));
    }
    if ($path === '/api/drone-models/create' && $method === 'POST') {
        $s   = Auth::require_auth(array('admin'));
        $b   = body_json();
        $name = trim(pval($b, 'name', ''));
        $cat  = (pval($b, 'category') === 'recon') ? 'recon' : 'attack';
        if ($name === '') { json_out(array('error' => 'name_required'), 400); }
        $st = $pdo->prepare("INSERT INTO iuccs_drone_models(name, category) VALUES (?,?)");
        $st->execute(array($name, $cat));
        log_activity('user#' . $s['user_id'], 'drone_model_create', 'drone_model', $pdo->lastInsertId(), $name);
        json_out(array('id' => $pdo->lastInsertId()), 201);
    }
    if ($path === '/api/drone-models/update' && $method === 'POST') {
        $s    = Auth::require_auth(array('admin'));
        $b    = body_json();
        $id   = pval($b, 'id');
        $name = trim(pval($b, 'name', ''));
        $cat  = (pval($b, 'category') === 'recon') ? 'recon' : 'attack';
        if (!$id || $name === '') { json_out(array('error' => 'fields_required'), 400); }
        $pdo->prepare("UPDATE iuccs_drone_models SET name = ?, category = ? WHERE id = ?")->execute(array($name, $cat, $id));
        log_activity('user#' . $s['user_id'], 'drone_model_update', 'drone_model', $id, $name);
        json_out(array('ok' => true));
    }
    if ($path === '/api/drone-models/delete' && $method === 'POST') {
        $s  = Auth::require_auth(array('admin'));
        $b  = body_json();
        $id = pval($b, 'id');
        if (!$id) { json_out(array('error' => 'id_required'), 400); }
        // drones.model_id has no FK constraint (by design); drones referencing this
        // model simply show no model name/category afterwards rather than failing.
        $pdo->prepare("UPDATE iuccs_drones SET model_id = NULL WHERE model_id = ?")->execute(array($id));
        $pdo->prepare("DELETE FROM iuccs_drone_models WHERE id = ?")->execute(array($id));
        log_activity('user#' . $s['user_id'], 'drone_model_delete', 'drone_model', $id, null);
        json_out(array('ok' => true));
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
            'status'      => array('available','maintenance','destroyed'),
        );
        $sets = array(); $vals = array();
        foreach (array('code','model_id','max_flight_min','battery_count','range_km','battery_pct','comm_status','gps_status','status','note') as $f) {
            if (!array_key_exists($f, $b)) { continue; }
            if (isset($enum[$f]) && !in_array($b[$f], $enum[$f], true)) { continue; }
            $sets[] = "$f = ?"; $vals[] = $b[$f];
        }
        if (!$sets) { json_out(array('error' => 'no_fields'), 400); }
        $vals[] = $id;
        try {
            $pdo->prepare("UPDATE iuccs_drones SET " . implode(', ', $sets) . " WHERE id = ?")->execute($vals);
        } catch (Exception $e) {
            json_out(array('error' => 'duplicate_or_invalid'), 400);
        }
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
                "INSERT INTO iuccs_drones(team_id, code, model_id, max_flight_min, battery_count, range_km, battery_pct, comm_status, gps_status, status)
                 VALUES (?,?,?,?,?,?,?,'good','fixed','available')"
            );
            $st->execute(array($team, $code, pval($b, 'model_id'), pval($b, 'max_flight_min'),
                pval($b, 'battery_count'), pval($b, 'range_km'), pval($b, 'battery_pct', 100)));
        } catch (Exception $e) {
            json_out(array('error' => 'duplicate_or_invalid'), 400);
        }
        json_out(array('id' => $pdo->lastInsertId()), 201);
    }
    if ($path === '/api/drones/delete' && $method === 'POST') {
        $s  = Auth::require_auth();
        $b  = body_json();
        $id = pval($b, 'id');
        if ($s['role'] !== 'admin') {
            $chk = $pdo->prepare("SELECT team_id FROM iuccs_drones WHERE id = ?");
            $chk->execute(array($id));
            $d = $chk->fetch();
            if (!$d || $d['team_id'] != $s['team_id']) { json_out(array('error' => 'forbidden'), 403); }
        }
        $pdo->prepare("DELETE FROM iuccs_drones WHERE id = ?")->execute(array($id));
        log_activity('team#' . pval($s, 'team_id', '?'), 'drone_delete', 'drone', $id, null);
        json_out(array('ok' => true));
    }
    if ($path === '/api/team-members') {
        Auth::require_auth();
        $tid = pval($_GET, 'team_id');
        if ($tid) {
            $st = $pdo->prepare("SELECT * FROM iuccs_team_members WHERE team_id = ? ORDER BY id");
            $st->execute(array($tid));
        } else {
            $st = $pdo->query("SELECT * FROM iuccs_team_members ORDER BY team_id, id");
        }
        json_out(array('members' => $st->fetchAll()));
    }
    if ($path === '/api/team-members/create' && $method === 'POST') {
        $s    = Auth::require_auth();
        $b    = body_json();
        $team = ($s['role'] === 'admin') ? pval($b, 'team_id', $s['team_id']) : $s['team_id'];
        $name = trim(pval($b, 'name', ''));
        $roles = array('공격','로봇','정찰','지원','통신','사이버');
        $role = in_array(pval($b, 'role'), $roles, true) ? $b['role'] : '지원';
        if ($name === '') { json_out(array('error' => 'name_required'), 400); }
        $st = $pdo->prepare("INSERT INTO iuccs_team_members(team_id, name, role) VALUES (?,?,?)");
        $st->execute(array($team, $name, $role));
        json_out(array('id' => $pdo->lastInsertId()), 201);
    }
    if ($path === '/api/team-members/update' && $method === 'POST') {
        $s  = Auth::require_auth();
        $b  = body_json();
        $id = pval($b, 'id');
        if ($s['role'] !== 'admin') {
            $chk = $pdo->prepare("SELECT team_id FROM iuccs_team_members WHERE id = ?");
            $chk->execute(array($id));
            $row = $chk->fetch();
            if (!$row || $row['team_id'] != $s['team_id']) { json_out(array('error' => 'forbidden'), 403); }
        }
        $roles = array('공격','로봇','정찰','지원','통신','사이버');
        $sets = array(); $vals = array();
        if (array_key_exists('name', $b)) { $sets[] = 'name = ?'; $vals[] = trim($b['name']); }
        if (array_key_exists('role', $b) && in_array($b['role'], $roles, true)) { $sets[] = 'role = ?'; $vals[] = $b['role']; }
        if (!$sets) { json_out(array('error' => 'no_fields'), 400); }
        $vals[] = $id;
        $pdo->prepare("UPDATE iuccs_team_members SET " . implode(', ', $sets) . " WHERE id = ?")->execute($vals);
        json_out(array('ok' => true));
    }
    if ($path === '/api/team-members/delete' && $method === 'POST') {
        $s  = Auth::require_auth();
        $b  = body_json();
        $id = pval($b, 'id');
        if ($s['role'] !== 'admin') {
            $chk = $pdo->prepare("SELECT team_id FROM iuccs_team_members WHERE id = ?");
            $chk->execute(array($id));
            $row = $chk->fetch();
            if (!$row || $row['team_id'] != $s['team_id']) { json_out(array('error' => 'forbidden'), 403); }
        }
        $pdo->prepare("DELETE FROM iuccs_team_members WHERE id = ?")->execute(array($id));
        json_out(array('ok' => true));
    }

    if ($path === '/api/teams/create' && $method === 'POST') {
        $s        = Auth::require_auth(array('admin'));
        $b        = body_json();
        $teamNo   = trim(pval($b, 'team_no', ''));
        $name     = trim(pval($b, 'name', ''));
        $loginId  = trim(pval($b, 'login_id', ''));
        $password = pval($b, 'password', '');
        if ($teamNo === '' || $loginId === '' || $password === '') {
            json_out(array('error' => 'fields_required'), 400);
        }
        if (strlen($password) < 4) {
            json_out(array('error' => 'password_too_short'), 400);
        }
        $displayName = $name !== '' ? $name : ($teamNo . '팀');
        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare("INSERT INTO iuccs_teams(team_no, name, status) VALUES (?,?,'ready')");
            $st->execute(array($teamNo, $displayName));
            $teamId = $pdo->lastInsertId();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $st2 = $pdo->prepare(
                "INSERT INTO iuccs_users(team_id, login_id, password_hash, role, display_name) VALUES (?,?,?,'operator',?)"
            );
            $st2->execute(array($teamId, $loginId, $hash, $displayName));
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            json_out(array('error' => 'duplicate_or_invalid'), 400);
        }
        log_activity('user#' . $s['user_id'], 'team_create', 'team', $teamId, $teamNo);
        json_out(array('id' => $teamId, 'team_no' => $teamNo), 201);
    }
    if ($path === '/api/teams/delete' && $method === 'POST') {
        $s  = Auth::require_auth(array('admin'));
        $b  = body_json();
        $id = pval($b, 'id');
        if (!$id) { json_out(array('error' => 'id_required'), 400); }
        // Explicitly remove login accounts and drones (their FK is ON DELETE SET NULL,
        // not CASCADE) so a deleted team doesn't leave orphaned accounts/equipment behind.
        $pdo->prepare("DELETE FROM iuccs_users WHERE team_id = ?")->execute(array($id));
        $pdo->prepare("DELETE FROM iuccs_drones WHERE team_id = ?")->execute(array($id));
        $pdo->prepare("DELETE FROM iuccs_teams WHERE id = ?")->execute(array($id));
        log_activity('user#' . $s['user_id'], 'team_delete', 'team', $id, null);
        json_out(array('ok' => true));
    }
    if ($path === '/api/teams/status' && $method === 'POST') {
        $s      = Auth::require_auth();
        $b      = body_json();
        $tid    = ($s['role'] === 'admin') ? pval($b, 'team_id', $s['team_id']) : $s['team_id'];
        $allowed = array('unavailable', 'preparing', 'ready', 'on_mission');
        $status = pval($b, 'status', '');
        if (!$tid) { json_out(array('error' => 'team_required'), 400); }
        if (!in_array($status, $allowed, true)) { json_out(array('error' => 'invalid_status'), 400); }
        $pdo->prepare("UPDATE iuccs_teams SET status = ? WHERE id = ?")->execute(array($status, $tid));
        $actor = ($s['role'] === 'admin') ? ('user#' . $s['user_id']) : ('team#' . $tid);
        log_activity($actor, 'team_status_update', 'team', $tid, $status);
        json_out(array('ok' => true));
    }

    if ($path === '/api/team-track' && $method === 'POST') {
        $s   = Auth::require_auth();
        $b   = body_json();
        $tid = ($s['role'] === 'admin') ? pval($b, 'team_id') : $s['team_id'];
        $lat = pval($b, 'lat');
        $lng = pval($b, 'lng');
        if (!$tid || $lat === null || $lng === null) { json_out(array('error' => 'lat_lng_required'), 400); }
        $src = (pval($b, 'source') === 'manual') ? 'manual' : 'auto';
        $pdo->prepare("INSERT INTO iuccs_team_tracks(team_id, lat, lng, source) VALUES (?,?,?,?)")
            ->execute(array($tid, $lat, $lng, $src));
        $pdo->prepare("UPDATE iuccs_teams SET lat = ?, lng = ? WHERE id = ?")->execute(array($lat, $lng, $tid));
        json_out(array('ok' => true));
    }

    if ($path === '/api/messages' && $method === 'GET') {
        $s   = Auth::require_auth();
        $tid = pval($_GET, 'team_id');
        if ($s['role'] !== 'admin') { $tid = $s['team_id']; }
        if ($tid) {
            // Viewing this team's conversation marks the OTHER party's messages as read.
            $otherDir = ($s['role'] === 'admin') ? 'from_team' : 'to_team';
            $pdo->prepare("UPDATE iuccs_messages SET read_at = NOW() WHERE team_id = ? AND direction = ? AND read_at IS NULL")
                ->execute(array($tid, $otherDir));
            $st = $pdo->prepare("SELECT * FROM iuccs_messages WHERE team_id = ? ORDER BY created_at ASC LIMIT 100");
            $st->execute(array($tid));
        } else {
            $st = $pdo->query("SELECT * FROM iuccs_messages ORDER BY created_at DESC LIMIT 50");
        }
        json_out(array('messages' => $st->fetchAll()));
    }
    if ($path === '/api/messages' && $method === 'POST') {
        $s    = Auth::require_auth();
        $b    = body_json();
        $tid  = ($s['role'] === 'admin') ? pval($b, 'team_id') : $s['team_id'];
        $body = trim(pval($b, 'body', ''));
        if (!$tid || $body === '') { json_out(array('error' => 'invalid'), 400); }
        $dir    = ($s['role'] === 'admin') ? 'to_team' : 'from_team';
        $sender = ($s['role'] === 'admin') ? 'admin' : ('team#' . $tid);
        $st = $pdo->prepare("INSERT INTO iuccs_messages(team_id, sender, direction, body) VALUES (?,?,?,?)");
        $st->execute(array($tid, $sender, $dir, $body));
        $id = $pdo->lastInsertId();
        log_activity($sender, 'message_send', 'message', $id, safe_substr($body, 0, 80));
        json_out(array('id' => $id), 201);
    }

    if ($path === '/api/missions/board') {
        Auth::require_auth();
        $missions = $pdo->query("SELECT * FROM iuccs_missions ORDER BY issued_at DESC LIMIT 100")->fetchAll();
        $reps = $pdo->query(
            "SELECT id, mission_id, team_id, kind, mode, coord, lat, lng, troops, trucks, vehicles, tanks, armored, artillery, unidentified, command_post,
                    armed, unarmed, kia, serious, minor, destroyed, heavy_damage, light_damage, failed, hostile, acknowledged,
                    attack_result, attack_result_note, activity, direction, created_at
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
        $type = pval($b, 'type', 'recon');
        if (!in_array($type, array('recon', 'attack', 'damage'), true)) { $type = 'recon'; }
        $status = ($type === 'attack') ? 'pending_approval' : 'issued';
        $mode   = (pval($b, 'mode', 'real') === 'sim') ? 'sim' : 'real';
        $rp = pval($b, 'route_points');
        if (is_array($rp)) { $rp = json_encode($rp); } elseif (!is_string($rp)) { $rp = null; }
        $route = trim((string) pval($b, 'route', ''));
        if ($route === '') { $route = '자율'; }
        $st = $pdo->prepare(
            "INSERT INTO iuccs_missions(team_id, type, coord, lat, lng, route, method, mode, status, issued_by, route_points, target, source_report_id)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $st->execute(array(
            pval($b, 'team_id', $s['team_id']), $type, pval($b, 'coord'), pval($b, 'lat'), pval($b, 'lng'),
            $route, pval($b, 'method'), $mode, $status, $s['user_id'], $rp, pval($b, 'target'), pval($b, 'source_report_id'),
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
        $startTime = ($resp === 'ack') ? pval($b, 'start_time') : null;
        $sql    = "UPDATE iuccs_missions SET response_type = ?, response_reason = ?, response_delay_min = ?, response_start_time = ?, responded_at = NOW()";
        $params = array($resp, $reason, $delay, $startTime);
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
    if ($path === '/api/missions/delete' && $method === 'POST') {
        $s  = Auth::require_auth(array('admin'));
        $b  = body_json();
        $id = pval($b, 'id');
        $pdo->prepare("DELETE FROM iuccs_missions WHERE id = ?")->execute(array($id));
        log_activity('user#' . $s['user_id'], 'mission_delete', 'mission', $id, null);
        json_out(array('ok' => true));
    }
    if ($path === '/api/missions/bulk-delete' && $method === 'POST') {
        $s   = Auth::require_auth(array('admin'));
        $b   = body_json();
        $ids = pval($b, 'ids');
        if (!is_array($ids) || !count($ids)) { json_out(array('error' => 'ids_required'), 400); }
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!count($ids)) { json_out(array('error' => 'ids_required'), 400); }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM iuccs_missions WHERE id IN ($ph)")->execute($ids);
        log_activity('user#' . $s['user_id'], 'mission_bulk_delete', 'mission', null, implode(',', $ids));
        json_out(array('ok' => true, 'count' => count($ids)));
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
        $tid = pval($_GET, 'team_id');
        if ($tid) {
            $st = $pdo->prepare("SELECT * FROM iuccs_reports WHERE team_id = ? ORDER BY created_at DESC LIMIT 100");
            $st->execute(array($tid));
            json_out(array('reports' => $st->fetchAll()));
        } else {
            json_out(array('reports' => $pdo->query("SELECT * FROM iuccs_reports ORDER BY created_at DESC LIMIT 100")->fetchAll()));
        }
    }
    if ($path === '/api/reports' && $method === 'POST') {
        $s   = Auth::require_auth();
        $b   = body_json();
        $kind = in_array(pval($b, 'kind', ''), array('recon', 'attack', 'photo', 'damage'), true) ? $b['kind'] : 'recon';
        $attackResult = in_array(pval($b, 'attack_result', ''), array('hit', 'unknown', 'other'), true) ? $b['attack_result'] : null;
        $activity = in_array(pval($b, 'activity', ''), array('moving', 'defending', 'attacking'), true) ? $b['activity'] : null;
        $st  = $pdo->prepare(
            "INSERT INTO iuccs_reports
                (mission_id, team_id, kind, mode, coord, lat, lng, troops, trucks, vehicles, tanks, armored, artillery, unidentified, command_post,
                 armed, unarmed, scale, kia, serious, minor, destroyed, heavy_damage, light_damage, failed, note, hostile,
                 attack_result, attack_result_note, activity, direction)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $st->execute(array(
            pval($b, 'mission_id'), pval($b, 'team_id', $s['team_id']), $kind,
            (pval($b, 'mode', 'real') === 'sim') ? 'sim' : 'real', pval($b, 'coord'),
            pval($b, 'lat'), pval($b, 'lng'),
            pval($b, 'troops'), pval($b, 'trucks'), pval($b, 'vehicles'),
            pval($b, 'tanks'), pval($b, 'armored'), pval($b, 'artillery'), pval($b, 'unidentified') ? 1 : 0, pval($b, 'command_post'),
            pval($b, 'armed'), pval($b, 'unarmed'), pval($b, 'scale'),
            pval($b, 'kia'), pval($b, 'serious'), pval($b, 'minor'),
            pval($b, 'destroyed'), pval($b, 'heavy_damage'), pval($b, 'light_damage'),
            pval($b, 'failed') ? 1 : 0, pval($b, 'note'), pval($b, 'hostile') ? 1 : 0,
            $attackResult, pval($b, 'attack_result_note'),
            $activity, ($activity === 'moving') ? pval($b, 'direction') : null,
        ));
        $id = $pdo->lastInsertId();
        log_activity('team#' . pval($s, 'team_id', '?'), 'report_submit', 'report', $id, $kind);
        json_out(array('id' => $id), 201);
    }
    if ($path === '/api/reports/update' && $method === 'POST') {
        $s  = Auth::require_auth();
        $b  = body_json();
        $id = pval($b, 'id');
        if (!$id) { json_out(array('error' => 'id_required'), 400); }
        if ($s['role'] !== 'admin') {
            $chk = $pdo->prepare("SELECT team_id FROM iuccs_reports WHERE id = ?");
            $chk->execute(array($id));
            $row = $chk->fetch();
            if (!$row || $row['team_id'] != $s['team_id']) { json_out(array('error' => 'forbidden'), 403); }
        }
        $fields = array(
            'troops', 'trucks', 'vehicles', 'tanks', 'armored', 'artillery', 'unidentified', 'command_post', 'hostile',
            'armed', 'unarmed', 'kia', 'serious', 'minor', 'destroyed', 'heavy_damage', 'light_damage', 'failed', 'note',
            'attack_result', 'attack_result_note', 'activity', 'direction', 'lat', 'lng', 'coord',
        );
        $enum = array('attack_result' => array('hit', 'unknown', 'other'), 'activity' => array('moving', 'defending', 'attacking'));
        $sets = array(); $vals = array();
        foreach ($fields as $f) {
            if (!array_key_exists($f, $b)) { continue; }
            if (isset($enum[$f]) && !in_array($b[$f], $enum[$f], true)) { continue; }
            $sets[] = "$f = ?";
            $vals[] = in_array($f, array('hostile', 'failed', 'unidentified'), true) ? (pval($b, $f) ? 1 : 0) : pval($b, $f);
        }
        if (!$sets) { json_out(array('error' => 'no_fields'), 400); }
        $vals[] = $id;
        $pdo->prepare("UPDATE iuccs_reports SET " . implode(', ', $sets) . " WHERE id = ?")->execute($vals);
        log_activity(($s['role'] === 'admin') ? ('user#' . $s['user_id']) : ('team#' . $s['team_id']), 'report_update', 'report', $id, null);
        json_out(array('ok' => true));
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
    if ($path === '/api/reports/delete' && $method === 'POST') {
        $s  = Auth::require_auth(array('admin'));
        $b  = body_json();
        $id = pval($b, 'id');
        $pdo->prepare("DELETE FROM iuccs_reports WHERE id = ?")->execute(array($id));
        log_activity('user#' . $s['user_id'], 'report_delete', 'report', $id, null);
        json_out(array('ok' => true));
    }
    if ($path === '/api/reports/bulk-delete' && $method === 'POST') {
        $s   = Auth::require_auth(array('admin'));
        $b   = body_json();
        $ids = pval($b, 'ids');
        if (!is_array($ids) || !count($ids)) { json_out(array('error' => 'ids_required'), 400); }
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!count($ids)) { json_out(array('error' => 'ids_required'), 400); }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM iuccs_reports WHERE id IN ($ph)")->execute($ids);
        log_activity('user#' . $s['user_id'], 'report_bulk_delete', 'report', null, implode(',', $ids));
        json_out(array('ok' => true, 'count' => count($ids)));
    }

    if ($path === '/api/fire-requests' && $method === 'GET') {
        Auth::require_auth();
        json_out(array('fire_requests' => $pdo->query("SELECT * FROM iuccs_fire_requests ORDER BY created_at DESC LIMIT 50")->fetchAll()));
    }
    if ($path === '/api/fire-requests' && $method === 'POST') {
        $s  = Auth::require_auth();
        $b  = body_json();
        $sf = pval($b, 'support_from');
        $sf = ($sf === 'adjacent') ? 'adjacent' : (($sf === 'higher') ? 'higher' : null);
        $st = $pdo->prepare(
            "INSERT INTO iuccs_fire_requests(team_id, coord, lat, lng, target, scale, support_from, mode)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        $st->execute(array(
            pval($b, 'team_id', $s['team_id']), pval($b, 'coord'), pval($b, 'lat'), pval($b, 'lng'),
            pval($b, 'target'), pval($b, 'scale'), $sf,
            (pval($b, 'mode', 'real') === 'sim') ? 'sim' : 'real',
        ));
        $id = $pdo->lastInsertId();
        log_activity('team#' . pval($s, 'team_id', '?'), 'fire_request', 'fire_request', $id, pval($b, 'target', ''));
        json_out(array('id' => $id, 'status' => 'received'), 201);
    }
    if ($path === '/api/fire-requests/status' && $method === 'POST') {
        $s       = Auth::require_auth(array('admin', 'fire_coord'));
        $b       = body_json();
        $allowed = array('received', 'held', 'decided');
        $stt     = in_array(pval($b, 'status', ''), $allowed, true) ? $b['status'] : 'received';
        $ft = pval($b, 'fire_time');
        $pdo->prepare("UPDATE iuccs_fire_requests SET status = ?, fire_time = ? WHERE id = ?")
            ->execute(array($stt, $ft, pval($b, 'id', 0)));
        log_activity('user#' . $s['user_id'], 'fire_status', 'fire_request', pval($b, 'id', 0), $stt);
        json_out(array('ok' => true));
    }
    if ($path === '/api/fire-requests/delete' && $method === 'POST') {
        $s  = Auth::require_auth(array('admin'));
        $b  = body_json();
        $id = pval($b, 'id');
        $pdo->prepare("DELETE FROM iuccs_fire_requests WHERE id = ?")->execute(array($id));
        log_activity('user#' . $s['user_id'], 'fire_delete', 'fire_request', $id, null);
        json_out(array('ok' => true));
    }
    if ($path === '/api/fire-requests/bulk-delete' && $method === 'POST') {
        $s   = Auth::require_auth(array('admin'));
        $b   = body_json();
        $ids = pval($b, 'ids');
        if (!is_array($ids) || !count($ids)) { json_out(array('error' => 'ids_required'), 400); }
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!count($ids)) { json_out(array('error' => 'ids_required'), 400); }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM iuccs_fire_requests WHERE id IN ($ph)")->execute($ids);
        log_activity('user#' . $s['user_id'], 'fire_bulk_delete', 'fire_request', null, implode(',', $ids));
        json_out(array('ok' => true, 'count' => count($ids)));
    }

    if ($path === '/api/admin/reset' && $method === 'POST') {
        $s = Auth::require_auth(array('admin'));
        $b = body_json();
        if (pval($b, 'confirm') !== 'RESET') {
            json_out(array('error' => 'confirm_required'), 400);
        }
        // Wipes operational/transactional data only. Teams, accounts, roster,
        // drones and drone models are preserved so the system stays usable.
        $tables = array(
            'iuccs_missions', 'iuccs_reports', 'iuccs_fire_requests',
            'iuccs_messages', 'iuccs_team_tracks', 'iuccs_activity_log',
        );
        foreach ($tables as $t) {
            $pdo->exec("TRUNCATE TABLE $t");
        }
        log_activity('user#' . $s['user_id'], 'admin_reset', null, null, 'full operational data reset');
        json_out(array('ok' => true));
    }

    json_out(array('error' => 'not_found', 'path' => $path), 404);
}
