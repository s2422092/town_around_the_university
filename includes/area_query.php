<?php
/**
 * includes/area_query.php — 登録キャンパス周辺エリアの動的ランキング
 *
 * 事前計算済みの area_scores に依存せず、キャンパスの緯度経度から
 * その場で距離（Haversine）・家賃相場・周辺施設数を集計して並べ替える。
 * これにより「新しく入力された大学」でも即座にエリア候補を表示できる。
 *
 * 使い方:
 *   require __DIR__ . '/area_query.php';
 *   $areas = ranked_areas($db, $lat, $lng, $radius_km, $priority);
 */

/**
 * キャンパス座標を基準に周辺エリアをランキングして返す。
 *
 * @param  resource $db        pg_connect リソース
 * @param  float    $lat       キャンパス緯度
 * @param  float    $lng       キャンパス経度
 * @param  int      $radius_km 検索半径（km）
 * @param  string   $priority  'near' | 'cheap' | 'livable'
 * @param  int      $limit     最大件数
 * @return array<int, array<string, mixed>> エリア行の配列
 */
/**
 * @param  int|null $rent_max 月額家賃上限（円）。null = 上限なし
 */
function ranked_areas($db, float $lat, float $lng, int $radius_km = 20, string $priority = 'near', int $limit = 12, ?int $rent_max = null): array
{
    // 優先順位ごとの並び替え。距離は常にタイブレークに使う。
    $order = match ($priority) {
        'cheap'   => 'price_per_tatami ASC NULLS LAST, distance_km ASC',
        'livable' => 'poi_count DESC, distance_km ASC',
        default   => 'distance_km ASC',
    };

    // Haversine 距離（km）を SQL で計算。acos の引数は浮動小数誤差対策で 1 にクランプする。
    $sql = <<<SQL
        SELECT
            a.id,
            a.name,
            a.lat,
            a.lng,
            ROUND(
                (6371 * acos(
                    LEAST(1, GREATEST(-1,
                        cos(radians($1)) * cos(radians(a.lat)) * cos(radians(a.lng - $2))
                        + sin(radians($1)) * sin(radians(a.lat))
                    ))
                ))::numeric, 1
            ) AS distance_km,
            rs.price_per_tatami,
            COALESCE(pc.poi_count, 0) AS poi_count
        FROM areas a
        LEFT JOIN rent_stats rs
               ON rs.region_code = a.prefecture_code
              AND rs.structure_type = '全構造'
        LEFT JOIN (
            SELECT area_id, COUNT(*) AS poi_count
            FROM pois
            GROUP BY area_id
        ) pc ON pc.area_id = a.id
        WHERE a.lat IS NOT NULL AND a.lng IS NOT NULL
    SQL;

    // 距離フィルタは計算列を使うのでサブクエリでラップする
    // rent_max が指定されている場合: price_per_tatami * 16 ≒ ワンルーム月額家賃
    $rent_clause = '';
    $params      = [$lat, $lng, $radius_km, $limit];
    if ($rent_max !== null) {
        $params[]    = $rent_max;
        $idx         = count($params);
        $rent_clause = "AND (price_per_tatami IS NULL OR price_per_tatami * 16 <= \${$idx})";
    }

    $wrapped = "SELECT * FROM ($sql) t WHERE distance_km <= \$3 $rent_clause ORDER BY $order LIMIT \$4";

    $result = pg_query_params($db, $wrapped, $params);
    if (!$result) {
        return [];
    }

    $rows = [];
    while ($row = pg_fetch_assoc($result)) {
        $row['badges'] = area_badges($row, $priority);
        $rows[] = $row;
    }
    return $rows;
}

/**
 * エリア1件の指標から表示用バッジ（near/cheap/livable）を決める。
 *
 * @param  array<string, mixed> $row
 * @return string[] バッジキーの配列
 */
function area_badges(array $row, string $priority): array
{
    $badges = [];
    if ((float) $row['distance_km'] <= 5.0) {
        $badges[] = 'near';
    }
    if ($row['price_per_tatami'] !== null && (float) $row['price_per_tatami'] <= 5000) {
        $badges[] = 'cheap';
    }
    if ((int) $row['poi_count'] >= 3) {
        $badges[] = 'livable';
    }
    // 何も該当しない場合は優先カテゴリのバッジを付けて空表示を避ける
    if (empty($badges)) {
        $badges[] = $priority;
    }
    return $badges;
}

/**
 * 1畳あたり単価から「○.○万円〜」形式の家賃相場表示を作る。
 * ワンルーム想定（約16畳=専有面積換算のざっくり係数）で月額概算を出す。
 *
 * @param  mixed $price_per_tatami 1畳単価（円）。NULL 可
 * @return string 表示用文字列
 */
function format_rent(mixed $price_per_tatami): string
{
    if ($price_per_tatami === null || $price_per_tatami === '') {
        return '―';
    }
    // ワンルーム約16畳相当で概算（あくまで目安）
    $monthly = (float) $price_per_tatami * 16;
    $man = $monthly / 10000;
    return number_format($man, 1) . '万円〜';
}
