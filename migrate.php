<?php
// Web migration endpoint (PHP 5.x compatible).
// Visit once after deploy:  https://YOURDOMAIN/dronemission/migrate.php?token=YOUR_TOKEN

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/src/Migrator.php';

header('Content-Type: text/plain; charset=utf-8');

$expected = getenv('MIGRATE_TOKEN');
if (!$expected) {
    $env = env_load(__DIR__ . '/.env');
    $expected = isset($env['MIGRATE_TOKEN']) ? $env['MIGRATE_TOKEN'] : '';
}
$given = isset($_GET['token']) ? $_GET['token'] : (isset($_SERVER['HTTP_X_MIGRATE_TOKEN']) ? $_SERVER['HTTP_X_MIGRATE_TOKEN'] : '');

if (!$expected || !hash_equals($expected, (string) $given)) {
    http_response_code(403);
    echo "forbidden — invalid or missing token\n";
    exit;
}

try {
    foreach (Migrator::run() as $line) {
        echo "[migrate] $line\n";
    }
} catch (Exception $e) {
    http_response_code(500);
    echo "[migrate] FAILED: " . $e->getMessage() . "\n";
}
