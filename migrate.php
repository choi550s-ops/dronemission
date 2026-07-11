<?php
// Web migration endpoint for FTP-only hosting (no SSH to run the CLI runner).
// Protect with a secret token stored in .env as MIGRATE_TOKEN.
// Visit once after deploy:  https://YOURDOMAIN/migrate.php?token=YOUR_TOKEN
// Idempotent: already-applied migrations are skipped on later runs.

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/src/Migrator.php';

header('Content-Type: text/plain; charset=utf-8');

$expected = getenv('MIGRATE_TOKEN');
if (!$expected) {
    $env = env_load(__DIR__ . '/.env');
    $expected = $env['MIGRATE_TOKEN'] ?? '';
}
$given = $_GET['token'] ?? ($_SERVER['HTTP_X_MIGRATE_TOKEN'] ?? '');

if (!$expected || !hash_equals($expected, (string) $given)) {
    http_response_code(403);
    echo "forbidden — invalid or missing token\n";
    exit;
}

try {
    foreach (Migrator::run() as $line) {
        echo "[migrate] $line\n";
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo "[migrate] FAILED: " . $e->getMessage() . "\n";
}
