// Trang Bản đồ thu chi (pages/map.html)
// Phụ thuộc: auth.js (apiFetch, showToast, checkAuth), Leaflet 1.9.4 (biến toàn cục L).
//
// API sử dụng:
//   GET /category/list.php[?type=expense|income]
//   GET /transaction/map.php?month=YYYY-MM[&type=all|expense|income][&category=<id>]

const $ = (id) => document.getElementById(id);

let map = null;
let markerLayer = null;
let loadSeq = 0; // chống phản hồi cũ ghi đè phản hồi mới khi đổi bộ lọc nhanh

/* ============ Tiện ích ============ */
// Tháng hiện tại theo giờ máy, dạng YYYY-MM
function currentMonthStr() {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

// Rút gọn số tiền: 2200000 -> "2,2tr", 430000 -> "430k"
function shortMoney(v) {
    const n = Number(v) || 0;
    const fmt = (x) => x.toFixed(1).replace('.', ',').replace(/,0$/, '');
    if (n >= 1e9) return fmt(n / 1e9) + 'tỷ';
    if (n >= 999500) return fmt(n / 1e6) + 'tr';
    if (n >= 1e3) return Math.round(n / 1e3) + 'k';
    return String(Math.round(n));
}

// Định dạng tiền theo vi-VN, ví dụ 2.200.000 đ
function vnMoney(v) {
    return new Intl.NumberFormat('vi-VN').format(Math.round(Number(v) || 0)) + ' đ';
}

// 2026-11-05 -> 05/11/2026
function fmtDate(str) {
    const [y, m, d] = String(str || '').slice(0, 10).split('-');
    return y && m && d ? `${d}/${m}/${y}` : '';
}

// Màu danh mục chỉ nhận mã hex hợp lệ
function safeColor(c, fallback = '#4F46E5') {
    return /^#[0-9a-fA-F]{3,8}$/.test(c || '') ? c : fallback;
}

/* ============ Thông báo trên bản đồ ============ */
function showMessage(text, { error = false, icon = 'fa-map-location-dot' } = {}) {
    const box = $('mapMessage');
    box.className = 'map-message' + (error ? ' error' : '');
    const i = document.createElement('i');
    i.className = 'fa-solid ' + icon;
    box.replaceChildren(i, document.createTextNode(text));
    box.hidden = false;
}

function hideMessage() {
    $('mapMessage').hidden = true;
}

/* ============ Khởi tạo Bản đồ Leaflet ============ */
function initMap() {
    if (typeof L === 'undefined') {
        showMessage('Không tải được thư viện bản đồ. Vui lòng kiểm tra kết nối mạng rồi tải lại trang.', { error: true, icon: 'fa-triangle-exclamation' });
        return false;
    }
    map = L.map('map').setView([16.0544, 108.2022], 5);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);
    markerLayer = L.layerGroup().addTo(map);

    // Tự động tính lại kích thước khi vùng chứa thay đổi
    if ('ResizeObserver' in window) {
        new ResizeObserver(() => map.invalidateSize()).observe($('map'));
    }
    window.addEventListener('map:tab-shown', () => setTimeout(() => map.invalidateSize(), 0));
    return true;
}

// Kích thước marker tăng theo tổng tiền (40px -> 76px)
function markerSize(total, maxTotal) {
    if (!(maxTotal > 0)) return 40;
    return Math.round(40 + 36 * Math.sqrt(total / maxTotal));
}

function buildPopup(p) {
    const root = document.createElement('div');
    root.className = 'cm-popup';

    const name = document.createElement('strong');
    name.textContent = p.location_name || 'Chưa đặt tên địa điểm';
    const count = document.createElement('span');
    count.textContent = `${p.count} giao dịch`;
    root.append(name, count);

    // Khối tổng tiền theo loại điểm (thu nhập / chi tiêu / cả hai)
    if (p.point_type === 'mixed') {
        const row = document.createElement('div');
        row.style.cssText = 'display:flex; justify-content:space-between; gap:0.5rem; margin-top:0.25rem; font-weight:700; font-size:0.95rem;';
        row.innerHTML = `<span style="color:#10b981;">+${vnMoney(p.total_income)}</span><span style="color:#4F46E5;">-${vnMoney(p.total_expense)}</span>`;
        root.appendChild(row);
    } else if (p.point_type === 'income') {
        const total = document.createElement('span');
        total.className = 'cm-total';
        total.style.color = '#10b981';
        total.textContent = '+' + vnMoney(p.total);
        root.appendChild(total);
    } else {
        const total = document.createElement('span');
        total.className = 'cm-total';
        total.style.color = '#4F46E5';
        total.textContent = '-' + vnMoney(p.total);
        root.appendChild(total);
    }

    // Chi tiết từng giao dịch tại điểm này
    const items = Array.isArray(p.items) ? p.items : [];
    if (items.length) {
        const list = document.createElement('div');
        list.className = 'cm-items';
        items.forEach(it => {
            const row = document.createElement('div');
            row.className = 'cm-item';

            const dot = document.createElement('i');
            dot.className = 'cm-dot';
            dot.style.background = safeColor(it.category_color, it.type === 'income' ? '#10b981' : '#4F46E5');

            const info = document.createElement('div');
            info.className = 'cm-item-info';
            const title = document.createElement('div');
            title.className = 'cm-item-title';
            title.textContent = (it.type === 'income' ? '🟢 ' : '🟣 ') + (it.category_name || 'Khác');

            const sub = document.createElement('div');
            sub.className = 'cm-item-sub';
            sub.textContent = [fmtDate(it.date), it.note].filter(Boolean).join(' • ');
            info.append(title, sub);

            const amt = document.createElement('div');
            amt.className = 'cm-item-amt';
            if (it.type === 'income') {
                amt.style.color = '#10b981';
                amt.textContent = '+' + vnMoney(it.amount);
            } else {
                amt.style.color = '#ef4444';
                amt.textContent = '-' + vnMoney(it.amount);
            }

            row.append(dot, info, amt);
            list.appendChild(row);
        });
        root.appendChild(list);

        const hidden = p.count - items.length;
        if (hidden > 0) {
            const more = document.createElement('span');
            more.className = 'cm-more';
            more.textContent = `và ${hidden} giao dịch khác...`;
            root.appendChild(more);
        }
    }
    return root;
}

function renderPoints(points) {
    markerLayer.clearLayers();
    const maxTotal = Math.max(0, ...points.map(p => p.total));
    const bounds = [];

    points.forEach(p => {
        const size = markerSize(p.total, maxTotal);
        const fontSize = size >= 60 ? 14 : (size >= 48 ? 13 : 12);
        const label = document.createElement('div');
        label.style.fontSize = fontSize + 'px';

        let prefix = '';
        if (p.point_type === 'income') prefix = '+';
        else if (p.point_type === 'expense') prefix = '-';
        label.textContent = prefix + shortMoney(p.total);

        // Marker class tương ứng loại điểm (thu / chi / hỗn hợp)
        let markerClass = 'cm-marker';
        if (p.point_type === 'income') markerClass += ' income';
        else if (p.point_type === 'mixed') markerClass += ' mixed';

        const icon = L.divIcon({
            className: markerClass,
            html: label.outerHTML,
            iconSize: [size, size],
            iconAnchor: [size / 2, size / 2],
            popupAnchor: [0, -size / 2]
        });
        L.marker([p.lat, p.lng], { icon, title: p.location_name || '' })
            .bindPopup(() => buildPopup(p), { minWidth: 260, maxWidth: 350 })
            .addTo(markerLayer);
        bounds.push([p.lat, p.lng]);
    });

    // Tự động thu phóng để vừa tất cả các điểm
    map.invalidateSize();
    if (bounds.length) map.fitBounds(bounds, { padding: [48, 48], maxZoom: 16 });
}

/* ============ Tải dữ liệu ============ */
async function loadMapData() {
    if (!map) return;
    const seq = ++loadSeq;
    const month = $('mapMonth').value || currentMonthStr();
    const type = $('mapType') ? $('mapType').value : 'all';
    const category = $('mapCategory').value;

    const qs = new URLSearchParams({ month, type });
    if (category) qs.set('category', category);

    $('mapLoading').hidden = false;
    hideMessage();
    try {
        const res = await apiFetch('/transaction/map.php?' + qs.toString(), { method: 'GET' });
        if (seq !== loadSeq || !res) return;

        if (!res.success) {
            markerLayer.clearLayers();
            $('mapSummary').textContent = '';
            showMessage(res.message || res.error || 'Không thể tải dữ liệu bản đồ. Vui lòng thử lại.', { error: true, icon: 'fa-triangle-exclamation' });
            return;
        }

        const points = ((res.data && res.data.points) || []).filter(p => Number.isFinite(p.lat) && Number.isFinite(p.lng));
        renderPoints(points);

        if (!points.length) {
            $('mapSummary').textContent = '';
            let emptyMsg = 'Chưa có giao dịch thu/chi nào có vị trí trong tháng này';
            if (type === 'income') emptyMsg = 'Chưa có khoản thu nhập nào có vị trí trong tháng này';
            else if (type === 'expense') emptyMsg = 'Chưa có khoản chi nào có vị trí trong tháng này';
            showMessage(emptyMsg);
            return;
        }

        const sumIncome = points.reduce((s, p) => s + (p.total_income || (p.point_type === 'income' ? p.total : 0)), 0);
        const sumExpense = points.reduce((s, p) => s + (p.total_expense || (p.point_type === 'expense' ? p.total : 0)), 0);
        const sumCount = points.reduce((s, p) => s + p.count, 0);

        const summaryFrag = document.createDocumentFragment();
        summaryFrag.appendChild(document.createTextNode(`${points.length} điểm • ${sumCount} giao dịch (`));

        if (type === 'all') {
            const incSpan = document.createElement('strong');
            incSpan.style.color = '#10b981';
            incSpan.textContent = '+' + vnMoney(sumIncome);

            const expSpan = document.createElement('strong');
            expSpan.style.color = '#4F46E5';
            expSpan.textContent = '-' + vnMoney(sumExpense);

            summaryFrag.append(incSpan, document.createTextNode(' • '), expSpan, document.createTextNode(')'));
        } else if (type === 'income') {
            const incSpan = document.createElement('strong');
            incSpan.style.color = '#10b981';
            incSpan.textContent = '+' + vnMoney(sumIncome);
            summaryFrag.append(incSpan, document.createTextNode(')'));
        } else {
            const expSpan = document.createElement('strong');
            expSpan.style.color = '#4F46E5';
            expSpan.textContent = '-' + vnMoney(sumExpense);
            summaryFrag.append(expSpan, document.createTextNode(')'));
        }
        $('mapSummary').replaceChildren(summaryFrag);
    } catch (e) {
        if (seq !== loadSeq) return;
        console.error('Lỗi tải bản đồ:', e);
        markerLayer.clearLayers();
        $('mapSummary').textContent = '';
        showMessage('Không thể tải dữ liệu bản đồ. Vui lòng kiểm tra kết nối và thử lại.', { error: true, icon: 'fa-triangle-exclamation' });
    } finally {
        if (seq === loadSeq) $('mapLoading').hidden = true;
    }
}

async function loadCategories() {
    const type = $('mapType') ? $('mapType').value : 'all';
    const currentVal = $('mapCategory').value;
    try {
        const url = type === 'all' ? '/category/list.php' : ('/category/list.php?type=' + encodeURIComponent(type));
        const res = await apiFetch(url, { method: 'GET' });
        if (!res || !res.success) return;
        const list = Array.isArray(res.data) ? res.data : (res.data && res.data.categories) || [];
        const opts = list
            .filter(c => type === 'all' || !c.type || c.type === type)
            .sort((a, b) => String(a.name).localeCompare(String(b.name), 'vi'))
            .map(c => Object.assign(document.createElement('option'), {
                value: c.id,
                textContent: (type === 'all' && c.type ? (c.type === 'income' ? '[Thu] ' : '[Chi] ') : '') + c.name
            }));

        const defaultOpt = Object.assign(document.createElement('option'), { value: '', textContent: 'Tất cả danh mục' });
        $('mapCategory').replaceChildren(defaultOpt, ...opts);
        if (currentVal && opts.some(o => o.value == currentVal)) {
            $('mapCategory').value = currentVal;
        }
    } catch (e) {
        console.error('Lỗi tải danh mục:', e);
    }
}

/* ============ Khởi tạo ============ */
document.addEventListener('DOMContentLoaded', async () => {
    const user = await checkAuth();
    if (!user) return;

    // Menu hamburger trên di động
    const sidebar = document.querySelector('.sidebar');
    const btnMenu = $('btnMenu');
    if (sidebar && btnMenu) {
        btnMenu.addEventListener('click', (e) => { e.stopPropagation(); sidebar.classList.toggle('open'); });
        document.addEventListener('click', (e) => {
            if (sidebar.classList.contains('open') && !sidebar.contains(e.target)) sidebar.classList.remove('open');
        });
    }
    $('btnLogout').addEventListener('click', async () => {
        try { await apiFetch('/auth/logout.php', { method: 'POST' }); } catch (e) { console.error(e); }
        window.location.href = 'login.html';
    });

    $('mapMonth').value = currentMonthStr();
    if (!initMap()) return;

    // Tải lại dữ liệu khi đổi loại giao dịch, tháng hoặc danh mục
    $('mapMonth').addEventListener('change', loadMapData);
    $('mapCategory').addEventListener('change', loadMapData);
    if ($('mapType')) {
        $('mapType').addEventListener('change', async () => {
            await loadCategories();
            loadMapData();
        });
    }

    await loadCategories();
    loadMapData();
});
