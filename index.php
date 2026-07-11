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
    header('Location: ' . $base . '/public/dashboard/');
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
            "SELECT id, team_id, kind, mode, coord, lat, lng, troops, trucks, vehicles, created_at
               FROM iuccs_reports WHERE lat IS NOT NULL ORDER BY created_at DESC LIMIT 50"
        )->fetchAll();
        $fires = $pdo->query(
            "SELECT id, team_id, coord, target, scale, status, lat, lng, created_at
               FROM iuccs_fire_requests WHERE lat IS NOT NULL ORDER BY created_at DESC LIMIT 50"
        )->fetchAll();
        json_out(array('teams' => $teams, 'reports' => $reports, 'fire_requests' => $fires));
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
                 armed, unarmed, scale, kia, serious, minor, failed, note)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $st->execute(array(
            pval($b, 'mission_id'), pval($b, 'team_id', $s['team_id']), $kind,
            (pval($b, 'mode', 'real') === 'sim') ? 'sim' : 'real', pval($b, 'coord'),
            pval($b, 'lat'), pval($b, 'lng'),
            pval($b, 'troops'), pval($b, 'trucks'), pval($b, 'vehicles'),
            pval($b, 'armed'), pval($b, 'unarmed'), pval($b, 'scale'),
            pval($b, 'kia'), pval($b, 'serious'), pval($b, 'minor'),
            pval($b, 'failed') ? 1 : 0, pval($b, 'note'),
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
