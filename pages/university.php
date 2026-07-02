<?php
/* 担当者:（空欄） / この画面でやること: ユーザーが大学・キャンパス・希望条件（家賃・優先カテゴリ・交通手段）を入力するフォーム。POST送信で大学・キャンパスをDBに保存し、選択をセッションに記録する。 */
session_start();

require __DIR__ . '/../db/connection.php';
require __DIR__ . '/../includes/geocode.php';

$page_title   = '大学情報入力 | 大学周辺の家';
$current_page = 'university';

$message      = '';
$message_type = '';   // 'success' | 'error'

/* フォーム初期値（前回入力を復元） */
$form = $_SESSION['university_form'] ?? [
    'university_name' => '',
    'campus_name'     => '',
    'campus_address'  => '',
    'rent_max'        => '',
    'priority'        => 'near',
    'transport'       => [],
    'radius'          => '20',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* 入力値を取得 */
    $form = [
        'university_name' => trim($_POST['university_name'] ?? ''),
        'campus_name'     => trim($_POST['campus_name'] ?? ''),
        'campus_address'  => trim($_POST['campus_address'] ?? ''),
        'rent_max'        => trim($_POST['rent_max'] ?? ''),
        'priority'        => $_POST['priority'] ?? 'near',
        'transport'       => $_POST['transport'] ?? [],
        'radius'          => $_POST['radius'] ?? '20',
    ];
    $_SESSION['university_form'] = $form;

    /* バリデーション */
    $errors = [];
    if ($form['university_name'] === '') {
        $errors[] = '大学名を入力してください。';
    }
    if ($form['campus_name'] === '') {
        $errors[] = 'キャンパス名を入力してください。';
    }
    if ($form['campus_address'] === '') {
        $errors[] = 'キャンパス住所を入力してください。';
    }
    if (!in_array($form['priority'], ['near', 'cheap', 'livable'], true)) {
        $form['priority'] = 'near';
    }

    if (empty($errors)) {
        try {
            $db = db_connect();

            /* 1. 大学を取得 or 新規登録 */
            $res = pg_query_params($db, 'SELECT id FROM universities WHERE name = $1', [$form['university_name']]);
            if ($res && pg_num_rows($res) > 0) {
                $university_id = (int) pg_fetch_result($res, 0, 'id');
            } else {
                $res = pg_query_params(
                    $db,
                    'INSERT INTO universities (name) VALUES ($1) RETURNING id',
                    [$form['university_name']]
                );
                $university_id = (int) pg_fetch_result($res, 0, 'id');
            }

            /* 2. キャンパスを取得 or 新規登録 */
            $res = pg_query_params(
                $db,
                'SELECT id, lat::float AS lat, lng::float AS lng FROM campuses WHERE university_id = $1 AND name = $2',
                [$university_id, $form['campus_name']]
            );
            if ($res && pg_num_rows($res) > 0) {
                /* 既存キャンパス → DB の正確な座標を優先（上書きしない） */
                $row       = pg_fetch_assoc($res);
                $campus_id = (int) $row['id'];
                $lat       = ($row['lat'] !== null && $row['lat'] != 0.0) ? (float) $row['lat'] : null;
                $lng       = ($row['lng'] !== null && $row['lng'] != 0.0) ? (float) $row['lng'] : null;
                /* 座標が未設定の場合のみ再取得 */
                if ($lat === null || $lng === null) {
                    $coords = nominatim_geocode($form['university_name'] . ' ' . $form['campus_name'])
                           ?? gsi_geocode($form['campus_address']);
                    $lat = $coords[0] ?? null;
                    $lng = $coords[1] ?? null;
                    if ($lat !== null) {
                        pg_query_params($db, 'UPDATE campuses SET lat=$1, lng=$2 WHERE id=$3', [$lat, $lng, $campus_id]);
                    }
                }
                /* 住所だけ更新 */
                pg_query_params($db, 'UPDATE campuses SET address=$1 WHERE id=$2', [$form['campus_address'], $campus_id]);
            } else {
                /* 新規キャンパス → Nominatim（大学名検索）→ GSI（住所）の順で取得 */
                $coords = nominatim_geocode($form['university_name'] . ' ' . $form['campus_name'])
                       ?? gsi_geocode($form['campus_address']);
                $lat = $coords[0] ?? null;
                $lng = $coords[1] ?? null;
                $res = pg_query_params(
                    $db,
                    'INSERT INTO campuses (university_id, name, address, lat, lng)
                     VALUES ($1, $2, $3, $4, $5) RETURNING id',
                    [$university_id, $form['campus_name'], $form['campus_address'], $lat, $lng]
                );
                $campus_id = (int) pg_fetch_result($res, 0, 'id');
            }

            /* 3. 登録内容をセッションに保存（ダッシュボード・ホームで参照） */
            $rent_max_val = $form['rent_max'] !== '' ? (int) $form['rent_max'] : null;
            $_SESSION['registered'] = [
                'university_id'   => $university_id,
                'university_name' => $form['university_name'],
                'campus_id'       => $campus_id,
                'campus_name'     => $form['campus_name'],
                'campus_address'  => $form['campus_address'],
                'lat'             => $lat,
                'lng'             => $lng,
                'rent_max'        => $rent_max_val,
                'priority'        => $form['priority'],
                'transport'       => $form['transport'],
                'radius'          => (int) $form['radius'],
            ];

            /* 4. 新規登録フロー（pending）なら自動ログインに昇格 */
            if (!empty($_SESSION['pending_user_id'])) {
                $_SESSION['user_id']  = $_SESSION['pending_user_id'];
                $_SESSION['username'] = $_SESSION['pending_username'] ?? '';
                unset($_SESSION['pending_user_id'], $_SESSION['pending_username']);
            }

            /* 5. ログイン済み（または直前に昇格）なら user_preferences に永続保存 */
            if (!empty($_SESSION['user_id'])) {
                pg_query_params($db, '
                    INSERT INTO user_preferences
                        (user_id, university_id, campus_id, rent_max, priority, transport, radius)
                    VALUES ($1, $2, $3, $4, $5, $6, $7)
                    ON CONFLICT (user_id) DO UPDATE SET
                        university_id = EXCLUDED.university_id,
                        campus_id     = EXCLUDED.campus_id,
                        rent_max      = EXCLUDED.rent_max,
                        priority      = EXCLUDED.priority,
                        transport     = EXCLUDED.transport,
                        radius        = EXCLUDED.radius
                ', [
                    $_SESSION['user_id'],
                    $university_id,
                    $campus_id,
                    $rent_max_val,
                    $form['priority'],
                    json_encode($form['transport']),
                    (int) $form['radius'],
                ]);
            }

            pg_close($db);

            /* ダッシュボードへリダイレクト */
            $geo_flag = ($lat === null) ? '&geo=0' : '';
            header('Location: index.php?page=dashboard&registered=1' . $geo_flag);
            exit;
        } catch (RuntimeException $e) {
            $message      = 'データベースに接続できませんでした。.env の設定を確認してください。';
            $message_type = 'error';
        }
    } else {
        $message      = implode(' ', $errors);
        $message_type = 'error';
    }
}

/* キャンパスデータをJSに渡す（オートコンプリート用） */
$campus_data = [];
try {
    $db_c = db_connect();
    $res_c = pg_query($db_c,
        'SELECT u.name AS uname, c.name AS cname, c.address
         FROM campuses c
         JOIN universities u ON u.id = c.university_id
         ORDER BY u.name, c.name'
    );
    while ($row = pg_fetch_assoc($res_c)) {
        $campus_data[$row['uname']][] = [
            'campus'  => $row['cname'],
            'address' => $row['address'] ?? '',
        ];
    }
    pg_close($db_c);
} catch (RuntimeException $e) {
    /* DB接続失敗時はオートコンプリートなし */
}

$page_js = 'university.js';

require __DIR__ . '/../includes/header.php';
?>
<script>window.CAMPUS_DATA = <?= json_encode($campus_data, JSON_UNESCAPED_UNICODE) ?>;</script>

<!-- 大学名サジェスト（PHP でサーバーサイド出力） -->
<datalist id="university-list">
<?php foreach (array_keys($campus_data) as $uname): ?>
  <option value="<?= htmlspecialchars($uname) ?>">
<?php endforeach; ?>
</datalist>

<!-- キャンパス名サジェスト（JS が動的に更新。初期値は現在の大学で出力） -->
<datalist id="campus-list">
<?php if (!empty($form['university_name']) && isset($campus_data[$form['university_name']])): ?>
  <?php foreach ($campus_data[$form['university_name']] as $c): ?>
    <option value="<?= htmlspecialchars($c['campus']) ?>">
  <?php endforeach; ?>
<?php endif; ?>
</datalist>

<main>

  <?php if (isset($_GET['setup'])): ?>
  <div class="notice" style="background:#dcfce7;border-color:#86efac;color:#166534;">
    <span class="material-icons mi-sm">check_circle</span>
    アカウントを作成しました。続けて通うキャンパスと希望条件を登録しましょう。
  </div>
  <?php endif; ?>

  <div class="page-hero">
    <h1><?= isset($_GET['setup']) ? '大学情報を登録しよう' : '大学情報の入力' ?></h1>
    <p>通うキャンパスと希望条件を登録してください。エリア比較に使用します。</p>
  </div>

  <?php if ($message !== ''): ?>
    <div class="notice" style="<?= $message_type === 'error'
        ? 'background:#fee2e2;border-color:#fca5a5;color:#991b1b;'
        : 'background:#dcfce7;border-color:#86efac;color:#166534;' ?>">
      <?= htmlspecialchars($message) ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <form action="index.php?page=university" method="post">

      <!-- セクション1: 大学・キャンパス -->
      <div class="form-section">
        <div class="form-section-title">1. 大学・キャンパス</div>

        <div class="form-group">
          <label for="university_name">大学名</label>
          <input type="text" id="university_name" name="university_name"
                 list="university-list"
                 value="<?= htmlspecialchars($form['university_name']) ?>"
                 placeholder="例：東京大学" required>
        </div>

        <div class="form-group">
          <label for="campus_name">キャンパス名</label>
          <input type="text" id="campus_name" name="campus_name"
                 list="campus-list"
                 value="<?= htmlspecialchars($form['campus_name']) ?>"
                 placeholder="例：本郷キャンパス" required>
          <span class="form-hint">大学名を入力するとキャンパス候補が表示されます。</span>
        </div>

        <div class="form-group">
          <label for="campus_address">キャンパス住所</label>
          <input type="text" id="campus_address" name="campus_address"
                 value="<?= htmlspecialchars($form['campus_address']) ?>"
                 placeholder="例：東京都文京区本郷7-3-1" required>
          <span id="autofill-hint" class="form-hint"
                style="display:none; opacity:0; transition:opacity 0.3s; color:var(--color-primary);">
            <span class="material-icons mi-xs">auto_fix_high</span>
            キャンパス住所を自動入力しました。必要に応じて修正できます。
          </span>
          <span class="form-hint">入力後、緯度経度を国土地理院APIで自動取得します。</span>
        </div>
      </div>

      <!-- セクション2: 希望の家賃 -->
      <div class="form-section">
        <div class="form-section-title">2. 希望の家賃</div>

        <div class="form-group">
          <label for="rent_max">家賃上限（月額）</label>
          <select id="rent_max" name="rent_max">
            <?php
            $rent_options = ['' => '上限なし', '30000' => '3 万円', '40000' => '4 万円',
                '50000' => '5 万円', '60000' => '6 万円', '70000' => '7 万円',
                '80000' => '8 万円', '100000' => '10 万円'];
            foreach ($rent_options as $val => $label): ?>
              <option value="<?= $val ?>" <?= (string) $form['rent_max'] === (string) $val ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- セクション3: 優先カテゴリ -->
      <div class="form-section">
        <div class="form-section-title">3. 優先カテゴリ</div>
        <p class="text-muted mb-1">エリア表示の優先順位を選択してください。</p>

        <div class="form-group">
          <div class="radio-group">
            <label><input type="radio" name="priority" value="near"    <?= $form['priority'] === 'near' ? 'checked' : '' ?>> 近さ優先</label>
            <label><input type="radio" name="priority" value="cheap"   <?= $form['priority'] === 'cheap' ? 'checked' : '' ?>> 安さ優先</label>
            <label><input type="radio" name="priority" value="livable" <?= $form['priority'] === 'livable' ? 'checked' : '' ?>> 住みやすさ優先</label>
          </div>
        </div>
      </div>

      <!-- セクション4: 交通手段 -->
      <div class="form-section">
        <div class="form-section-title">4. 主な交通手段</div>
        <p class="text-muted mb-1">通学に使う交通手段をすべて選択してください。</p>

        <div class="form-group">
          <div class="checkbox-group">
            <?php
            $transport_options = ['train' => '電車', 'bus' => 'バス', 'bike' => '自転車',
                'walk' => '徒歩', 'taxi' => 'タクシー'];
            foreach ($transport_options as $val => $label): ?>
              <label><input type="checkbox" name="transport[]" value="<?= $val ?>" <?= in_array($val, (array) $form['transport'], true) ? 'checked' : '' ?>> <?= $label ?></label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- セクション5: 距離絞り込み -->
      <div class="form-section">
        <div class="form-section-title">5. 検索する距離範囲</div>

        <div class="form-group">
          <div class="radio-group">
            <label><input type="radio" name="radius" value="10" <?= (string) $form['radius'] === '10' ? 'checked' : '' ?>> 10 km 以内</label>
            <label><input type="radio" name="radius" value="20" <?= (string) $form['radius'] === '20' ? 'checked' : '' ?>> 20 km 以内</label>
            <label><input type="radio" name="radius" value="30" <?= (string) $form['radius'] === '30' ? 'checked' : '' ?>> 30 km 以内</label>
          </div>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%; padding:0.75rem;">
        登録して検索する
      </button>

    </form>
  </div>

</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
