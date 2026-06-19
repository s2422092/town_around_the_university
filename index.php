<?php
/**
 * フロントコントローラー（ルーター）
 * ?page=xxx で pages/ 内の対応ファイルを読み込む。
 * 不正なページ名は dashboard にフォールバック。
 * 起動: php -S localhost:8000
 */
$page = trim($_GET['page'] ?? 'dashboard');

$allowed_pages = [
    'dashboard',
    'home',
    'detail',
    'university',
    'account',
    'login',
    'register',
];

if (!in_array($page, $allowed_pages, true)) {
    $page = 'dashboard';
}

require __DIR__ . "/pages/{$page}.php";
