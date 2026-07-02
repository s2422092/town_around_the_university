/* js/pages/university.js — 大学・キャンパスのオートコンプリート & 住所自動入力 */

document.addEventListener('DOMContentLoaded', () => {
    const data      = window.CAMPUS_DATA || {};
    const uniInput  = document.getElementById('university_name');
    const camInput  = document.getElementById('campus_name');
    const addrInput = document.getElementById('campus_address');
    const uniList   = document.getElementById('university-list');
    const camList   = document.getElementById('campus-list');
    const hint      = document.getElementById('autofill-hint');

    if (!uniInput || !camInput || !addrInput) return;

    /* ── 大学名のサジェストを datalist に追加 ── */
    Object.keys(data).forEach(uname => {
        const opt = document.createElement('option');
        opt.value = uname;
        uniList.appendChild(opt);
    });

    /* ── 大学名が変わったらキャンパス候補を更新 ── */
    function refreshCampusList() {
        camList.innerHTML = '';
        const campuses = data[uniInput.value.trim()] || [];
        campuses.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.campus;
            camList.appendChild(opt);
        });
    }

    uniInput.addEventListener('input',  refreshCampusList);
    uniInput.addEventListener('change', refreshCampusList);

    /* ── キャンパス名が確定したら住所を自動入力 ── */
    function tryAutoFillAddress() {
        const campuses = data[uniInput.value.trim()] || [];
        const matched  = campuses.find(c => c.campus === camInput.value.trim());
        if (matched && matched.address) {
            addrInput.value = matched.address;
            if (hint) {
                hint.style.display = 'flex';
                setTimeout(() => { hint.style.opacity = '1'; }, 10);
            }
        } else {
            if (hint) { hint.style.display = 'none'; hint.style.opacity = '0'; }
        }
    }

    camInput.addEventListener('change', tryAutoFillAddress);
    camInput.addEventListener('blur',   tryAutoFillAddress);

    /* ── ユーザーが住所を手動編集したらヒントを消す ── */
    addrInput.addEventListener('input', () => {
        if (hint) { hint.style.display = 'none'; hint.style.opacity = '0'; }
    });

    /* ── 初期値がある場合もキャンパス候補を構築 ── */
    if (uniInput.value.trim()) refreshCampusList();
});
