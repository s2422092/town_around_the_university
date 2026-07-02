<?php
/**
 * scripts/fetch_pois.php
 * Overpass API (OpenStreetMap) で全エリアの周辺施設を自動取得して pois テーブルに保存する。
 *
 * 取得施設: 交番(police) / 公園(park) / コンビニ(convenience) / スーパー(supermarket) / 病院(hospital)
 *
 * 実行方法:
 *   php scripts/fetch_pois.php                # 全エリア
 *   php scripts/fetch_pois.php --area-id=17   # 特定エリアのみ（ID指定）
 *   php scripts/fetch_pois.php --dry-run      # DB書込なし・件数確認のみ
 *   php scripts/fetch_pois.php --radius=5000  # 検索半径を変更（デフォルト 3000m）
 */

require __DIR__ . '/../db/connection.php';

// ── オプション解析 ───────────────────────────────────────────────────
$dry_run      = in_array('--dry-run', $argv);
$area_id_only = null;
$radius       = 3000;
foreach ($argv as $arg) {
    if (preg_match('/^--area-id=(\d+)$/', $arg, $m)) $area_id_only = (int) $m[1];
    if (preg_match('/^--radius=(\d+)$/',  $arg, $m)) $radius       = (int) $m[1];
}

// ── DB ───────────────────────────────────────────────────────────────
$db = db_connect();

$cond = $area_id_only
    ? "WHERE id = {$area_id_only} AND lat IS NOT NULL AND lng IS NOT NULL"
    : "WHERE lat IS NOT NULL AND lng IS NOT NULL";
$res = pg_query($db, "SELECT id, name, lat::float AS lat, lng::float AS lng FROM areas $cond ORDER BY id");

$areas = [];
while ($row = pg_fetch_assoc($res)) $areas[] = $row;

$n = count($areas);
echo "=== POI 自動取得スクリプト ===\n";
echo "対象エリア: {$n} 件 / 検索半径: {$radius}m\n";
if ($dry_run) echo "[DRY RUN — DB への書き込みは行いません]\n";
echo str_repeat('─', 52) . "\n";

// ── Overpass QL クエリ生成 ───────────────────────────────────────────
function build_query(float $lat, float $lng, int $r): string
{
    return <<<OQL
[out:json][timeout:30];
(
  node["amenity"="police"](around:{$r},{$lat},{$lng});
  way["amenity"="police"](around:{$r},{$lat},{$lng});
  node["leisure"="park"](around:{$r},{$lat},{$lng});
  way["leisure"="park"](around:{$r},{$lat},{$lng});
  node["shop"="convenience"](around:{$r},{$lat},{$lng});
  node["shop"="supermarket"](around:{$r},{$lat},{$lng});
  way["shop"="supermarket"](around:{$r},{$lat},{$lng});
  node["amenity"="hospital"](around:{$r},{$lat},{$lng});
  way["amenity"="hospital"](around:{$r},{$lat},{$lng});
  node["amenity"="clinic"](around:{$r},{$lat},{$lng});
);
out center;
OQL;
}

// ── OSM タグ → POI タイプ ────────────────────────────────────────────
function detect_type(array $tags): ?string
{
    $a = $tags['amenity'] ?? '';
    $l = $tags['leisure'] ?? '';
    $s = $tags['shop']    ?? '';
    if ($a === 'police')                        return 'police';
    if ($l === 'park')                          return 'park';
    if ($s === 'convenience')                   return 'convenience';
    if ($s === 'supermarket')                   return 'supermarket';
    if ($a === 'hospital' || $a === 'clinic')   return 'hospital';
    return null;
}

// ── Overpass API 呼び出し（リトライ付き） ────────────────────────────
function fetch_overpass(string $query, int $max_retry = 3): ?array
{
    for ($try = 1; $try <= $max_retry; $try++) {
        $ch = curl_init('https://overpass-api.de/api/interpreter');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => 'data=' . urlencode($query),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_USERAGENT      => 'town_around_the_university/1.0 (educational project)',
        ]);
        $body    = curl_exec($ch);
        $http    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err     = curl_error($ch);
        curl_close($ch);

        if ($body && $http === 200) {
            $json = json_decode($body, true);
            if (isset($json['elements'])) return $json['elements'];
            echo "  ⚠ JSON パース失敗\n";
        } elseif ($http === 429 || $http === 503) {
            $wait = $try * 10;
            echo "  ⚠ レートリミット (HTTP {$http})。{$wait}秒待機…\n";
            sleep($wait);
            continue;
        } else {
            echo "  ⚠ HTTP {$http} / curl: {$err}\n";
        }
        if ($try < $max_retry) sleep(5);
    }
    return null;
}

// ── メイン ──────────────────────────────────────────────────────────
$total_pois = 0;
$failed     = [];

foreach ($areas as $idx => $area) {
    $progress = sprintf('[%3d/%d]', $idx + 1, $n);
    echo "{$progress} {$area['name']}\n";

    $elements = fetch_overpass(build_query((float)$area['lat'], (float)$area['lng'], $radius));
    if ($elements === null) {
        echo "  → ✗ スキップ（API 失敗）\n";
        $failed[] = $area['name'];
        sleep(5);
        continue;
    }

    // OSM 要素をパース
    $pois = [];
    foreach ($elements as $el) {
        $tags = $el['tags'] ?? [];
        $type = detect_type($tags);
        if (!$type) continue;

        if (isset($el['lat'], $el['lon'])) {
            $plat = $el['lat'];  $plng = $el['lon'];
        } elseif (isset($el['center'])) {
            $plat = $el['center']['lat'];  $plng = $el['center']['lon'];
        } else {
            continue;
        }
        $pois[] = ['type' => $type, 'name' => $tags['name'] ?? null, 'lat' => $plat, 'lng' => $plng];
    }

    // 種別ごとの件数表示
    $c = array_count_values(array_column($pois, 'type'));
    echo sprintf(
        "  交番:%2d  公園:%2d  コンビニ:%2d  スーパー:%2d  病院:%2d  合計:%d 件\n",
        $c['police'] ?? 0, $c['park'] ?? 0, $c['convenience'] ?? 0,
        $c['supermarket'] ?? 0, $c['hospital'] ?? 0, count($pois)
    );

    if (!$dry_run && !empty($pois)) {
        pg_query_params($db, 'DELETE FROM pois WHERE area_id = $1', [$area['id']]);
        foreach ($pois as $poi) {
            pg_query_params($db, '
                INSERT INTO pois (area_id, type, name, lat, lng)
                VALUES ($1, $2, $3, $4, $5)
            ', [$area['id'], $poi['type'], $poi['name'], $poi['lat'], $poi['lng']]);
        }
        $total_pois += count($pois);
    }

    // Overpass API のレートリミット対策（最低 2 秒間隔）
    sleep(2);
}

pg_close($db);

echo str_repeat('─', 52) . "\n";
if ($dry_run) {
    echo "DRY RUN 完了。\n";
} else {
    echo "完了！ 合計 {$total_pois} 件の POI を登録しました。\n";
}
if ($failed) {
    echo "失敗したエリア: " . implode(', ', $failed) . "\n";
}
