/**
 * main.js — 全ページ共通スクリプト
 */

document.addEventListener('DOMContentLoaded', function () {

  // ハンバーガーメニュートグル（SP幅）
  const toggle = document.getElementById('navToggle');
  const nav    = document.getElementById('globalNav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      nav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', nav.classList.contains('open'));
    });
  }

  // ナビ外クリックで閉じる
  document.addEventListener('click', function (e) {
    if (nav && nav.classList.contains('open')) {
      if (!nav.contains(e.target) && !toggle.contains(e.target)) {
        nav.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
      }
    }
  });

});
