<?php
/* 担当者:（空欄） / この画面でやること: 検索条件のフィルタバーと、エリア候補カード一覧を表示する。大学登録済みならDBからエリアを動的取得し、未登録ならダミーデータを表示する。 */
session_start();

$page_title   = 'ホーム — エリア候補一覧 | 大学周辺の家';
$current_page = 'home';
$page_js      = 'home.js';

$registered = $_SESSION['registered'] ?? null;

/* フィルタ：URLパラメータがあれば優先、なければ登録時の値を初期値に */
$filter = [
    'radius'    => $_GET['radius']    ?? (string) ($registered['radius']   ?? '20'),
    'priority'  => $_GET['priority']  ?? ($registered['priority'] ?? 'near'),
    'transport' => $_GET['transport'] ?? '',
    'rent_max'  => $_GET['rent_max']  ?? '',
];

$areas              = [];
$db_error           = false;
$transport_deferred = false;   // 電車・バスは別フェーズのため絞り込み不可
$use_db             = ($registered !== null && $registered['lat'] !== null && $registered['lng'] !== null);

if ($use_db) {
    require __DIR__ . '/../db/connection.php';
    require __DIR__ . '/../includes/area_query.php';
    require __DIR__ . '/../includes/routing.php';
    try {
        $db = db_connect();
        $rows = ranked_areas(
            $db,
            (float) $registered['lat'],
            (float) $registered['lng'],
            (int) $filter['radius'],
            in_array($filter['priority'], ['near', 'cheap', 'livable'], true) ? $filter['priority'] : 'near',
            12
        );
        pg_close($db);

        /* 交通手段の反映：道路系（徒歩・自転車・タクシー）は実ルーティングで所要時間を算出 */
        $transport = $filter['transport'];
        $commutes  = [];                                  // 行index => 所要時間(分)
        if ($transport !== '' && !is_road_mode($transport)) {
            $transport_deferred = true;                   // 電車・バスは未対応
        } elseif (is_road_mode($transport) && !empty($rows)) {
            $dests = array_map(
                fn($r) => ['lat' => (float) $r['lat'], 'lng' => (float) $r['lng']],
                $rows
            );
            $table = osrm_table((float) $registered['lat'], (float) $registered['lng'], $dests);
            foreach ($rows as $i => $r) {
                $commutes[$i] = isset($table[$i]) && $table[$i] !== null
                    ? mode_minutes($transport, $table[$i]['distance_km'], $table[$i]['driving_min'])
                    : null;
            }
            /* 選択手段の所要時間が短い順に並べ替え（算出不可は末尾） */
            uksort($rows, function ($a, $b) use ($commutes) {
                $ca = $commutes[$a] ?? PHP_INT_MAX;
                $cb = $commutes[$b] ?? PHP_INT_MAX;
                return $ca <=> $cb;
            });
        }

        /* カード表示用に整形 */
        foreach ($rows as $i => $r) {
            $areas[] = [
                'id'           => $r['id'],
                'name'         => $r['name'],
                'distance'     => $r['distance_km'],
                'rent'         => format_rent($r['price_per_tatami']),
                'poi'          => (int) $r['poi_count'],
                'badges'       => $r['badges'],
                'commute_mode' => is_road_mode($transport) ? $transport : '',
                'commute_min'  => $commutes[$i] ?? null,
            ];
        }
    } catch (RuntimeException $e) {
        $db_error = true;
    }
}

/* 未登録時のダミーデータ */
$dummy_areas = [
  ['id' => 1, 'name' => '〇〇市△△区', 'distance' => '12.3', 'rent' => '5.2万円〜', 'poi' => 4, 'badges' => ['near', 'livable']],
  ['id' => 2, 'name' => '□□市◇◇町', 'distance' => '8.7',  'rent' => '4.8万円〜', 'poi' => 3, 'badges' => ['cheap', 'near']],
  ['id' => 3, 'name' => '▲▲区××丁目', 'distance' => '19.1', 'rent' => '3.9万円〜', 'poi' => 2, 'badges' => ['cheap']],
];

$display_areas = $use_db ? $areas : $dummy_areas;

$badge_labels = [
  'cheap'   => ['label' => '安さ',       'class' => 'badge-cheap'],
  'near'    => ['label' => '近さ',       'class' => 'badge-near'],
  'livable' => ['label' => '住みやすさ', 'class' => 'badge-livable'],
];

require __DIR__ . '/../includes/header.php';
?>

<main>

  <div class="page-hero">
    <h1>エリア候補一覧</h1>
    <p>大学名またはキャンパス住所を入力すると、候補地を選んで周辺駅を検索できます。</p>
  </div>

  <?php if (!$use_db): ?>
    <div class="notice">
      ℹ️ 現在はダミーデータを表示しています。大学情報を登録すると実際のエリアが表示されます。
      <a href="index.php?page=university">→ 大学情報を入力</a>
    </div>
  <?php elseif ($db_error): ?>
    <div class="notice" style="background:#fee2e2;border-color:#fca5a5;color:#991b1b;">
      データベースに接続できませんでした。.env の設定を確認してください。
    </div>
  <?php else: ?>
    <div class="notice" style="background:#dcfce7;border-color:#86efac;color:#166534;">
      📍 <?= htmlspecialchars($registered['university_name']) ?>
      <?= htmlspecialchars($registered['campus_name']) ?> 周辺のエリアを表示しています。
    </div>
  <?php endif; ?>

  <?php if ($transport_deferred): ?>
    <div class="notice">
      ℹ️ 電車・バスの所要時間は今後対応予定です。現在は徒歩・自転車・タクシーのみ通学時間を算出します。
    </div>
  <?php endif; ?>

  <!-- フィルタバー（GETメソッドで page パラメータを維持するため hidden を使用） -->
  <form action="index.php" method="get">
    <input type="hidden" name="page" value="home">

    <div class="filter-bar">
      <div class="form-group">
        <label for="radius">検索範囲</label>
        <select id="radius" name="radius">
          <?php foreach (['30' => '30 km 以内', '20' => '20 km 以内', '10' => '10 km 以内'] as $val => $label): ?>
            <option value="<?= $val ?>" <?= (string) $filter['radius'] === $val ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="transport">交通手段</label>
        <select id="transport" name="transport">
          <?php foreach (['' => 'すべて', 'train' => '電車', 'bus' => 'バス', 'bike' => '自転車', 'walk' => '徒歩', 'taxi' => 'タクシー'] as $val => $label): ?>
            <option value="<?= $val ?>" <?= (string) $filter['transport'] === (string) $val ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="rent_max">家賃上限</label>
        <select id="rent_max" name="rent_max">
          <?php foreach (['' => '上限なし', '30000' => '3 万円', '40000' => '4 万円', '50000' => '5 万円', '60000' => '6 万円', '70000' => '7 万円', '80000' => '8 万円'] as $val => $label): ?>
            <option value="<?= $val ?>" <?= (string) $filter['rent_max'] === (string) $val ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="priority">優先順位</label>
        <select id="priority" name="priority">
          <?php foreach (['near' => '近さ優先', 'cheap' => '安さ優先', 'livable' => '住みやすさ優先'] as $val => $label): ?>
            <option value="<?= $val ?>" <?= (string) $filter['priority'] === $val ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <button type="submit" class="btn btn-primary" style="align-self:flex-end;">絞り込む</button>
    </div>
  </form>

  <!-- エリアカード一覧 -->
  <?php if ($use_db && !$db_error && empty($display_areas)): ?>
    <div class="notice">
      条件に合うエリアが見つかりませんでした。検索範囲を広げてお試しください。
    </div>
  <?php else: ?>
  <div class="card-grid">
    <?php foreach ($display_areas as $area): ?>
    <div class="area-card">
      <div class="area-card-img">🏘️</div>
      <div class="area-card-body">
        <div class="area-card-title"><?= htmlspecialchars($area['name']) ?></div>
        <div class="area-card-meta">
          <span>📍 直線距離 <?= htmlspecialchars($area['distance']) ?> km</span>
          <?php if (!empty($area['commute_min'])): ?>
            <span><?= htmlspecialchars(mode_label($area['commute_mode'])) ?> 通学時間 約<?= (int) $area['commute_min'] ?>分</span>
          <?php endif; ?>
          <span>💴 家賃相場 <?= htmlspecialchars($area['rent']) ?></span>
          <span>🏪 周辺施設 <?= (int) $area['poi'] ?> 件</span>
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

</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
