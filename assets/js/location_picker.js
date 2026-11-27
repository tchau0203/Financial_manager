// Bộ chọn vị trí cho form thêm/sửa giao dịch (pages/expense.html và pages/income.html).
// Phụ thuộc: Leaflet 1.9.4 (biến toàn cục L). Vị trí là KHÔNG bắt buộc.
//
// API công khai (window.LocationPicker):
//   open(item)  - gọi mỗi lần mở modal; nạp vị trí của khoản giao dịch đang sửa (hoặc xóa trắng)
//   get()       - trả về { latitude, longitude, location_name } (null nếu chưa chọn vị trí)
(function () {
    const $ = (id) => document.getElementById(id);
    const DEFAULT_CENTER = [16.0544, 108.2022]; // Giữa Việt Nam
    const DEFAULT_ZOOM = 5;

    let map = null;
    let marker = null;
    let isSearching = false;

    function setStatus(text, isError = false) {
        const el = $('locStatus');
        if (!el) return;
        el.textContent = text || '';
        el.style.color = isError ? 'var(--danger)' : 'var(--text-muted)';
    }

    // Đặt/di chuyển marker và ghi tọa độ vào ô ẩn
    function setPoint(lat, lng, { zoom = null } = {}) {
        const latInput = $('tx_lat');
        const lngInput = $('tx_lng');
        const btnClear = $('btnClearLocation');
        if (latInput) latInput.value = Number(lat).toFixed(6);
        if (lngInput) lngInput.value = Number(lng).toFixed(6);
        if (btnClear) btnClear.hidden = false;
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
        if (zoom) {
            map.setView(latlng, zoom);
        }
    }

    function clearPoint() {
        if ($('tx_lat')) $('tx_lat').value = '';
        if ($('tx_lng')) $('tx_lng').value = '';
        if ($('tx_location_name')) $('tx_location_name').value = '';
        if ($('tx_address')) $('tx_address').value = '';
        if ($('btnClearLocation')) $('btnClearLocation').hidden = true;
        setStatus('Chưa chọn vị trí (không bắt buộc). Bấm lên bản đồ để chọn điểm.');
        if (marker && map) {
            map.removeLayer(marker);
            marker = null;
        }
    }

    // Tìm kiếm tọa độ theo địa chỉ với 3 tầng dự phòng:
    // Tầng 1: Backend proxy (/api/transaction/geocode.php) - cùng origin, không bị CORS, không bị adblocker chặn
    // Tầng 2: Photon API (OpenStreetMap qua Komoot) - cực nhanh, chuyên dùng cho tìm kiếm địa chỉ
    // Tầng 3: Nominatim OSM chính chủ
    async function searchAddress() {
        const input = $('tx_address');
        if (!input) return;
        const query = input.value.trim();
        if (!query) {
            setStatus('Vui lòng nhập địa chỉ cần tìm.', true);
            input.focus();
            return;
        }

        if (isSearching) return;
        isSearching = true;

        const btn = $('btnSearchAddress');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang tìm...';
        }
        setStatus(`🔍 Đang tìm địa chỉ: "${query}"...`);

        let result = null;

        try {
            // Tầng 1: Gọi backend proxy nội bộ
            try {
                const res = await fetch(`../api/transaction/geocode.php?q=${encodeURIComponent(query)}`, { credentials: 'include' });
                if (res.ok) {
                    const json = await res.json();
                    if (json && json.success && json.data && Number.isFinite(json.data.lat) && Number.isFinite(json.data.lng)) {
                        result = json.data;
                    }
                }
            } catch (errBackend) {
                console.warn('Backend geocode proxy không phản hồi, chuyển sang tầng dự phòng:', errBackend);
            }

            // Tầng 2: Gọi trực tiếp Photon API nếu tầng 1 chưa có kết quả
            if (!result) {
                try {
                    const noNum = query.replace(/^\s*(số\s*)?[\d\/\-]+[a-zA-Z]?\s*[,\s]\s*/iu, '').trim();
                    const queriesToTry = [query];
                    if (noNum && noNum !== query) queriesToTry.push(noNum);

                    for (const q of queriesToTry) {
                        const pRes = await fetch(`https://photon.komoot.io/api/?q=${encodeURIComponent(q)}&limit=1&lang=default`);
                        if (pRes.ok) {
                            const pJson = await pRes.json();
                            const feat = pJson.features && pJson.features[0];
                            if (feat && feat.geometry && Array.isArray(feat.geometry.coordinates)) {
                                const [lng, lat] = feat.geometry.coordinates;
                                if (Number.isFinite(lat) && Number.isFinite(lng)) {
                                    const props = feat.properties || {};
                                    const name = props.name || props.street || q;
                                    const parts = [props.name, props.street, props.district, props.city, props.country].filter(Boolean);
                                    result = {
                                        lat,
                                        lng,
                                        name,
                                        display_name: parts.length ? parts.join(', ') : name,
                                        approx: (q !== query)
                                    };
                                    break;
                                }
                            }
                        }
                    }
                } catch (errPhoton) {
                    console.warn('Photon fallback không phản hồi:', errPhoton);
                }
            }

            // Tầng 3: Gọi Nominatim OSM trực tiếp
            if (!result) {
                try {
                    const noNum = query.replace(/^\s*(số\s*)?[\d\/\-]+[a-zA-Z]?\s*[,\s]\s*/iu, '').trim();
                    const attempts = [{ q: query, approx: false }];
                    if (noNum && noNum !== query) attempts.push({ q: noNum, approx: true });

                    for (const a of attempts) {
                        const nUrl = `https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&accept-language=vi&countrycodes=vn&q=${encodeURIComponent(a.q)}`;
                        const nRes = await fetch(nUrl, { headers: { 'Accept': 'application/json' } });
                        if (nRes.ok) {
                            const list = await nRes.json();
                            if (Array.isArray(list) && list.length > 0) {
                                const hit = list[0];
                                const lat = parseFloat(hit.lat), lng = parseFloat(hit.lon);
                                if (Number.isFinite(lat) && Number.isFinite(lng)) {
                                    result = {
                                        lat,
                                        lng,
                                        name: hit.name || String(hit.display_name || '').split(',')[0],
                                        display_name: hit.display_name || a.q,
                                        approx: a.approx
                                    };
                                    break;
                                }
                            }
                        }
                    }
                } catch (errNom) {
                    console.warn('Nominatim fallback không phản hồi:', errNom);
                }
            }

            // Xử lý kết quả trả về
            if (result) {
                ensureMap();
                if (map) {
                    map.invalidateSize();
                }
                setPoint(result.lat, result.lng, { zoom: result.approx ? 16 : 17 });

                // Gợi ý tên địa điểm nếu ô tên còn trống
                const locNameInput = $('tx_location_name');
                if (locNameInput && !locNameInput.value.trim()) {
                    locNameInput.value = String(result.name || result.display_name || query).split(',')[0].trim().slice(0, 255);
                }

                const label = result.display_name || result.name || query;
                if (result.approx) {
                    setStatus(`✓ Không thấy số nhà chính xác, đã trỏ tới khu vực gần đúng: ${label} (bạn có thể kéo thả marker trên bản đồ để chỉnh lại).`);
                } else {
                    setStatus(`✓ Đã trỏ tới: ${label}`);
                }
            } else {
                setStatus('Không tìm thấy địa chỉ này. Hãy thử nhập đầy đủ hơn (đường, phường/xã, quận/huyện) hoặc bấm trực tiếp trên bản đồ.', true);
            }
        } catch (err) {
            console.error('Lỗi khi tìm địa chỉ:', err);
            setStatus('Không thể tìm địa chỉ lúc này. Bạn có thể chọn điểm trực tiếp trên bản đồ.', true);
        } finally {
            isSearching = false;
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-magnifying-glass-location"></i> Tìm';
            }
        }
    }

    // Khởi tạo bản đồ một lần khi container đã hiển thị
    function ensureMap() {
        const box = $('locMap');
        if (!box) return false;
        if (typeof L === 'undefined') {
            box.hidden = true;
            setStatus('Không tải được thư viện bản đồ. Bạn vẫn có thể lưu giao dịch không kèm vị trí.', true);
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
        if (btn) btn.disabled = true;
        setStatus('Đang xác định vị trí của bạn...');

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                if (btn) btn.disabled = false;
                ensureMap();
                setPoint(pos.coords.latitude, pos.coords.longitude, { zoom: 16 });
            },
            (err) => {
                if (btn) btn.disabled = false;
                const messages = {
                    1: 'Bạn đã từ chối quyền truy cập vị trí. Hãy cấp quyền cho trang web hoặc chọn điểm trực tiếp trên bản đồ.',
                    2: 'Không xác định được vị trí hiện tại. Hãy thử lại hoặc chọn điểm trên bản đồ.',
                    3: 'Xác định vị trí quá lâu. Hãy thử lại hoặc chọn điểm trên bản đồ.'
                };
                setStatus(messages[err.code] || 'Không thể lấy vị trí hiện tại.', true);
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
        );
    }

    // Gắn sự kiện cho các nút và ô nhập liệu
    function bindEvents() {
        const btnGet = $('btnGetLocation');
        if (btnGet && !btnGet.dataset.locBound) {
            btnGet.dataset.locBound = '1';
            btnGet.addEventListener('click', (e) => {
                e.preventDefault();
                getCurrentLocation();
            });
        }

        const btnClear = $('btnClearLocation');
        if (btnClear && !btnClear.dataset.locBound) {
            btnClear.dataset.locBound = '1';
            btnClear.addEventListener('click', (e) => {
                e.preventDefault();
                clearPoint();
            });
        }

        const addr = $('tx_address');
        if (addr && !addr.dataset.locBound) {
            addr.dataset.locBound = '1';
            addr.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    e.stopPropagation();
                    searchAddress();
                }
            });
            addr.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                }
            });
        }

        const btnSearch = $('btnSearchAddress');
        if (btnSearch && !btnSearch.dataset.locBound) {
            btnSearch.dataset.locBound = '1';
            btnSearch.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                searchAddress();
            });
        }
    }

    window.LocationPicker = {
        open(item) {
            bindEvents();
            clearPoint();
            const ready = ensureMap();
            if (ready) {
                setTimeout(() => map.invalidateSize(), 80);
                map.setView(DEFAULT_CENTER, DEFAULT_ZOOM);
            }
            if (item && item.latitude != null && item.longitude != null) {
                if ($('tx_location_name')) $('tx_location_name').value = item.location_name || '';
                const lat = Number(item.latitude), lng = Number(item.longitude);
                if (Number.isFinite(lat) && Number.isFinite(lng)) {
                    setPoint(lat, lng, { zoom: ready ? 16 : null });
                }
            }
        },
        get() {
            const latInput = $('tx_lat');
            const lngInput = $('tx_lng');
            const locNameInput = $('tx_location_name');
            const lat = latInput ? latInput.value : '';
            const lng = lngInput ? lngInput.value : '';
            if (lat === '' || lng === '') return { latitude: null, longitude: null, location_name: null };
            return {
                latitude: Number(lat),
                longitude: Number(lng),
                location_name: (locNameInput && locNameInput.value.trim()) || null
            };
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindEvents);
    } else {
        bindEvents();
    }
})();
