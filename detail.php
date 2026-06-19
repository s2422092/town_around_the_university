<?php
/* 担当者:（空欄） / この画面でやること: 特定エリア1件の詳細情報（スコア・周辺施設・地図・物件リンク）を表示する。実装時は ?id= パラメータでDBからエリア情報を取得する */
$page_title   = 'エリア詳細 | 大学周辺の家';
$current_page = 'home';
require 'includes/header.php';

/* ダミーデータ（後でDB連携に差し替え） */
$area = [
  'name'       => '〇〇市△△区',
  'distance'   => '12.3',
  'time'       => '約25分（電車）',
  'rent'       => '5.2万円〜',
  'cheapness'  => 72,
  'closeness'  => 88,
  'livability' => 81,
];

$pois = [
  ['icon' => '🚔', 'type' => '交番',      'name' => '△△交番',        'distance' => '徒歩3分'],
  ['icon' => '🌳', 'type' => '公園',      'name' => '〇〇中央公園',   'distance' => '徒歩5分'],
  ['icon' => '🏪', 'type' => 'コンビニ',  'name' => 'コンビニ △△店', 'distance' => '徒歩2分'],
  ['icon' => '🛒', 'type' => 'スーパー',  'name' => 'スーパー ××店', 'distance' => '徒歩7分'],
  ['icon' => '🏥', 'type' => '病院',      'name' => '△△クリニック',  'distance' => '徒歩10分'],
];
?>

<main>

  <!-- ページ上部 -->
  <div class="detail-header">
    <div>
      <a href="home.php" style="font-size:0.85rem; color:var(--color-primary); text-decoration:none;">← エリア一覧に戻る</a>
      <h1 style="font-size:1.6rem; font-weight:700; margin-top:0.5rem;"><?= htmlspecialchars($area['name']) ?></h1>
      <p class="text-muted mt-1">
        📍 直線距離 <?= $area['distance'] ?> km &nbsp;|&nbsp;
        🚃 <?= $area['time'] ?> &nbsp;|&nbsp;
        💴 家賃相場 <?= $area['rent'] ?>
      </p>
    </div>
    <div style="display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center;">
      <span class="badge badge-near" style="font-size:0.85rem; padding:0.3rem 0.75rem;">近さ ★</span>
      <span class="badge badge-livable" style="font-size:0.85rem; padding:0.3rem 0.75rem;">住みやすさ ★</span>
    </div>
  </div>

  <!-- スコアボックス -->
  <div class="score-grid">
    <div class="score-box cheap">
      <div class="score-label">安さスコア</div>
      <div class="score-value"><?= $area['cheapness'] ?></div>
      <div style="font-size:0.75rem; color:var(--color-text-muted);">/ 100</div>
    </div>
    <div class="score-box near">
      <div class="score-label">近さスコア</div>
      <div class="score-value"><?= $area['closeness'] ?></div>
      <div style="font-size:0.75rem; color:var(--color-text-muted);">/ 100</div>
    </div>
    <div class="score-box livable">
      <div class="score-label">住みやすさスコア</div>
      <div class="score-value"><?= $area['livability'] ?></div>
      <div style="font-size:0.75rem; color:var(--color-text-muted);">/ 100</div>
    </div>
  </div>

  <!-- 地図プレースホルダ -->
  <div class="placeholder-map">
    🗺️ 地図（OpenStreetMap 埋め込み予定）
  </div>

  <!-- 周辺施設 -->
  <div class="card mb-2">
    <h2 class="section-title">周辺施設（OpenStreetMap から取得予定）</h2>
    <ul class="poi-list">
      <?php foreach ($pois as $poi): ?>
      <li>
        <span class="poi-icon"><?= $poi['icon'] ?></span>
        <span style="flex:1;"><?= htmlspecialchars($poi['name']) ?></span>
        <span class="text-muted"><?= $poi['type'] ?> &nbsp;·&nbsp; <?= $poi['distance'] ?></span>
      </li>
      <?php endforeach; ?>
    </ul>
    <p class="text-muted mt-2" style="font-size:0.78rem;">※ 施設データは Overpass API から取得・キャッシュします（実装予定）</p>
  </div>

  <!-- 交通情報 -->
  <div class="card mb-2">
    <h2 class="section-title">通学時間・交通費（ダミー）</h2>
    <table style="width:100%; border-collapse:collapse; font-size:0.9rem;">
      <thead>
        <tr style="border-bottom:2px solid var(--color-border); text-align:left;">
          <th style="padding:0.5rem 0.75rem;">交通手段</th>
          <th style="padding:0.5rem 0.75rem;">所要時間</th>
          <th style="padding:0.5rem 0.75rem;">運賃目安</th>
        </tr>
      </thead>
      <tbody>
        <tr style="border-bottom:1px solid var(--color-border);">
          <td style="padding:0.5rem 0.75rem;">🚃 電車</td>
          <td style="padding:0.5rem 0.75rem;">約25分</td>
          <td style="padding:0.5rem 0.75rem;">320円</td>
        </tr>
        <tr style="border-bottom:1px solid var(--color-border);">
          <td style="padding:0.5rem 0.75rem;">🚌 バス</td>
          <td style="padding:0.5rem 0.75rem;">約35分</td>
          <td style="padding:0.5rem 0.75rem;">210円</td>
        </tr>
        <tr style="border-bottom:1px solid var(--color-border);">
          <td style="padding:0.5rem 0.75rem;">🚲 自転車</td>
          <td style="padding:0.5rem 0.75rem;">約45分</td>
          <td style="padding:0.5rem 0.75rem;">無料</td>
        </tr>
        <tr>
          <td style="padding:0.5rem 0.75rem;">🚶 徒歩</td>
          <td style="padding:0.5rem 0.75rem;">約150分</td>
          <td style="padding:0.5rem 0.75rem;">無料</td>
        </tr>
      </tbody>
    </table>
    <p class="text-muted mt-2" style="font-size:0.78rem;">※ 実所要時間・運賃は Google Maps Directions API で取得予定</p>
  </div>

  <!-- 物件リンク -->
  <div class="card">
    <h2 class="section-title">物件を探す（外部ポータル）</h2>
    <p class="text-muted mb-1">このエリアの物件を外部ポータルで検索できます（URLは動的生成予定）</p>
    <div class="portal-links">
      <a class="btn btn-primary" href="#" target="_blank" rel="noopener">SUUMO で探す</a>
      <a class="btn btn-outline" href="#" target="_blank" rel="noopener">HOME'S で探す</a>
      <a class="btn btn-outline" href="#" target="_blank" rel="noopener">athome で探す</a>
    </div>
  </div>

</main>

<?php require 'includes/footer.php'; ?>
