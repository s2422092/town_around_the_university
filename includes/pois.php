<?php
require_once __DIR__ . '/geocode.php';

const POI_META = [
    'police'      => ['icon' => 'local_police',  'label' => '交番・警察署'],
    'park'        => ['icon' => 'park',           'label' => '公園'],
    'convenience' => ['icon' => 'store',          'label' => 'コンビニ'],
    'supermarket' => ['icon' => 'shopping_cart',  'label' => 'スーパー'],
    'hospital'    => ['icon' => 'local_hospital', 'label' => '病院・クリニック'],
];

function poi_meta(string $type): array
{
    return POI_META[$type] ?? ['icon' => 'place', 'label' => $type];
}

/**
 * エリアの周辺施設をDBから取得し、なければ Overpass API から取得してDBにキャッシュする。
 */
function fetch_area_pois($db, int $area_id, float $lat, float $lng, int $radius = 1500): array
{
    $res  = pg_query_params($db, 'SELECT * FROM pois WHERE area_id=$1 ORDER BY type, name LIMIT 20', [$area_id]);
    $rows = [];
    if ($res) {
        while ($row = pg_fetch_assoc($res)) {
            $rows[] = $row;
        }
    }

    if (!empty($rows)) {
        return $rows;
    }

    $fetched = overpass_fetch_pois($lat, $lng, $radius);
    foreach ($fetched as $poi) {
        pg_query_params($db,
            'INSERT INTO pois (area_id, type, name, lat, lng) VALUES ($1,$2,$3,$4,$5) ON CONFLICT DO NOTHING',
            [$area_id, $poi['type'], $poi['name'], $poi['lat'], $poi['lng']]
        );
    }
    return $fetched;
}

/**
 * Overpass API から周辺施設を取得する。
 *
 * @return array<int, array{type: string, name: string, lat: float, lng: float}>
 */
function overpass_fetch_pois(float $lat, float $lng, int $radius): array
{
    $query = '[out:json][timeout:15];('
        . "node[\"amenity\"=\"police\"](around:{$radius},{$lat},{$lng});"
        . "node[\"leisure\"=\"park\"](around:{$radius},{$lat},{$lng});"
        . "way[\"leisure\"=\"park\"](around:{$radius},{$lat},{$lng});"
        . "node[\"shop\"=\"convenience\"](around:{$radius},{$lat},{$lng});"
        . "node[\"shop\"=\"supermarket\"](around:{$radius},{$lat},{$lng});"
        . "node[\"amenity\"=\"hospital\"](around:{$radius},{$lat},{$lng});"
        . "node[\"amenity\"=\"clinic\"](around:{$radius},{$lat},{$lng});"
        . ');out center tags 30;';

    $url  = 'https://overpass-api.de/api/interpreter?data=' . urlencode($query);
    $json = http_get($url);
    if ($json === null) {
        return [];
    }

    $data     = json_decode($json, true);
    $elements = $data['elements'] ?? [];
    $rows     = [];

    foreach ($elements as $el) {
        $type = overpass_tag_to_type($el['tags'] ?? []);
        if ($type === null) {
            continue;
        }
        $name = $el['tags']['name'] ?? null;
        if ($name === null) {
            continue;
        }
        $elat = (float)($el['lat'] ?? $el['center']['lat'] ?? 0);
        $elng = (float)($el['lon'] ?? $el['center']['lon'] ?? 0);
        if ($elat === 0.0 || $elng === 0.0) {
            continue;
        }
        $rows[] = ['type' => $type, 'name' => $name, 'lat' => $elat, 'lng' => $elng];
        if (count($rows) >= 15) {
            break;
        }
    }

    return $rows;
}

/**
 * カテゴリ・名前別の集計サマリーを返す。
 * 戻り値: [ 'convenience' => ['label'=>'コンビニ','icon'=>'store','total'=>103,'names'=>[...],'extra'=>N], ... ]
 */
function fetch_area_poi_summary($db, int $area_id): array
{
    $res = pg_query_params($db,
        "SELECT type,
                COALESCE(NULLIF(TRIM(name), ''), '（名称不明）') AS name,
                COUNT(*) AS cnt
         FROM pois
         WHERE area_id = \$1
         GROUP BY type, name
         ORDER BY type, cnt DESC",
        [$area_id]
    );
    if (!$res) return [];

    $summary = [];
    while ($row = pg_fetch_assoc($res)) {
        $type = $row['type'];
        if (!isset($summary[$type])) {
            $meta = poi_meta($type);
            $summary[$type] = ['label' => $meta['label'], 'icon' => $meta['icon'],
                               'total' => 0, 'names' => [], 'extra' => 0];
        }
        $summary[$type]['total'] += (int)$row['cnt'];
        if (count($summary[$type]['names']) < 10) {
            $summary[$type]['names'][] = ['name' => $row['name'], 'count' => (int)$row['cnt']];
        } else {
            $summary[$type]['extra']++;
        }
    }

    $order  = ['convenience', 'supermarket', 'park', 'hospital', 'police'];
    $sorted = [];
    foreach ($order as $key) {
        if (isset($summary[$key])) $sorted[$key] = $summary[$key];
    }
    foreach ($summary as $key => $val) {
        if (!isset($sorted[$key])) $sorted[$key] = $val;
    }
    return $sorted;
}

function overpass_tag_to_type(array $tags): ?string
{
    if (($tags['amenity'] ?? '') === 'police') {
        return 'police';
    }
    if (($tags['leisure'] ?? '') === 'park') {
        return 'park';
    }
    if (($tags['shop'] ?? '') === 'convenience') {
        return 'convenience';
    }
    if (($tags['shop'] ?? '') === 'supermarket') {
        return 'supermarket';
    }
    if (in_array($tags['amenity'] ?? '', ['hospital', 'clinic'], true)) {
        return 'hospital';
    }
    return null;
}
