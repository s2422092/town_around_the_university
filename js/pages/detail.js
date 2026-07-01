/* detail.js — エリア詳細ページ */

document.addEventListener('DOMContentLoaded', () => {
    // Leaflet が CDN からまだ届いていない場合に少し待ってリトライする
    if (typeof L === 'undefined') {
        setTimeout(initMap, 300);
    } else {
        initMap();
    }
    animateScores();
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
    const iconEmoji = { police: '🚔', park: '🌳', convenience: '🏪', supermarket: '🛒', hospital: '🏥' };
    (pois || []).forEach(poi => {
        const emoji = iconEmoji[poi.type] || '📍';
        const divIcon = L.divIcon({
            html: `<span class="leaflet-poi-icon">${emoji}</span>`,
            className: '',
            iconSize: [28, 28],
            iconAnchor: [14, 14],
        });
        L.marker([poi.lat, poi.lng], { icon: divIcon })
            .addTo(map)
            .bindPopup(`${emoji} <b>${poi.name}</b>`);
    });

    // キャンパスマーカー + 直線
    if (campus) {
        const campusIcon = L.divIcon({
            html: '<span class="leaflet-poi-icon">🏫</span>',
            className: '',
            iconSize: [28, 28],
            iconAnchor: [14, 14],
        });
        L.marker([campus.lat, campus.lng], { icon: campusIcon })
            .addTo(map)
            .bindPopup(`🏫 <b>${campus.name || '大学キャンパス'}</b>`);

        L.polyline([[campus.lat, campus.lng], [lat, lng]], {
            color: '#2563eb',
            weight: 2,
            dashArray: '6 4',
            opacity: 0.65,
        }).addTo(map);

        map.fitBounds([[campus.lat, campus.lng], [lat, lng]], { padding: [50, 50] });
    }
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
