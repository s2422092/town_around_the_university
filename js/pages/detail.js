/* detail.js — エリア詳細ページ */

document.addEventListener('DOMContentLoaded', () => {
    if (typeof L === 'undefined') {
        setTimeout(initMap, 300);
    } else {
        initMap();
    }
    animateScores();
    initPoiCardTilt();
});

function initMap() {
    const mapEl = document.getElementById('map');
    if (!mapEl) return;

    if (typeof L === 'undefined') {
        mapEl.innerHTML = '<p style="padding:1rem;color:#64748b;">地図を読み込めませんでした。インターネット接続を確認してください。</p>';
        return;
    }
    if (!window.AREA_DATA) return;

    const { lat, lng, name, pois, campus } = window.AREA_DATA;

    const map = L.map('map').setView([lat, lng], 14);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a> contributors',
        maxZoom: 19,
    }).addTo(map);

    // エリア中心マーカー
    L.marker([lat, lng])
        .addTo(map)
        .bindPopup(`<b>${name}</b><br>エリア中心`)
        .openPopup();

    // 施設マーカー
    const iconName = { police: 'local_police', park: 'park', convenience: 'store', supermarket: 'shopping_cart', hospital: 'local_hospital' };
    (pois || []).forEach(poi => {
        const icon = iconName[poi.type] || 'place';
        const divIcon = L.divIcon({
            html: `<span class="leaflet-poi-icon material-icons">${icon}</span>`,
            className: '',
            iconSize: [28, 28],
            iconAnchor: [14, 14],
        });
        L.marker([poi.lat, poi.lng], { icon: divIcon })
            .addTo(map)
            .bindPopup(`<span class="material-icons" style="vertical-align:middle;font-size:1rem;">${icon}</span> <b>${poi.name}</b>`);
    });

    // キャンパスマーカー（赤ピン） + 直線 + 距離ラベル
    if (campus) {
        // 赤い location_on ピン
        const campusIcon = L.divIcon({
            html: '<span class="material-icons" style="color:#dc2626;font-size:2.4rem;line-height:1;display:block;filter:drop-shadow(0 2px 6px rgba(220,38,38,0.5));">location_on</span>',
            className: '',
            iconSize:   [38, 38],
            iconAnchor: [19, 38],
        });
        L.marker([campus.lat, campus.lng], { icon: campusIcon })
            .addTo(map)
            .bindPopup(`<span class="material-icons" style="vertical-align:middle;font-size:1rem;color:#dc2626;">location_on</span> <b>${campus.name || '大学キャンパス'}</b>`);

        // 赤い直線
        L.polyline([[campus.lat, campus.lng], [lat, lng]], {
            color:     '#dc2626',
            weight:    2,
            dashArray: '7 5',
            opacity:   0.75,
        }).addTo(map);

        // 直線の中点に距離ラベル
        if (campus.distance_km != null) {
            const midLat = (campus.lat + lat) / 2;
            const midLng = (campus.lng + lng) / 2;
            const labelIcon = L.divIcon({
                html: `<span class="map-dist-label">${campus.distance_km} km</span>`,
                className: '',
                iconSize:   [70, 24],
                iconAnchor: [35, 12],
            });
            L.marker([midLat, midLng], { icon: labelIcon, interactive: false }).addTo(map);
        }

        map.fitBounds([[campus.lat, campus.lng], [lat, lng]], { padding: [60, 60] });
    }
}

/* ── POI カード 3D チルト ── */
function initPoiCardTilt() {
    document.querySelectorAll('.poi-cat').forEach(card => {
        card.addEventListener('mouseenter', () => {
            card.style.transition = 'transform 0.08s ease, box-shadow 0.08s ease';
        });
        card.addEventListener('mousemove', e => {
            const r  = card.getBoundingClientRect();
            const nx = (e.clientX - r.left  - r.width  / 2) / (r.width  / 2);
            const ny = (e.clientY - r.top   - r.height / 2) / (r.height / 2);
            card.style.transform =
                `perspective(700px) rotateX(${-ny * 7}deg) rotateY(${nx * 7}deg) translateY(-3px) scale(1.018)`;
        });
        card.addEventListener('mouseleave', () => {
            card.style.transition = 'transform 0.5s ease, box-shadow 0.5s ease';
            card.style.transform  = '';
        });
    });
}

function animateScores() {
    document.querySelectorAll('.score-value[data-target]').forEach(el => {
        const target = parseInt(el.dataset.target, 10);
        if (isNaN(target) || target === 0) return;

        let current = 0;
        const step = Math.max(1, Math.ceil(target / 40));
        const timer = setInterval(() => {
            current = Math.min(current + step, target);
            el.textContent = current;
            if (current >= target) clearInterval(timer);
        }, 20);
    });
}
