/* js/pages/dashboard.js — ダッシュボード 3D エフェクト */

document.addEventListener('DOMContentLoaded', () => {
    initScrollReveal();
    initCardTilt();
    initVanta();
});

/* ── スクロールリビール ────────────────────────────── */
function initScrollReveal() {
    const targets = document.querySelectorAll('.feature-card, .area-card, .score-box');
    if (!targets.length || !window.IntersectionObserver) return;

    const observer = new IntersectionObserver((entries) => {
        let delay = 0;
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                setTimeout(() => entry.target.classList.add('is-revealed'), delay);
                delay += 70;
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.08 });

    targets.forEach(el => {
        el.classList.add('will-reveal');
        observer.observe(el);
    });
}

/* ── エリアカード 3D チルト ──────────────────────── */
function initCardTilt() {
    document.querySelectorAll('.area-card').forEach(card => {
        card.addEventListener('mouseenter', () => {
            card.style.transition = 'transform 0.08s ease, box-shadow 0.08s ease';
        });
        card.addEventListener('mousemove', e => {
            const r = card.getBoundingClientRect();
            const x = (e.clientX - r.left  - r.width  / 2) / (r.width  / 2);
            const y = (e.clientY - r.top   - r.height / 2) / (r.height / 2);
            card.style.transform = `perspective(900px) rotateX(${-y * 7}deg) rotateY(${x * 7}deg) translateY(-6px) scale(1.02)`;
            card.style.boxShadow = `${-x * 10}px ${-y * 10}px 36px rgba(79,70,229,0.2)`;
        });
        card.addEventListener('mouseleave', () => {
            card.style.transition = 'transform 0.5s ease, box-shadow 0.5s ease';
            card.style.transform  = '';
            card.style.boxShadow  = '';
        });
    });
}

/* ── Vanta 3D 背景（複数インスタンス） ──────────── */
async function initVanta() {
    try {
        await loadScript('https://cdnjs.cloudflare.com/ajax/libs/three.js/r134/three.min.js');
        await loadScript('https://cdn.jsdelivr.net/npm/vanta@latest/dist/vanta.net.min.js');

        /* ヒーロー：ダークスレート + シアン */
        if (document.getElementById('hero-bg')) {
            VANTA.NET({
                el:              '#hero-bg',
                mouseControls:   true,
                touchControls:   true,
                gyroControls:    false,
                color:           0x06b6d4,
                backgroundColor: 0x0f172a,
                points:          9,
                maxDistance:     22,
                spacing:         18,
            });
        }

        /* アプリ紹介：ダーク + ライトインディゴ（密度・間隔を変えて差別化） */
        if (document.getElementById('app-about-bg')) {
            VANTA.NET({
                el:              '#app-about-bg',
                mouseControls:   true,
                touchControls:   true,
                gyroControls:    false,
                color:           0x818cf8,
                backgroundColor: 0x0f172a,
                points:          7,
                maxDistance:     28,
                spacing:         22,
            });
        }

    } catch (e) {
        /* CDN 読み込み失敗時は CSS グラデーションのまま表示 */
    }
}

function loadScript(src) {
    return new Promise((resolve, reject) => {
        if (document.querySelector(`script[src="${src}"]`)) { resolve(); return; }
        const s   = document.createElement('script');
        s.src     = src;
        s.onload  = resolve;
        s.onerror = reject;
        document.head.appendChild(s);
    });
}
