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

            /* 住所→緯度経度（国土地理院API）。失敗しても登録は続行する */
            $coords = gsi_geocode($form['campus_address']);
            $lat    = $coords[0] ?? null;
            $lng    = $coords[1] ?? null;

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

            /* 2. キャンパスを取得 or 新規登録（同名キャンパスは住所・座標を更新） */
            $res = pg_query_params(
                $db,
                'SELECT id FROM campuses WHERE university_id = $1 AND name = $2',
                [$university_id, $form['campus_name']]
            );
            if ($res && pg_num_rows($res) > 0) {
                $campus_id = (int) pg_fetch_result($res, 0, 'id');
                pg_query_params(
                    $db,
                    'UPDATE campuses SET address = $1, lat = $2, lng = $3 WHERE id = $4',
                    [$form['campus_address'], $lat, $lng, $campus_id]
                );
            } else {
                $res = pg_query_params(
                    $db,
                    'INSERT INTO campuses (university_id, name, address, lat, lng)
                     VALUES ($1, $2, $3, $4, $5) RETURNING id',
                    [$university_id, $form['campus_name'], $form['campus_address'], $lat, $lng]
                );
                $campus_id = (int) pg_fetch_result($res, 0, 'id');
            }

            /* 3. 登録内容をセッションに保存（ダッシュボード・ホームで参照） */
            $_SESSION['registered'] = [
                'university_id'   => $university_id,
                'university_name' => $form['university_name'],
                'campus_id'       => $campus_id,
                'campus_name'     => $form['campus_name'],
                'campus_address'  => $form['campus_address'],
                'lat'             => $lat,
                'lng'             => $lng,
                'rent_max'        => $form['rent_max'] !== '' ? (int) $form['rent_max'] : null,
                'priority'        => $form['priority'],
                'transport'       => $form['transport'],
                'radius'          => (int) $form['radius'],
            ];

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

require __DIR__ . '/../includes/header.php';
?>

<main>

  <div class="page-hero">
    <h1>大学情報の入力</h1>
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
                 value="<?= htmlspecialchars($form['university_name']) ?>"
                 placeholder="例：〇〇大学" required>
        </div>

        <div class="form-group">
          <label for="campus_name">キャンパス名</label>
          <input type="text" id="campus_name" name="campus_name"
                 value="<?= htmlspecialchars($form['campus_name']) ?>"
                 placeholder="例：本キャンパス・△△キャンパス" required>
        </div>

        <div class="form-group">
          <label for="campus_address">キャンパス住所</label>
          <input type="text" id="campus_address" name="campus_address"
                 value="<?= htmlspecialchars($form['campus_address']) ?>"
                 placeholder="例：東京都〇〇区△△1-2-3" required>
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
