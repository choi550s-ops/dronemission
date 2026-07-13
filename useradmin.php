<?php
// 임시: 오류 원인을 화면에 표시 (진단용). 해결 후 이 파일은 삭제합니다.
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
// ============================================================================
//  임시 사용자 관리 도구 (TEMPORARY) — PHP 5.x 호환
//  사용:  https://도메인/dronemission/useradmin.php?token=(MIGRATE_TOKEN)
//  끝나면 이 파일(useradmin.php)을 반드시 삭제하세요.
// ============================================================================

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/src/Database.php';

$expected = getenv('MIGRATE_TOKEN');
if (!$expected) {
    $env = env_load(__DIR__ . '/.env');
    $expected = isset($env['MIGRATE_TOKEN']) ? $env['MIGRATE_TOKEN'] : '';
}
$token = isset($_REQUEST['token']) ? $_REQUEST['token'] : '';
if (!$expected || !hash_equals($expected, (string) $token)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "forbidden — invalid or missing token";
    exit;
}

$pdo = Database::pdo();
$msg = '';
$ok  = false;

function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    try {
        if ($action === 'reset') {
            $login = trim(isset($_POST['login_id']) ? $_POST['login_id'] : '');
            $pw    = (string) (isset($_POST['password']) ? $_POST['password'] : '');
            if ($login === '' || $pw === '') {
                $msg = '아이디와 새 비밀번호를 입력하세요.';
            } else {
                $hash = password_hash($pw, PASSWORD_DEFAULT);
                $st = $pdo->prepare("UPDATE iuccs_users SET password_hash = ? WHERE login_id = ?");
                $st->execute(array($hash, $login));
                if ($st->rowCount() > 0) { $ok = true; $msg = "'" . $login . "' 비밀번호가 변경되었습니다."; }
                else { $msg = "'" . $login . "' 계정을 찾지 못했습니다."; }
            }
        } elseif ($action === 'reset_all') {
            $pw = (string) (isset($_POST['password']) ? $_POST['password'] : '');
            if ($pw === '') {
                $msg = '새 비밀번호를 입력하세요.';
            } else {
                $hash = password_hash($pw, PASSWORD_DEFAULT);
                $n = $pdo->exec("UPDATE iuccs_users SET password_hash = " . $pdo->quote($hash));
                $ok = true; $msg = "전체 " . $n . "개 계정의 비밀번호가 변경되었습니다.";
            }
        } elseif ($action === 'create') {
            $login = trim(isset($_POST['login_id']) ? $_POST['login_id'] : '');
            $pw    = (string) (isset($_POST['password']) ? $_POST['password'] : '');
            $role  = isset($_POST['role']) ? $_POST['role'] : 'operator';
            $allowed = array('admin', 'leader', 'operator', 'fire_coord', 'observer');
            if (!in_array($role, $allowed, true)) { $role = 'operator'; }
            if ($login === '' || $pw === '') {
                $msg = '아이디와 비밀번호를 입력하세요.';
            } else {
                $hash = password_hash($pw, PASSWORD_DEFAULT);
                $st = $pdo->prepare(
                    "INSERT INTO iuccs_users(login_id, password_hash, role, display_name)
                     VALUES (?,?,?,?)
                     ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role = VALUES(role)"
                );
                $st->execute(array($login, $hash, $role, $login));
                $ok = true; $msg = "'" . $login . "' 계정을 생성/갱신했습니다 (역할: " . $role . ").";
            }
        } elseif ($action === 'delete') {
            $login = trim(isset($_POST['login_id']) ? $_POST['login_id'] : '');
            if ($login === '') {
                $msg = '삭제할 아이디를 선택하세요.';
            } else {
                $chk = $pdo->prepare("SELECT id, role FROM iuccs_users WHERE login_id = ?");
                $chk->execute(array($login));
                $u = $chk->fetch();
                if (!$u) {
                    $msg = "'" . $login . "' 계정을 찾지 못했습니다.";
                } elseif ($u['role'] === 'admin' && (int) $pdo->query("SELECT COUNT(*) FROM iuccs_users WHERE role = 'admin'")->fetchColumn() <= 1) {
                    $msg = '마지막 관리자 계정은 삭제할 수 없습니다.';
                } else {
                    $pdo->prepare("DELETE FROM iuccs_users WHERE login_id = ?")->execute(array($login));
                    $ok = true; $msg = "'" . $login . "' 계정이 삭제되었습니다.";
                }
            }
        }
    } catch (Exception $e) {
        $msg = '오류: ' . $e->getMessage();
    }
}

$users = $pdo->query("SELECT id, login_id, role, team_id, display_name FROM iuccs_users ORDER BY id")->fetchAll();
$T = h($token);
$roleKo = array('admin'=>'관리자','leader'=>'팀장','operator'=>'운용자','fire_coord'=>'화력협조','observer'=>'참관');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>IUCCS 사용자 관리 (임시)</title>
<style>
  body{font-family:-apple-system,"Malgun Gothic","Noto Sans KR",sans-serif;background:#0f1a2b;color:#dce8f5;max-width:620px;margin:0 auto;padding:18px 16px 60px}
  h1{font-size:19px;color:#fff;margin:0 0 4px}
  .warn{background:#3a2a17;border:1px solid #7a5a2a;color:#f3c882;padding:10px 12px;border-radius:9px;font-size:13px;margin:12px 0;line-height:1.5}
  .ok{background:#183a2a;border:1px solid #2e7d46;color:#8fe0ad}
  .card{background:#152439;border:1px solid #263a52;border-radius:12px;padding:14px;margin:12px 0}
  .card h2{font-size:14px;color:#fff;margin:0 0 10px}
  label{display:block;font-size:12px;color:#9fb2c8;margin:8px 0 4px}
  input,select{width:100%;box-sizing:border-box;background:#0b1626;border:1px solid #26384f;border-radius:8px;color:#eaf2fc;padding:10px;font-size:15px}
  button{width:100%;margin-top:12px;border:none;border-radius:9px;padding:12px;font-size:15px;font-weight:700;background:linear-gradient(180deg,#e8a33d,#c9871b);color:#1a1206;cursor:pointer}
  button.sub{background:#243248;color:#cfe0f0}
  table{width:100%;border-collapse:collapse;font-size:13px}
  th,td{text-align:left;padding:7px 6px;border-bottom:1px solid #223550}
  th{color:#8fa4bd}
  code{background:#0b1626;padding:1px 6px;border-radius:5px}
  .msg{padding:10px 12px;border-radius:9px;font-size:13.5px;margin:12px 0}
</style>
</head>
<body>
  <h1>IUCCS 사용자 관리</h1>
  <div style="color:#8fa4bd;font-size:12px">임시 관리 도구</div>

  <div class="warn">⚠ <b>임시 도구입니다.</b> 설정이 끝나면 서버에서 <code>useradmin.php</code> 파일을 <b>반드시 삭제</b>하세요.</div>

  <?php if ($msg !== ''): ?>
    <div class="msg <?php echo $ok ? 'ok' : 'warn'; ?>"><?php echo h($msg); ?></div>
  <?php endif; ?>

  <div class="card">
    <h2>현재 계정</h2>
    <table>
      <tr><th>ID</th><th>아이디</th><th>역할</th><th>팀</th></tr>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?php echo h($u['id']); ?></td>
          <td><code><?php echo h($u['login_id']); ?></code></td>
          <td><?php echo h(isset($roleKo[$u['role']]) ? $roleKo[$u['role']] : $u['role']); ?></td>
          <td><?php echo h(isset($u['team_id']) ? $u['team_id'] : '-'); ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="card">
    <h2>비밀번호 재설정 (한 계정)</h2>
    <form method="post">
      <input type="hidden" name="token" value="<?php echo $T; ?>">
      <input type="hidden" name="action" value="reset">
      <label>아이디</label>
      <select name="login_id">
        <?php foreach ($users as $u): ?>
          <option value="<?php echo h($u['login_id']); ?>"><?php echo h($u['login_id']); ?> (<?php echo h(isset($roleKo[$u['role']]) ? $roleKo[$u['role']] : $u['role']); ?>)</option>
        <?php endforeach; ?>
      </select>
      <label>새 비밀번호</label>
      <input type="text" name="password" placeholder="예: 1234" autocomplete="off">
      <button type="submit">이 계정 비밀번호 변경</button>
    </form>
  </div>

  <div class="card">
    <h2>계정 삭제</h2>
    <form method="post" onsubmit="return confirm('이 계정을 삭제하시겠습니까?\n되돌릴 수 없습니다.');">
      <input type="hidden" name="token" value="<?php echo $T; ?>">
      <input type="hidden" name="action" value="delete">
      <label>아이디</label>
      <select name="login_id">
        <?php foreach ($users as $u): ?>
          <option value="<?php echo h($u['login_id']); ?>"><?php echo h($u['login_id']); ?> (<?php echo h(isset($roleKo[$u['role']]) ? $roleKo[$u['role']] : $u['role']); ?>)</option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="sub" style="background:#3a1c1c;color:#ff8579">이 계정 삭제</button>
    </form>
  </div>

  <div class="card">
    <h2>전체 계정 비밀번호 일괄 변경</h2>
    <form method="post" onsubmit="return confirm('모든 계정의 비밀번호를 바꿉니다. 진행할까요?');">
      <input type="hidden" name="token" value="<?php echo $T; ?>">
      <input type="hidden" name="action" value="reset_all">
      <label>새 비밀번호 (admin·01·03·05 전부 적용)</label>
      <input type="text" name="password" placeholder="예: 1234" autocomplete="off">
      <button type="submit" class="sub">전체 변경</button>
    </form>
  </div>

  <div class="card">
    <h2>계정 추가 / 갱신</h2>
    <form method="post">
      <input type="hidden" name="token" value="<?php echo $T; ?>">
      <input type="hidden" name="action" value="create">
      <label>아이디 (팀번호 또는 이름)</label>
      <input type="text" name="login_id" placeholder="예: 07 또는 admin2" autocomplete="off">
      <label>비밀번호</label>
      <input type="text" name="password" autocomplete="off">
      <label>역할</label>
      <select name="role">
        <option value="operator">운용자</option>
        <option value="leader">팀장</option>
        <option value="fire_coord">화력협조</option>
        <option value="observer">참관</option>
        <option value="admin">관리자</option>
      </select>
      <button type="submit" class="sub">계정 생성/갱신</button>
    </form>
  </div>

  <div class="warn">완료 후 <code>useradmin.php</code> 삭제를 잊지 마세요.</div>
</body>
</html>
