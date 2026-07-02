<?php
/**
 * db/migrate.php — CLIマイグレーションスクリプト
 * schema.sql → seed.sql の順で適用する（IF NOT EXISTS で冪等）
 * 実行: php db/migrate.php
 */

$url = getenv('DATABASE_URL');
if (!$url) {
    // ローカル .env フォールバック
    $env = __DIR__ . '/../.env';
    if (file_exists($env)) {
        foreach (file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
            putenv(trim($k) . '=' . trim($v));
        }
    }
    $url = getenv('DATABASE_URL') ?: null;
}

if ($url) {
    $p    = parse_url($url);
    $dsn  = 'host='     . $p['host']
          . ' port='    . ($p['port'] ?? 5432)
          . ' dbname='  . ltrim($p['path'], '/')
          . ' user='    . $p['user']
          . ' password=' . ($p['pass'] ?? '');
} else {
    $dsn = 'host='     . (getenv('DB_HOST')     ?: 'localhost')
         . ' port='    . (getenv('DB_PORT')     ?: '5432')
         . ' dbname='  . (getenv('DB_NAME')     ?: '')
         . ' user='    . (getenv('DB_USER')     ?: '')
         . ' password=' . (getenv('DB_PASSWORD') ?: '');
}

$conn = pg_connect($dsn);
if (!$conn) {
    fwrite(STDERR, "DB接続失敗\n");
    exit(1);
}

foreach (['schema.sql', 'seed.sql'] as $file) {
    $path = __DIR__ . '/' . $file;
    if (!file_exists($path)) {
        echo "スキップ: {$file} が見つかりません\n";
        continue;
    }
    $sql = file_get_contents($path);
    $ok  = pg_query($conn, $sql);
    if ($ok === false) {
        fwrite(STDERR, "{$file} 適用エラー: " . pg_last_error($conn) . "\n");
        exit(1);
    }
    echo "{$file} 適用完了\n";
}

pg_close($conn);
echo "マイグレーション完了\n";
