# 大学周辺の家 (town_around_the_university)

大学周辺で住みやすい街を探す Web アプリの**レベル1スケルトン**です。
ページ遷移のみが動く骨組みで、DB接続・認証・API連携は未実装です。

---

## フォルダ構成

```
town_around_the_university/
├── index.php              … フロントコントローラー（ルーター）
│
├── pages/                 … 各ページの PHP テンプレート
│   ├── dashboard.php      … ダッシュボード（トップ）
│   ├── home.php           … ホーム / エリア候補一覧
│   ├── detail.php         … エリア詳細
│   ├── university.php     … 大学情報入力フォーム
│   ├── account.php        … アカウント設定
│   ├── login.php          … ログイン
│   └── register.php       … 新規登録
│
├── includes/              … 全ページ共通の PHP パーツ
│   ├── header.php         … ヘッダー・ナビゲーション
│   └── footer.php         … フッター
│
├── css/
│   └── style.css          … 共通スタイルシート（1枚構成・レスポンシブ対応）
│
└── js/
    ├── main.js            … 共通 JS（ハンバーガーメニューなど）
    └── pages/             … ページ固有 JS（将来の実装用スタブ）
        ├── home.js        … フィルター・ソート処理（未実装）
        └── detail.js      … 地図・POI 表示（未実装）
```

---

## ローカル起動手順

PHP が入っていれば追加インストール不要です。

```bash
# プロジェクトルートへ移動
cd /path/to/town_around_the_university

# PHP ビルトインサーバーを起動
php -S localhost:8000

# ブラウザで開く → http://localhost:8000
```

---

## URL 一覧

| URL | ページ |
|---|---|
| `http://localhost:8000/` | ダッシュボード |
| `http://localhost:8000/?page=home` | ホーム / エリア候補一覧 |
| `http://localhost:8000/?page=detail&id=1` | エリア詳細 |
| `http://localhost:8000/?page=university` | 大学情報入力 |
| `http://localhost:8000/?page=account` | アカウント設定 |
| `http://localhost:8000/?page=login` | ログイン |
| `http://localhost:8000/?page=register` | 新規登録 |

---

## 各ファイルの役割

| ファイル | 役割 |
|---|---|
| `index.php` | ルーター。`?page=xxx` を見て `pages/` 内のファイルを require する |
| `pages/dashboard.php` | ダッシュボード。機能紹介・使い方ステップ・各画面への導線 |
| `pages/home.php` | フィルタバー＋ダミーカード3枚。各カードは `detail.php` へリンク |
| `pages/detail.php` | スコア・周辺施設・地図プレースホルダ・物件ポータルリンク |
| `pages/university.php` | 大学・キャンパス・希望条件の入力フォーム（送信処理なし） |
| `pages/account.php` | プロフィール・希望条件・ログアウトのプレースホルダ |
| `pages/login.php` | ログインフォーム（認証処理なし） |
| `pages/register.php` | 新規登録フォーム（登録処理なし） |
| `includes/header.php` | 共通ヘッダー。`$current_page` 変数でナビをハイライト。`$page_js` 変数でページ固有 JS を読み込む |
| `includes/footer.php` | 共通フッター |
| `css/style.css` | 全ページ共通スタイル |
| `js/main.js` | ハンバーガーメニュー等の共通 JS |
| `js/pages/home.js` | ホームページ固有 JS のスタブ（フィルター実装予定） |
| `js/pages/detail.js` | 詳細ページ固有 JS のスタブ（地図・POI 実装予定） |

---

## 実装方針メモ（各担当者向け）

- 各 `pages/*.php` の冒頭 `/* 担当者:（空欄） */` に担当者名を記入してください
- フォームの `action=""` を実際の処理先（同ファイル or API エンドポイント）に変更して実装してください
- ダミーデータは `/* ダミーデータ */` コメント以下にあります。DB 連携後は削除してください
- 認証は **Supabase Auth** を使用予定（`signUp` / `signInWithPassword`）
- ページ固有 JS を追加する場合は `pages/` 内のページファイルで `$page_js = 'xxxx.js';` を設定してください
- データベースは **PostgreSQL**（Supabase）を予定
