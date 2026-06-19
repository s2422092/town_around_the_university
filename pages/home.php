<?php
/* 担当者:（空欄） / この画面でやること: 検索条件のフィルタバーと、エリア候補カード一覧を表示する。実装時はDBからエリアスコアを取得してカードを動的生成する */
$page_title   = 'ホーム — エリア候補一覧 | 大学周辺の家';
$current_page = 'home';
$page_js      = 'home.js';
require __DIR__ . '/../includes/header.php';

/* ダミーデータ（後でDB連携に差し替え） */
$dummy_areas = [
  [
    'id'       => 1,
    'name'     => '〇〇市△△区',
    'distance' => '12.3',
    'time'     => '約25分',
    'rent'     => '5.2万円〜',
    'badges'   => ['near', 'livable'],
  ],
  [
    'id'       => 2,
    'name'     => '□□市◇◇町',
    'distance' => '8.7',
    'time'     => '約18分',
    'rent'     => '4.8万円〜',
    'badges'   => ['cheap', 'near'],
  ],
  [
    'id'       => 3,
    'name'     => '▲▲区××丁目',
    'distance' => '19.1',
    'time'     => '約40分',
    'rent'     => '3.9万円〜',
    'badges'   => ['cheap'],
  ],
];

$badge_labels = [
  'cheap'   => ['label' => '安さ',       'class' => 'badge-cheap'],
  'near'    => ['label' => '近さ',       'class' => 'badge-near'],
  'livable' => ['label' => '住みやすさ', 'class' => 'badge-livable'],
];
?>

<main>

  <div class="page-hero">
    <h1>エリア候補一覧</h1>
    <p>登録キャンパスからの距離・家賃・住みやすさでエリアを比較できます。</p>
  </div>

  <div class="notice">
    ℹ️ 現在はダミーデータを表示しています。大学情報を登録すると実際のエリアが表示されます。
    <a href="index.php?page=university">→ 大学情報を入力</a>
  </div>

  <!-- フィルタバー（GETメソッドで page パラメータを維持するため hidden を使用） -->
  <form action="index.php" method="get">
    <input type="hidden" name="page" value="home">
    <div class="filter-bar">
      <div class="form-group">
        <label for="radius">距離（直線）</label>
        <select id="radius" name="radius">
          <option value="30">30 km 以内</option>
          <option value="20">20 km 以内</option>
          <option value="10">10 km 以内</option>
        </select>
      </div>

      <div class="form-group">
        <label for="transport">交通手段</label>
        <select id="transport" name="transport">
          <option value="">すべて</option>
          <option value="train">電車</option>
          <option value="bus">バス</option>
          <option value="bike">自転車</option>
          <option value="walk">徒歩</option>
          <option value="taxi">タクシー</option>
        </select>
      </div>

      <div class="form-group">
        <label for="rent_max">家賃上限</label>
        <select id="rent_max" name="rent_max">
          <option value="">上限なし</option>
          <option value="30000">3 万円</option>
          <option value="40000">4 万円</option>
          <option value="50000">5 万円</option>
          <option value="60000">6 万円</option>
          <option value="70000">7 万円</option>
          <option value="80000">8 万円</option>
        </select>
      </div>

      <div class="form-group">
        <label for="priority">優先順位</label>
        <select id="priority" name="priority">
          <option value="near">近さ優先</option>
          <option value="cheap">安さ優先</option>
          <option value="livable">住みやすさ優先</option>
        </select>
      </div>

      <button type="submit" class="btn btn-primary" style="align-self:flex-end;">絞り込む</button>
    </div>
  </form>

  <!-- エリアカード一覧 -->
  <div class="card-grid">
    <?php foreach ($dummy_areas as $area): ?>
    <div class="area-card">
      <div class="area-card-img">🏘️</div>
      <div class="area-card-body">
        <div class="area-card-title"><?= htmlspecialchars($area['name']) ?></div>
        <div class="area-card-meta">
          <span>📍 直線距離 <?= $area['distance'] ?> km</span>
          <span>🚃 通学時間 <?= $area['time'] ?>（目安）</span>
          <span>💴 家賃相場 <?= $area['rent'] ?></span>
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

</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
