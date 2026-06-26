<?php
/* 担当者:（空欄） / この画面でやること: 特定エリア1件の詳細情報（スコア・周辺施設・地図・物件リンク）を表示する */
$current_page = 'home';
$page_js      = 'detail.js';

require __DIR__ . '/../includes/search_helpers.php';

$place_name = trim($_GET['name'] ?? '');
$lat = isset($_GET['lat']) && is_numeric($_GET['lat']) ? (float)$_GET['lat'] : null;
$lng = isset($_GET['lng']) && is_numeric($_GET['lng']) ? (float)$_GET['lng'] : null;
$address = trim($_GET['address'] ?? '');
$campus_lat = isset($_GET['campus_lat']) && is_numeric($_GET['campus_lat']) ? (float)$_GET['campus_lat'] : null;
$campus_lng = isset($_GET['campus_lng']) && is_numeric($_GET['campus_lng']) ? (float)$_GET['campus_lng'] : null;
$campus_title = trim($_GET['campus_title'] ?? '');

$page_title = ($place_name !== '' ? $place_name : 'エリア詳細') . ' | 大学周辺の家';
$has_place = $place_name !== '' && $lat !== null && $lng !== null;
$poi_radius = 1200;

function detail_distance_text($km) {
    if ($km === null) {
        return '未取得';
    }

    if ($km < 1) {
        return round($km * 1000) . ' m';
    }

    return number_format($km, 2) . ' km';
}

function detail_poi_type($tags) {
    if (($tags['shop'] ?? '') === 'convenience') {
        return ['label' => 'コンビニ', 'icon' => '🏪', 'score' => 8];
    }

    if (($tags['amenity'] ?? '') === 'police') {
        return ['label' => '警察署・交番', 'icon' => '🚔', 'score' => 15];
    }

    if (($tags['leisure'] ?? '') === 'park') {
        return ['label' => '公園', 'icon' => '🌳', 'score' => 10];
    }

    return null;
}

function detail_score_near($distance_km) {
    if ($distance_km === null) {
        return null;
    }

    return max(0, min(100, (int)round(100 - ($distance_km * 12))));
}

function detail_score_livable($pois) {
    $score = 0;

    foreach ($pois as $poi) {
        $score += $poi['score'];
    }

    return max(0, min(100, $score));
}

function detail_time_minutes($distance_km, $speed_kmh) {
    if ($distance_km === null || $speed_kmh <= 0) {
        return null;
    }

    return (int)ceil(($distance_km / $speed_kmh) * 60);
}

$distance_from_campus = ($has_place && $campus_lat !== null && $campus_lng !== null)
    ? distance_km($campus_lat, $campus_lng, $lat, $lng)
    : null;

$pois = [];

if ($has_place) {
    $raw_pois = search_nearby_pois($lat, $lng, $poi_radius);
    $seen_pois = [];

    foreach ($raw_pois as $raw_poi) {
        $tags = $raw_poi['tags'] ?? [];
        $type = detail_poi_type($tags);
        $poi_name = $tags['name:ja'] ?? $tags['name'] ?? '';
        $poi_lat = $raw_poi['lat'] ?? $raw_poi['center']['lat'] ?? null;
        $poi_lng = $raw_poi['lon'] ?? $raw_poi['center']['lon'] ?? null;

        if (!$type || $poi_name === '' || $poi_lat === null || $poi_lng === null) {
            continue;
        }

        $poi_key = $type['label'] . '_' . $poi_name;

        if (isset($seen_pois[$poi_key])) {
            continue;
        }

        $seen_pois[$poi_key] = true;
        $pois[] = [
            'icon' => $type['icon'],
            'type' => $type['label'],
            'name' => $poi_name,
            'score' => $type['score'],
            'distance_km' => distance_km($lat, $lng, (float)$poi_lat, (float)$poi_lng),
        ];
    }

    usort($pois, function ($a, $b) {
        return $a['distance_km'] <=> $b['distance_km'];
    });

    $pois = array_slice($pois, 0, 12);
}

$near_score = detail_score_near($distance_from_campus);
$livable_score = detail_score_livable($pois);
$cheap_score = null;
$rent_text = '外部ポータルで確認';
$time_text = $distance_from_campus !== null ? '徒歩・自転車は直線距離から目安計算' : '未取得';

$map_embed_url = '';
$osm_url = '';

if ($has_place) {
    $map_embed_url = 'https://www.openstreetmap.org/export/embed.html?' . http_build_query([
        'bbox' => ($lng - 0.01) . ',' . ($lat - 0.006) . ',' . ($lng + 0.01) . ',' . ($lat + 0.006),
        'layer' => 'mapnik',
        'marker' => $lat . ',' . $lng,
    ]);

    $osm_url = 'https://www.openstreetmap.org/?' . http_build_query([
        'mlat' => $lat,
        'mlon' => $lng,
    ]) . '#map=16/' . rawurlencode((string)$lat) . '/' . rawurlencode((string)$lng);
}

$google_map_url = 'https://www.google.com/maps/search/?' . http_build_query([
    'api' => 1,
    'query' => $has_place ? ($place_name . ' ' . $lat . ',' . $lng) : $place_name,
]);

$route_url = 'https://www.google.com/maps/dir/?' . http_build_query([
    'api' => 1,
    'origin' => ($campus_lat !== null && $campus_lng !== null) ? ($campus_lat . ',' . $campus_lng) : $address,
    'destination' => $has_place ? ($lat . ',' . $lng) : $place_name,
    'travelmode' => 'transit',
]);

$suumo_url = 'https://suumo.jp/jj/chintai/ichiran/FR301FC001/?' . http_build_query([
    'fw' => $place_name,
]);

$homes_url = 'https://www.homes.co.jp/chintai/?' . http_build_query([
    'keyword' => $place_name,
]);

$back_url = 'index.php?' . http_build_query(array_filter([
    'page' => 'home',
    'address' => $address,
    'lat' => $campus_lat,
    'lng' => $campus_lng,
    'place_title' => $campus_title,
], function ($value) {
    return $value !== null && $value !== '';
}));

$walk_minutes = detail_time_minutes($distance_from_campus, 4.8);
$bike_minutes = detail_time_minutes($distance_from_campus, 15);
$taxi_minutes = detail_time_minutes($distance_from_campus, 28);

require __DIR__ . '/../includes/header.php';
?>

<main>

  <!-- ページ上部 -->
  <div class="detail-header">
    <div>
      <a href="<?= htmlspecialchars($back_url, ENT_QUOTES, 'UTF-8') ?>" style="font-size:0.85rem; color:var(--color-primary); text-decoration:none;">← エリア一覧に戻る</a>
      <h1 style="font-size:1.6rem; font-weight:700; margin-top:0.5rem;"><?= htmlspecialchars($place_name !== '' ? $place_name : 'エリア詳細', ENT_QUOTES, 'UTF-8') ?></h1>
      <p class="text-muted mt-1">
        📍 直線距離 <?= htmlspecialchars(detail_distance_text($distance_from_campus), ENT_QUOTES, 'UTF-8') ?> &nbsp;|&nbsp;
        🚃 <?= htmlspecialchars($time_text, ENT_QUOTES, 'UTF-8') ?> &nbsp;|&nbsp;
        💴 家賃相場 <?= htmlspecialchars($rent_text, ENT_QUOTES, 'UTF-8') ?>
      </p>
    </div>
    <div style="display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center;">
      <span class="badge badge-near"    style="font-size:0.85rem; padding:0.3rem 0.75rem;">近さ ★</span>
      <span class="badge badge-livable" style="font-size:0.85rem; padding:0.3rem 0.75rem;">住みやすさ ★</span>
    </div>
  </div>

  <?php if (!$has_place): ?>
    <div class="notice">
      ホーム画面で候補を検索してから「詳細を見る」を押してください。
    </div>
  <?php else: ?>

  <!-- スコアボックス -->
  <div class="score-grid">
    <div class="score-box cheap">
      <div class="score-label">安さスコア</div>
      <div class="score-value"><?= $cheap_score === null ? '未取得' : $cheap_score ?></div>
      <div style="font-size:0.75rem; color:var(--color-text-muted);">外部ポータルで確認</div>
    </div>
    <div class="score-box near">
      <div class="score-label">近さスコア</div>
      <div class="score-value"><?= $near_score === null ? '未取得' : $near_score ?></div>
      <div style="font-size:0.75rem; color:var(--color-text-muted);">直線距離から算出</div>
    </div>
    <div class="score-box livable">
      <div class="score-label">住みやすさスコア</div>
      <div class="score-value"><?= $livable_score ?></div>
      <div style="font-size:0.75rem; color:var(--color-text-muted);">周辺施設から算出</div>
    </div>
  </div>

  <!-- 地図 -->
  <div class="placeholder-map" id="map">
    <iframe
      src="<?= htmlspecialchars($map_embed_url, ENT_QUOTES, 'UTF-8') ?>"
      title="<?= htmlspecialchars($place_name, ENT_QUOTES, 'UTF-8') ?> の地図"
      loading="lazy">
    </iframe>
  </div>

  <!-- 周辺施設 -->
  <div class="card mb-2">
    <h2 class="section-title">周辺施設（OpenStreetMap）</h2>

    <?php if (count($pois) > 0): ?>
      <ul class="poi-list">
        <?php foreach ($pois as $poi): ?>
        <li>
          <span class="poi-icon"><?= $poi['icon'] ?></span>
          <span style="flex:1;"><?= htmlspecialchars($poi['name'], ENT_QUOTES, 'UTF-8') ?></span>
          <span class="text-muted"><?= htmlspecialchars($poi['type'], ENT_QUOTES, 'UTF-8') ?> &nbsp;·&nbsp; <?= htmlspecialchars(detail_distance_text($poi['distance_km']), ENT_QUOTES, 'UTF-8') ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="text-muted">指定した範囲では、名前付きのコンビニ・公園・警察署/交番が見つかりませんでした。</p>
    <?php endif; ?>

    <p class="text-muted mt-2" style="font-size:0.78rem;">※ 施設データは Overpass API から取得・キャッシュしています</p>
  </div>

  <!-- 交通情報 -->
  <div class="card mb-2">
    <h2 class="section-title">通学時間・交通費</h2>
    <table style="width:100%; border-collapse:collapse; font-size:0.9rem;">
      <thead>
        <tr style="border-bottom:2px solid var(--color-border); text-align:left;">
          <th style="padding:0.5rem 0.75rem;">交通手段</th>
          <th style="padding:0.5rem 0.75rem;">所要時間</th>
          <th style="padding:0.5rem 0.75rem;">運賃目安</th>
        </tr>
      </thead>
      <tbody>
        <tr style="border-bottom:1px solid var(--color-border);">
          <td style="padding:0.5rem 0.75rem;">🚃 電車</td>
          <td style="padding:0.5rem 0.75rem;">Googleマップで確認</td>
          <td style="padding:0.5rem 0.75rem;">Googleマップで確認</td>
        </tr>
        <tr style="border-bottom:1px solid var(--color-border);">
          <td style="padding:0.5rem 0.75rem;">🚌 バス</td>
          <td style="padding:0.5rem 0.75rem;">Googleマップで確認</td>
          <td style="padding:0.5rem 0.75rem;">Googleマップで確認</td>
        </tr>
        <tr style="border-bottom:1px solid var(--color-border);">
          <td style="padding:0.5rem 0.75rem;">🚲 自転車</td>
          <td style="padding:0.5rem 0.75rem;"><?= $bike_minutes === null ? '未取得' : '約' . $bike_minutes . '分' ?></td>
          <td style="padding:0.5rem 0.75rem;">無料</td>
        </tr>
        <tr style="border-bottom:1px solid var(--color-border);">
          <td style="padding:0.5rem 0.75rem;">🚶 徒歩</td>
          <td style="padding:0.5rem 0.75rem;"><?= $walk_minutes === null ? '未取得' : '約' . $walk_minutes . '分' ?></td>
          <td style="padding:0.5rem 0.75rem;">無料</td>
        </tr>
        <tr>
          <td style="padding:0.5rem 0.75rem;">🚕 タクシー</td>
          <td style="padding:0.5rem 0.75rem;"><?= $taxi_minutes === null ? '未取得' : '約' . $taxi_minutes . '分' ?></td>
          <td style="padding:0.5rem 0.75rem;">外部サイトで確認</td>
        </tr>
      </tbody>
    </table>
    <div class="portal-links">
      <a class="btn btn-outline" href="<?= htmlspecialchars($route_url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">大学からのルートを見る</a>
      <a class="btn btn-outline" href="<?= htmlspecialchars($google_map_url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Googleマップで見る</a>
      <a class="btn btn-outline" href="<?= htmlspecialchars($osm_url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">OpenStreetMapで見る</a>
    </div>
  </div>

  <!-- 物件リンク -->
  <div class="card">
    <h2 class="section-title">物件を探す（外部ポータル）</h2>
    <p class="text-muted mb-1">実際の物件写真・家賃・空室情報は、外部ポータルで確認できます。</p>
    <div class="portal-links">
      <a class="btn btn-primary" href="<?= htmlspecialchars($suumo_url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">SUUMO で探す</a>
      <a class="btn btn-outline" href="<?= htmlspecialchars($homes_url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">HOME'S で探す</a>
    </div>
  </div>

  <?php endif; ?>

</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
