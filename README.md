# 大学周辺の家 (town_around_the_university)

大学周辺で住みやすい街を探す Web アプリの**レベル1スケルトン**です。
ページ遷移のみが動く骨組みで、DB接続・認証・API連携は未実装です。

---

## フォルダ構成

```
town_around_the_university/
├── index.php          … ダッシュボード（トップ）
├── home.php           … ホーム / エリア候補一覧
├── detail.php         … エリア詳細ページ
├── university.php     … 大学情報入力フォーム
├── account.php        … アカウント設定
├── login.php          … ログイン
├── register.php       … ユーザー登録
├── css/
│   └── style.css      … 共通スタイルシート（1枚構成）
├── includes/
│   ├── header.php     … 共通ヘッダー・ナビゲーション
│   └── footer.php     … 共通フッター
├── requirements_definition … 要件定義書
├── GITHUB_GUIDE.md
├── GIT_NAMING_RULES.md
└── README.md          … このファイル
```

---

## ローカル起動手順

PHP が入っていれば追加インストール不要です。

```bash
# プロジェクトルートへ移動
cd /path/to/town_around_the_university

# PHP ビルトインサーバーを起動
php -S localhost:8000

# ブラウザで開く
open http://localhost:8000
```

---

## 各ファイルの役割

| ファイル | 役割 |
|---|---|
| `index.php` | ダッシュボード。サービス説明・使い方ステップ・各画面への導線 |
| `home.php` | エリア候補一覧。フィルタバー＋ダミーカード3枚。各カードは `detail.php` へリンク |
| `detail.php` | エリア詳細。スコア3種・周辺施設リスト・地図プレースホルダ・物件ポータルリンク |
| `university.php` | 大学・キャンパス・希望条件の入力フォーム（見た目のみ、送信処理なし） |
| `account.php` | アカウント設定のプレースホルダ（プロフィール・希望条件・ログアウト） |
| `login.php` | ログインフォーム（見た目のみ、認証処理なし） |
| `register.php` | ユーザー登録フォーム（見た目のみ、登録処理なし） |
| `css/style.css` | 全ページ共通のスタイルシート。レスポンシブ対応 |
| `includes/header.php` | 共通ヘッダー・ナビゲーション。現在ページを `$current_page` 変数でハイライト |
| `includes/footer.php` | 共通フッター |

---

## 実装方針メモ（各担当者向け）

- 各 `.php` ファイルの冒頭 `/* 担当者:（空欄） / この画面でやること: ... */` に担当者名を記入してください
- フォームの `action=""` を実際のPHP処理ファイルまたは同一ファイルに変更して実装してください
- ダミーデータは `/* ダミーデータ */` コメント以下にあります。DB連携後は削除してください
- 認証は **Supabase Auth** を使用予定（`signUp` / `signInWithPassword`）
- データベースは **PostgreSQL**（Supabase）を予定
