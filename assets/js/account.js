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
    full_name: $('fullName').value.trim(), email: $('email').value.trim(),
    email_alerts: $('emailAlert').checked ? 1 : 0,
    bank_code: $('bankCode').value, bank_account: $('bankAcc').value.replace(/\s/g, '')   // [HUY T3]
  });

  // ===== Thông tin cá nhân + công tắc email =====
  initUser().then(u => {
    $('fullName').value = u.full_name || u.name || '';
    $('email').value = u.email || '';
    $('emailAlert').checked = !!+(u.email_alerts ?? u.email_notifications ?? 0); // [HUY T2]
    $('bankCode').value = u.bank_code ?? '';        // [HUY T3]
    $('bankAcc').value = u.bank_account ?? '';      // [HUY T3]
  });

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

  // ===== Danh mục riêng của người dùng =====
  function render() {
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
    try {
      cats = list(await api('../api/category/list.php')).filter(c => !+c.is_default);
      render();
    } catch (e) { toast(e.message, true); }
  }

  function openModal(c) {
    editId = c ? cid(c) : null;
    $('mTitle').textContent = c ? 'Sửa danh mục' : 'Thêm danh mục';
    $('catName').value = c ? cname(c) : '';
    $('catType').value = c ? c.type : 'expense';
    $('modal').classList.add('show');
    $('catName').focus();
  }
  const closeModal = () => $('modal').classList.remove('show');

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

  $('addCat').onclick = () => openModal();
  $('cancel').onclick = closeModal;
  $('modal').onclick = e => { if (e.target === $('modal')) closeModal(); };

  // ===== [HUY T2] Chuyển tab =====
  document.querySelectorAll('.tabs button').forEach(b => b.onclick = () => {
    document.querySelectorAll('.tabs button').forEach(x => x.classList.toggle('on', x === b));
    document.querySelectorAll('.tab').forEach(t => t.classList.toggle('hide', t.id !== 'tab-' + b.dataset.tab));
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
})();