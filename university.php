<?php
/* 担当者:（空欄） / この画面でやること: ユーザーが大学・キャンパス・希望条件（家賃・優先カテゴリ・交通手段）を入力するフォーム。実装時はPOST送信でDB保存する */
$page_title   = '大学情報入力 | 大学周辺の家';
$current_page = 'university';
require 'includes/header.php';
?>

<main>

  <div class="page-hero">
    <h1>大学情報の入力</h1>
    <p>通うキャンパスと希望条件を登録してください。エリア比較に使用します。</p>
  </div>

  <div class="notice">
    ℹ️ このフォームは現在見た目のみです。送信ボタンを押しても保存されません（実装予定）。
  </div>

  <div class="card">
    <!-- action 空 = 実送信しない -->
    <form action="" method="post">

      <!-- セクション1: 大学・キャンパス -->
      <div class="form-section">
        <div class="form-section-title">1. 大学・キャンパス</div>

        <div class="form-group">
          <label for="university_name">大学名</label>
          <input type="text" id="university_name" name="university_name"
                 placeholder="例：〇〇大学">
        </div>

        <div class="form-group">
          <label for="campus_name">キャンパス名</label>
          <input type="text" id="campus_name" name="campus_name"
                 placeholder="例：本キャンパス・△△キャンパス">
        </div>

        <div class="form-group">
          <label for="campus_address">キャンパス住所</label>
          <input type="text" id="campus_address" name="campus_address"
                 placeholder="例：東京都〇〇区△△1-2-3">
          <span class="form-hint">入力後、緯度経度を国土地理院APIで自動取得します（実装予定）</span>
        </div>
      </div>

      <!-- セクション2: 希望の家賃 -->
      <div class="form-section">
        <div class="form-section-title">2. 希望の家賃</div>

        <div class="form-group">
          <label for="rent_max">家賃上限（月額）</label>
          <select id="rent_max" name="rent_max">
            <option value="">上限なし</option>
            <option value="30000">3 万円</option>
            <option value="40000">4 万円</option>
            <option value="50000">5 万円</option>
            <option value="60000">6 万円</option>
            <option value="70000">7 万円</option>
            <option value="80000">8 万円</option>
            <option value="100000">10 万円</option>
          </select>
        </div>
      </div>

      <!-- セクション3: 優先カテゴリ -->
      <div class="form-section">
        <div class="form-section-title">3. 優先カテゴリ</div>
        <p class="text-muted mb-1">エリア表示の優先順位を選択してください。</p>

        <div class="form-group">
          <div class="radio-group">
            <label>
              <input type="radio" name="priority" value="near" checked>
              近さ優先
            </label>
            <label>
              <input type="radio" name="priority" value="cheap">
              安さ優先
            </label>
            <label>
              <input type="radio" name="priority" value="livable">
              住みやすさ優先
            </label>
          </div>
        </div>
      </div>

      <!-- セクション4: 交通手段 -->
      <div class="form-section">
        <div class="form-section-title">4. 主な交通手段</div>
        <p class="text-muted mb-1">通学に使う交通手段をすべて選択してください。</p>

        <div class="form-group">
          <div class="checkbox-group">
            <label><input type="checkbox" name="transport[]" value="train"> 電車</label>
            <label><input type="checkbox" name="transport[]" value="bus">   バス</label>
            <label><input type="checkbox" name="transport[]" value="bike">  自転車</label>
            <label><input type="checkbox" name="transport[]" value="walk">  徒歩</label>
            <label><input type="checkbox" name="transport[]" value="taxi">  タクシー</label>
          </div>
        </div>
      </div>

      <!-- セクション5: 距離絞り込み -->
      <div class="form-section">
        <div class="form-section-title">5. 検索する距離範囲</div>

        <div class="form-group">
          <div class="radio-group">
            <label><input type="radio" name="radius" value="10"> 10 km 以内</label>
            <label><input type="radio" name="radius" value="20" checked> 20 km 以内</label>
            <label><input type="radio" name="radius" value="30"> 30 km 以内</label>
          </div>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%; padding:0.75rem;">
        登録して検索する（未実装）
      </button>

    </form>
  </div>

</main>

<?php require 'includes/footer.php'; ?>
