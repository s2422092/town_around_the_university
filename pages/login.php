<?php
/* 担当者:（空欄） / この画面でやること: メールアドレス＋パスワードでのログインフォーム。実装時はSupabase Auth（signInWithPassword）を使う。認証後はhome.phpへリダイレクト */
$page_title   = 'ログイン | 大学周辺の家';
$current_page = 'login';
require __DIR__ . '/../includes/header.php';
?>

<main style="max-width: 480px; margin: 3rem auto; padding: 0 1.5rem;">

  <div style="text-align:center; margin-bottom:2rem;">
    <h1 style="font-size:1.6rem; font-weight:700; margin-bottom:0.4rem;">ログイン</h1>
    <p class="text-muted">アカウントをお持ちでない方は <a href="index.php?page=register" style="color:var(--color-primary);">新規登録</a></p>
  </div>

  <div class="notice">
    ℹ️ このフォームは見た目のみです。送信しても認証は行われません（実装予定）。
  </div>

  <div class="card">
    <!-- action 空 = 実送信しない -->
    <form action="" method="post">

      <div class="form-group">
        <label for="email">メールアドレス</label>
        <input type="email" id="email" name="email"
               placeholder="example@mail.com" autocomplete="email">
      </div>

      <div class="form-group">
        <label for="password">パスワード</label>
        <input type="password" id="password" name="password"
               placeholder="パスワードを入力" autocomplete="current-password">
      </div>

      <div style="text-align:right; margin-bottom:1.25rem;">
        <a href="#" style="font-size:0.85rem; color:var(--color-primary);">パスワードを忘れた方（未実装）</a>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%; padding:0.75rem;">
        ログイン（未実装）
      </button>

    </form>

    <hr class="divider">

    <p style="text-align:center; font-size:0.875rem; color:var(--color-text-muted);">または</p>

    <!-- ソーシャルログインプレースホルダ -->
    <div style="display:flex; flex-direction:column; gap:0.6rem; margin-top:1rem;">
      <button class="btn btn-outline" style="width:100%;" disabled>
        Google でログイン（未実装）
      </button>
    </div>
  </div>

  <p style="text-align:center; margin-top:1.5rem; font-size:0.875rem;">
    アカウントをお持ちでない方は <a href="index.php?page=register" style="color:var(--color-primary); font-weight:600;">新規登録 →</a>
  </p>

</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
