<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/helpers.php';

class Auth
{
    // Log in with team number (login_id) + password. Returns session info or null.
    public static function login($loginId, $password, $mode = 'real')
    {
        $pdo = Database::pdo();
        $st = $pdo->prepare(
            "SELECT u.*, t.team_no
               FROM users u
               LEFT JOIN teams t ON t.id = u.team_id
              WHERE u.login_id = ? LIMIT 1"
        );
        $st->execute([$loginId]);
        $u = $st->fetch();
        if (!$u || !password_verify($password, $u['password_hash'])) {
            return null;
        }
        $token = bin2hex(random_bytes(32));
        $mode  = ($mode === 'sim') ? 'sim' : 'real';
        $exp   = (new DateTime('+12 hours'))->format('Y-m-d H:i:s');
        $pdo->prepare(
            "INSERT INTO sessions(token, user_id, team_id, role, mode, expires_at)
             VALUES (?,?,?,?,?,?)"
        )->execute([$token, $u['id'], $u['team_id'], $u['role'], $mode, $exp]);

        if ($u['team_id']) {
            $pdo->prepare("UPDATE teams SET last_login_at = NOW() WHERE id = ?")
                ->execute([$u['team_id']]);
        }
        log_activity($loginId, 'login', 'user', $u['id'], 'mode=' . $mode);

        return [
            'token'        => $token,
            'role'         => $u['role'],
            'team_id'      => $u['team_id'],
            'team_no'      => $u['team_no'],
            'display_name' => $u['display_name'],
            'mode'         => $mode,
        ];
    }

    // Returns the current session row (or null) based on the bearer token.
    public static function current()
    {
        $token = bearer_token();
        if (!$token) {
            return null;
        }
        $st = Database::pdo()->prepare(
            "SELECT * FROM sessions WHERE token = ? AND expires_at > NOW() LIMIT 1"
        );
        $st->execute([$token]);
        $s = $st->fetch();
        return $s ?: null;
    }

    // Guard: aborts with 401/403 unless authenticated (and, optionally, in an allowed role).
    public static function require_auth($roles = null)
    {
        $s = self::current();
        if (!$s) {
            json_out(['error' => 'unauthorized'], 401);
        }
        if ($roles && !in_array($s['role'], $roles, true)) {
            json_out(['error' => 'forbidden'], 403);
        }
        return $s;
    }

    public static function logout()
    {
        $token = bearer_token();
        if ($token) {
            Database::pdo()->prepare("DELETE FROM sessions WHERE token = ?")->execute([$token]);
        }
    }
}
