<?php
// Loads configuration from a .env file (kept out of git) or real environment variables.

function env_load($path)
{
    static $loaded = null;
    if ($loaded !== null) {
        return $loaded;
    }
    $loaded = [];
    if (is_file($path)) {
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $k = trim(substr($line, 0, $pos));
            $v = trim(substr($line, $pos + 1));
            $v = trim($v, "\"'");
            $loaded[$k] = $v;
        }
    }
    return $loaded;
}

function config()
{
    static $c = null;
    if ($c !== null) {
        return $c;
    }
    $env = env_load(__DIR__ . '/../.env');
    $get = function ($k, $d = null) use ($env) {
        $v = getenv($k);
        if ($v !== false && $v !== '') {
            return $v;
        }
        return isset($env[$k]) ? $env[$k] : $d;
    };
    $c = [
        'db' => [
            'host'    => $get('DB_HOST', 'localhost'),
            'port'    => $get('DB_PORT', '3306'),
            'name'    => $get('DB_NAME', ''),
            'user'    => $get('DB_USER', ''),
            'pass'    => $get('DB_PASS', ''),
            'charset' => 'utf8mb4',
        ],
        'app' => [
            'name'          => $get('APP_NAME', 'IUCCS'),
            'env'           => $get('APP_ENV', 'production'),
            'init_password' => $get('INIT_PASSWORD', 'changeme1234'),
        ],
    ];
    return $c;
}
