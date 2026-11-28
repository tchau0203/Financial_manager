(() => {
  const $ = id => document.getElementById(id);
  let cats = [], editId = null, timer, file = null;

  // ===== Hàm dùng chung =====
  const api = async (url, opts = {}) => {
    const o = { credentials: 'same-origin', headers: {}, ...opts };
    // [HUY T2] FormData (upload file) không được chuyển thành JSON
    if (o.body && !(o.body instanceof FormData) && typeof o.body !== 'string') {
      o.headers['Content-Type'] = 'application/json';
      o.body = JSON.stringify(o.body);
    }
    const r = await fetch(url, o);
    if (r.status === 401) { location.href = 'login.html'; throw new Error('Chưa đăng nhập'); }
    let j = {};
    try { j = await r.json(); } catch (e) {}
    if (!r.ok || j.success === false || j.status === 'error') throw new Error(j.message || j.error || 'Có lỗi xảy ra, vui lòng thử lại');
    return j.data !== undefined ? j.data : j;
  };
  const post = (url, body) => api(url, { method: 'POST', body });
  const list = d => Array.isArray(d) ? d : (d && Object.values(d).find(Array.isArray)) || [];
  const cid = c => c.id ?? c.category_id;
  const cname = c => c.name ?? c.category_name ?? '';
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const toast = (msg, err) => {
    const t = $('toast');
    t.textContent = msg;
    t.className = 'toast show' + (err ? ' err' : '');
    clearTimeout(timer);
    timer = setTimeout(() => { t.className = 'toast'; }, 2800);
  };
  async function initUser() {
    let u = {};
    try {
      const d = await api('../api/auth/me.php');
      u = d.user || d;
      $('userName').textContent = u.full_name || u.name || u.username || '';
    } catch (e) {}
    $('logoutBtn').onclick = async () => {
      try { await api('../api/auth/logout.php', { method: 'POST' }); } catch (e) {}
      location.href = 'login.html';
    };
    return u;
  }

  // [HUY T3] Danh sách ngân hàng (mã BIN theo chuẩn VietQR)
  const BANKS = [['970436', 'Vietcombank (VCB)'], ['970418', 'BIDV'], ['970415', 'VietinBank'], ['970405', 'Agribank'],
    ['970407', 'Techcombank'], ['970422', 'MB Bank'], ['970416', 'ACB'], ['970432', 'VPBank'], ['970403', 'Sacombank'],
    ['970423', 'TPBank'], ['970441', 'VIB'], ['970437', 'HDBank'], ['970448', 'OCB'], ['970443', 'SHB'],
    ['970426', 'MSB'], ['970431', 'Eximbank'], ['970449', 'LPBank'], ['970440', 'SeABank']];
  if ($('bankCode')) {
    $('bankCode').innerHTML = '<option value="">-- Chọn ngân hàng --</option>' + BANKS.map(([c, n]) => `<option value="${c}">${n}</option>`).join('');
  }

  // Dữ liệu gửi lên api/account/update.php: luôn gửi đủ để không làm mất trường khác
  const payload = () => ({
    full_name: $('fullName') ? $('fullName').value.trim() : '',
    email: $('email') ? $('email').value.trim() : '',
    email_alerts: $('emailAlert') && $('emailAlert').checked ? 1 : 0,
    bank_code: $('bankCode') ? $('bankCode').value : '',
    bank_account: $('bankAcc') ? $('bankAcc').value.replace(/\s/g, '') : ''
  });

  // ===== Thông tin cá nhân + công tắc email =====
  initUser().then(u => {
    if ($('fullName')) $('fullName').value = u.full_name || u.name || '';
    if ($('email')) $('email').value = u.email || '';
    if ($('emailAlert')) $('emailAlert').checked = !!+(u.email_alerts ?? u.email_notifications ?? 0);
    if ($('bankCode')) $('bankCode').value = u.bank_code ?? '';
    if ($('bankAcc')) $('bankAcc').value = u.bank_account ?? '';
    initAvatarControls(u);
    initLoginSessionInfo(u);
    if (window.HeaderNav) window.HeaderNav.updateUser(u);
  });

  // ===== [AVATAR] Quản lý ảnh đại diện =====
  function renderAvatarBox(avatarUrl, fullName) {
    const box = $('accountAvatarBox');
    if (!box) return;
    const initial = (fullName || 'U').trim().charAt(0).toUpperCase();
    if (avatarUrl) {
      box.style.background = 'transparent';
      box.innerHTML = `<img src="${avatarUrl}" alt="${fullName}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" onerror="this.onerror=null; this.parentElement.style.background='#4e342e'; this.parentElement.textContent='${initial}';">`;
      if ($('btnDeleteAvatar')) $('btnDeleteAvatar').style.display = 'inline-flex';
    } else {
      box.style.background = '#4e342e';
      box.textContent = initial;
      if ($('btnDeleteAvatar')) $('btnDeleteAvatar').style.display = 'none';
    }
  }

  function initAvatarControls(u) {
    renderAvatarBox(u.avatar_url, u.full_name || u.name);

    // Kích hoạt chọn file ảnh
    const fileInput = $('avatarInputFile');
    const triggerBtn = $('btnUploadAvatarFile');
    const cameraBtn = $('btnChangeAvatarCamera');
    if (triggerBtn && fileInput) triggerBtn.onclick = () => fileInput.click();
    if (cameraBtn && fileInput) cameraBtn.onclick = () => fileInput.click();

    if (fileInput) {
      fileInput.onchange = async () => {
        const file = fileInput.files && fileInput.files[0];
        if (!file) return;
        if (file.size > 5 * 1024 * 1024) return toast('Kích thước ảnh tối đa là 5MB', true);
        const fd = new FormData();
        fd.append('avatar_file', file);
        try {
          toast('Đang tải ảnh lên...');
          const res = await api('../api/account/avatar.php', { method: 'POST', body: fd });
          const newUrl = res.avatar_url || res.url;
          u.avatar_url = newUrl;
          renderAvatarBox(newUrl, $('fullName').value);
          if (window.HeaderNav) window.HeaderNav.updateUser({ ...u, avatar_url: newUrl });
          toast('Cập nhật ảnh đại diện thành công');
        } catch (err) {
          toast(err.message, true);
        } finally {
          fileInput.value = '';
        }
      };
    }

    // Modal nhập link ảnh
    const urlModal = $('modalAvatarUrl');
    const urlBtn = $('btnInputAvatarUrl');
    const closeUrlBtn = $('btnCloseAvatarUrlModal');
    const cancelUrlBtn = $('btnCancelAvatarUrlModal');
    const urlForm = $('formAvatarUrl');

    if (urlBtn && urlModal) urlBtn.onclick = () => {
      $('inputAvatarUrlVal').value = u.avatar_url || '';
      urlModal.classList.add('show');
      $('inputAvatarUrlVal').focus();
    };
    if (closeUrlBtn && urlModal) closeUrlBtn.onclick = () => urlModal.classList.remove('show');
    if (cancelUrlBtn && urlModal) cancelUrlBtn.onclick = () => urlModal.classList.remove('show');
    if (urlModal) urlModal.onclick = e => { if (e.target === urlModal) urlModal.classList.remove('show'); };

    if (urlForm) {
      urlForm.onsubmit = async (e) => {
        e.preventDefault();
        const url = $('inputAvatarUrlVal').value.trim();
        if (!url) return toast('Vui lòng nhập đường dẫn ảnh', true);
        try {
          const res = await post('../api/account/avatar.php', { avatar_url: url });
          const newUrl = res.avatar_url || url;
          u.avatar_url = newUrl;
          renderAvatarBox(newUrl, $('fullName').value);
          if (window.HeaderNav) window.HeaderNav.updateUser({ ...u, avatar_url: newUrl });
          if (urlModal) urlModal.classList.remove('show');
          toast('Cập nhật ảnh đại diện thành công');
        } catch (err) {
          toast(err.message, true);
        }
      };
    }

    // Xóa ảnh đại diện
    const delBtn = $('btnDeleteAvatar');
    if (delBtn) {
      delBtn.onclick = async () => {
        if (!confirm('Bạn có chắc muốn xóa ảnh đại diện và quay về chữ cái mặc định?')) return;
        try {
          await post('../api/account/avatar.php', { avatar_url: '' });
          u.avatar_url = null;
          renderAvatarBox(null, $('fullName').value);
          if (window.HeaderNav) window.HeaderNav.updateUser({ ...u, avatar_url: null });
          toast('Đã xóa ảnh đại diện');
        } catch (err) {
          toast(err.message, true);
        }
      };
    }
  }

  // ===== Cài đặt Đa tiền tệ (Nhóm 2) =====
  if ($('currencyForm')) {
    $('currencyForm').onsubmit = async e => {
      e.preventDefault();
      const selected = $('currencySelect').value;
      try {
        await post('../api/account/currency.php', { currency: selected });
        localStorage.setItem('app_currency', selected);
        toast(`Đã đổi đơn vị tiền tệ thành ${selected}`);
      } catch (err) { toast(err.message, true); }
    };
  }

  $('profileForm').onsubmit = async e => {
    e.preventDefault();
    try {
      await post('../api/account/update.php', payload());
      $('userName').textContent = $('fullName').value.trim();
      toast('Đã cập nhật thông tin');
    } catch (err) { toast(err.message, true); }
  };

  // [HUY T2] Chức năng 4: bật/tắt email cảnh báo, lưu ngay khi gạt công tắc
  $('emailAlert').onchange = async e => {
    const on = e.target.checked;
    try {
      await post('../api/account/update.php', payload());
      toast(on ? 'Đã bật email cảnh báo ngân sách' : 'Đã tắt email cảnh báo ngân sách');
    } catch (err) { e.target.checked = !on; toast(err.message, true); }
  };

  // [HUY T3] Lưu thông tin tài khoản ngân hàng
  if ($('bankForm')) {
    $('bankForm').onsubmit = async e => {
      e.preventDefault();
      const acc = $('bankAcc').value.replace(/\s/g, '');
      if (($('bankCode').value || acc) && (!$('bankCode').value || !/^\d{6,20}$/.test(acc)))
        return toast('Chọn ngân hàng và nhập số tài khoản (6–20 chữ số)', true);
      try {
        await post('../api/account/update.php', payload());
        toast('Đã lưu tài khoản ngân hàng');
      } catch (err) { toast(err.message, true); }
    };
  }

  // ===== Đổi mật khẩu =====
  $('pwForm').onsubmit = async e => {
    e.preventDefault();
    const nw = $('newPw').value;
    if (nw.length < 6) return toast('Mật khẩu mới cần tối thiểu 6 ký tự', true);
    if (nw !== $('cfPw').value) return toast('Mật khẩu nhập lại chưa khớp', true);
    try {
      await post('../api/account/change_password.php', { current_password: $('curPw').value, new_password: nw, confirm_password: nw });
      $('pwForm').reset();
      toast('Đã đổi mật khẩu');
    } catch (err) { toast(err.message, true); }
  };

  // ===== [PHIÊN BẢN ĐĂNG NHẬP] Quản lý thiết bị & Phiên làm việc =====
  function initLoginSessionInfo(u) {
    const devBox = $('sessionDeviceInfo');
    const timeBox = $('sessionTimeInfo');
    if (devBox) {
      const ua = navigator.userAgent;
      let browser = 'Trình duyệt Web';
      if (ua.includes('CocCoc')) browser = 'Cốc Cốc';
      else if (ua.includes('Edg/')) browser = 'Microsoft Edge';
      else if (ua.includes('Chrome/')) browser = 'Google Chrome';
      else if (ua.includes('Firefox/')) browser = 'Mozilla Firefox';
      else if (ua.includes('Safari/') && !ua.includes('Chrome')) browser = 'Apple Safari';
      
      let os = 'Máy tính';
      if (ua.includes('Windows')) os = 'Windows PC';
      else if (ua.includes('Macintosh') || ua.includes('Mac OS')) os = 'macOS';
      else if (ua.includes('Android')) os = 'Thiết bị Android';
      else if (ua.includes('iPhone') || ua.includes('iPad')) os = 'Thiết bị iOS';
      else if (ua.includes('Linux')) os = 'Linux';

      devBox.textContent = `${os} • ${browser}`;
    }

    if (timeBox) {
      const now = new Date();
      const timeStr = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')} - ${now.getDate()}/${now.getMonth() + 1}/${now.getFullYear()}`;
      timeBox.textContent = `Phiên hoạt động hiện tại (Đăng nhập lúc ${timeStr})`;
    }

    const refreshBtn = $('btnRefreshSession');
    if (refreshBtn) {
      refreshBtn.onclick = () => {
        toast('Đã làm mới thông tin phiên đăng nhập');
        if (timeBox) {
          const now = new Date();
          timeBox.textContent = `Phiên hoạt động hiện tại (Cập nhật lúc ${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')})`;
        }
      };
    }

    const logoutBtn = $('btnLogoutSession');
    if (logoutBtn) {
      logoutBtn.onclick = async () => {
        if (!confirm('Bạn có chắc chắn muốn đăng xuất phiên làm việc này?')) return;
        try {
          await api('../api/auth/logout.php', { method: 'POST' });
        } catch (e) {}
        localStorage.clear();
        location.href = 'login.html';
      };
    }
  }

  // ===== Danh mục riêng của người dùng =====
  function render() {
    if (!$('catBody')) return;
    $('catBody').innerHTML = cats.length ? cats.map(c => `<tr>
      <td>${esc(cname(c))}</td>
      <td><span class="tag ${c.type === 'income' ? 'ok' : 'over'}">${c.type === 'income' ? 'Thu nhập' : 'Chi tiêu'}</span></td>
      <td style="text-align:right">
        <button class="btn ghost" data-e="${cid(c)}"><i class="fa-solid fa-pen"></i> Sửa</button>
        <button class="btn out" data-d="${cid(c)}"><i class="fa-solid fa-trash"></i> Xóa</button>
      </td></tr>`).join('')
      : '<tr><td colspan="3" class="empty">Bạn chưa có danh mục riêng. Chọn "Thêm danh mục" để tạo mới.</td></tr>';
  }

  async function loadCats() {
    if (!$('catBody')) return;
    try {
      cats = list(await api('../api/category/list.php')).filter(c => !+c.is_default);
      render();
    } catch (e) { toast(e.message, true); }
  }

  function openModal(c) {
    if (!$('modal')) return;
    editId = c ? cid(c) : null;
    if ($('mTitle')) $('mTitle').textContent = c ? 'Sửa danh mục' : 'Thêm danh mục';
    if ($('catName')) $('catName').value = c ? cname(c) : '';
    if ($('catType')) $('catType').value = c ? c.type : 'expense';
    $('modal').classList.add('show');
    if ($('catName')) $('catName').focus();
  }
  const closeModal = () => { if ($('modal')) $('modal').classList.remove('show'); };

  if ($('catForm')) {
    $('catForm').onsubmit = async e => {
      e.preventDefault();
      const body = { name: $('catName').value.trim(), type: $('catType').value };
      if (!body.name) return toast('Nhập tên danh mục', true);
      try {
        if (editId) await post('../api/category/edit.php', { ...body, id: editId, category_id: editId });
        else await post('../api/category/add.php', body);
        closeModal(); toast(editId ? 'Đã cập nhật danh mục' : 'Đã thêm danh mục'); loadCats();
      } catch (err) { toast(err.message, true); }
    };
  }

  if ($('catBody')) {
    $('catBody').onclick = async e => {
      const btn = e.target.closest('button');
      if (!btn) return;
      if (btn.dataset.e) return openModal(cats.find(c => cid(c) == btn.dataset.e));
      if (btn.dataset.d && confirm('Xóa danh mục này?')) {
        try {
          await post('../api/category/delete.php', { id: btn.dataset.d, category_id: btn.dataset.d });
          toast('Đã xóa danh mục'); loadCats();
        } catch (err) { toast(err.message, true); }
      }
    };
  }

  if ($('addCat')) $('addCat').onclick = () => openModal();
  if ($('cancel')) $('cancel').onclick = closeModal;
  if ($('modal')) $('modal').onclick = e => { if (e.target === $('modal')) closeModal(); };

  // ===== [HUY T2] Chuyển tab =====
  function switchTab(tabKey) {
    if (tabKey === 'info') tabKey = 'account';
    document.querySelectorAll('.tabs button').forEach(x => {
      const match = x.dataset.tab === tabKey;
      x.classList.toggle('on', match);
    });
    document.querySelectorAll('.tab').forEach(t => {
      t.classList.toggle('hide', t.id !== 'tab-' + tabKey);
    });
  }

  document.querySelectorAll('.tabs button').forEach(b => b.onclick = () => {
    switchTab(b.dataset.tab);
  });

  // Tự động kích hoạt tab dựa theo param ?tab= hoặc #
  try {
    const urlParams = new URLSearchParams(window.location.search);
    let requestedTab = urlParams.get('tab') || window.location.hash.replace('#', '');
    if (requestedTab) {
      switchTab(requestedTab);
    }
  } catch (_) {}

  // Lắng nghe sự kiện hashchange nếu người dùng bấm menu dropdown khi đang ở trang account.html
  window.addEventListener('hashchange', () => {
    const hashTab = window.location.hash.replace('#', '');
    if (hashTab) switchTab(hashTab);
  });

  // ===== [HUY T2] Chức năng 6: Sao lưu & Khôi phục =====
  $('backupBtn').onclick = async () => {
    try {
      const r = await fetch('../api/backup/export.php', { credentials: 'same-origin' });
      if (r.status === 401) { location.href = 'login.html'; return; }
      if (!r.ok) throw new Error('Không tạo được bản sao lưu, vui lòng thử lại');
      const a = document.createElement('a');
      a.href = URL.createObjectURL(await r.blob());
      a.download = `cashmanager-backup-${new Date().toLocaleDateString('sv-SE')}.json`;
      a.click();
      setTimeout(() => URL.revokeObjectURL(a.href), 1000);
      toast('Đã tải bản sao lưu');
    } catch (err) { toast(err.message, true); }
  };

  const DROP_TEXT = 'Kéo thả file sao lưu (.json) vào đây';
  function pick(f) {
    if (!f) return;
    if (!/\.json$/i.test(f.name)) return toast('Chỉ chọn file sao lưu có đuôi .json', true);
    if (f.size > 10 * 1024 * 1024) return toast('File quá lớn, tối đa 10 MB', true);
    file = f;
    $('dropText').textContent = `${f.name} (${(f.size / 1024).toFixed(1)} KB)`;
    $('restoreBtn').disabled = false;
  }
  $('restoreFile').onchange = e => pick(e.target.files[0]);
  ['dragenter', 'dragover'].forEach(ev => $('drop').addEventListener(ev, e => { e.preventDefault(); $('drop').classList.add('over'); }));
  ['dragleave', 'drop'].forEach(ev => $('drop').addEventListener(ev, e => { e.preventDefault(); $('drop').classList.remove('over'); }));
  $('drop').addEventListener('drop', e => pick(e.dataTransfer.files[0]));

  $('restoreBtn').onclick = async () => {
    const mode = document.querySelector('input[name=mode]:checked').value;
    const msg = mode === 'overwrite'
      ? 'Dữ liệu hiện tại sẽ bị thay thế bằng dữ liệu trong file. Bạn chắc chắn muốn tiếp tục?'
      : 'Khôi phục dữ liệu từ file này?';
    if (!file || !confirm(msg)) return;
    const fd = new FormData();
    fd.append('file', file);
    fd.append('mode', mode);
    $('restoreBtn').disabled = true;
    try {
      await api('../api/backup/restore.php', { method: 'POST', body: fd });
      toast('Đã khôi phục dữ liệu');
      file = null; $('restoreFile').value = ''; $('dropText').textContent = DROP_TEXT;
      loadCats();
    } catch (err) { toast(err.message, true); $('restoreBtn').disabled = false; }
  };

  loadCats();

  // [NGÔ BẢO - FE] Chức năng 3.2: Nhắc nhở ghi chép hàng ngày (Web Push Notification)
  function updateNotifBadge() {
    const badge = $('notifStatusBadge');
    if (!badge) return;
    if (!('Notification' in window)) {
      badge.className = 'tag over';
      badge.textContent = 'Trình duyệt không hỗ trợ';
      return;
    }
    if (Notification.permission === 'granted') {
      badge.className = 'tag ok';
      badge.textContent = 'Đã cho phép';
    } else if (Notification.permission === 'denied') {
      badge.className = 'tag over';
      badge.textContent = 'Bị chặn (mở cài đặt để bật)';
    } else {
      badge.className = 'tag';
      badge.textContent = 'Chưa cấp quyền';
    }
  }

  const isReminderOn = localStorage.getItem('reminder_enabled') === 'true';
  const savedTime = localStorage.getItem('reminder_time') || '21:00';
  if ($('reminderToggle')) $('reminderToggle').checked = isReminderOn;
  if ($('reminderTime')) $('reminderTime').value = savedTime;
  updateNotifBadge();

  if ($('reminderToggle')) {
    $('reminderToggle').onchange = async e => {
      const on = e.target.checked;
      if (on) {
        if ('Notification' in window && Notification.permission !== 'granted') {
          const perm = await (window.PWA ? window.PWA.requestNotificationPermission() : Notification.requestPermission());
          updateNotifBadge();
          if (perm !== 'granted') {
            e.target.checked = false;
            return toast('Vui lòng cho phép quyền thông báo trên trình duyệt', true);
          }
        }
      }
      localStorage.setItem('reminder_enabled', on ? 'true' : 'false');
      toast(on ? 'Đã bật nhắc nhở ghi chép hàng ngày' : 'Đã tắt nhắc nhở ghi chép');
      try {
        await post('../api/notification/settings.php', { reminder_enabled: on ? 1 : 0, reminder_time: $('reminderTime').value });
      } catch (_) {}
    };
  }

  if ($('btnTestNotif')) {
    $('btnTestNotif').onclick = async () => {
      if ('Notification' in window && Notification.permission !== 'granted') {
        const perm = await (window.PWA ? window.PWA.requestNotificationPermission() : Notification.requestPermission());
        updateNotifBadge();
        if (perm !== 'granted') return toast('Bạn đã từ chối quyền thông báo', true);
      }
      const sent = window.PWA ? window.PWA.sendPushNotification('Nhắc nhở chi tiêu - CashManager', {
        body: 'Đây là thông báo thử nghiệm! Đừng quên ghi lại các khoản chi hôm nay nhé.'
      }) : false;
      if (sent) toast('Đã gửi thông báo thử nghiệm');
      else toast('Không thể hiển thị thông báo, kiểm tra quyền trình duyệt', true);
    };
  }

  if ($('btnSaveReminder')) {
    $('btnSaveReminder').onclick = async () => {
      const timeVal = $('reminderTime').value || '21:00';
      localStorage.setItem('reminder_time', timeVal);
      toast(`Đã lưu giờ nhắc nhở mỗi tối: ${timeVal}`);
      try {
        await post('../api/notification/settings.php', { reminder_enabled: $('reminderToggle').checked ? 1 : 0, reminder_time: timeVal });
      } catch (_) {}
    };
  }

  // ===== [BUDGETBAKERS STYLE] Cài đặt Giao diện (Sáng/Tối, Mật độ) & Ngôn ngữ =====
  function initAppearanceSettings() {
    // 1. Chế độ sáng và tối
    const currentTheme = localStorage.getItem('app_theme') || 'auto';
    const themeRadios = document.querySelectorAll('input[name="appTheme"]');
    themeRadios.forEach(r => {
      if (r.value === currentTheme) {
        r.checked = true;
        updateCardHighlight(r);
      }
      r.addEventListener('change', () => {
        const val = r.value;
        localStorage.setItem('app_theme', val);
        const isDark = val === 'dark' || (val === 'auto' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
        if (isDark) document.documentElement.classList.add('dark-theme');
        else document.documentElement.classList.remove('dark-theme');
        updateCardHighlight(r);
        toast('Đã áp dụng chế độ giao diện: ' + (val === 'dark' ? 'Tối' : val === 'light' ? 'Sáng' : 'Theo trình duyệt'));
      });
    });

    // 2. Mật độ danh sách ghi chép
    const currentDensity = localStorage.getItem('app_density') || 'low';
    const densityRadios = document.querySelectorAll('input[name="appDensity"]');
    densityRadios.forEach(r => {
      if (r.value === currentDensity) {
        r.checked = true;
        updateCardHighlight(r);
      }
      r.addEventListener('change', () => {
        const val = r.value;
        localStorage.setItem('app_density', val);
        document.documentElement.setAttribute('data-density', val);
        document.body.setAttribute('data-density', val);
        updateCardHighlight(r);
        toast('Đã đổi mật độ danh sách: ' + (val === 'high' ? 'Cao' : val === 'medium' ? 'Trung bình' : 'Thấp'));
      });
    });

    // 3. Ngôn ngữ của trang web
    const langSelect = document.getElementById('selectAppLanguage');
    if (langSelect) {
      const currentSavedLang = localStorage.getItem('app_language') || 'vi';
      langSelect.value = currentSavedLang;
      langSelect.addEventListener('change', () => {
        const val = langSelect.value;
        localStorage.setItem('app_language', val);
        if (window.CashManagerI18n) {
          window.CashManagerI18n.applyLanguage(val);
        }
        const langNames = {
          vi: 'Tiếng Việt',
          en: 'English',
          ja: '日本語',
          ko: '한국어',
          zh: '简体中文',
          fr: 'Français'
        };
        toast('Đã áp dụng ngôn ngữ: ' + (langNames[val] || val));
      });
    }

    function updateCardHighlight(radio) {
      const container = radio.closest('.theme-options-grid, .density-options-grid');
      if (container) {
        container.querySelectorAll('.setting-choice-card').forEach(c => c.classList.remove('active'));
        const card = radio.closest('.setting-choice-card');
        if (card) card.classList.add('active');
      }
    }
  }

  // ===== [HỖ TRỢ / TRỢ GIÚP] Gửi yêu cầu hỗ trợ tới Quản trị viên =====
  function initSupportHelp() {
    const supportForm = $('supportHelpForm');
    if (!supportForm) return;

    supportForm.onsubmit = async (e) => {
      e.preventDefault();
      const subject = $('supportSubject') ? $('supportSubject').value : 'Hỗ trợ tính năng';
      const message = $('supportMessage') ? $('supportMessage').value.trim() : '';

      if (!message || message.length < 5) {
        return toast('Vui lòng nhập nội dung yêu cầu hỗ trợ (tối thiểu 5 ký tự)', true);
      }

      const btn = $('btnSubmitSupport');
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang gửi...';
      }

      try {
        const userName = ($('fullName') && $('fullName').value.trim()) || 'Người dùng CashManager';
        const userEmail = ($('email') && $('email').value.trim()) || 'cashmanager.contact@gmail.com';

        const res = await api('../api/support/contact.php', {
          method: 'POST',
          body: {
            name: userName,
            email: userEmail,
            subject: subject,
            message: message
          }
        });

        toast('Yêu cầu hỗ trợ của bạn đã được gửi thành công!');
        supportForm.reset();
      } catch (err) {
        toast(err.message || 'Không thể gửi yêu cầu hỗ trợ, vui lòng thử lại sau', true);
      } finally {
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Gửi yêu cầu hỗ trợ';
        }
      }
    };
  }

  initAppearanceSettings();
  initSupportHelp();
})();