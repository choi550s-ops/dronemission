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
               FROM iuccs_users u
               LEFT JOIN iuccs_teams t ON t.id = u.team_id
              WHERE u.login_id = ? LIMIT 1"
        );
        $st->execute(array($loginId));
        $u = $st->fetch();
        if (!$u || !password_verify($password, $u['password_hash'])) {
            return null;
        }
        $token = random_token(32);
        $mode  = ($mode === 'sim') ? 'sim' : 'real';
        $exp   = date('Y-m-d H:i:s', time() + 12 * 3600);
        $pdo->prepare(
            "INSERT INTO iuccs_sessions(token, user_id, team_id, role, mode, expires_at)
             VALUES (?,?,?,?,?,?)"
        )->execute(array($token, $u['id'], $u['team_id'], $u['role'], $mode, $exp));

        if ($u['team_id']) {
            $pdo->prepare("UPDATE iuccs_teams SET last_login_at = NOW() WHERE id = ?")
                ->execute(array($u['team_id']));
        }
        log_activity($loginId, 'login', 'user', $u['id'], 'mode=' . $mode);

        return array(
            'token'        => $token,
            'role'         => $u['role'],
            'team_id'      => $u['team_id'],
            'team_no'      => $u['team_no'],
            'display_name' => $u['display_name'],
            'mode'         => $mode,
        );
    }

    public static function current()
    {
        $token = bearer_token();
        if (!$token) {
            return null;
        }
        $st = Database::pdo()->prepare(
            "SELECT * FROM iuccs_sessions WHERE token = ? AND expires_at > NOW() LIMIT 1"
        );
        $st->execute(array($token));
        $s = $st->fetch();
        return $s ? $s : null;
    }

    public static function require_auth($roles = null)
    {
        $s = self::current();
        if (!$s) {
            json_out(array('error' => 'unauthorized'), 401);
        }
        if ($roles && !in_array($s['role'], $roles, true)) {
            json_out(array('error' => 'forbidden'), 403);
        }
        return $s;
    }

    public static function logout()
    {
        $token = bearer_token();
        if ($token) {
            Database::pdo()->prepare("DELETE FROM iuccs_sessions WHERE token = ?")->execute(array($token));
        }
    }
}
