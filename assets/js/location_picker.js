// Bộ chọn vị trí cho form thêm/sửa giao dịch (pages/expense.html).
// Phụ thuộc: Leaflet 1.9.4 (biến toàn cục L). Vị trí là KHÔNG bắt buộc.
//
// API công khai (window.LocationPicker):
//   open(item)  - gọi mỗi lần mở modal; nạp vị trí của khoản chi đang sửa (hoặc xóa trắng)
//   get()       - trả về { latitude, longitude, location_name } (null nếu chưa chọn vị trí)
(function () {
    const $ = (id) => document.getElementById(id);
    const DEFAULT_CENTER = [16.0544, 108.2022]; // giữa Việt Nam
    const DEFAULT_ZOOM = 5;

    let map = null;
    let marker = null;

    function setStatus(text, isError = false) {
        const el = $('locStatus');
        if (!el) return;
        el.textContent = text || '';
        el.style.color = isError ? 'var(--danger)' : 'var(--text-muted)';
    }

    // Đặt/di chuyển marker và ghi tọa độ vào ô ẩn
    function setPoint(lat, lng, { zoom = null } = {}) {
        $('tx_lat').value = Number(lat).toFixed(6);
        $('tx_lng').value = Number(lng).toFixed(6);
        $('btnClearLocation').hidden = false;
        setStatus(`Đã chọn vị trí: ${Number(lat).toFixed(5)}, ${Number(lng).toFixed(5)}`);

        if (!map) return;
        const latlng = [lat, lng];
        if (marker) {
            marker.setLatLng(latlng);
        } else {
            marker = L.marker(latlng, { draggable: true }).addTo(map);
            // Cho phép kéo marker để tinh chỉnh vị trí
            marker.on('dragend', () => {
                const p = marker.getLatLng();
                setPoint(p.lat, p.lng);
            });
        }
        if (zoom) map.setView(latlng, zoom);
    }

    function clearPoint() {
        $('tx_lat').value = '';
        $('tx_lng').value = '';
        $('tx_location_name').value = '';
        $('btnClearLocation').hidden = true;
        setStatus('Chưa chọn vị trí (không bắt buộc).');
        if (marker && map) { map.removeLayer(marker); marker = null; }
    }

    // Khởi tạo bản đồ một lần, khi modal đã hiển thị (Leaflet cần container có kích thước)
    function ensureMap() {
        const box = $('locMap');
        if (!box) return false;
        if (typeof L === 'undefined') {
            box.hidden = true;
            setStatus('Không tải được thư viện bản đồ. Bạn vẫn có thể lưu khoản chi không kèm vị trí.', true);
            return false;
        }
        if (map) return true;

        map = L.map(box).setView(DEFAULT_CENTER, DEFAULT_ZOOM);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);
        // Bấm lên bản đồ để chọn điểm
        map.on('click', (e) => setPoint(e.latlng.lat, e.latlng.lng));
        return true;
    }

    function getCurrentLocation() {
        if (!('geolocation' in navigator)) {
            return setStatus('Trình duyệt của bạn không hỗ trợ định vị. Hãy chọn điểm trực tiếp trên bản đồ.', true);
        }
        const btn = $('btnGetLocation');
        btn.disabled = true;
        setStatus('Đang xác định vị trí của bạn...');

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                btn.disabled = false;
                ensureMap();
                setPoint(pos.coords.latitude, pos.coords.longitude, { zoom: 16 });
            },
            (err) => {
                btn.disabled = false;
                const messages = {
                    1: 'Bạn đã từ chối quyền truy cập vị trí. Hãy cấp quyền cho trang web trong trình duyệt hoặc chọn điểm trực tiếp trên bản đồ.',
                    2: 'Không xác định được vị trí hiện tại. Hãy thử lại hoặc chọn điểm trên bản đồ.',
                    3: 'Xác định vị trí quá lâu. Hãy thử lại hoặc chọn điểm trên bản đồ.'
                };
                setStatus(messages[err.code] || 'Không thể lấy vị trí hiện tại.', true);
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
        );
    }

    window.LocationPicker = {
        open(item) {
            clearPoint();
            const ready = ensureMap();
            if (ready) {
                // Modal vừa hiện nên phải tính lại kích thước bản đồ
                setTimeout(() => map.invalidateSize(), 50);
                map.setView(DEFAULT_CENTER, DEFAULT_ZOOM);
            }
            if (item && item.latitude != null && item.longitude != null) {
                $('tx_location_name').value = item.location_name || '';
                const lat = Number(item.latitude), lng = Number(item.longitude);
                if (Number.isFinite(lat) && Number.isFinite(lng)) {
                    setPoint(lat, lng, { zoom: ready ? 16 : null });
                }
            }
        },
        get() {
            const lat = $('tx_lat').value, lng = $('tx_lng').value;
            if (lat === '' || lng === '') return { latitude: null, longitude: null, location_name: null };
            return {
                latitude: Number(lat),
                longitude: Number(lng),
                location_name: $('tx_location_name').value.trim() || null
            };
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        const btnGet = $('btnGetLocation');
        if (!btnGet) return;
        btnGet.addEventListener('click', getCurrentLocation);
        $('btnClearLocation').addEventListener('click', clearPoint);
    });
})();
