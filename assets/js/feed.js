/* Finance Feed (giao diện kiểu Locket) - JS thuần */
(function () {
    'use strict';

    const API = '../api/';
    const MAX_SIZE = 5 * 1024 * 1024;
    const ALLOWED = ['image/jpeg', 'image/png', 'image/webp'];
    const $ = (id) => document.getElementById(id);

    const state = {
        posts: [], idx: 0, page: 1, hasMore: false, loading: false, filter: 'all', filterLabel: 'Mọi người',
        type: 'expense', file: null, previewUrl: null, friends: [], me: null
    };

    // ---------- Tiện ích ----------
    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }
    function money(n) {
        return Number(n).toLocaleString('vi-VN', { maximumFractionDigits: 0 }) + ' đ';
    }
    function timeShort(str) {
        const d = new Date(String(str).replace(' ', 'T'));
        if (isNaN(d)) return '';
        const s = Math.max(0, Math.floor((Date.now() - d.getTime()) / 1000));
        if (s < 60) return 'Vừa xong';
        if (s < 3600) return Math.floor(s / 60) + ' phút';
        if (s < 86400) return Math.floor(s / 3600) + ' giờ';
        if (s < 30 * 86400) return Math.floor(s / 86400) + ' ngày';
        return d.toLocaleDateString('vi-VN');
    }
    function toast(msg, isErr) {
        const t = $('toast');
        t.textContent = msg;
        t.className = 'toast show' + (isErr ? ' err' : '');
        clearTimeout(toast._t);
        toast._t = setTimeout(() => { t.className = 'toast'; }, 3200);
    }
    function initial(name) { return (String(name || '?').trim()[0] || '?').toUpperCase(); }
    function avatarInner(name, url) {
        const img = url ? '<img src="' + esc(/^(https?:)?\/\//.test(url) ? url : '../' + url.replace(/^\/+/, '')) + '" alt="" onerror="this.remove()">' : '';
        return '<span>' + esc(initial(name)) + '</span>' + img;
    }

    async function api(path, opts) {
        const r = await fetch(API + path, Object.assign({ credentials: 'include' }, opts || {}));
        if (r.status === 401) { location.href = 'login.html'; throw new Error('Chưa đăng nhập'); }
        let j = null;
        try { j = await r.json(); } catch (e) { /* ignore */ }
        if (!r.ok || !j || j.success === false) {
            throw new Error((j && (j.message || j.error)) || 'Máy chủ phản hồi lỗi (HTTP ' + r.status + ')');
        }
        return j;
    }
    function postJson(path, body) {
        return api(path, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    }

    // ---------- Trình xem bài (từng bài một, như Locket) ----------
    function current() { return state.posts[state.idx] || null; }

    function renderViewer(dir) {
        const p = current();
        const box = $('photoBox');
        $('emptyBox').classList.toggle('hide', !!p);
        $('photoImg').classList.toggle('hide', !p);
        ['photoAmount', 'photoCaption', 'photoCount', 'authorRow', 'reactBar'].forEach((id) => $(id).classList.toggle('hide', !p));
        $('btnPrev').disabled = state.idx <= 0;
        $('btnNext').disabled = !p || (state.idx >= state.posts.length - 1 && !state.hasMore);
        if (!p) { $('photoCount').textContent = ''; return; }

        const img = $('photoImg');
        img.src = '../' + p.image_path;
        img.alt = 'Ảnh của ' + p.full_name;
        if (dir) {
            box.classList.remove('slide-up', 'slide-down');
            void box.offsetWidth;
            box.classList.add(dir > 0 ? 'slide-up' : 'slide-down');
        }

        const sign = p.type === 'income' ? '+' : '-';
        const am = $('photoAmount');
        am.className = 'l-amount ' + (p.type === 'income' ? 'inc' : 'exp');
        am.innerHTML = '<i class="fa-solid ' + (p.type === 'income' ? 'fa-arrow-down' : 'fa-arrow-up-right-from-square') + '"></i> ' +
            esc(sign + money(p.amount)) + (p.category_name ? ' <small>· ' + esc(p.category_name) + '</small>' : '');
        const cap = $('photoCaption');
        cap.textContent = p.caption || '';
        cap.classList.toggle('hide', !p.caption);
        $('photoCount').textContent = (state.idx + 1) + ' / ' + state.posts.length + (state.hasMore ? '+' : '');

        $('authorRow').innerHTML = '<span class="av">' + avatarInner(p.full_name, p.avatar_url) + '</span>' +
            '<strong>' + esc(p.is_mine ? 'Bạn' : p.full_name) + '</strong>' +
            '<span class="ago">' + esc(timeShort(p.created_at)) + (p.is_public ? '' : ' · <i class="fa-solid fa-lock" title="Chỉ mình tôi"></i>') + '</span>';

        $('reactBar').innerHTML = p.reactions.map((r) =>
            '<button type="button" class="emo' + (r.mine ? ' on' : '') + '" data-emoji="' + esc(r.emoji) + '" title="' + esc(r.emoji) + '">' +
            '<span>' + esc(r.emoji) + '</span>' + (r.count ? '<b>' + r.count + '</b>' : '') + '</button>').join('');

        // Tải trước bài kế tiếp cho mượt
        const nx = state.posts[state.idx + 1];
        if (nx) { const pre = new Image(); pre.src = '../' + nx.image_path; }
    }

    async function loadFeed(reset) {
        if (state.loading) return;
        state.loading = true;
        if (reset) { state.page = 1; state.posts = []; state.idx = 0; }
        try {
            const j = await api('posts/feed.php?page=' + state.page + '&filter=' + encodeURIComponent(state.filter));
            state.posts = state.posts.concat(j.data);
            state.hasMore = j.pagination.has_more;
            if (!state.posts.length) {
                $('emptyBox').querySelector('p').innerHTML = state.filter === 'all'
                    ? 'Chưa có bài đăng nào.<br>Bấm nút tròn bên dưới để đăng bài đầu tiên, hoặc kết bạn để xem bài của bạn bè.'
                    : 'Chưa có bài đăng nào trong mục này.';
            }
            renderViewer(0);
            if (!$('gridView').classList.contains('hide')) renderGrid();
        } catch (e) {
            state.posts = [];
            $('emptyBox').querySelector('p').textContent = 'Không tải được bảng tin: ' + e.message;
            renderViewer(0);
        } finally {
            state.loading = false;
        }
    }

    async function go(delta) {
        if (state.loading) return;
        const target = state.idx + delta;
        if (target < 0) return;
        if (target >= state.posts.length) {
            if (!state.hasMore) return;
            state.page++;
            const before = state.posts.length;
            await loadFeed(false);
            if (state.posts.length <= before) return;
        }
        state.idx = Math.min(target, state.posts.length - 1);
        renderViewer(delta);
        if (state.hasMore && state.idx >= state.posts.length - 3) { state.page++; loadFeed(false).then(() => renderViewer(0)); }
    }

    async function toggleReaction(btn) {
        const p = current();
        if (!p || btn.disabled) return;
        btn.disabled = true;
        try {
            const j = await postJson('posts/react.php', { post_id: p.id, emoji: btn.dataset.emoji });
            const r = p.reactions.find((x) => x.emoji === j.data.emoji);
            if (r) { r.mine = j.data.active; r.count = j.data.count; }
            renderViewer(0);
            if (j.data.active) {
                const b = document.querySelector('#reactBar .emo[data-emoji="' + btn.dataset.emoji + '"]');
                if (b) b.classList.add('pop');
            }
        } catch (e) {
            toast(e.message, true);
            btn.disabled = false;
        }
    }

    // ---------- Lưới lịch sử ----------
    function renderGrid() {
        $('gridBody').innerHTML = state.posts.length
            ? state.posts.map((p, i) => '<button type="button" class="g-item" data-i="' + i + '" aria-label="Xem bài ' + (i + 1) + '">' +
                '<img src="../' + esc(p.image_path) + '" alt="" loading="lazy">' +
                '<span class="g-amt ' + (p.type === 'income' ? 'inc' : 'exp') + '">' + (p.type === 'income' ? '+' : '-') + esc(money(p.amount)) + '</span></button>').join('')
            : '<p class="g-empty">Chưa có bài đăng nào.</p>';
        $('gridMore').classList.toggle('hide', !state.hasMore);
    }
    function openGrid() { renderGrid(); $('gridView').classList.remove('hide'); }
    function closeGrid() { $('gridView').classList.add('hide'); }

    // ---------- Bộ lọc "Mọi người" ----------
    function buildFilterMenu() {
        const items = [{ k: 'all', t: 'Mọi người', i: 'fa-user-group' }, { k: 'me', t: 'Chỉ mình tôi', i: 'fa-user' }]
            .concat(state.friends.map((f) => ({ k: String(f.user_id), t: f.full_name, i: 'fa-circle-user' })));
        $('filterMenu').innerHTML = items.map((it) =>
            '<button type="button" data-k="' + esc(it.k) + '" data-t="' + esc(it.t) + '" class="' + (state.filter === it.k ? 'on' : '') + '">' +
            '<i class="fa-solid ' + it.i + '"></i> ' + esc(it.t) + '</button>').join('') +
            '<hr><a href="dashboard.html"><i class="fa-solid fa-chart-pie"></i> Về Bảng điều khiển</a>';
    }

    // ---------- Modal bạn bè ----------
    function openModal(id) { $(id).classList.add('show'); }
    function closeModal(id) { $(id).classList.remove('show'); }

    function personRow(name, username, actionHtml) {
        return '<li class="person"><span class="av">' + avatarInner(name) + '</span><div class="person-info"><strong>' + esc(name) +
            '</strong><small>@' + esc(username) + '</small></div>' + (actionHtml || '') + '</li>';
    }

    async function loadFriends() {
        try {
            const j = await api('friends/list.php');
            const d = j.data;
            state.friends = d.friends;
            buildFilterMenu();
            $('incomingBox').classList.toggle('hide', !d.incoming.length);
            $('incomingList').innerHTML = d.incoming.map((f) => personRow(f.full_name, f.username,
                '<button type="button" class="btn sm" data-accept="' + f.friendship_id + '">Chấp nhận</button>')).join('');
            $('friendList').innerHTML = d.friends.length
                ? d.friends.map((f) => personRow(f.full_name, f.username)).join('')
                : '<li class="muted pad">Bạn chưa có bạn bè nào. Hãy tìm theo username ở trên.</li>';
            $('outgoingList').innerHTML = d.outgoing.map((f) => personRow(f.full_name, f.username, '<span class="tag">Chờ phản hồi</span>')).join('');
            $('outgoingBox').classList.toggle('hide', !d.outgoing.length);
            $('badgeInvite').textContent = d.incoming.length || '';
            $('badgeInvite').classList.toggle('hide', !d.incoming.length);
        } catch (e) { toast(e.message, true); }
    }

    function relationAction(u) {
        if (u.relation === 'friends') return '<span class="tag ok">Bạn bè</span>';
        if (u.relation === 'sent') return '<span class="tag">Đã gửi lời mời</span>';
        if (u.relation === 'received') return '<button type="button" class="btn sm" data-accept="' + u.friendship_id + '">Chấp nhận</button>';
        return '<button type="button" class="btn sm" data-add="' + u.id + '">Kết bạn</button>';
    }

    let searchTimer = null;
    function onSearch() {
        clearTimeout(searchTimer);
        const q = $('friendSearch').value.trim();
        if (q.length < 2) { $('searchList').innerHTML = ''; return; }
        searchTimer = setTimeout(async () => {
            try {
                const j = await api('friends/search.php?q=' + encodeURIComponent(q));
                $('searchList').innerHTML = j.data.length
                    ? j.data.map((u) => personRow(u.full_name, u.username, relationAction(u))).join('')
                    : '<li class="muted pad">Không tìm thấy người dùng nào.</li>';
            } catch (e) { toast(e.message, true); }
        }, 300);
    }

    async function friendAction(btn) {
        btn.disabled = true;
        try {
            const j = btn.dataset.add
                ? await postJson('friends/add.php', { friend_id: Number(btn.dataset.add) })
                : await postJson('friends/accept.php', { friendship_id: Number(btn.dataset.accept) });
            toast(j.message || 'Thành công');
            await loadFriends();
            if (btn.dataset.add || $('friendSearch').value.trim().length >= 2) onSearch();
            if (btn.dataset.accept) loadFeed(true);
        } catch (e) {
            toast(e.message, true);
            btn.disabled = false;
        }
    }

    // ---------- Camera + Thêm giao dịch (webcam trên máy tính) ----------
    const cam = { stream: null, facing: 'environment', cats: {}, catId: null, share: true, ledger: true, lat: null, lng: null };

    function show(step) {
        $('stepCamera').classList.toggle('hide', step !== 'camera');
        $('stepForm').classList.toggle('hide', step !== 'form');
    }
    function stopCamera() {
        if (cam.stream) { cam.stream.getTracks().forEach((t) => t.stop()); cam.stream = null; }
        $('camVideo').srcObject = null;
    }
    async function startCamera() {
        stopCamera();
        const msg = $('camMsg');
        msg.classList.add('hide');
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            msg.textContent = 'Trình duyệt không hỗ trợ camera (cần mở bằng http://127.0.0.1 hoặc https). Bạn vẫn có thể chọn ảnh từ máy bằng nút thư viện bên trái.';
            msg.classList.remove('hide');
            return;
        }
        try {
            cam.stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: cam.facing, width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false
            });
            const v = $('camVideo');
            v.srcObject = cam.stream;
            const st = cam.stream.getVideoTracks()[0].getSettings();
            v.classList.toggle('mirror', st.facingMode === 'user' || !st.facingMode);
        } catch (e) {
            msg.textContent = (e && e.name === 'NotAllowedError')
                ? 'Bạn chưa cho phép dùng camera. Hãy bấm biểu tượng ổ khóa trên thanh địa chỉ để cho phép, hoặc chọn ảnh từ máy bằng nút thư viện.'
                : 'Không mở được camera (' + (e && e.name || 'lỗi') + '). Hãy chọn ảnh từ máy bằng nút thư viện bên trái.';
            msg.classList.remove('hide');
        }
    }
    function openCamApp(withCamera) {
        resetForm();
        $('camApp').classList.add('show');
        show('camera');
        if (withCamera !== false) startCamera();
    }
    function closeCamApp() {
        stopCamera();
        $('camApp').classList.remove('show');
        resetForm();
    }
    function setFile(file) {
        if (!file) return;
        if (!ALLOWED.includes(file.type)) { toast('Chỉ chấp nhận ảnh JPG, PNG hoặc WEBP', true); return; }
        if (file.size > MAX_SIZE) { toast('Ảnh vượt quá 5MB, hãy chọn ảnh nhỏ hơn', true); return; }
        if (!$('camApp').classList.contains('show')) $('camApp').classList.add('show');
        if (state.previewUrl) URL.revokeObjectURL(state.previewUrl);
        state.file = file;
        state.previewUrl = URL.createObjectURL(file);
        $('previewImg').src = state.previewUrl;
        stopCamera();
        show('form');
        setType(state.type);
        setTimeout(() => $('postAmount').focus(), 50);
    }
    function capture() {
        const v = $('camVideo');
        if (!v.videoWidth) { toast('Camera chưa sẵn sàng, hãy chọn ảnh từ máy', true); return; }
        const c = $('camCanvas');
        c.width = v.videoWidth;
        c.height = v.videoHeight;
        c.getContext('2d').drawImage(v, 0, 0, c.width, c.height);
        c.toBlob((blob) => {
            if (!blob) { toast('Không chụp được ảnh', true); return; }
            setFile(new File([blob], 'capture.jpg', { type: 'image/jpeg' }));
        }, 'image/jpeg', 0.9);
    }
    function todayStr() {
        const d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
    function renderDate() {
        const v = $('postDate').value || todayStr();
        $('chipDateText').textContent = v === todayStr() ? 'Hôm nay' : v.split('-').reverse().join('/');
    }
    function resetForm() {
        state.file = null;
        if (state.previewUrl) URL.revokeObjectURL(state.previewUrl);
        state.previewUrl = null;
        $('previewImg').removeAttribute('src');
        $('fileGallery').value = '';
        $('postAmount').value = '';
        $('postCaption').value = '';
        $('postDate').value = todayStr();
        $('postDate').max = todayStr();
        renderDate();
        cam.lat = cam.lng = null;
        $('chipLocText').textContent = 'Thêm vị trí';
        cam.share = true; cam.ledger = true;
        $('chipShare').classList.add('on');
        $('chipLedger').classList.add('on');
        $('catPop').classList.add('hide');
    }
    async function loadCategories(type) {
        if (!cam.cats[type]) {
            try {
                const j = await api('category/list.php?type=' + type);
                cam.cats[type] = j.data;
            } catch (e) { toast(e.message, true); cam.cats[type] = []; }
        }
        const list = cam.cats[type];
        const cur = list.find((c) => Number(c.id) === Number(cam.catId));
        pickCategory(cur || list[0] || null);
    }
    function pickCategory(c) {
        cam.catId = c ? Number(c.id) : null;
        $('chipCatName').textContent = c ? c.name : 'Danh mục';
        $('chipCatIcon').innerHTML = c ? '<i class="fa-solid ' + esc(c.icon || 'fa-tag') + '" style="color:' + esc(c.color || '#d946ef') + '"></i>' : '🏷️';
        $('catPop').classList.add('hide');
    }
    function toggleCatPop() {
        const pop = $('catPop');
        if (!pop.classList.contains('hide')) { pop.classList.add('hide'); return; }
        const list = cam.cats[state.type] || [];
        pop.innerHTML = list.map((c) => '<button type="button" data-cat="' + Number(c.id) + '"' + (Number(c.id) === cam.catId ? ' class="on"' : '') + '>' +
            '<i class="fa-solid ' + esc(c.icon || 'fa-tag') + '" style="color:' + esc(c.color || '#d946ef') + ';width:18px;text-align:center"></i> ' + esc(c.name) + '</button>').join('') ||
            '<div style="padding:10px;color:#a1a1aa">Chưa có danh mục</div>';
        pop.classList.remove('hide');
    }
    function setType(type) {
        state.type = type;
        cam.catId = null;
        document.querySelectorAll('#typeSeg button').forEach((b) => b.classList.toggle('on', b.dataset.type === type));
        loadCategories(type);
    }
    function formatAmountInput() {
        const el = $('postAmount');
        const digits = el.value.replace(/\D/g, '').replace(/^0+(?=\d)/, '');
        el.value = digits ? Number(digits).toLocaleString('vi-VN') : '';
    }
    function toggleLocation() {
        if (cam.lat !== null) { cam.lat = cam.lng = null; $('chipLocText').textContent = 'Thêm vị trí'; return; }
        if (!navigator.geolocation) { toast('Trình duyệt không hỗ trợ định vị', true); return; }
        $('chipLocText').textContent = 'Đang lấy vị trí...';
        navigator.geolocation.getCurrentPosition((pos) => {
            cam.lat = pos.coords.latitude;
            cam.lng = pos.coords.longitude;
            $('chipLocText').textContent = 'Đã gắn vị trí';
        }, () => {
            $('chipLocText').textContent = 'Thêm vị trí';
            toast('Không lấy được vị trí (bạn chưa cho phép hoặc bị chặn)', true);
        }, { enableHighAccuracy: false, timeout: 10000 });
    }
    async function submitPost(e) {
        e.preventDefault();
        if (!state.file) { toast('Hãy chụp hoặc chọn một bức ảnh', true); return; }
        const amount = String($('postAmount').value).replace(/\D/g, '');
        if (!amount || Number(amount) <= 0) { toast('Nhập số tiền hợp lệ (lớn hơn 0)', true); return; }
        if (!cam.catId) { toast('Vui lòng chọn danh mục', true); return; }

        const fd = new FormData();
        fd.append('image', state.file);
        fd.append('amount', amount);
        fd.append('type', state.type);
        fd.append('category_id', String(cam.catId));
        fd.append('caption', $('postCaption').value.trim());
        fd.append('is_public', cam.share ? '1' : '0');
        fd.append('save_transaction', cam.ledger ? '1' : '0');
        fd.append('transaction_date', $('postDate').value || todayStr());
        if (cam.lat !== null) { fd.append('latitude', String(cam.lat)); fd.append('longitude', String(cam.lng)); }

        const btn = $('btnPost');
        btn.disabled = true;
        btn.innerHTML = 'Đang lưu...';
        try {
            await api('posts/create.php', { method: 'POST', body: fd });
            toast('Đã lưu giao dịch và đăng lên bảng tin');
            closeCamApp();
            state.filter = 'all';
            state.filterLabel = 'Mọi người';
            $('filterLabel').textContent = state.filterLabel;
            buildFilterMenu();
            loadFeed(true);
        } catch (err) {
            toast(err.message, true);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Lưu';
        }
    }

    // ---------- Khởi tạo ----------
    async function init() {
        try {
            const me = await api('auth/me.php');
            const u = me.data || me.user || me;
            state.me = u;
            $('meAvatar').innerHTML = avatarInner(u.full_name || u.username, u.avatar_url);
            $('meAvatar').title = u.full_name || u.username || '';
        } catch (e) { return; }

        // Trình xem: điều hướng
        $('btnPrev').addEventListener('click', () => go(-1));
        $('btnNext').addEventListener('click', () => go(1));
        document.addEventListener('keydown', (e) => {
            if (e.target.closest('input,textarea,select') || $('camApp').classList.contains('show') || $('friendModal').classList.contains('show')) return;
            if (e.key === 'ArrowDown' || e.key === 'ArrowRight') { e.preventDefault(); go(1); }
            if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') { e.preventDefault(); go(-1); }
        });
        let wheelLock = false;
        $('photoBox').addEventListener('wheel', (e) => {
            e.preventDefault();
            if (wheelLock || Math.abs(e.deltaY) < 20) return;
            wheelLock = true;
            go(e.deltaY > 0 ? 1 : -1);
            setTimeout(() => { wheelLock = false; }, 500);
        }, { passive: false });
        let touchY = null;
        $('photoBox').addEventListener('touchstart', (e) => { touchY = e.touches[0].clientY; }, { passive: true });
        $('photoBox').addEventListener('touchend', (e) => {
            if (touchY === null) return;
            const dy = e.changedTouches[0].clientY - touchY;
            touchY = null;
            if (Math.abs(dy) > 50) go(dy < 0 ? 1 : -1);
        });
        $('reactBar').addEventListener('click', (e) => {
            const b = e.target.closest('.emo');
            if (b) toggleReaction(b);
        });

        // Lưới lịch sử
        $('btnGrid').addEventListener('click', openGrid);
        $('gridClose').addEventListener('click', closeGrid);
        $('gridBody').addEventListener('click', (e) => {
            const b = e.target.closest('.g-item');
            if (!b) return;
            state.idx = Number(b.dataset.i);
            closeGrid();
            renderViewer(0);
        });
        $('gridMore').addEventListener('click', async () => { state.page++; await loadFeed(false); renderGrid(); });

        // Bộ lọc
        $('filterBtn').addEventListener('click', (e) => { e.stopPropagation(); $('filterMenu').classList.toggle('hide'); });
        document.addEventListener('click', (e) => { if (!e.target.closest('.filter-wrap')) $('filterMenu').classList.add('hide'); });
        $('filterMenu').addEventListener('click', (e) => {
            const b = e.target.closest('button[data-k]');
            if (!b) return;
            state.filter = b.dataset.k;
            state.filterLabel = b.dataset.t;
            $('filterLabel').textContent = state.filterLabel;
            $('filterMenu').classList.add('hide');
            buildFilterMenu();
            loadFeed(true);
        });

        // Modal bạn bè
        document.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', () => closeModal(b.dataset.close)));
        document.querySelectorAll('.modal').forEach((m) => m.addEventListener('click', (e) => { if (e.target === m) closeModal(m.id); }));
        $('btnFriends').addEventListener('click', () => { openModal('friendModal'); loadFriends(); });
        $('friendSearch').addEventListener('input', onSearch);
        $('friendModal').addEventListener('click', (e) => {
            const b = e.target.closest('[data-add],[data-accept]');
            if (b) friendAction(b);
        });

        // Camera app
        $('fab').addEventListener('click', () => openCamApp(true));
        $('btnUpload').addEventListener('click', () => { resetForm(); $('fileGallery').click(); });
        $('camClose').addEventListener('click', closeCamApp);
        $('camApp').addEventListener('click', (e) => { if (e.target === $('camApp')) closeCamApp(); });
        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') return;
            if ($('camApp').classList.contains('show')) closeCamApp();
            else if (!$('gridView').classList.contains('hide')) closeGrid();
        });
        $('btnShutter').addEventListener('click', capture);
        $('btnGallery').addEventListener('click', () => $('fileGallery').click());
        $('btnManual').addEventListener('click', () => $('fileGallery').click());
        $('fileGallery').addEventListener('change', (e) => setFile(e.target.files[0]));
        $('btnFlip').addEventListener('click', () => { cam.facing = cam.facing === 'user' ? 'environment' : 'user'; startCamera(); });
        $('formBack').addEventListener('click', () => { resetForm(); show('camera'); startCamera(); });

        const vp = $('viewport');
        ['dragenter', 'dragover'].forEach((ev) => vp.addEventListener(ev, (e) => { e.preventDefault(); vp.classList.add('drag'); }));
        ['dragleave', 'drop'].forEach((ev) => vp.addEventListener(ev, (e) => { e.preventDefault(); vp.classList.remove('drag'); }));
        vp.addEventListener('drop', (e) => setFile(e.dataTransfer.files[0]));

        document.querySelectorAll('#typeSeg button').forEach((b) => b.addEventListener('click', () => setType(b.dataset.type)));
        $('chipCategory').addEventListener('click', toggleCatPop);
        $('catPop').addEventListener('click', (e) => {
            const b = e.target.closest('[data-cat]');
            if (!b) return;
            pickCategory((cam.cats[state.type] || []).find((c) => Number(c.id) === Number(b.dataset.cat)));
        });
        $('chipDate').addEventListener('click', () => { const d = $('postDate'); if (d.showPicker) d.showPicker(); else d.click(); });
        $('postDate').addEventListener('change', renderDate);
        $('chipLocation').addEventListener('click', toggleLocation);
        $('chipShare').addEventListener('click', () => { cam.share = !cam.share; $('chipShare').classList.toggle('on', cam.share); });
        $('chipLedger').addEventListener('click', () => { cam.ledger = !cam.ledger; $('chipLedger').classList.toggle('on', cam.ledger); });
        $('postAmount').addEventListener('input', formatAmountInput);
        $('amountClear').addEventListener('click', () => { $('postAmount').value = ''; $('postAmount').focus(); });
        $('stepForm').addEventListener('submit', submitPost);

        buildFilterMenu();
        loadFeed(true);
        loadFriends();
    }

    document.addEventListener('DOMContentLoaded', init);
})();
