# データベース セットアップ＆操作ガイド

## ファイル構成

```
db/
├── schema.sql      … テーブル定義（CREATE TABLE）
├── seed.sql        … サンプルデータ（開発用）
├── connection.php  … PHP 共通接続ユーティリティ
├── check.php       … 接続確認ツール
└── README.md       … このファイル
```

---

## 初回セットアップ（チームメンバー全員が行う）

### 手順1：自分の `.env` を作成する

プロジェクトルートで実行：

```bash
cp .env.example .env
```

`.env` を開いて `DB_USER` を **自分のMacユーザー名** に書き換える。

```
# 自分のユーザー名がわからない場合は whoami コマンドで確認
DB_HOST=localhost
DB_PORT=5432
DB_NAME=town_around_the_university
DB_USER=← whoami の結果を入れる
DB_PASSWORD=
```

> ⚠ `.env` は Git にコミットしないこと（`.gitignore` で除外済み）

---

### 手順2：データベースを作成する

```bash
createdb town_around_the_university
```

---

### 手順3：テーブルを作成する

```bash
psql -U $(whoami) -h localhost -d town_around_the_university -f db/schema.sql
```

---

### 手順4：サンプルデータを入れる

```bash
psql -U $(whoami) -h localhost -d town_around_the_university -f db/seed.sql
```

---

### 手順5：接続確認する

**CLI で確認：**

```bash
php db/check.php
```

**ブラウザで確認：**

```bash
php -S localhost:8000
# → http://localhost:8000/db/check.php を開く
```

---

## データベースへの接続方法（psql）

### 接続する

```bash
psql -U $(whoami) -h localhost -d town_around_the_university
```

成功すると以下のプロンプトが出る：

```
town_around_the_university=#
```

### 終了する

```
\q
```

---

## psql でよく使うコマンド

| コマンド | 内容 |
|---|---|
| `\dt` | テーブル一覧を表示 |
| `\d テーブル名` | テーブルのカラム定義を表示 |
| `\l` | データベース一覧を表示 |
| `\q` | psql を終了 |

---

## よく使うSQL

```sql
-- 大学一覧
SELECT * FROM universities;

-- キャンパス一覧（大学名も一緒に）
SELECT c.name AS campus, u.name AS university, c.address, c.lat, c.lng
FROM campuses c
JOIN universities u ON u.id = c.university_id;

-- エリア一覧
SELECT * FROM areas;

-- 東大本郷キャンパス周辺のエリアスコア
SELECT a.name, s.distance_km, s.cheapness_score, s.closeness_score, s.livability_score
FROM area_scores s
JOIN areas a ON a.id = s.area_id
WHERE s.campus_id = 1
ORDER BY s.closeness_score DESC;

-- 登録ユーザー一覧
SELECT user_id, uname FROM users;
```

---

## テーブル一覧

| テーブル | 役割 |
|---|---|
| `universities` | 大学マスタ |
| `campuses` | キャンパスマスタ（緯度経度付き） |
| `areas` | エリア（市区町村）★主役 |
| `rent_stats` | 家賃相場（e-Stat） |
| `stations` | 駅マスタ（国土数値情報） |
| `profiles` | ユーザー情報（Supabase Auth と連携予定） |
| `pois` | 周辺施設（OpenStreetMap） |
| `commutes` | 通学時間・運賃 |
| `area_scores` | エリアスコア事前計算 |
| `favorites` | お気に入りエリア |
| `users` | ローカル認証ユーザー（login/register で使用） |

---

## トラブルシューティング

### 「接続できません」と表示される

1. PostgreSQL が起動しているか確認する：
   ```bash
   pg_isready
   ```
2. 起動していない場合：
   ```bash
   brew services start postgresql@14
   ```

### 「データベースが存在しません」と表示される

手順2 の `createdb` を実行する。

### 「ユーザーが存在しません」と表示される

`.env` の `DB_USER` が正しいか確認する：
```bash
whoami
```
