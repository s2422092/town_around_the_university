<?php
/**
 * db/check.php — データベース接続確認ツール
 *
 * ブラウザ: http://localhost:8000/db/check.php
 * CLI:     php db/check.php
 *
 * ⚠ 本番環境では必ず削除またはアクセス制限をかけること。
 */

require __DIR__ . '/connection.php';

$is_cli = (PHP_SAPI === 'cli');

// ---- ヘルパー ----
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }
function out(string $text, string $html = ''): void
{
    if (PHP_SAPI === 'cli') {
        echo $text . PHP_EOL;
    } else {
        echo ($html !== '' ? $html : nl2br(h($text))) . PHP_EOL;
    }
}

// ---- HTML ヘッダー（ブラウザ時のみ） ----
if (!$is_cli): ?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>DB 接続確認 | 大学周辺の家</title>
  <style>
    body { font-family: monospace; max-width: 900px; margin: 2rem auto; padding: 0 1rem; }
    h1   { font-size: 1.3rem; border-bottom: 2px solid #2563eb; padding-bottom: .4rem; }
    h2   { font-size: 1rem; margin-top: 1.5rem; color: #1d4ed8; }
    .ok  { color: #166534; font-weight: bold; }
    .err { color: #991b1b; font-weight: bold; }
    table { border-collapse: collapse; width: 100%; margin-top: .5rem; font-size: .9rem; }
    th, td { border: 1px solid #e2e8f0; padding: .4rem .75rem; text-align: left; }
    th { background: #f1f5f9; }
    pre { background: #f8fafc; border: 1px solid #e2e8f0; padding: 1rem; border-radius: 6px; overflow-x: auto; }
    .warn { background: #fef9c3; border: 1px solid #fde047; padding: .5rem 1rem; border-radius: 6px; font-size: .85rem; margin-top: 1rem;}
  </style>
</head>
<body>
<h1>DB 接続確認ツール</h1>
<p class="warn">⚠ このページは開発用です。チームメンバー以外に公開しないでください。</p>
<?php endif;

// ---- 接続テスト ----
out("\n=== 接続テスト ===");
try {
    $db = db_connect();
    $row = pg_fetch_row(pg_query($db, "SELECT current_user, current_database(), version()"));
    $info = [
        'ユーザー'       => $row[0],
        'データベース'   => $row[1],
        'PostgreSQL版'  => $row[2],
    ];
    out('接続 OK', '<p class="ok">✅ 接続 OK</p>');
    if (!$is_cli) {
        echo '<table><tr><th>項目</th><th>値</th></tr>';
        foreach ($info as $k => $v) {
            echo "<tr><td>" . h($k) . "</td><td>" . h($v) . "</td></tr>";
        }
        echo '</table>';
    } else {
        foreach ($info as $k => $v) { echo "  {$k}: {$v}\n"; }
    }
} catch (RuntimeException $e) {
    out('接続失敗: ' . $e->getMessage(), '<p class="err">❌ 接続失敗: ' . h($e->getMessage()) . '</p>');
    if (!$is_cli) { echo '</body></html>'; }
    exit(1);
}

// ---- テーブル一覧 & 行数 ----
out("\n=== テーブル一覧 ===");
$res = pg_query($db, "
    SELECT
        t.table_name,
        obj_description(pc.oid, 'pg_class') AS comment,
        (SELECT count(*) FROM information_schema.columns c
         WHERE c.table_name = t.table_name AND c.table_schema = 'public') AS cols,
        (SELECT reltuples::bigint FROM pg_class pc2
         JOIN pg_namespace pn ON pn.oid = pc2.relnamespace
         WHERE pc2.relname = t.table_name AND pn.nspname = 'public') AS approx_rows
    FROM information_schema.tables t
    JOIN pg_class pc ON pc.relname = t.table_name
    JOIN pg_namespace pn ON pn.oid = pc.relnamespace AND pn.nspname = 'public'
    WHERE t.table_schema = 'public'
    ORDER BY t.table_name
");

if (!$is_cli) {
    echo '<h2>テーブル一覧</h2>';
    echo '<table><tr><th>テーブル名</th><th>説明</th><th>カラム数</th><th>おおよその行数</th></tr>';
    while ($row = pg_fetch_assoc($res)) {
        echo '<tr>'
           . '<td>' . h($row['table_name'])   . '</td>'
           . '<td>' . h($row['comment'] ?? '') . '</td>'
           . '<td style="text-align:center">' . h($row['cols'])        . '</td>'
           . '<td style="text-align:center">' . h($row['approx_rows']) . '</td>'
           . '</tr>';
    }
    echo '</table>';
} else {
    printf("  %-20s %-6s %s\n", 'テーブル', 'カラム', '説明');
    echo "  " . str_repeat('-', 60) . "\n";
    while ($row = pg_fetch_assoc($res)) {
        printf("  %-20s %-6s %s\n", $row['table_name'], $row['cols'], $row['comment'] ?? '');
    }
}

// ---- users テーブル（登録確認） ----
out("\n=== users テーブルの内容 ===");
$res2 = pg_query($db, "SELECT user_id, uname, LEFT(upass,20)||'...' AS upass_head FROM users ORDER BY user_id");
$count = pg_num_rows($res2);

if (!$is_cli) {
    echo '<h2>users テーブル（登録ユーザー確認）</h2>';
    if ($count === 0) {
        echo '<p>まだユーザーが登録されていません。</p>';
    } else {
        echo '<table><tr><th>user_id</th><th>uname</th><th>パスワードハッシュ（先頭）</th></tr>';
        while ($row = pg_fetch_assoc($res2)) {
            echo '<tr>'
               . '<td>' . h($row['user_id'])    . '</td>'
               . '<td>' . h($row['uname'])       . '</td>'
               . '<td>' . h($row['upass_head'])  . '</td>'
               . '</tr>';
        }
        echo '</table>';
    }
} else {
    if ($count === 0) {
        echo "  （まだユーザーが登録されていません）\n";
    } else {
        printf("  %-8s %-20s %s\n", 'user_id', 'uname', 'hash（先頭）');
        while ($row = pg_fetch_assoc($res2)) {
            printf("  %-8s %-20s %s\n", $row['user_id'], $row['uname'], $row['upass_head']);
        }
    }
}

// ---- .env の読み込み値を表示 ----
out("\n=== .env 読み込み値 ===");
load_env(__DIR__ . '/../.env');
$env_keys = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'APP_ENV'];

if (!$is_cli) {
    echo '<h2>.env 読み込み値</h2>';
    echo '<table><tr><th>キー</th><th>値</th></tr>';
    foreach ($env_keys as $k) {
        $v = $_ENV[$k] ?? '（未設定）';
        if ($k === 'DB_PASSWORD') $v = $v !== '' ? '***' : '（空）';
        echo '<tr><td>' . h($k) . '</td><td>' . h($v) . '</td></tr>';
    }
    echo '</table>';
    echo '</body></html>';
} else {
    foreach ($env_keys as $k) {
        $v = $_ENV[$k] ?? '（未設定）';
        printf("  %-14s = %s\n", $k, $v);
    }
    echo "\n完了。問題なければ接続設定は正しいです。\n";
}

pg_close($db);
