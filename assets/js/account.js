(() => {
    const $ = id => document.getElementById(id);
    let cats = [], editId = null, timer;
  
    // ===== Hàm dùng chung =====
    const api = async (url, opts = {}) => {
      const o = { credentials: 'same-origin', headers: {}, ...opts };
      if (o.body && typeof o.body !== 'string') { o.headers['Content-Type'] = 'application/json'; o.body = JSON.stringify(o.body); }
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
  
    // ===== Thông tin cá nhân =====
    initUser().then(u => { $('fullName').value = u.full_name || u.name || ''; $('email').value = u.email || ''; });
  
    $('profileForm').onsubmit = async e => {
      e.preventDefault();
      try {
        await post('../api/account/update.php', { full_name: $('fullName').value.trim(), email: $('email').value.trim() });
        $('userName').textContent = $('fullName').value.trim();
        toast('Đã cập nhật thông tin');
      } catch (err) { toast(err.message, true); }
    };
  
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
        cats = list(await api('../api/category/list.php')).filter(c => c.user_id !== null && c.user_id !== undefined);
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
  
    loadCats();
  })();