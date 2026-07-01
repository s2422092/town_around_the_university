<?php
session_start();

$page_title   = 'アカウント設定 | 大学周辺の家';
$current_page = 'account';

$user_id    = $_SESSION['user_id']  ?? null;
$username   = $_SESSION['username'] ?? null;
$registered = $_SESSION['registered'] ?? null;

if ($user_id === null) {
    header('Location: index.php?page=login');
    exit;
}

require __DIR__ . '/../db/connection.php';

$message = '';
$error   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    $current  = trim($_POST['current_password'] ?? '');
    $new_pass = trim($_POST['new_password'] ?? '');
    $confirm  = trim($_POST['confirm_password'] ?? '');

    if ($current === '' || $new_pass === '' || $confirm === '') {
        $message = 'すべての項目を入力してください。';
        $error   = true;
    } elseif ($new_pass !== $confirm) {
        $message = '新しいパスワードと確認用パスワードが一致しません。';
        $error   = true;
    } elseif (mb_strlen($new_pass) < 6) {
        $message = 'パスワードは6文字以上で設定してください。';
        $error   = true;
    } else {
        try {
            $db  = db_connect();
            $res = pg_query_params($db, 'SELECT upass FROM users WHERE user_id = $1', [$user_id]);
            if ($res && pg_num_rows($res) === 1) {
                $row = pg_fetch_assoc($res);
                if (!password_verify($current, $row['upass'])) {
                    $message = '現在のパスワードが正しくありません。';
                    $error   = true;
                } else {
                    $hash = password_hash($new_pass, PASSWORD_DEFAULT);
                    pg_query_params($db, 'UPDATE users SET upass = $1 WHERE user_id = $2', [$hash, $user_id]);
                    $message = 'パスワードを変更しました。';
                }
            }
            pg_close($db);
        } catch (RuntimeException $e) {
            $message = 'データベースに接続できませんでした。';
            $error   = true;
        }
    }
}

$badge_labels = [
    'cheap'   => '安さ優先',
    'near'    => '近さ優先',
    'livable' => '住みやすさ優先',
];
$transport_labels = [
    'train' => '電車', 'bus' => 'バス', 'bike' => '自転車',
    'walk'  => '徒歩', 'taxi' => 'タクシー',
];

require __DIR__ . '/../includes/header.php';
?>

<main>

  <div class="page-hero">
    <h1>アカウント設定</h1>
    <p>ログイン情報や登録内容を確認・変更できます。</p>
  </div>

  <?php if ($message !== ''): ?>
    <div class="notice" style="<?= $error
        ? 'background:#fee2e2;border-color:#fca5a5;color:#991b1b;'
        : 'background:#dcfce7;border-color:#86efac;color:#166534;' ?>">
      <?= htmlspecialchars($message) ?>
    </div>
  <?php endif; ?>

  <!-- アカウント情報 -->
  <div class="card mb-2">
    <h2 class="section-title">アカウント情報</h2>
    <p style="font-size:0.95rem; margin-bottom:1rem;">
      ログイン中のユーザー名：<strong><?= htmlspecialchars($username) ?></strong>
    </p>

    <form action="index.php?page=account" method="POST">
      <input type="hidden" name="action" value="change_password">
      <div class="form-section-title" style="margin-bottom:1rem;">パスワード変更</div>
      <div class="form-group">
        <label for="current_password">現在のパスワード</label>
        <input type="password" id="current_password" name="current_password"
               autocomplete="current-password" required>
      </div>
      <div class="form-group">
        <label for="new_password">新しいパスワード</label>
        <input type="password" id="new_password" name="new_password"
               autocomplete="new-password" required minlength="6">
        <span class="form-hint">6文字以上で設定してください。</span>
      </div>
      <div class="form-group">
        <label for="confirm_password">新しいパスワード（確認）</label>
        <input type="password" id="confirm_password" name="confirm_password"
               autocomplete="new-password" required minlength="6">
      </div>
      <button type="submit" class="btn btn-primary">パスワードを変更する</button>
    </form>
  </div>

  <!-- 登録済み大学情報 -->
  <div class="card mb-2">
    <h2 class="section-title">登録済みの大学・希望条件</h2>

    <?php if ($registered !== null): ?>
      <div class="feature-grid" style="margin-bottom:1rem;">
        <div class="feature-card">
          <div class="icon"><span class="material-icons mi-lg">school</span></div>
          <h3>大学</h3>
          <p><?= htmlspecialchars($registered['university_name']) ?></p>
        </div>
        <div class="feature-card">
          <div class="icon"><span class="material-icons mi-lg">account_balance</span></div>
          <h3>キャンパス</h3>
          <p><?= htmlspecialchars($registered['campus_name']) ?></p>
        </div>
        <div class="feature-card">
          <div class="icon"><span class="material-icons mi-lg">payments</span></div>
          <h3>家賃上限</h3>
          <p><?= $registered['rent_max'] !== null
              ? number_format($registered['rent_max'] / 10000, 0) . ' 万円'
              : '上限なし' ?></p>
        </div>
        <div class="feature-card">
          <div class="icon"><span class="material-icons mi-lg">star</span></div>
          <h3>優先カテゴリ</h3>
          <p><?= htmlspecialchars($badge_labels[$registered['priority']] ?? '近さ優先') ?></p>
        </div>
        <div class="feature-card">
          <div class="icon"><span class="material-icons mi-lg">place</span></div>
          <h3>検索範囲</h3>
          <p><?= (int)($registered['radius'] ?? 20) ?> km 以内</p>
        </div>
        <div class="feature-card">
          <div class="icon"><span class="material-icons mi-lg">train</span></div>
          <h3>交通手段</h3>
          <p><?php
            $sel = array_map(fn($t) => $transport_labels[$t] ?? $t, (array)($registered['transport'] ?? []));
            echo $sel ? htmlspecialchars(implode('・', $sel)) : '未選択';
          ?></p>
        </div>
      </div>
      <a href="index.php?page=university" class="btn btn-outline">大学情報を変更する</a>
    <?php else: ?>
      <p class="text-muted mb-2">まだ大学情報が登録されていません。</p>
      <a href="index.php?page=university" class="btn btn-primary">大学情報を入力する</a>
    <?php endif; ?>
  </div>

  <!-- ログアウト -->
  <div class="card" style="text-align:center; padding:2rem;">
    <p class="text-muted mb-2">ログアウトするとセッション情報がすべてクリアされます。</p>
    <a href="index.php?page=logout" class="btn btn-outline"
       style="border-color:#dc2626; color:#dc2626;"
       onclick="return confirm('ログアウトしますか？')">ログアウト</a>
  </div>

</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
