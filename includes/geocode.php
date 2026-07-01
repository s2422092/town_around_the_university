<?php
/**
 * includes/geocode.php — 住所→緯度経度 変換ユーティリティ
 *
 * 国土地理院（GSI）の住所検索APIを利用してジオコーディングする。
 * エンドポイント: https://msearch.gsi.go.jp/address-search/AddressSearch?q=<住所>
 * レスポンスは GeoJSON 形式で、coordinates は [経度, 緯度] の順。
 *
 * 使い方:
 *   require __DIR__ . '/geocode.php';
 *   $coords = gsi_geocode('東京都文京区本郷7-3-1');
 *   if ($coords !== null) { [$lat, $lng] = $coords; }
 */

/**
 * 住所文字列から緯度経度を取得する。
 *
 * @param  string $address 住所（例: '東京都文京区本郷7-3-1'）
 * @return array{0: float, 1: float}|null [緯度, 経度]。取得失敗時は null
 */
function gsi_geocode(string $address): ?array
{
    $address = trim($address);
    if ($address === '') {
        return null;
    }

    $url = 'https://msearch.gsi.go.jp/address-search/AddressSearch?q=' . rawurlencode($address);

    $json = http_get($url);
    if ($json === null) {
        return null;
    }

    $data = json_decode($json, true);
    if (!is_array($data) || empty($data)) {
        return null;
    }

    // 先頭（最も確からしい）候補の座標を採用する
    $coords = $data[0]['geometry']['coordinates'] ?? null;
    if (!is_array($coords) || count($coords) < 2) {
        return null;
    }

    $lng = (float) $coords[0];
    $lat = (float) $coords[1];

    // 日本国内の妥当な範囲かをざっくり検証する
    if ($lat < 20 || $lat > 46 || $lng < 122 || $lng > 154) {
        return null;
    }

    return [$lat, $lng];
}

/**
 * URL を GET して本文を返す。curl が使えればそれを、無ければ
 * file_get_contents をフォールバックとして使う。
 *
 * @return string|null 本文。失敗時は null
 */
function http_get(string $url): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_USERAGENT      => 'town-around-the-university/1.0',
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // PHP 8.0+ では curl_close() は不要（ハンドルはGCで解放される）
        if ($body !== false && $code >= 200 && $code < 300) {
            return (string) $body;
        }
        return null;
    }

    // curl が無い環境向けフォールバック
    $context = stream_context_create([
        'http' => ['timeout' => 10, 'header' => "User-Agent: town-around-the-university/1.0\r\n"],
    ]);
    $body = @file_get_contents($url, false, $context);
    return $body === false ? null : $body;
}
