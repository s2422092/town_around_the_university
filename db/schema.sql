-- =============================================================
--  大学周辺の家 — PostgreSQL スキーマ定義
--  適用方法: psql -U <user> -d <database> -f db/schema.sql
--  Supabase で使う場合は SQL Editor に貼り付けて実行する。
--  ※ profiles.id は Supabase Auth の auth.users を参照する。
--    ローカル PostgreSQL の場合は末尾の注釈を参照。
-- =============================================================

BEGIN;

-- --------------------------------------------------
-- 1. universities（大学）
-- --------------------------------------------------
CREATE TABLE IF NOT EXISTS universities (
    id   SERIAL       PRIMARY KEY,
    name VARCHAR(200) NOT NULL
);

COMMENT ON TABLE  universities      IS '大学マスタ';
COMMENT ON COLUMN universities.name IS '大学名';


-- --------------------------------------------------
-- 2. campuses（キャンパス）
-- --------------------------------------------------
CREATE TABLE IF NOT EXISTS campuses (
    id            SERIAL        PRIMARY KEY,
    university_id INTEGER       NOT NULL REFERENCES universities(id) ON DELETE CASCADE,
    name          VARCHAR(200)  NOT NULL,
    address       TEXT,
    lat           NUMERIC(10,7),  -- 緯度（国土地理院ジオコーディングAPIで取得）
    lng           NUMERIC(10,7)   -- 経度
);

COMMENT ON TABLE  campuses               IS 'キャンパスマスタ';
COMMENT ON COLUMN campuses.lat           IS '緯度（WGS84）';
COMMENT ON COLUMN campuses.lng           IS '経度（WGS84）';

CREATE INDEX IF NOT EXISTS idx_campuses_university ON campuses(university_id);


-- --------------------------------------------------
-- 3. areas（エリア ★主役テーブル）
-- --------------------------------------------------
CREATE TABLE IF NOT EXISTS areas (
    id              SERIAL        PRIMARY KEY,
    name            VARCHAR(200)  NOT NULL,   -- 市区町村名
    prefecture_code CHAR(2),                  -- 都道府県コード（JIS X 0401）例: '13'
    city_code       CHAR(5),                  -- 市区町村コード（JIS X 0402）例: '13101'
    lat             NUMERIC(10,7),
    lng             NUMERIC(10,7)
);

COMMENT ON TABLE  areas                 IS 'エリア（市区町村）マスタ。スコア計算の主役';
COMMENT ON COLUMN areas.prefecture_code IS '都道府県コード（JIS X 0401）';
COMMENT ON COLUMN areas.city_code       IS '市区町村コード（JIS X 0402）';

CREATE INDEX IF NOT EXISTS idx_areas_city_code       ON areas(city_code);
CREATE INDEX IF NOT EXISTS idx_areas_prefecture_code ON areas(prefecture_code);


-- --------------------------------------------------
-- 4. rent_stats（家賃相場）
-- --------------------------------------------------
CREATE TABLE IF NOT EXISTS rent_stats (
    id               SERIAL        PRIMARY KEY,
    region_code      VARCHAR(10)   NOT NULL,  -- 都道府県/大都市コード（e-Stat に合わせる）
    structure_type   VARCHAR(50),             -- 構造種別 例: '木造', '鉄筋コンクリート'
    price_per_sqm    NUMERIC(8,2),            -- ㎡単価（円）
    price_per_tatami NUMERIC(8,2),            -- 1畳単価（円）
    survey_year      SMALLINT                 -- 調査年（例: 2023）
);

COMMENT ON TABLE  rent_stats                 IS 'e-Stat 住宅・土地統計調査の家賃相場';
COMMENT ON COLUMN rent_stats.region_code     IS 'e-Stat の地域コード';
COMMENT ON COLUMN rent_stats.price_per_sqm   IS '㎡単価（円）';
COMMENT ON COLUMN rent_stats.price_per_tatami IS '1畳単価（円）';

CREATE INDEX IF NOT EXISTS idx_rent_stats_region ON rent_stats(region_code);


-- --------------------------------------------------
-- 5. stations（駅）
-- --------------------------------------------------
CREATE TABLE IF NOT EXISTS stations (
    id        SERIAL        PRIMARY KEY,
    name      VARCHAR(100)  NOT NULL,
    line_name VARCHAR(100),           -- 路線名
    city_code CHAR(5),                -- 所在市区町村コード
    lat       NUMERIC(10,7),
    lng       NUMERIC(10,7)
);

COMMENT ON TABLE stations IS '国土数値情報（N02）から取得する駅マスタ';

CREATE INDEX IF NOT EXISTS idx_stations_city_code ON stations(city_code);


-- --------------------------------------------------
-- 6. profiles（ユーザープロフィール）
--    id は Supabase Auth の auth.users(id) を参照する。
--    ローカル PostgreSQL では REFERENCES 句を外すか、
--    auth スキーマを手動で作成する必要がある。
-- --------------------------------------------------
CREATE TABLE IF NOT EXISTS profiles (
    id                 UUID         PRIMARY KEY,
    -- Supabase 使用時は以下のように REFERENCES を追加する:
    -- id UUID PRIMARY KEY REFERENCES auth.users(id) ON DELETE CASCADE,
    name               VARCHAR(100),
    university_id      INTEGER      REFERENCES universities(id) ON DELETE SET NULL,
    campus_id          INTEGER      REFERENCES campuses(id)     ON DELETE SET NULL,
    preferred_rent_max INTEGER      CHECK (preferred_rent_max > 0),  -- 月額上限（円）
    priority           VARCHAR(10)  CHECK (priority IN ('cheap', 'near', 'livable'))
);

COMMENT ON TABLE  profiles                    IS 'ユーザープロフィール（Supabase Auth と 1:1 対応）';
COMMENT ON COLUMN profiles.preferred_rent_max IS '希望家賃上限（円/月）';
COMMENT ON COLUMN profiles.priority           IS '優先カテゴリ: cheap=安さ / near=近さ / livable=住みやすさ';


-- --------------------------------------------------
-- 7. pois（周辺施設）
-- --------------------------------------------------
CREATE TABLE IF NOT EXISTS pois (
    id      BIGSERIAL    PRIMARY KEY,
    area_id INTEGER      NOT NULL REFERENCES areas(id) ON DELETE CASCADE,
    type    VARCHAR(50)  NOT NULL,   -- police / park / convenience / supermarket / hospital
    name    VARCHAR(200),
    lat     NUMERIC(10,7) NOT NULL,
    lng     NUMERIC(10,7) NOT NULL
);

COMMENT ON TABLE  pois      IS 'OpenStreetMap Overpass API から取得した周辺施設。エリアごとに事前キャッシュ';
COMMENT ON COLUMN pois.type IS 'police=交番 / park=公園 / convenience=コンビニ / supermarket=スーパー / hospital=病院';

CREATE INDEX IF NOT EXISTS idx_pois_area_id      ON pois(area_id);
CREATE INDEX IF NOT EXISTS idx_pois_area_id_type ON pois(area_id, type);


-- --------------------------------------------------
-- 8. commutes（通学情報）
-- --------------------------------------------------
CREATE TABLE IF NOT EXISTS commutes (
    id           SERIAL       PRIMARY KEY,
    campus_id    INTEGER      NOT NULL REFERENCES campuses(id) ON DELETE CASCADE,
    area_id      INTEGER      NOT NULL REFERENCES areas(id)    ON DELETE CASCADE,
    mode         VARCHAR(20)  NOT NULL CHECK (mode IN ('train', 'bus', 'bike', 'walk', 'taxi')),
    duration_min INTEGER      CHECK (duration_min >= 0),  -- 所要時間（分）
    fare         INTEGER      CHECK (fare >= 0),           -- 運賃（円）
    updated_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE  commutes              IS '通学情報。Google Maps Directions API / GTFS-JP で取得';
COMMENT ON COLUMN commutes.mode         IS '交通手段: train=電車 / bus=バス / bike=自転車 / walk=徒歩 / taxi=タクシー';
COMMENT ON COLUMN commutes.duration_min IS '所要時間（分）';
COMMENT ON COLUMN commutes.fare         IS '運賃（円）';

CREATE INDEX IF NOT EXISTS idx_commutes_campus_area ON commutes(campus_id, area_id);


-- --------------------------------------------------
-- 9. area_scores（エリアスコア事前計算 ★主役）
--    PK は (campus_id, area_id) の複合キー
-- --------------------------------------------------
CREATE TABLE IF NOT EXISTS area_scores (
    campus_id        INTEGER       NOT NULL REFERENCES campuses(id) ON DELETE CASCADE,
    area_id          INTEGER       NOT NULL REFERENCES areas(id)    ON DELETE CASCADE,
    distance_km      NUMERIC(6,2)  CHECK (distance_km >= 0),  -- 直線距離（km、Haversine で計算）
    cheapness_score  SMALLINT      CHECK (cheapness_score  BETWEEN 0 AND 100),
    closeness_score  SMALLINT      CHECK (closeness_score  BETWEEN 0 AND 100),
    livability_score SMALLINT      CHECK (livability_score BETWEEN 0 AND 100),
    PRIMARY KEY (campus_id, area_id)
);

COMMENT ON TABLE  area_scores                  IS 'エリアスコア事前計算テーブル。バッチで更新する';
COMMENT ON COLUMN area_scores.distance_km      IS '大学キャンパスからの直線距離（km）';
COMMENT ON COLUMN area_scores.cheapness_score  IS '安さスコア（0〜100）';
COMMENT ON COLUMN area_scores.closeness_score  IS '近さスコア（0〜100）';
COMMENT ON COLUMN area_scores.livability_score IS '住みやすさスコア（0〜100）';

CREATE INDEX IF NOT EXISTS idx_area_scores_area_id ON area_scores(area_id);


-- --------------------------------------------------
-- 10. favorites（お気に入り）
-- --------------------------------------------------
CREATE TABLE IF NOT EXISTS favorites (
    id         SERIAL       PRIMARY KEY,
    user_id    UUID         NOT NULL REFERENCES profiles(id) ON DELETE CASCADE,
    area_id    INTEGER      NOT NULL REFERENCES areas(id)    ON DELETE CASCADE,
    created_at TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    UNIQUE (user_id, area_id)  -- 同じエリアを重複登録しない
);

COMMENT ON TABLE favorites IS 'ユーザーのお気に入りエリア';

CREATE INDEX IF NOT EXISTS idx_favorites_user_id ON favorites(user_id);

COMMIT;
