<?php
require_once __DIR__ . '/Database.php';

function json_out($data, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function body_json()
{
    $raw = file_get_contents('php://input');
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}

function bearer_token()
{
    $h = $_SERVER['HTTP_AUTHORIZATION']
        ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if ($h && preg_match('/Bearer\s+(\S+)/i', $h, $m)) {
        return $m[1];
    }
    return $_SERVER['HTTP_X_TOKEN'] ?? '';
}

function log_activity($actor, $action, $entity = null, $entityId = null, $detail = null)
{
    try {
        $st = Database::pdo()->prepare(
            "INSERT INTO activity_log(actor, action, entity, entity_id, detail) VALUES (?,?,?,?,?)"
        );
        $st->execute([$actor, $action, $entity, $entityId, $detail]);
    } catch (Throwable $e) {
        // logging must never break the request
    }
}
