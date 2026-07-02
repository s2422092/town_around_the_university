#!/bin/bash
set -e

echo "=== 大学周辺の家 — 起動スクリプト ==="

# DATABASE_URL をパースして接続確認（最大30秒）
wait_for_db() {
    echo "データベース接続を待機中..."
    for i in $(seq 1 15); do
        php -r "
            \$url = getenv('DATABASE_URL');
            if (!\$url) { exit(1); }
            \$p = parse_url(\$url);
            \$conn = @pg_connect(
                'host='    . \$p['host'] .
                ' port='   . (\$p['port'] ?? 5432) .
                ' dbname=' . ltrim(\$p['path'], '/') .
                ' user='   . \$p['user'] .
                ' password=' . (\$p['pass'] ?? '')
            );
            exit(\$conn ? 0 : 1);
        " && return 0
        echo "  待機中... ($i/15)"
        sleep 2
    done
    echo "データベースに接続できませんでした。" >&2
    exit 1
}

wait_for_db

# マイグレーション（IF NOT EXISTS で冪等）
echo "スキーマ・シードデータを適用中..."
php /var/www/html/db/migrate.php && echo "マイグレーション完了"

echo "Apache を起動..."
exec apache2-foreground
