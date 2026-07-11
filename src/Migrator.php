<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';

class Migrator
{
    /**
     * Applies pending SQL migrations and seeds initial accounts.
     * Returns an array of human-readable log lines. Throws on fatal DB errors.
     */
    public static function run()
    {
        $log = [];
        $pdo = Database::pdo();

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS iuccs_schema_migrations (
                version    VARCHAR(64) PRIMARY KEY,
                applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $applied = array_flip(
            $pdo->query("SELECT version FROM iuccs_schema_migrations")->fetchAll(PDO::FETCH_COLUMN)
        );

        $files = glob(__DIR__ . '/../db/migrations/*.sql');
        sort($files);

        foreach ($files as $file) {
            $version = basename($file);
            if (isset($applied[$version])) {
                $log[] = "skip    $version";
                continue;
            }
            $log[] = "apply   $version";
            foreach (self::splitSql(file_get_contents($file)) as $stmt) {
                if (trim($stmt) === '') {
                    continue;
                }
                $pdo->exec($stmt);
            }
            $pdo->prepare("INSERT INTO iuccs_schema_migrations(version) VALUES (?)")->execute([$version]);
        }

        $log = array_merge($log, self::seedUsers($pdo));
        $log[] = 'done';
        return $log;
    }

    private static function splitSql($sql)
    {
        $out = [];
        foreach (preg_split('/\r?\n/', $sql) as $line) {
            if (strpos(ltrim($line), '--') === 0) {
                continue;
            }
            $out[] = $line;
        }
        return explode(';', implode("\n", $out));
    }

    private static function seedUsers($pdo)
    {
        $log = [];
        $count = (int) $pdo->query("SELECT COUNT(*) FROM iuccs_users")->fetchColumn();
        if ($count > 0) {
            return ['users exist — skip seeding'];
        }
        $initPassword = config()['app']['init_password'] ?: 'changeme1234';
        $hash = password_hash($initPassword, PASSWORD_DEFAULT);

        $pdo->prepare(
            "INSERT INTO iuccs_users(team_id, login_id, password_hash, role, display_name)
             VALUES (NULL, ?, ?, 'admin', ?)"
        )->execute(['admin', $hash, '관리자']);

        $teams = $pdo->query("SELECT id, team_no FROM iuccs_teams")->fetchAll();
        foreach ($teams as $t) {
            $pdo->prepare(
                "INSERT INTO iuccs_users(team_id, login_id, password_hash, role, display_name)
                 VALUES (?, ?, ?, 'operator', ?)"
            )->execute([$t['id'], $t['team_no'], $hash, $t['team_no'] . '팀 운용자']);
        }
        $log[] = "seeded 'admin' + " . count($teams) . " team accounts (password = INIT_PASSWORD)";
        $log[] = '*** change these passwords after first login ***';
        return $log;
    }
}
