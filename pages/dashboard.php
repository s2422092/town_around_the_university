<?php
/* 担当者:（空欄） / この画面でやること: サービスのトップページ。大学未登録時は概要説明、登録済みなら登録キャンパスと周辺エリアの要約を表示する。 */
session_start();

$page_title   = 'ダッシュボード | 大学周辺の家';
$current_page = 'dashboard';

/* 登録済みの大学情報（university.php で保存） */
$registered = $_SESSION['registered'] ?? null;
$top_areas  = [];
$db_error   = false;

if ($registered !== null && $registered['lat'] !== null && $registered['lng'] !== null) {
    require __DIR__ . '/../db/connection.php';
    require __DIR__ . '/../includes/area_query.php';
    try {
        $db = db_connect();
        $top_areas = ranked_areas(
            $db,
            (float) $registered['lat'],
            (float) $registered['lng'],
            (int) ($registered['radius'] ?? 20),
            $registered['priority'] ?? 'near',
            3
        );
        pg_close($db);
    } catch (RuntimeException $e) {
        $db_error = true;
    }
}

$badge_labels = [
    'cheap'   => ['label' => '安さ',       'class' => 'badge-cheap'],
    'near'    => ['label' => '近さ',       'class' => 'badge-near'],
    'livable' => ['label' => '住みやすさ', 'class' => 'badge-livable'],
];

require __DIR__ . '/../includes/header.php';
?>

<main>

<?php if ($registered !== null): ?>

  <!-- ===== 登録済み：パーソナライズ表示 ===== -->

  <?php if (isset($_GET['registered'])): ?>
    <div class="notice" style="background:#dcfce7;border-color:#86efac;color:#166534;">
      ✅ 「<?= htmlspecialchars($registered['university_name']) ?>
      <?= htmlspecialchars($registered['campus_name']) ?>」を登録しました。
      <?php if (isset($_GET['geo']) && $_GET['geo'] === '0'): ?>
        <br>※ 住所から位置情報を取得できなかったため、エリア候補は表示されません。住所を見直して再登録してください。
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- 登録キャンパスの概要 -->
  <section class="hero-section">
    <h1><?= htmlspecialchars($registered['university_name']) ?> 周辺のエリアを探す</h1>
    <p>
      📍 <?= htmlspecialchars($registered['campus_name']) ?>
      （<?= htmlspecialchars($registered['campus_address']) ?>）
    </p>
    <div class="hero-actions">
      <a class="btn btn-accent" href="index.php?page=home">エリア候補を一覧で見る</a>
      <a class="btn btn-outline" style="border-color:#fff;color:#fff;" href="index.php?page=university">登録内容を変更</a>
    </div>
  </section>

  <!-- 登録した希望条件 -->
  <section class="mb-2">
    <h2 class="section-title">登録した希望条件</h2>
    <div class="feature-grid">
      <div class="feature-card">
        <div class="icon">💴</div>
        <h3>家賃上限</h3>
        <p><?= $registered['rent_max'] !== null
            ? number_format($registered['rent_max'] / 10000, 0) . ' 万円'
            : '上限なし' ?></p>
      </div>
      <div class="feature-card">
        <div class="icon">⭐</div>
        <h3>優先カテゴリ</h3>
        <p><?= htmlspecialchars($badge_labels[$registered['priority']]['label'] ?? '近さ') ?>優先</p>
      </div>
      <div class="feature-card">
        <div class="icon">📍</div>
        <h3>検索範囲</h3>
        <p><?= (int) ($registered['radius'] ?? 20) ?> km 以内</p>
      </div>
      <div class="feature-card">
        <div class="icon">🚃</div>
        <h3>交通手段</h3>
        <p><?php
          $tlabels = ['train' => '電車', 'bus' => 'バス', 'bike' => '自転車', 'walk' => '徒歩', 'taxi' => 'タクシー'];
          $selected = array_map(fn($t) => $tlabels[$t] ?? $t, (array) ($registered['transport'] ?? []));
          echo $selected ? htmlspecialchars(implode('・', $selected)) : '未選択';
        ?></p>
      </div>
    </div>
  </section>

  <!-- おすすめエリア（上位3件） -->
  <section class="mb-2">
    <h2 class="section-title">あなたへのおすすめエリア</h2>

    <?php if ($db_error): ?>
      <div class="notice" style="background:#fee2e2;border-color:#fca5a5;color:#991b1b;">
        データベースに接続できませんでした。.env の設定を確認してください。
      </div>
    <?php elseif (empty($top_areas)): ?>
      <div class="notice">
        この範囲に表示できるエリアデータがまだありません。検索範囲を広げるか、別のキャンパスでお試しください。
      </div>
    <?php else: ?>
      <div class="card-grid">
        <?php foreach ($top_areas as $area): ?>
        <div class="area-card">
          <div class="area-card-img">🏘️</div>
          <div class="area-card-body">
            <div class="area-card-title"><?= htmlspecialchars($area['name']) ?></div>
            <div class="area-card-meta">
              <span>📍 直線距離 <?= htmlspecialchars($area['distance_km']) ?> km</span>
              <span>💴 家賃相場 <?= htmlspecialchars(format_rent($area['price_per_tatami'])) ?></span>
              <span>🏪 周辺施設 <?= (int) $area['poi_count'] ?> 件</span>
            </div>
            <div class="badge-row">
              <?php foreach ($area['badges'] as $b): ?>
                <span class="badge <?= $badge_labels[$b]['class'] ?>"><?= $badge_labels[$b]['label'] ?></span>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="area-card-footer">
            <a href="index.php?page=detail&id=<?= $area['id'] ?>">詳細を見る →</a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

<?php else: ?>

  <!-- ===== 未登録：サービス紹介 ===== -->

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

<?php endif; ?>

</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
