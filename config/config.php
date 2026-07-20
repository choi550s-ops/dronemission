<?php
// Config loader + small compatibility polyfills (works on PHP 5.4+).

// ---- temporary login lockout ----
// Fallback default used only when no row exists yet in iuccs_settings
// (maintenance_mode) -- once the settings table has a value, that value
// always wins. Toggle live via the separate 관제 control page instead of
// editing this.
define('MAINTENANCE_MODE_DEFAULT', false);

// ---- compatibility helpers ----
if (!function_exists('pval')) {
    // safe array read: pval($arr,'key','default')
    function pval($arr, $key, $default = null) {
        return (is_array($arr) && isset($arr[$key])) ? $arr[$key] : $default;
    }
}
if (!function_exists('random_token')) {
    // hex token, works without random_bytes() (PHP < 7)
    function random_token($bytes = 32) {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($bytes));
        }
        if (function_exists('openssl_random_pseudo_bytes')) {
            $b = openssl_random_pseudo_bytes($bytes);
            if ($b !== false) { return bin2hex($b); }
        }
        $s = '';
        for ($i = 0; $i < $bytes * 2; $i++) { $s .= dechex(mt_rand(0, 15)); }
        return $s;
    }
}
if (!function_exists('safe_substr')) {
    // multi-byte-safe truncate; falls back to plain substr if mbstring isn't available
    function safe_substr($str, $start, $len) {
        if (function_exists('mb_substr')) {
            return mb_substr($str, $start, $len, 'UTF-8');
        }
        return substr($str, $start, $len);
    }
}
if (!function_exists('hash_equals')) {
    // constant-time compare polyfill (PHP < 5.6)
    function hash_equals($known, $user) {
        $known = (string) $known; $user = (string) $user;
        if (strlen($known) !== strlen($user)) { return false; }
        $res = 0;
        for ($i = 0, $n = strlen($known); $i < $n; $i++) {
            $res |= ord($known[$i]) ^ ord($user[$i]);
        }
        return $res === 0;
    }
}

function env_load($path)
{
    static $loaded = null;
    if ($loaded !== null) {
        return $loaded;
    }
    $loaded = array();
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
    $c = array(
        'db' => array(
            'host'    => $get('DB_HOST', 'localhost'),
            'port'    => $get('DB_PORT', '3306'),
            'name'    => $get('DB_NAME', ''),
            'user'    => $get('DB_USER', ''),
            'pass'    => $get('DB_PASS', ''),
            'charset' => 'utf8mb4',
        ),
        'app' => array(
            'name'          => $get('APP_NAME', 'IUCCS'),
            'env'           => $get('APP_ENV', 'production'),
            'init_password' => $get('INIT_PASSWORD', 'changeme1234'),
            // Temporary login lockout switch. Checked two ways so it can be
            // flipped either by editing .env on the server directly, or by
            // changing the code constant below and redeploying — whichever
            // is more convenient at the time. .env (if set) takes priority.
            'maintenance_mode' => $get('MAINTENANCE_MODE', MAINTENANCE_MODE_DEFAULT ? '1' : '0') === '1',
            'maintenance_message' => $get('MAINTENANCE_MESSAGE', '프로그램 개발 중으로 임시로 접속을 차단합니다.'),
        ),
    );
    return $c;
}
