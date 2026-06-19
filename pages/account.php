<?php
/* 担当者:（空欄） / この画面でやること: ログイン済みユーザーのアカウント情報（名前・登録大学・優先カテゴリ・通知設定など）を表示・編集するページ。実装時はSupabase Authのセッションから情報取得する */
$page_title   = 'アカウント設定 | 大学周辺の家';
$current_page = 'account';
require __DIR__ . '/../includes/header.php';
?>

<main>

  <div class="page-hero">
    <h1>アカウント設定</h1>
    <p>プロフィールと登録情報を管理します。</p>
  </div>

  <div class="notice">
    ℹ️ このページは現在プレースホルダです。実装時はログインセッションから情報を取得します。
  </div>

  <!-- プロフィール -->
  <div class="card mb-2">
    <h2 class="section-title">プロフィール</h2>
    <form action="" method="post">
      <div class="form-group">
        <label for="name">名前</label>
        <input type="text" id="name" name="name" placeholder="山田 太郎">
      </div>
      <div class="form-group">
        <label for="email">メールアドレス</label>
        <input type="email" id="email" name="email" placeholder="example@mail.com"
               disabled style="background:#f1f5f9; cursor:not-allowed;">
        <span class="form-hint">メールアドレスの変更はログイン画面から行います</span>
      </div>
      <button type="submit" class="btn btn-primary">変更を保存（未実装）</button>
    </form>
  </div>

  <!-- 登録大学情報 -->
  <div class="card mb-2">
    <h2 class="section-title">登録大学・キャンパス</h2>
    <p class="text-muted mb-2">現在の設定：未登録</p>
    <a class="btn btn-outline" href="index.php?page=university">大学情報を入力・変更する</a>
  </div>

  <!-- 希望条件 -->
  <div class="card mb-2">
    <h2 class="section-title">希望条件</h2>
    <form action="" method="post">
      <div class="form-group">
        <label for="rent_max_acc">家賃上限</label>
        <select id="rent_max_acc" name="rent_max">
          <option value="">未設定</option>
          <option value="30000">3 万円</option>
          <option value="40000">4 万円</option>
          <option value="50000">5 万円</option>
          <option value="60000">6 万円</option>
          <option value="70000">7 万円</option>
          <option value="80000">8 万円</option>
          <option value="100000">10 万円</option>
        </select>
      </div>
      <div class="form-group">
        <label>優先カテゴリ</label>
        <div class="radio-group">
          <label><input type="radio" name="priority_acc" value="near"> 近さ優先</label>
          <label><input type="radio" name="priority_acc" value="cheap"> 安さ優先</label>
          <label><input type="radio" name="priority_acc" value="livable"> 住みやすさ優先</label>
        </div>
      </div>
      <button type="submit" class="btn btn-primary">条件を保存（未実装）</button>
    </form>
  </div>

  <!-- アカウント操作 -->
  <div class="card">
    <h2 class="section-title">アカウント操作</h2>
    <div style="display:flex; flex-direction:column; gap:0.75rem; align-items:flex-start;">
      <a class="btn btn-outline" href="index.php?page=login">ログアウト（未実装）</a>
      <button class="btn" style="background:#fee2e2; color:#991b1b; border:1px solid #fca5a5;"
              onclick="return confirm('本当にアカウントを削除しますか？')">
        アカウントを削除（未実装）
      </button>
    </div>
  </div>

</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
