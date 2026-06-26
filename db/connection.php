<?php
/**
 * db/connection.php — PostgreSQL 接続ユーティリティ
 *
 * プロジェクトルートの .env を読み込んで接続情報を取得する。
 * 各ページから require する際は __DIR__ でパスを解決すること。
 *
 * 使い方:
 *   require __DIR__ . '/../../db/connection.php'; // pages/ からの場合
 *   $db = db_connect();
 *   $result = pg_query_params($db, 'SELECT ...', [...]);
 */

/**
 * プロジェクトルートの .env ファイルを読み込んで $_ENV に展開する。
 * KEY=VALUE 形式。# 始まりはコメント。空行はスキップ。
 */
function load_env(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $_ENV[trim($key)] = trim($value);
    }
}

/**
 * PostgreSQL に接続してリソースを返す。
 * 失敗時は例外を投げる。
 *
 * @return resource pg_connect リソース
 * @throws RuntimeException 接続失敗時
 */
function db_connect()
{
    // プロジェクトルートの .env を読む（何度呼ばれても一度だけ）
    static $loaded = false;
    if (!$loaded) {
        load_env(__DIR__ . '/../.env');
        $loaded = true;
    }

    $host     = $_ENV['DB_HOST']     ?? 'localhost';
    $port     = $_ENV['DB_PORT']     ?? '5432';
    $dbname   = $_ENV['DB_NAME']     ?? '';
    $user     = $_ENV['DB_USER']     ?? '';
    $password = $_ENV['DB_PASSWORD'] ?? '';

    $dsn = "host={$host} port={$port} dbname={$dbname} user={$user}";
    if ($password !== '') {
        $dsn .= " password={$password}";
    }

    $conn = pg_connect($dsn);
    if (!$conn) {
        throw new RuntimeException(
            '.env の接続情報を確認してください。pg_connect に失敗しました。'
        );
    }
    return $conn;
}
