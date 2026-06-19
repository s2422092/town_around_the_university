<?php
/**
 * 共通ヘッダー・ナビゲーション
 * 各ページで $current_page を定義してからインクルードすること。
 * 例: $current_page = 'home';
 */
$pages = [
    'index'      => ['url' => 'index.php',      'label' => 'ダッシュボード'],
    'home'       => ['url' => 'home.php',        'label' => 'ホーム'],
    'university' => ['url' => 'university.php',  'label' => '大学情報入力'],
    'account'    => ['url' => 'account.php',     'label' => 'アカウント'],
    'login'      => ['url' => 'login.php',       'label' => 'ログイン'],
    'register'   => ['url' => 'register.php',    'label' => '新規登録'],
];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_title ?? '大学周辺の家') ?></title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header>
  <div class="header-inner">
    <a class="site-title" href="index.php">大学周辺の<span>家</span></a>

    <button class="nav-toggle" id="navToggle" aria-label="メニューを開く">
      <span></span><span></span><span></span>
    </button>

    <nav id="globalNav">
      <?php foreach ($pages as $key => $page): ?>
        <a href="<?= $page['url'] ?>"
           class="<?= ($current_page ?? '') === $key ? 'active' : '' ?>">
          <?= $page['label'] ?>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>
</header>

<script>
  document.getElementById('navToggle').addEventListener('click', function () {
    document.getElementById('globalNav').classList.toggle('open');
  });
</script>
