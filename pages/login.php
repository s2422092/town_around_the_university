<?php
session_start();

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require __DIR__ . '/../db/connection.php';

$page_title   = 'ログイン | 大学周辺の家';
$current_page = 'login';

$message = '';
$error   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uname = trim($_POST['username'] ?? '');
    $upass = trim($_POST['password'] ?? '');

    if ($uname === '' || $upass === '') {
        $message = 'ユーザー名またはパスワードを入力してください。';
        $error   = true;
    } else {
        try {
            $db  = db_connect();
            $res = pg_query_params($db, 'SELECT user_id, upass FROM users WHERE uname = $1', [$uname]);
            if ($res && pg_num_rows($res) === 1) {
                $row = pg_fetch_assoc($res);
                if (password_verify($upass, $row['upass'])) {
                    $uid = (int)$row['user_id'];
                    $_SESSION['user_id']  = $uid;
                    $_SESSION['username'] = $uname;

                    /* ログイン時に保存済み大学情報をセッションに復元 */
                    $pref = pg_query_params($db, '
                        SELECT up.rent_max, up.priority, up.transport, up.radius,
                               u.id AS university_id, u.name AS university_name,
                               c.id AS campus_id, c.name AS campus_name,
                               c.address AS campus_address,
                               c.lat::float AS lat, c.lng::float AS lng
                        FROM user_preferences up
                        JOIN universities u ON u.id = up.university_id
                        JOIN campuses c     ON c.id = up.campus_id
                        WHERE up.user_id = $1
                    ', [$uid]);
                    if ($pref && pg_num_rows($pref) > 0) {
                        $p = pg_fetch_assoc($pref);
                        $_SESSION['registered'] = [
                            'university_id'   => (int)$p['university_id'],
                            'university_name' => $p['university_name'],
                            'campus_id'       => (int)$p['campus_id'],
                            'campus_name'     => $p['campus_name'],
                            'campus_address'  => $p['campus_address'],
                            'lat'             => $p['lat'],
                            'lng'             => $p['lng'],
                            'rent_max'        => $p['rent_max'] !== null ? (int)$p['rent_max'] : null,
                            'priority'        => $p['priority'] ?? 'near',
                            'transport'       => json_decode($p['transport'] ?? '[]', true) ?? [],
                            'radius'          => (int)($p['radius'] ?? 20),
                        ];
                    }

                    pg_close($db);
                    header('Location: index.php');
                    exit;
                }
            }
            pg_close($db);
            $message = 'ユーザー名またはパスワードが正しくありません。';
            $error   = true;
        } catch (RuntimeException $e) {
            $message = 'データベースに接続できませんでした。';
            $error   = true;
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>

<main>
  <div style="max-width:420px; margin:0 auto;">
    <div class="page-hero">
      <h1>ログイン</h1>
      <p>アカウントにサインインしてください。</p>
    </div>

    <?php if ($message !== ''): ?>
      <div class="notice" style="<?= $error
          ? 'background:#fee2e2;border-color:#fca5a5;color:#991b1b;'
          : 'background:#dcfce7;border-color:#86efac;color:#166534;' ?>">
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <div class="card">
      <form action="index.php?page=login" method="POST">
        <div class="form-group">
          <label for="username">ユーザー名</label>
          <input type="text" id="username" name="username"
                 autocomplete="username" required
                 value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="password">パスワード</label>
          <input type="password" id="password" name="password"
                 autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%; padding:0.75rem;">
          ログイン
        </button>
      </form>
    </div>

    <p style="text-align:center; margin-top:1rem; font-size:0.9rem; color:var(--color-text-muted);">
      アカウントをお持ちでないですか？
      <a href="index.php?page=register" style="color:var(--color-primary);">新規登録はこちら</a>
    </p>
  </div>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
