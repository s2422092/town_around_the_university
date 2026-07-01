<?php
/**
 * includes/routing.php — 道路ルーティングによる通学時間の算出
 *
 * OSRM の公開デモサーバ（APIキー不要）の table サービスを使い、
 * キャンパス1点から複数エリアまでの「道路の動線」上の距離・所要時間を
 * 1リクエストでまとめて取得する。
 *
 *   https://router.project-osrm.org/table/v1/driving/{coords}?sources=0&annotations=distance,duration
 *
 * 対応手段は道路系（徒歩・自転車・タクシー/車）のみ。
 * 電車・バスは GTFS-JP / 経路探索API が必要なため別フェーズで対応する。
 */

require_once __DIR__ . '/geocode.php'; // http_get() を利用

/** 道路ルーティングで対応する交通手段 */
const ROAD_MODES = ['walk', 'bike', 'taxi'];

/**
 * 指定手段が道路ルーティングで算出可能か。
 */
function is_road_mode(string $mode): bool
{
    return in_array($mode, ROAD_MODES, true);
}

/**
 * キャンパス1点から複数エリアまでの道路距離・車所要時間を一括取得する。
 *
 * @param  float                         $lat   キャンパス緯度
 * @param  float                         $lng   キャンパス経度
 * @param  array<int, array{lat: float, lng: float}> $dests エリア座標の配列
 * @return array<int, array{distance_km: float, driving_min: float}|null>
 *         $dests と同じ並びで結果を返す。取得失敗要素は null。全体失敗時は空配列。
 */
function osrm_table(float $lat, float $lng, array $dests): array
{
    if (empty($dests)) {
        return [];
    }

    // 経度,緯度 の順で「起点;目的地1;目的地2…」を組み立てる
    $coords = "{$lng},{$lat}";
    foreach ($dests as $d) {
        $coords .= ';' . $d['lng'] . ',' . $d['lat'];
    }

    $url = 'https://router.project-osrm.org/table/v1/driving/' . $coords
         . '?sources=0&annotations=distance,duration';

    $json = http_get($url);
    if ($json === null) {
        return [];
    }

    $data = json_decode($json, true);
    if (!is_array($data) || ($data['code'] ?? '') !== 'Ok') {
        return [];
    }

    $durations = $data['durations'][0] ?? null; // 秒
    $distances = $data['distances'][0] ?? null; // メートル
    if (!is_array($durations) || !is_array($distances)) {
        return [];
    }

    $out = [];
    // index 0 は起点自身なので 1 から
    foreach ($dests as $i => $_) {
        $sec = $durations[$i + 1] ?? null;
        $met = $distances[$i + 1] ?? null;
        if ($sec === null || $met === null) {
            $out[$i] = null;
            continue;
        }
        $out[$i] = [
            'distance_km' => $met / 1000,
            'driving_min' => $sec / 60,
        ];
    }
    return $out;
}

/**
 * 道路距離・車所要時間から、手段ごとの通学時間（分）を求める。
 * タクシー/車はOSRMの所要時間をそのまま、徒歩・自転車は道路距離÷標準速度で概算する。
 *
 * @param  string $mode        'walk' | 'bike' | 'taxi'
 * @param  float  $distance_km 道路距離（km）
 * @param  float  $driving_min 車での所要時間（分）
 * @return int|null 所要時間（分）。対応外手段は null
 */
function mode_minutes(string $mode, float $distance_km, float $driving_min): ?int
{
    return match ($mode) {
        'taxi'  => (int) ceil($driving_min),
        'bike'  => (int) ceil($distance_km / 15.0 * 60),   // 自転車 約15km/h
        'walk'  => (int) ceil($distance_km / 4.8 * 60),    // 徒歩 約4.8km/h
        default => null,                                    // train / bus は別フェーズ
    };
}

/**
 * 手段キーの表示用ラベル（アイコン付き）。
 */
function mode_label(string $mode): string
{
    $map = [
        'walk'  => ['icon' => 'directions_walk', 'label' => '徒歩'],
        'bike'  => ['icon' => 'directions_bike', 'label' => '自転車'],
        'taxi'  => ['icon' => 'local_taxi',      'label' => 'タクシー'],
        'train' => ['icon' => 'train',           'label' => '電車'],
        'bus'   => ['icon' => 'directions_bus',  'label' => 'バス'],
    ];
    $item = $map[$mode] ?? ['icon' => 'commute', 'label' => $mode];
    return '<span class="material-icons mi-sm">' . $item['icon'] . '</span> ' . $item['label'];
}
