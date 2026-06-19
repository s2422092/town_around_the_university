<?php
/* 担当者:（空欄） / この画面でやること: サービスのトップページ。概要説明・使い方ステップ・主要機能の紹介を行い、各画面への導線を置く */
$page_title   = 'ダッシュボード | 大学周辺の家';
$current_page = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>

<main>

  <!-- ヒーローバナー -->
  <section class="hero-section">
    <h1>大学周辺で、理想の街を見つけよう</h1>
    <p>通学時間・家賃・住みやすさで、あなたにぴったりのエリアを比較・発見できます。</p>
    <div class="hero-actions">
      <a class="btn btn-accent" href="index.php?page=register">無料で始める</a>
      <a class="btn btn-outline" style="border-color:#fff;color:#fff;" href="index.php?page=home">エリアを見る</a>
    </div>
  </section>

  <!-- 主要機能紹介 -->
  <section class="mb-2">
    <h2 class="section-title">このサービスでできること</h2>
    <div class="feature-grid">
      <div class="feature-card">
        <div class="icon">🎓</div>
        <h3>大学・キャンパス登録</h3>
        <p>通学元のキャンパスを登録して、距離・時間を自動計算</p>
      </div>
      <div class="feature-card">
        <div class="icon">📍</div>
        <h3>距離で絞り込み</h3>
        <p>直線距離 10 / 20 / 30 km の半径でエリアを絞れます</p>
      </div>
      <div class="feature-card">
        <div class="icon">🚃</div>
        <h3>交通手段を選択</h3>
        <p>電車・バス・自転車・徒歩・タクシーの所要時間を比較</p>
      </div>
      <div class="feature-card">
        <div class="icon">💴</div>
        <h3>家賃相場で比較</h3>
        <p>エリアごとの家賃相場を表示。希望家賃でフィルタも可能</p>
      </div>
      <div class="feature-card">
        <div class="icon">🏪</div>
        <h3>住みやすさスコア</h3>
        <p>コンビニ・公園・交番などの周辺施設から算出したスコア</p>
      </div>
      <div class="feature-card">
        <div class="icon">🔗</div>
        <h3>物件ポータルへ誘導</h3>
        <p>SUUMO・HOME'S などへのリンクをエリアごとに自動生成</p>
      </div>
    </div>
  </section>

  <!-- 使い方ステップ -->
  <section class="steps-section">
    <h2 class="section-title">かんたん 3 ステップ</h2>
    <div class="steps">
      <div class="step">
        <div class="step-num">1</div>
        <strong>大学を登録</strong>
        <p>通うキャンパスと希望条件を入力</p>
      </div>
      <div class="step">
        <div class="step-num">2</div>
        <strong>エリアを比較</strong>
        <p>安さ・近さ・住みやすさでランキング表示</p>
      </div>
      <div class="step">
        <div class="step-num">3</div>
        <strong>物件を探す</strong>
        <p>気に入ったエリアから物件ポータルへ</p>
      </div>
    </div>
  </section>

  <!-- CTAカード -->
  <div class="card" style="text-align:center; padding: 2rem;">
    <h2 style="margin-bottom:0.5rem;">まずは大学情報を入力しましょう</h2>
    <p class="text-muted mb-2">キャンパスの場所を登録すると、エリア比較が使えるようになります。</p>
    <a class="btn btn-primary" href="index.php?page=university">大学情報を入力する →</a>
  </div>

</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
