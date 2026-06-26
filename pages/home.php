<?php
/* 担当者:（空欄） / この画面でやること: 大学住所から周辺駅を検索し、住居候補として表示する */
$page_title   = 'ホーム — エリア候補一覧 | 大学周辺の家';
$current_page = 'home';
$page_js      = 'home.js';

require __DIR__ . '/../includes/search_helpers.php';

$address = trim($_GET['address'] ?? '');
$radius = (int)($_GET['radius'] ?? 3000);
$selected_lat = isset($_GET['lat']) && is_numeric($_GET['lat']) ? (float)$_GET['lat'] : null;
$selected_lng = isset($_GET['lng']) && is_numeric($_GET['lng']) ? (float)$_GET['lng'] : null;
$place_title = trim($_GET['place_title'] ?? '');

$campus = null;
$error_message = '';
$place_candidates = [];
$station_candidates = [];

if ($address === '') {
    $error_message = '大学名、またはキャンパス住所を入力してください。';
} elseif ($selected_lat !== null && $selected_lng !== null) {
    $campus = [
        'lat' => $selected_lat,
        'lng' => $selected_lng,
        'title' => $place_title !== '' ? $place_title : $address,
    ];
} else {
    $place_candidates = search_place_candidates($address);

    if (count($place_candidates) === 1) {
        $campus = $place_candidates[0];
    } elseif (count($place_candidates) > 1) {
        $error_message = '大学名だけでは候補が複数あります。正しいキャンパスを選択してください。';
    } else {
        $error_message = '大学名・住所から位置情報を取得できませんでした。住所を詳しく入力してください。';
    }
}

if ($campus) {
    $stations = search_nearby_stations($campus['lat'], $campus['lng'], $radius);
    $seen_names = [];

    foreach ($stations as $station) {
        if (empty($station['tags']['name']) || empty($station['lat']) || empty($station['lon'])) {
            continue;
        }

        $name = $station['tags']['name'];

        if (isset($seen_names[$name])) {
            continue;
        }

        $seen_names[$name] = true;

        $station_candidates[] = [
            'name' => $name,
            'lat' => $station['lat'],
            'lng' => $station['lon'],
            'distance' => distance_km($campus['lat'], $campus['lng'], $station['lat'], $station['lon']),
        ];
    }

    usort($station_candidates, function ($a, $b) {
        return $a['distance'] <=> $b['distance'];
    });

}

require __DIR__ . '/../includes/header.php';
?>

<main>

  <div class="page-hero">
    <h1>エリア候補一覧</h1>
    <p>大学名またはキャンパス住所を入力すると、候補地を選んで周辺駅を検索できます。</p>
  </div>

  <form action="index.php" method="get">
    <input type="hidden" name="page" value="home">

    <div class="filter-bar">
      <div class="form-group">
        <label for="address">大学名・キャンパス住所</label>
        <input
          id="address"
          name="address"
          type="text"
          value="<?= htmlspecialchars($address, ENT_QUOTES, 'UTF-8') ?>"
          placeholder="例：武蔵野大学 / 東京都新宿区戸塚町1-104"
        >
      </div>

      <div class="form-group">
        <label for="radius">検索範囲</label>
        <select id="radius" name="radius">
          <option value="1000" <?= $radius === 1000 ? 'selected' : '' ?>>1 km 以内</option>
          <option value="3000" <?= $radius === 3000 ? 'selected' : '' ?>>3 km 以内</option>
          <option value="5000" <?= $radius === 5000 ? 'selected' : '' ?>>5 km 以内</option>
          <option value="10000" <?= $radius === 10000 ? 'selected' : '' ?>>10 km 以内</option>
        </select>
      </div>

      <button type="submit" class="btn btn-primary" style="align-self:flex-end;">
        周辺を検索する
      </button>
    </div>
  </form>

  <?php if ($error_message !== ''): ?>
    <div class="notice">
      <?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <?php if (count($place_candidates) > 1): ?>
    <div class="card mb-2">
      <h2 class="section-title">大学・キャンパス候補</h2>
      <p class="text-muted mb-2">検索結果から正しいキャンパスを選択してください。</p>

      <div class="portal-links">
        <?php foreach ($place_candidates as $candidate): ?>
          <?php
            $candidate_url = 'index.php?' . http_build_query([
                'page' => 'home',
                'address' => $address,
                'radius' => $radius,
                'lat' => $candidate['lat'],
                'lng' => $candidate['lng'],
                'place_title' => $candidate['title'],
            ]);
          ?>
          <a class="btn btn-outline" href="<?= htmlspecialchars($candidate_url, ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($candidate['title'], ENT_QUOTES, 'UTF-8') ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($campus): ?>
    <div class="notice">
      検索基準：<?= htmlspecialchars($campus['title'], ENT_QUOTES, 'UTF-8') ?>
      （緯度 <?= htmlspecialchars($campus['lat'], ENT_QUOTES, 'UTF-8') ?> /
      経度 <?= htmlspecialchars($campus['lng'], ENT_QUOTES, 'UTF-8') ?>）
    </div>
  <?php endif; ?>

  <div class="card-grid">
    <?php foreach ($station_candidates as $station): ?>
      <?php
        $detail_url = 'index.php?' . http_build_query([
            'page' => 'detail',
            'name' => $station['name'],
            'lat' => $station['lat'],
            'lng' => $station['lng'],
            'address' => $address,
            'campus_lat' => $campus['lat'],
            'campus_lng' => $campus['lng'],
            'campus_title' => $campus['title'],
        ]);
      ?>

      <div class="area-card">
        <div class="area-card-img">
          <div class="property-photo-empty">
            <span>写真未取得</span>
            <small>実際の物件写真は外部サイトで確認</small>
          </div>
        </div>

        <div class="area-card-body">
          <div class="area-card-title">
            <?= htmlspecialchars($station['name'], ENT_QUOTES, 'UTF-8') ?>
          </div>

          <div class="area-card-meta">
            <span>📍 大学から直線距離 <?= number_format($station['distance'], 2) ?> km</span>
            <span>🚃 周辺駅データ：OpenStreetMap</span>
            <span>💴 家賃・物件情報は詳細ページから外部サイトで確認</span>
          </div>

          <div class="badge-row">
            <span class="badge badge-near">近さ</span>
            <span class="badge badge-livable">周辺確認可</span>
          </div>
        </div>

        <div class="area-card-footer">
          <a href="<?= htmlspecialchars($detail_url, ENT_QUOTES, 'UTF-8') ?>">
            詳細を見る →
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($campus && count($station_candidates) === 0): ?>
    <div class="notice">
      指定した範囲では周辺駅が見つかりませんでした。検索範囲を広げてください。
    </div>
  <?php endif; ?>

</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
