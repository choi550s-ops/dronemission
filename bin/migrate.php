<?php
// CLI migration runner (for SSH hosts).  Usage:  php bin/migrate.php
require_once __DIR__ . '/../src/Migrator.php';
try {
    foreach (Migrator::run() as $line) {
        fwrite(STDOUT, "[migrate] $line\n");
    }
} catch (Exception $e) {
    fwrite(STDERR, "[migrate] FAILED: " . $e->getMessage() . "\n");
    exit(1);
}
