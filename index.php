<?php
// Front controller. Static files under /public are served by Apache directly (see .htaccess).
// Everything else routes here; /api/* is the JSON API.

require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Auth.php';

$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base   = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($base !== '' && strpos($path, $base) === 0) {
    $path = substr($path, strlen($base));
}
$path   = '/' . ltrim($path, '/');
$method = $_SERVER['REQUEST_METHOD'];

// Landing: send people to the dashboard app.
if ($path === '/' || $path === '') {
    header('Location: ' . $base . '/public/dashboard/');
    exit;
}

if (strpos($path, '/api/') !== 0) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

// CORS-friendly for same-origin apps; adjust if you split domains.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Token');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    route($path, $method);
} catch (Throwable $e) {
    $msg = (config()['app']['env'] === 'production') ? 'internal error' : $e->getMessage();
    json_out(['error' => 'server_error', 'message' => $msg], 500);
}

function route($path, $method)
{
    $pdo = Database::pdo();

    // --- public ---
    if ($path === '/api/time') {
        json_out(['now' => date('c'), 'ts' => time()]);
    }
    if ($path === '/api/login' && $method === 'POST') {
        $b = body_json();
        $loginId = trim($b['team_no'] ?? ($b['login_id'] ?? ''));
        $r = Auth::login($loginId, $b['password'] ?? '', $b['mode'] ?? 'real');
        if (!$r) {
            json_out(['error' => 'invalid_credentials'], 401);
        }
        json_out($r);
    }

    // --- session / dashboard ---
    if ($path === '/api/session') {
        $s = Auth::require_auth();
        json_out(['role' => $s['role'], 'team_id' => $s['team_id'], 'mode' => $s['mode']]);
    }
    if ($path === '/api/logout' && $method === 'POST') {
        Auth::logout();
        json_out(['ok' => true]);
    }
    if ($path === '/api/dashboard') {
        Auth::require_auth();
        $teams = $pdo->query(
            "SELECT t.id, t.team_no, t.name, t.status, t.coord, t.last_login_at,
                    (SELECT COUNT(*) FROM drones d WHERE d.team_id = t.id) AS drone_count
               FROM teams t ORDER BY t.team_no"
        )->fetchAll();
        $counts = [
            'missions_pending' => (int) $pdo->query(
                "SELECT COUNT(*) FROM missions WHERE status IN ('issued','pending_approval')"
            )->fetchColumn(),
            'reports_unack'    => (int) $pdo->query(
                "SELECT COUNT(*) FROM reports WHERE acknowledged = 0"
            )->fetchColumn(),
            'fire_pending'     => (int) $pdo->query(
                "SELECT COUNT(*) FROM fire_requests WHERE status IN ('received','reviewing')"
            )->fetchColumn(),
        ];
        json_out(['teams' => $teams, 'counts' => $counts, 'server_time' => date('c')]);
    }

    // --- teams / drones ---
    if ($path === '/api/teams') {
        Auth::require_auth();
        json_out(['teams' => $pdo->query("SELECT * FROM teams ORDER BY team_no")->fetchAll()]);
    }
    if ($path === '/api/drones') {
        $s = Auth::require_auth();
        $tid = $_GET['team_id'] ?? null;
        if ($tid) {
            $st = $pdo->prepare("SELECT * FROM drones WHERE team_id = ? ORDER BY code");
            $st->execute([$tid]);
        } else {
            $st = $pdo->query("SELECT * FROM drones ORDER BY code");
        }
        json_out(['drones' => $st->fetchAll()]);
    }

    // --- missions ---
    if ($path === '/api/missions' && $method === 'GET') {
        $s   = Auth::require_auth();
        $tid = $_GET['team_id'] ?? $s['team_id'];
        $st  = $pdo->prepare("SELECT * FROM missions WHERE team_id = ? ORDER BY issued_at DESC LIMIT 50");
        $st->execute([$tid]);
        json_out(['missions' => $st->fetchAll()]);
    }
    if ($path === '/api/missions' && $method === 'POST') {
        $s    = Auth::require_auth(['admin', 'leader']);
        $b    = body_json();
        $type = (($b['type'] ?? 'recon') === 'attack') ? 'attack' : 'recon';
        // Attack missions start in pending_approval and require a human ROE gate before issue.
        $status = ($type === 'attack') ? 'pending_approval' : 'issued';
        $mode   = (($b['mode'] ?? 'real') === 'sim') ? 'sim' : 'real';
        $st = $pdo->prepare(
            "INSERT INTO missions(team_id, type, coord, route, method, mode, status, issued_by)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        $st->execute([
            $b['team_id'] ?? $s['team_id'], $type, $b['coord'] ?? null, $b['route'] ?? null,
            $b['method'] ?? null, $mode, $status, $s['user_id'],
        ]);
        $id = $pdo->lastInsertId();
        log_activity('user#' . $s['user_id'], 'mission_issue', 'mission', $id, $type);
        json_out(['id' => $id, 'status' => $status], 201);
    }
    if ($path === '/api/missions/ack' && $method === 'POST') {
        Auth::require_auth();
        $b = body_json();
        $pdo->prepare("UPDATE missions SET status = 'acknowledged' WHERE id = ? AND status = 'issued'")
            ->execute([$b['id'] ?? 0]);
        json_out(['ok' => true]);
    }
    if ($path === '/api/missions/approve' && $method === 'POST') {
        $s = Auth::require_auth(['admin']);
        $b = body_json();
        if (empty($b['roe_confirmed'])) {
            json_out(['error' => 'roe_required', 'message' => '교전규칙(ROE) 확인이 필요합니다.'], 422);
        }
        $pdo->prepare(
            "UPDATE missions SET approved = 1, roe_confirmed = 1, status = 'issued'
              WHERE id = ? AND type = 'attack'"
        )->execute([$b['id'] ?? 0]);
        log_activity('user#' . $s['user_id'], 'mission_approve', 'mission', $b['id'] ?? 0, 'roe_confirmed');
        json_out(['ok' => true]);
    }

    // --- reports ---
    if ($path === '/api/reports' && $method === 'GET') {
        Auth::require_auth();
        json_out(['reports' => $pdo->query("SELECT * FROM reports ORDER BY created_at DESC LIMIT 100")->fetchAll()]);
    }
    if ($path === '/api/reports' && $method === 'POST') {
        $s   = Auth::require_auth();
        $b   = body_json();
        $st  = $pdo->prepare(
            "INSERT INTO reports
                (mission_id, team_id, kind, mode, coord, troops, trucks, vehicles,
                 armed, unarmed, scale, kia, serious, minor, failed, note)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $st->execute([
            $b['mission_id'] ?? null, $b['team_id'] ?? $s['team_id'],
            in_array($b['kind'] ?? '', ['recon', 'attack', 'photo'], true) ? $b['kind'] : 'recon',
            (($b['mode'] ?? 'real') === 'sim') ? 'sim' : 'real', $b['coord'] ?? null,
            $b['troops'] ?? null, $b['trucks'] ?? null, $b['vehicles'] ?? null,
            $b['armed'] ?? null, $b['unarmed'] ?? null, $b['scale'] ?? null,
            $b['kia'] ?? null, $b['serious'] ?? null, $b['minor'] ?? null,
            !empty($b['failed']) ? 1 : 0, $b['note'] ?? null,
        ]);
        $id = $pdo->lastInsertId();
        log_activity('team#' . ($s['team_id'] ?? '?'), 'report_submit', 'report', $id, $b['kind'] ?? 'recon');
        json_out(['id' => $id], 201);
    }
    if ($path === '/api/reports/ack' && $method === 'POST') {
        Auth::require_auth(['admin', 'leader', 'fire_coord']);
        $b = body_json();
        $pdo->prepare("UPDATE reports SET acknowledged = 1 WHERE id = ?")->execute([$b['id'] ?? 0]);
        json_out(['ok' => true]);
    }

    // --- fire support requests ---
    if ($path === '/api/fire-requests' && $method === 'GET') {
        Auth::require_auth();
        json_out(['fire_requests' => $pdo->query("SELECT * FROM fire_requests ORDER BY created_at DESC LIMIT 50")->fetchAll()]);
    }
    if ($path === '/api/fire-requests' && $method === 'POST') {
        $s  = Auth::require_auth();
        $b  = body_json();
        $st = $pdo->prepare(
            "INSERT INTO fire_requests(team_id, coord, target, scale, support_from, mode)
             VALUES (?,?,?,?,?,?)"
        );
        $st->execute([
            $b['team_id'] ?? $s['team_id'], $b['coord'] ?? null, $b['target'] ?? null,
            $b['scale'] ?? null, (($b['support_from'] ?? 'higher') === 'adjacent') ? 'adjacent' : 'higher',
            (($b['mode'] ?? 'real') === 'sim') ? 'sim' : 'real',
        ]);
        $id = $pdo->lastInsertId();
        log_activity('team#' . ($s['team_id'] ?? '?'), 'fire_request', 'fire_request', $id, $b['target'] ?? '');
        json_out(['id' => $id, 'status' => 'received'], 201);
    }
    if ($path === '/api/fire-requests/status' && $method === 'POST') {
        $s       = Auth::require_auth(['admin', 'fire_coord']);
        $b       = body_json();
        $allowed = ['received', 'reviewing', 'approved', 'held', 'executed', 'rejected'];
        $stt     = in_array($b['status'] ?? '', $allowed, true) ? $b['status'] : 'received';
        $pdo->prepare("UPDATE fire_requests SET status = ? WHERE id = ?")->execute([$stt, $b['id'] ?? 0]);
        log_activity('user#' . $s['user_id'], 'fire_status', 'fire_request', $b['id'] ?? 0, $stt);
        json_out(['ok' => true]);
    }

    json_out(['error' => 'not_found', 'path' => $path], 404);
}
