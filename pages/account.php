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

/* セッションに大学情報がなければDBから取得して補完 */
if ($registered === null) {
    try {
        $db   = db_connect();
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
        ', [$user_id]);
        if ($pref && pg_num_rows($pref) > 0) {
            $p = pg_fetch_assoc($pref);
            $registered = [
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
            $_SESSION['registered'] = $registered;
        }
        pg_close($db);
    } catch (RuntimeException $e) {
        /* DB接続失敗時は未登録扱いのまま */
    }
}

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

      <!-- キャンパスサマリー -->
      <div class="univ-summary">
        <div class="univ-summary-icon">
          <span class="material-icons" style="font-size:2.5rem; color:var(--color-primary);">account_balance</span>
        </div>
        <div class="univ-summary-body">
          <div class="univ-name"><?= htmlspecialchars($registered['university_name']) ?></div>
          <div class="univ-campus">
            <span class="material-icons mi-xs">place</span>
            <?= htmlspecialchars($registered['campus_name']) ?>
            <?php if (!empty($registered['campus_address'])): ?>
              <span class="text-muted" style="font-size:0.8rem; margin-left:0.4rem;">（<?= htmlspecialchars($registered['campus_address']) ?>）</span>
            <?php endif; ?>
          </div>
          <?php if (!empty($registered['lat']) && !empty($registered['lng'])): ?>
            <div class="univ-coords text-muted">
              <span class="material-icons mi-xs">gps_fixed</span>
              緯度 <?= number_format((float)$registered['lat'], 4) ?>　経度 <?= number_format((float)$registered['lng'], 4) ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- 希望条件カードグリッド -->
      <div class="pref-grid">

        <!-- 家賃上限 -->
        <div class="pref-card">
          <div class="pref-card-icon" style="background:linear-gradient(135deg,#7c3aed,#a78bfa);">
            <span class="material-icons">payments</span>
          </div>
          <div class="pref-card-body">
            <div class="pref-card-label">家賃上限</div>
            <div class="pref-card-value">
              <?php if ($registered['rent_max'] !== null): ?>
                <?= number_format($registered['rent_max'] / 10000, 0) ?><span class="pref-card-unit">万円 / 月</span>
              <?php else: ?>
                <span style="font-size:0.95rem; font-weight:500;">上限なし</span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- 優先カテゴリ -->
        <?php
          $p = $registered['priority'] ?? 'near';
          $p_grad = [
            'cheap'   => 'linear-gradient(135deg,#f59e0b,#fbbf24)',
            'near'    => 'linear-gradient(135deg,#4f46e5,#818cf8)',
            'livable' => 'linear-gradient(135deg,#0d9488,#2dd4bf)',
          ];
          $p_icon = ['cheap' => 'savings', 'near' => 'near_me', 'livable' => 'favorite'];
        ?>
        <div class="pref-card">
          <div class="pref-card-icon" style="background:<?= $p_grad[$p] ?? $p_grad['near'] ?>;">
            <span class="material-icons"><?= $p_icon[$p] ?? 'star' ?></span>
          </div>
          <div class="pref-card-body">
            <div class="pref-card-label">優先カテゴリ</div>
            <div class="pref-card-value"><?= htmlspecialchars($badge_labels[$p] ?? '近さ優先') ?></div>
          </div>
        </div>

        <!-- 検索範囲 -->
        <div class="pref-card">
          <div class="pref-card-icon" style="background:linear-gradient(135deg,#059669,#34d399);">
            <span class="material-icons">explore</span>
          </div>
          <div class="pref-card-body">
            <div class="pref-card-label">検索範囲</div>
            <div class="pref-card-value">
              <?= (int)($registered['radius'] ?? 20) ?><span class="pref-card-unit">km 以内</span>
            </div>
          </div>
        </div>

        <!-- 交通手段 -->
        <div class="pref-card">
          <div class="pref-card-icon" style="background:linear-gradient(135deg,#0284c7,#38bdf8);">
            <span class="material-icons">directions_transit</span>
          </div>
          <div class="pref-card-body">
            <div class="pref-card-label">交通手段</div>
            <div class="pref-card-value pref-card-badges">
              <?php
                $sel = (array)($registered['transport'] ?? []);
                if ($sel) {
                    foreach ($sel as $t) {
                        echo '<span class="badge badge-near">' . htmlspecialchars($transport_labels[$t] ?? $t) . '</span>';
                    }
                } else {
                    echo '<span class="text-muted" style="font-size:0.88rem;">未選択</span>';
                }
              ?>
            </div>
          </div>
        </div>

      </div>

      <div style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-top:1.25rem;">
        <a href="index.php?page=university" class="btn btn-outline">
          <span class="material-icons mi-sm">edit</span> 大学情報を変更する
        </a>
        <a href="index.php?page=home" class="btn btn-primary">
          <span class="material-icons mi-sm">search</span> エリアを探す
        </a>
      </div>

    <?php else: ?>
      <div style="text-align:center; padding:2rem 1rem;">
        <span class="material-icons" style="font-size:3rem; color:var(--color-border); display:block; margin-bottom:0.75rem;">school</span>
        <p class="text-muted mb-2">まだ大学情報が登録されていません。</p>
        <a href="index.php?page=university" class="btn btn-primary">大学情報を入力する</a>
      </div>
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
