/* home.js — エリア候補ページ：Leaflet マップ */

document.addEventListener('DOMContentLoaded', () => {
    if (!window.HOME_DATA) return;

    if (typeof L === 'undefined') {
        setTimeout(initHomeMap, 400);
    } else {
        initHomeMap();
    }
});

function initHomeMap() {
    const { campus, areas } = window.HOME_DATA;
    const mapEl = document.getElementById('home-map');
    if (!mapEl) return;

    if (typeof L === 'undefined') {
        mapEl.innerHTML = '<p style="padding:2rem;text-align:center;color:#64748b;">地図を読み込めませんでした。インターネット接続を確認してください。</p>';
        return;
    }

    /* ── マップ初期化 ── */
    const map = L.map('home-map', { zoomControl: true }).setView([campus.lat, campus.lng], 11);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a> contributors',
        maxZoom: 19,
    }).addTo(map);

    /* ── 大学キャンパス：赤ピン ── */
    const campusIcon = L.divIcon({
        html: `<span class="material-icons" style="color:#dc2626;font-size:2.4rem;line-height:1;display:block;filter:drop-shadow(0 2px 6px rgba(220,38,38,0.55));">location_on</span>`,
        className: '',
        iconSize:    [32, 40],
        iconAnchor:  [16, 40],
        popupAnchor: [0, -42],
    });
    L.marker([campus.lat, campus.lng], { icon: campusIcon, zIndexOffset: 1000 })
        .addTo(map)
        .bindPopup(
            `<b style="font-size:0.95rem;">${campus.name}</b><br>` +
            `<span style="font-size:0.8rem;color:#64748b;">${campus.campus}</span>`,
            { maxWidth: 220 }
        );

    /* ── バッジ別カラー ── */
    const colors = {
        near:    { stroke: '#4338ca', fill: '#4f46e5' },
        cheap:   { stroke: '#15803d', fill: '#16a34a' },
        livable: { stroke: '#0e7490', fill: '#06b6d4' },
    };
    const defaultColors = { stroke: '#475569', fill: '#64748b' };

    const circleMap = {};

    areas.forEach(area => {
        if (!area.lat || !area.lng) return;

        const mainBadge = area.badges?.[0];
        const c         = colors[mainBadge] || defaultColors;

        const distText   = area.distance ? `${area.distance} km` : '―';
        const rentText   = area.rent || '―';
        const commuteStr = area.commute_min
            ? `<br><span style="color:#4338ca;">通学 約${area.commute_min}分</span>`
            : '';

        const tooltipHtml = `
            <div style="min-width:150px;font-family:inherit;">
                <div style="font-weight:700;font-size:0.92rem;margin-bottom:4px;">${area.name}</div>
                <div style="font-size:0.79rem;color:#334155;line-height:1.6;">
                    大学まで ${distText}${commuteStr}<br>
                    家賃相場 ${rentText}
                </div>
            </div>`;

        const circle = L.circle([area.lat, area.lng], {
            radius:      3500,
            color:       c.stroke,
            fillColor:   c.fill,
            fillOpacity: 0.18,
            weight:      2,
            opacity:     0.75,
        }).addTo(map);

        circle.bindTooltip(tooltipHtml, {
            sticky:    true,
            direction: 'top',
            offset:    [0, -12],
            className: 'home-map-tooltip',
        });

        circle.on('mouseover', function () {
            this.setStyle({ fillOpacity: 0.42, weight: 3, opacity: 1 });
            highlightCard(area.id, true);
        });
        circle.on('mouseout', function () {
            this.setStyle({ fillOpacity: 0.18, weight: 2, opacity: 0.75 });
            highlightCard(area.id, false);
        });
        circle.on('click', () => {
            window.location.href = `index.php?page=detail&id=${area.id}`;
        });

        circleMap[area.id] = circle;
    });

    /* ── カードホバー → マップ円ハイライト ── */
    document.querySelectorAll('.area-card[data-id]').forEach(card => {
        const id = parseInt(card.dataset.id, 10);
        card.addEventListener('mouseenter', () => {
            circleMap[id]?.setStyle({ fillOpacity: 0.42, weight: 3 });
        });
        card.addEventListener('mouseleave', () => {
            circleMap[id]?.setStyle({ fillOpacity: 0.18, weight: 2 });
        });
    });

    /* ── 全エリアが収まるように自動ズーム ── */
    const allPoints = [
        [campus.lat, campus.lng],
        ...areas.filter(a => a.lat && a.lng).map(a => [a.lat, a.lng]),
    ];
    if (allPoints.length > 1) {
        map.fitBounds(allPoints, { padding: [50, 50], maxZoom: 12 });
    }
}

function highlightCard(areaId, on) {
    const card = document.querySelector(`.area-card[data-id="${areaId}"]`);
    if (!card) return;
    card.classList.toggle('area-card--highlighted', on);
}
