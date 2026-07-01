<?php
session_start();

$page_title   = 'エリア詳細 | 大学周辺の家';
$current_page = 'home';
$page_js      = 'detail.js';
$extra_head   = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">'
              . '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>';

require __DIR__ . '/../db/connection.php';
require __DIR__ . '/../includes/area_query.php';
require __DIR__ . '/../includes/routing.php';
require __DIR__ . '/../includes/pois.php';

$area_id    = isset($_GET['id']) && ctype_digit($_GET['id']) ? (int)$_GET['id'] : 0;
$registered = $_SESSION['registered'] ?? null;

$area     = null;
$pois     = [];
$scores   = [];
$commutes = [];
$db_error = false;

if ($area_id > 0) {
    try {
        $db = db_connect();

        // エリア情報 + 家賃相場
        $res = pg_query_params($db, '
            SELECT a.id, a.name, a.prefecture_code, a.city_code,
                   a.lat::float AS lat, a.lng::float AS lng,
                   rs.price_per_tatami::float
            FROM areas a
            LEFT JOIN rent_stats rs
                   ON rs.region_code = a.prefecture_code
                  AND rs.structure_type = $2
            WHERE a.id = $1
        ', [$area_id, '全構造']);

        $area = ($res && pg_num_rows($res) > 0) ? pg_fetch_assoc($res) : null;

        if ($area) {
            // 周辺施設（DBになければ Overpass API から取得してキャッシュ）
            $pois = fetch_area_pois($db, $area_id, (float)$area['lat'], (float)$area['lng']);

            $campus_id  = $registered['campus_id'] ?? null;
            $campus_lat = isset($registered['lat'])  ? (float)$registered['lat']  : null;
            $campus_lng = isset($registered['lng'])  ? (float)$registered['lng']  : null;

            // 事前計算スコア
            if ($campus_id) {
                $res2 = pg_query_params($db,
                    'SELECT distance_km::float, cheapness_score, closeness_score, livability_score
                     FROM area_scores WHERE campus_id=$1 AND area_id=$2',
                    [$campus_id, $area_id]
                );
                if ($res2 && pg_num_rows($res2) > 0) {
                    $scores = pg_fetch_assoc($res2);
                }
            }

            // 事前計算がなければ動的算出
            if (empty($scores)) {
                $distance = ($campus_lat !== null && $campus_lng !== null)
                    ? haversine_km($campus_lat, $campus_lng, (float)$area['lat'], (float)$area['lng'])
                    : null;
                $scores = compute_scores($distance, $area['price_per_tatami'], count($pois));
                if ($distance !== null) {
                    $scores['distance_km'] = round($distance, 1);
                }
            }

            // OSRM 通学時間（道路手段のみ）
            if ($campus_lat !== null && $campus_lng !== null) {
                $table = osrm_table($campus_lat, $campus_lng, [
                    ['lat' => (float)$area['lat'], 'lng' => (float)$area['lng']],
                ]);
                if (!empty($table[0])) {
                    foreach (ROAD_MODES as $mode) {
                        $min = mode_minutes($mode, $table[0]['distance_km'], $table[0]['driving_min']);
                        if ($min !== null) {
                            $commutes[$mode] = $min;
                        }
                    }
                }
            }
        }

        pg_close($db);
    } catch (RuntimeException $e) {
        $db_error = true;
    }
}

if (!$area) {
    header('Location: index.php?page=home');
    exit;
}

function haversine_km(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $R    = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a    = sin($dLat / 2) ** 2
          + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $R * 2 * asin(min(1.0, sqrt($a)));
}

/**
 * 距離・家賃・施設数からスコアを動的算出する。
 * cheapness: 畳単価 2500円=100pt, 7500円=0pt
 * closeness: 0km=100pt, 30km=0pt
 * livability: 施設数 0=0pt, 13件以上=100pt
 */
function compute_scores(?float $distance_km, $price_per_tatami, int $poi_count): array
{
    $cheapness = null;
    if ($price_per_tatami !== null && $price_per_tatami !== '') {
        $cheapness = (int)max(0, min(100, (7500 - (float)$price_per_tatami) / 50));
    }
    $closeness  = $distance_km !== null
        ? (int)max(0, min(100, 100 - $distance_km / 30 * 100))
        : null;
    $livability = (int)min(100, $poi_count * 8);

    return [
        'cheapness_score'  => $cheapness,
        'closeness_score'  => $closeness,
        'livability_score' => $livability,
    ];
}

/**
 * 各ポータルサイトのエリア検索 URL を生成する。
 */
function portal_url(string $site, array $area): string
{
    $enc  = urlencode($area['name']);
    $pref = $area['prefecture_code'] ?? '';
    $city = $area['city_code']       ?? '';

    return match ($site) {
        'suumo' => 'https://suumo.jp/jj/chintai/ichiran/FR301FC001/?' . http_build_query([
            'ar'    => suumo_ar($pref),
            'bs'    => '040',
            'ta'    => $pref,
            'sc'    => $city,
            'kb'    => '1',
            'mb'    => '0',
            'mt'    => '9999999',
            'cn'    => '9999999',
            'shkr1' => '03',
            'shkr2' => '03',
            'shkr3' => '03',
            'shkr4' => '03',
        ]),
        'homes'  => 'https://www.homes.co.jp/chintai/list/?searchword=' . $enc,
        'athome' => 'https://www.athome.co.jp/chintai/?searchword=' . $enc,
        default  => '#',
    };
}

function suumo_ar(string $pref_code): string
{
    return match ($pref_code) {
        '26', '27', '28', '29', '30' => '020', // 関西
        '23'                         => '050', // 東海
        default                      => '030', // 首都圏
    };
}

// JS へ渡すデータ（地図・マーカー用）
$js_data = [
    'lat'    => (float)$area['lat'],
    'lng'    => (float)$area['lng'],
    'name'   => $area['name'],
    'pois'   => array_values(array_map(fn($p) => [
        'type' => $p['type'],
        'name' => $p['name'],
        'lat'  => (float)$p['lat'],
        'lng'  => (float)$p['lng'],
    ], $pois)),
    'campus' => ($registered && $campus_lat !== null) ? [
        'lat'  => $campus_lat,
        'lng'  => $campus_lng,
        'name' => trim(($registered['university_name'] ?? '') . ' ' . ($registered['campus_name'] ?? '')),
    ] : null,
];

$page_title = htmlspecialchars($area['name']) . ' — エリア詳細 | 大学周辺の家';

// バッジ用ヘルパーデータ
$badge_row = [
    'distance_km'      => $scores['distance_km'] ?? 99,
    'price_per_tatami' => $area['price_per_tatami'],
    'poi_count'        => count($pois),
];
$badges = area_badges($badge_row, 'near');
$badge_labels = [
    'cheap'   => ['label' => '安さ',       'class' => 'badge-cheap'],
    'near'    => ['label' => '近さ',       'class' => 'badge-near'],
    'livable' => ['label' => '住みやすさ', 'class' => 'badge-livable'],
];

require __DIR__ . '/../includes/header.php';
?>
<script>window.AREA_DATA = <?= json_encode($js_data, JSON_UNESCAPED_UNICODE) ?>;</script>

<main>

  <!-- ページ上部 -->
  <div class="detail-header">
    <div>
      <a href="index.php?page=home" style="font-size:0.85rem; color:var(--color-primary); text-decoration:none;">← エリア一覧に戻る</a>
      <h1 style="font-size:1.6rem; font-weight:700; margin-top:0.5rem;"><?= htmlspecialchars($area['name']) ?></h1>
      <p class="text-muted mt-1">
        <?php if (!empty($scores['distance_km'])): ?>
          📍 直線距離 <?= htmlspecialchars((string)$scores['distance_km']) ?> km &nbsp;|&nbsp;
        <?php endif; ?>
        💴 家賃相場 <?= htmlspecialchars(format_rent($area['price_per_tatami'])) ?>
      </p>
    </div>
    <div style="display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center;">
      <?php foreach ($badges as $b): ?>
        <span class="badge <?= $badge_labels[$b]['class'] ?>" style="font-size:0.85rem; padding:0.3rem 0.75rem;">
          <?= $badge_labels[$b]['label'] ?> ★
        </span>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- スコアボックス -->
  <div class="score-grid">
    <div class="score-box cheap">
      <div class="score-label">安さスコア</div>
      <div class="score-value" data-target="<?= (int)($scores['cheapness_score'] ?? 0) ?>">
        <?= $scores['cheapness_score'] !== null ? (int)$scores['cheapness_score'] : '―' ?>
      </div>
      <div class="score-sub">/ 100</div>
    </div>
    <div class="score-box near">
      <div class="score-label">近さスコア</div>
      <div class="score-value" data-target="<?= (int)($scores['closeness_score'] ?? 0) ?>">
        <?= $scores['closeness_score'] !== null ? (int)$scores['closeness_score'] : '―' ?>
      </div>
      <div class="score-sub">
        <?php if ($scores['closeness_score'] === null): ?>
          大学登録後に表示
        <?php else: ?>
          / 100
        <?php endif; ?>
      </div>
    </div>
    <div class="score-box livable">
      <div class="score-label">住みやすさスコア</div>
      <div class="score-value" data-target="<?= (int)($scores['livability_score'] ?? 0) ?>">
        <?= $scores['livability_score'] !== null ? (int)$scores['livability_score'] : '―' ?>
      </div>
      <div class="score-sub">/ 100</div>
    </div>
  </div>

  <!-- 地図（Leaflet + OpenStreetMap） -->
  <div id="map" class="area-map mb-2"></div>

  <!-- 周辺施設 -->
  <div class="card mb-2">
    <h2 class="section-title">周辺施設（OpenStreetMap データ）</h2>
    <?php if (empty($pois)): ?>
      <p class="text-muted">周辺施設データを取得できませんでした。時間をおいて再度お試しください。</p>
    <?php else: ?>
      <ul class="poi-list">
        <?php foreach ($pois as $poi):
          $meta = poi_meta($poi['type']); ?>
        <li>
          <span class="poi-icon"><?= $meta['icon'] ?></span>
          <span style="flex:1;"><?= htmlspecialchars($poi['name']) ?></span>
          <span class="text-muted"><?= $meta['label'] ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
      <p class="text-muted mt-2" style="font-size:0.78rem;">※ OpenStreetMap Overpass API から取得（エリア中心から半径 1.5 km）</p>
    <?php endif; ?>
  </div>

  <!-- 通学時間 -->
  <div class="card mb-2">
    <h2 class="section-title">通学時間（道路距離による概算）</h2>
    <?php if (empty($commutes)): ?>
      <p class="text-muted">
        大学情報を登録すると通学時間が表示されます。
        <a href="index.php?page=university" style="color:var(--color-primary);">→ 大学情報を入力</a>
      </p>
    <?php else: ?>
      <table style="width:100%; border-collapse:collapse; font-size:0.9rem;">
        <thead>
          <tr style="border-bottom:2px solid var(--color-border); text-align:left;">
            <th style="padding:0.5rem 0.75rem;">交通手段</th>
            <th style="padding:0.5rem 0.75rem;">所要時間（概算）</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($commutes as $mode => $min): ?>
          <tr style="border-bottom:1px solid var(--color-border);">
            <td style="padding:0.5rem 0.75rem;"><?= mode_label($mode) ?></td>
            <td style="padding:0.5rem 0.75rem;">約 <?= $min ?> 分</td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p class="text-muted mt-2" style="font-size:0.78rem;">
        ※ OSRM（OpenStreetMap 道路データ）による概算。電車・バスは今後対応予定。
      </p>
    <?php endif; ?>
  </div>

  <!-- 物件ポータルリンク -->
  <div class="card">
    <h2 class="section-title">物件を探す（外部ポータル）</h2>
    <p class="text-muted mb-1">「<?= htmlspecialchars($area['name']) ?>」の賃貸物件を外部ポータルで検索します。</p>
    <div class="portal-links">
      <a class="btn btn-primary"
         href="<?= htmlspecialchars(portal_url('suumo', $area)) ?>"
         target="_blank" rel="noopener noreferrer">SUUMO で探す</a>
      <a class="btn btn-outline"
         href="<?= htmlspecialchars(portal_url('homes', $area)) ?>"
         target="_blank" rel="noopener noreferrer">HOME'S で探す</a>
      <a class="btn btn-outline"
         href="<?= htmlspecialchars(portal_url('athome', $area)) ?>"
         target="_blank" rel="noopener noreferrer">athome で探す</a>
    </div>
  </div>

</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
