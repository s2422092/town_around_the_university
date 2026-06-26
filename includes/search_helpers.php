<?php
function api_json_get($url, $cache_key, $ttl = 1800) {
    $cache_dir = __DIR__ . '/../cache';

    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0777, true);
    }

    $cache_file = $cache_dir . '/' . $cache_key . '.json';

    if (is_file($cache_file) && time() - filemtime($cache_file) < $ttl) {
        return json_decode(file_get_contents($cache_file), true);
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 20,
            'header' => "User-Agent: town-around-university/1.0\r\n",
        ],
    ]);

    $json = @file_get_contents($url, false, $context);

    if ($json === false) {
        return is_file($cache_file) ? json_decode(file_get_contents($cache_file), true) : null;
    }

    file_put_contents($cache_file, $json);
    return json_decode($json, true);
}

function search_gsi_candidates($keyword) {
    $url = 'https://msearch.gsi.go.jp/address-search/AddressSearch?q=' . urlencode($keyword);
    $data = api_json_get($url, 'gsi_' . md5($keyword), 86400);
    $is_address = looks_like_japanese_address($keyword);
    $candidates = [];

    foreach (($data ?? []) as $item) {
        if (empty($item['geometry']['coordinates']) || empty($item['properties']['title'])) {
            continue;
        }

        $title = $item['properties']['title'];

        if (!$is_address && strpos($title, $keyword) === false) {
            continue;
        }

        if (!$is_address && (
            strpos($title, '高等学校') !== false ||
            strpos($title, '中学校') !== false ||
            strpos($title, '高等学院') !== false
        )) {
            continue;
        }

        $candidates[] = [
            'title' => $title,
            'lat' => (float)$item['geometry']['coordinates'][1],
            'lng' => (float)$item['geometry']['coordinates'][0],
            'source' => '国土地理院',
        ];
    }

    usort($candidates, function ($a, $b) use ($keyword) {
        $score_a = place_candidate_score($a['title'], $keyword);
        $score_b = place_candidate_score($b['title'], $keyword);

        return $score_b <=> $score_a;
    });

    return unique_place_candidates($candidates);
}

function place_candidate_score($title, $keyword) {
    $score = 0;

    if (strpos($title, $keyword) !== false) {
        $score += 50;
    }

    if (strpos($title, 'キャンパス') !== false) {
        $score += 40;
    }

    if (strpos($title, '大学') !== false) {
        $score += 30;
    }

    if (strpos($title, '高等学校') !== false || strpos($title, '中学校') !== false || strpos($title, '高等学院') !== false) {
        $score -= 50;
    }

    return $score;
}

function looks_like_japanese_address($keyword) {
    return preg_match('/[都道府県市区町村郡].*[0-9０-９]/u', $keyword) === 1;
}

function unique_place_candidates($candidates) {
    $unique = [];
    $seen = [];

    foreach ($candidates as $candidate) {
        $key = round($candidate['lat'], 5) . '_' . round($candidate['lng'], 5);

        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $unique[] = $candidate;
    }

    return $unique;
}

function search_osm_university_candidates($keyword) {
    $keyword_regex = str_replace('"', '\\"', $keyword);
    $query = '
[out:json][timeout:25];
area["ISO3166-1"="JP"][admin_level=2]->.jp;
(
  node["amenity"~"university|college"]["name"~"' . $keyword_regex . '",i](area.jp);
  way["amenity"~"university|college"]["name"~"' . $keyword_regex . '",i](area.jp);
  relation["amenity"~"university|college"]["name"~"' . $keyword_regex . '",i](area.jp);
  node["amenity"~"university|college"]["name:ja"~"' . $keyword_regex . '",i](area.jp);
  way["amenity"~"university|college"]["name:ja"~"' . $keyword_regex . '",i](area.jp);
  relation["amenity"~"university|college"]["name:ja"~"' . $keyword_regex . '",i](area.jp);
);
out center tags 10;
';

    $url = 'https://overpass-api.de/api/interpreter?data=' . urlencode($query);
    $data = api_json_get($url, 'universities_' . md5($keyword), 86400);
    $candidates = [];

    foreach (($data['elements'] ?? []) as $item) {
        $lat = $item['lat'] ?? $item['center']['lat'] ?? null;
        $lng = $item['lon'] ?? $item['center']['lon'] ?? null;
        $name = $item['tags']['name:ja'] ?? $item['tags']['name'] ?? $keyword;

        if ($lat === null || $lng === null) {
            continue;
        }

        $candidates[] = [
            'title' => $name,
            'lat' => (float)$lat,
            'lng' => (float)$lng,
            'source' => 'OpenStreetMap',
        ];
    }

    return unique_place_candidates($candidates);
}

function search_place_candidates($keyword) {
    $university_candidates = search_osm_university_candidates($keyword);

    if (count($university_candidates) > 0) {
        return $university_candidates;
    }

    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'format' => 'jsonv2',
        'countrycodes' => 'jp',
        'limit' => 6,
        'addressdetails' => 1,
        'q' => $keyword,
    ]);

    $data = api_json_get($url, 'nominatim_' . md5($keyword), 86400);
    $candidates = [];

    foreach (($data ?? []) as $item) {
        if (empty($item['lat']) || empty($item['lon']) || empty($item['display_name'])) {
            continue;
        }

        $candidates[] = [
            'title' => $item['display_name'],
            'lat' => (float)$item['lat'],
            'lng' => (float)$item['lon'],
            'source' => 'OpenStreetMap',
        ];
    }

    if (count($candidates) > 0) {
        return unique_place_candidates($candidates);
    }

    $gsi_candidates = search_gsi_candidates($keyword);

    if (count($gsi_candidates) > 0) {
        return $gsi_candidates;
    }

    return [];
}

function distance_km($lat1, $lng1, $lat2, $lng2) {
    $earth = 6371;
    $d_lat = deg2rad($lat2 - $lat1);
    $d_lng = deg2rad($lng2 - $lng1);

    $a = sin($d_lat / 2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
        * sin($d_lng / 2) ** 2;

    return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

function search_nearby_stations($lat, $lng, $radius) {
    $query = '
[out:json][timeout:25];
(
  node["railway"="station"](around:' . (int)$radius . ',' . $lat . ',' . $lng . ');
  node["public_transport"="station"](around:' . (int)$radius . ',' . $lat . ',' . $lng . ');
);
out body 40;
';

    $url = 'https://overpass-api.de/api/interpreter?data=' . urlencode($query);
    $data = api_json_get($url, 'stations_' . md5($lat . '_' . $lng . '_' . $radius), 1800);

    return $data['elements'] ?? [];
}

function search_nearby_pois($lat, $lng, $radius = 1000) {
    $query = '
[out:json][timeout:25];
(
  node["shop"="convenience"](around:' . (int)$radius . ',' . $lat . ',' . $lng . ');
  way["shop"="convenience"](around:' . (int)$radius . ',' . $lat . ',' . $lng . ');
  relation["shop"="convenience"](around:' . (int)$radius . ',' . $lat . ',' . $lng . ');
  node["amenity"="police"](around:' . (int)$radius . ',' . $lat . ',' . $lng . ');
  way["amenity"="police"](around:' . (int)$radius . ',' . $lat . ',' . $lng . ');
  relation["amenity"="police"](around:' . (int)$radius . ',' . $lat . ',' . $lng . ');
  node["leisure"="park"](around:' . (int)$radius . ',' . $lat . ',' . $lng . ');
  way["leisure"="park"](around:' . (int)$radius . ',' . $lat . ',' . $lng . ');
  relation["leisure"="park"](around:' . (int)$radius . ',' . $lat . ',' . $lng . ');
);
out center tags 30;
';

    $url = 'https://overpass-api.de/api/interpreter?data=' . urlencode($query);
    $data = api_json_get($url, 'pois_' . md5($lat . '_' . $lng . '_' . $radius), 1800);

    return $data['elements'] ?? [];
}
