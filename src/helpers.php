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
    return is_array($d) ? $d : array();
}

function bearer_token()
{
    $h = '';
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $h = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $h = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    if ($h && preg_match('/Bearer\s+(\S+)/i', $h, $m)) {
        return $m[1];
    }
    return isset($_SERVER['HTTP_X_TOKEN']) ? $_SERVER['HTTP_X_TOKEN'] : '';
}

function log_activity($actor, $action, $entity = null, $entityId = null, $detail = null)
{
    try {
        $st = Database::pdo()->prepare(
            "INSERT INTO iuccs_activity_log(actor, action, entity, entity_id, detail) VALUES (?,?,?,?,?)"
        );
        $st->execute(array($actor, $action, $entity, $entityId, $detail));
    } catch (Exception $e) {
        // logging must never break the request
    }
}

function haversine_m($lat1, $lng1, $lat2, $lng2)
{
    $R = 6371000.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = pow(sin($dLat / 2), 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * pow(sin($dLng / 2), 2);
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

function get_setting($key, $default = null)
{
    try {
        $st = Database::pdo()->prepare("SELECT setting_value FROM iuccs_settings WHERE setting_key = ?");
        $st->execute(array($key));
        $row = $st->fetch();
        return $row ? $row['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function set_setting($key, $value)
{
    $st = Database::pdo()->prepare(
        "INSERT INTO iuccs_settings(setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );
    $st->execute(array($key, $value));
}
