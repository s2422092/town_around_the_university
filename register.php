<?php
/* 担当者:（空欄） / この画面でやること: 新規ユーザー登録フォーム（名前・メール・パスワード）。実装時はSupabase Auth（signUp）を使い、profiles テーブルにも初期レコードを作成する */
$page_title   = '新規登録 | 大学周辺の家';
$current_page = 'register';
require 'includes/header.php';
?>

<main style="max-width: 520px; margin: 3rem auto; padding: 0 1.5rem;">

  <div style="text-align:center; margin-bottom:2rem;">
    <h1 style="font-size:1.6rem; font-weight:700; margin-bottom:0.4rem;">新規アカウント登録</h1>
    <p class="text-muted">既にアカウントをお持ちの方は <a href="login.php" style="color:var(--color-primary);">ログイン</a></p>
  </div>

  <div class="notice">
    ℹ️ このフォームは見た目のみです。送信しても登録は行われません（実装予定）。
  </div>

  <div class="card">
    <!-- action 空 = 実送信しない -->
    <form action="" method="post">

      <div class="form-group">
        <label for="name">名前</label>
        <input type="text" id="name" name="name"
               placeholder="山田 太郎" autocomplete="name">
      </div>

      <div class="form-group">
        <label for="email">メールアドレス</label>
        <input type="email" id="email" name="email"
               placeholder="example@mail.com" autocomplete="email">
      </div>

      <div class="form-group">
        <label for="password">パスワード</label>
        <input type="password" id="password" name="password"
               placeholder="8文字以上で入力" autocomplete="new-password"
               minlength="8">
        <span class="form-hint">8文字以上、英数字を組み合わせることを推奨</span>
      </div>

      <div class="form-group">
        <label for="password_confirm">パスワード（確認）</label>
        <input type="password" id="password_confirm" name="password_confirm"
               placeholder="もう一度入力" autocomplete="new-password">
      </div>

      <hr class="divider">

      <div class="form-group">
        <label>
          <input type="checkbox" name="agree" id="agree" required>
          <span style="font-weight:400;">利用規約に同意する（未作成）</span>
        </label>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%; padding:0.75rem;">
        アカウントを作成（未実装）
      </button>

    </form>
  </div>

  <p style="text-align:center; margin-top:1.5rem; font-size:0.875rem;">
    既にアカウントをお持ちの方は <a href="login.php" style="color:var(--color-primary); font-weight:600;">ログイン →</a>
  </p>

</main>

<?php require 'includes/footer.php'; ?>
