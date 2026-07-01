<?php
session_start();

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require __DIR__ . '/../db/connection.php';

$page_title   = '新規登録 | 大学周辺の家';
$current_page = 'register';

$message = '';
$error   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uname = trim($_POST['uname'] ?? '');
    $upass = trim($_POST['upass'] ?? '');

    if ($uname === '' || $upass === '') {
        $message = 'ユーザー名とパスワードを入力してください。';
        $error   = true;
    } elseif (mb_strlen($upass) < 6) {
        $message = 'パスワードは6文字以上で設定してください。';
        $error   = true;
    } else {
        try {
            $db  = db_connect();
            $res = pg_query_params($db, 'SELECT 1 FROM users WHERE uname = $1', [$uname]);
            if ($res && pg_num_rows($res) > 0) {
                $message = 'このユーザー名は既に使われています。別の名前をお試しください。';
                $error   = true;
            } else {
                $hash = password_hash($upass, PASSWORD_DEFAULT);
                $ins  = pg_query_params($db, 'INSERT INTO users (uname, upass) VALUES ($1, $2)', [$uname, $hash]);
                if ($ins) {
                    pg_close($db);
                    header('Location: index.php?page=login&registered=1');
                    exit;
                }
                $message = 'ユーザー登録に失敗しました。';
                $error   = true;
            }
            pg_close($db);
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
      <h1>新規登録</h1>
      <p>アカウントを作成して、エリア検索を始めましょう。</p>
    </div>

    <?php if (!$error && isset($_GET['registered'])): ?>
      <div class="notice" style="background:#dcfce7;border-color:#86efac;color:#166534;">
        登録が完了しました！ログインしてください。
      </div>
    <?php endif; ?>

    <?php if ($message !== ''): ?>
      <div class="notice" style="<?= $error
          ? 'background:#fee2e2;border-color:#fca5a5;color:#991b1b;'
          : 'background:#dcfce7;border-color:#86efac;color:#166534;' ?>">
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <div class="card">
      <form action="index.php?page=register" method="POST">
        <div class="form-group">
          <label for="uname">ユーザー名</label>
          <input type="text" id="uname" name="uname"
                 autocomplete="username" required
                 value="<?= htmlspecialchars($_POST['uname'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="upass">パスワード</label>
          <input type="password" id="upass" name="upass"
                 autocomplete="new-password" required minlength="6">
          <span class="form-hint">6文字以上で設定してください。</span>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%; padding:0.75rem;">
          アカウントを作成
        </button>
      </form>
    </div>

    <p style="text-align:center; margin-top:1rem; font-size:0.9rem; color:var(--color-text-muted);">
      すでにアカウントをお持ちですか？
      <a href="index.php?page=login" style="color:var(--color-primary);">ログインはこちら</a>
    </p>
  </div>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
