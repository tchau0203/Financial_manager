(() => {
  const $ = id => document.getElementById(id);
  let mode = 'personal', me = {}, spaces = [], cats = [], members = [], txs = [], timer;

  // ===== Hàm dùng chung =====
  const api = async (url, opts = {}) => {
    const o = { credentials: 'same-origin', headers: {}, ...opts };
    if (o.body && !(o.body instanceof FormData) && typeof o.body !== 'string') { o.headers['Content-Type'] = 'application/json'; o.body = JSON.stringify(o.body); }
    const r = await fetch(url, o);
    if (r.status === 401) { location.href = 'login.html'; throw new Error('Chưa đăng nhập'); }
    let j = {};
    try { j = await r.json(); } catch (e) {}
    if (!r.ok || j.success === false || j.status === 'error') throw new Error(j.message || j.error || 'Có lỗi xảy ra, vui lòng thử lại');
    return j.data !== undefined ? j.data : j;
  };
  const post = (url, body) => api(url, { method: 'POST', body });
  const list = d => Array.isArray(d) ? d : (d && Object.values(d).find(Array.isArray)) || [];
  const money = n => new Intl.NumberFormat('vi-VN').format(Math.round(+n || 0)) + ' đ';
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

  // ===== Avatar chữ cái đầu (có ảnh thì hiện ảnh) =====
  const PALETTE = ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#06b6d4', '#8b5cf6', '#ec4899', '#0ea5e9'];
  const ini = n => { const w = String(n || '?').trim().split(/\s+/); return ((w[0][0] || '?') + (w.length > 1 ? w[w.length - 1][0] : '')).toUpperCase(); };
  const avatar = (name, url) => {
    let h = 0;
    for (const ch of String(name)) h = (h * 31 + ch.charCodeAt(0)) >>> 0;
    const img = url ? `<img src="${esc(url)}" alt="" onerror="this.remove()">` : '';
    return `<span class="av" style="background:${PALETTE[h % PALETTE.length]}">${esc(ini(name))}${img}</span>`;
  };
  const fmtDate = s => { const m = String(s || '').match(/^(\d{4})-(\d{2})-(\d{2})/); return m ? `${m[3]}/${m[2]}/${m[1]}` : (s || ''); };
  const who = t => t.user_name ?? t.full_name ?? t.paid_by ?? t.created_by_name ?? (me.full_name || me.name || 'Tôi');
  const sid = s => s.id ?? s.space_id;
  const cur = () => spaces.find(s => sid(s) == $('space').value);

  // ===== Hiển thị =====
  function updateActions() {
    const shared = mode === 'shared', s = cur();
    $('spaceBox').classList.toggle('hide', !shared || !spaces.length);
    $('newSpace').classList.toggle('hide', !shared);
    $('inviteBtn').classList.toggle('hide', !shared || !s || !!(s.role && s.role !== 'owner'));
    $('addTx').classList.toggle('hide', !shared || !s);
    $('membersCard').classList.toggle('hide', !shared || !members.length);
    $('tblTitle').textContent = shared ? (s ? `Chi tiêu của "${s.name}"` : 'Chi tiêu của quỹ') : 'Giao dịch cá nhân gần đây';
  }

  function render() {
    const sum = type => txs.filter(t => t.type === type).reduce((a, t) => a + (+t.amount || 0), 0);
    const inc = sum('income'), exp = sum('expense');
    $('sIn').textContent = money(inc);
    $('sOut').textContent = money(exp);
    $('sNet').textContent = money(inc - exp);

    const empty = mode === 'shared' && !spaces.length
      ? 'Bạn chưa tham gia quỹ chung nào. Chọn "Tạo quỹ mới" để bắt đầu.'
      : 'Chưa có giao dịch nào.';
    $('tbody').innerHTML = txs.length ? txs.map(t => {
      const inc = t.type === 'income';
      return `<tr>
        <td><div class="who">${avatar(who(t), t.avatar)}<span>${esc(who(t))}</span></div></td>
        <td>${esc(fmtDate(t.date ?? t.transaction_date))}</td>
        <td>${esc(t.category_name ?? t.category ?? '')}</td>
        <td>${esc(t.note ?? t.description ?? '')}</td>
        <td style="text-align:right"><b class="${inc ? 'green' : 'red'}">${inc ? '+' : '-'}${money(t.amount)}</b></td>
      </tr>`;
    }).join('') : `<tr><td colspan="5" class="empty">${empty}</td></tr>`;

    $('members').innerHTML = members.map(m => {
      const n = m.full_name ?? m.name ?? m.user_name ?? '';
      return `<div class="mem">${avatar(n, m.avatar)}<span>${esc(n)}</span>${m.role === 'owner' ? '<span class="tag ok">Chủ quỹ</span>' : ''}</div>`;
    }).join('');
    updateActions();
  }

  // ===== Tải dữ liệu =====
  async function loadTx() {
    try {
      if (mode === 'personal') {
        const [i, e] = await Promise.all(['income', 'expense'].map(type =>
          api(`../api/transaction/list.php?type=${type}`).then(d => list(d).map(x => ({ type, ...x })))));
        txs = [...i, ...e];
        members = [];
      } else if (cur()) {
        const d = await api(`../api/shared/transactions.php?space_id=${$('space').value}`);
        txs = d.transactions || d.items || list(d);
        members = d.members || cur().members || [];
      } else { txs = []; members = []; }
      const day = t => String(t.date ?? t.transaction_date ?? '');
      txs.sort((a, b) => day(b).localeCompare(day(a)));
      if (mode === 'personal') txs = txs.slice(0, 50);
    } catch (e) { txs = []; members = []; toast(e.message, true); }
    render();
  }

  async function loadSpaces(selectId) {
    try {
      spaces = list(await api('../api/shared/list.php'));
      $('space').innerHTML = spaces.map(s => `<option value="${sid(s)}">${esc(s.name)}</option>`).join('');
      if (selectId) $('space').value = selectId;
    } catch (e) { spaces = []; toast(e.message, true); }
    loadTx();
  }

  function setMode(m) {
    mode = m;
    document.querySelectorAll('#seg button').forEach(b => b.classList.toggle('on', b.dataset.mode === m));
    m === 'shared' ? loadSpaces() : loadTx();
  }

  // ===== Modal =====
  const open = id => $(id).classList.add('show');
  document.querySelectorAll('[data-close]').forEach(b => b.onclick = () => b.closest('.modal').classList.remove('show'));
  document.querySelectorAll('.modal').forEach(m => m.onclick = e => { if (e.target === m) m.classList.remove('show'); });

  // Tạo quỹ
  $('newSpace').onclick = () => { $('spaceForm').reset(); open('spaceModal'); $('spaceName').focus(); };
  $('spaceForm').onsubmit = async e => {
    e.preventDefault();
    try {
      const d = await post('../api/shared/create.php', { name: $('spaceName').value.trim() });
      $('spaceModal').classList.remove('show');
      toast('Đã tạo quỹ chung');
      loadSpaces(d.id ?? d.space_id);
    } catch (err) { toast(err.message, true); }
  };

  // Mời thành viên
  $('inviteBtn').onclick = () => { $('inviteForm').reset(); open('inviteModal'); $('inviteEmail').focus(); };
  $('inviteForm').onsubmit = async e => {
    e.preventDefault();
    try {
      const d = await post('../api/shared/invite.php', { space_id: +$('space').value, email: $('inviteEmail').value.trim() });
      $('inviteModal').classList.remove('show');
      const code = d.invite_code ?? d.code;
      toast(code ? `Đã mời thành viên. Mã mời: ${code}` : 'Đã gửi lời mời');
      loadTx();
    } catch (err) { toast(err.message, true); }
  };

  // Thêm giao dịch vào quỹ
  const fillCats = () => {
    $('txCat').innerHTML = cats.filter(c => c.type === $('txType').value)
      .map(c => `<option value="${c.id ?? c.category_id}">${esc(c.name ?? c.category_name)}</option>`).join('');
  };
  $('txType').onchange = fillCats;
  $('addTx').onclick = () => {
    $('txForm').reset();
    $('txDate').value = new Date().toLocaleDateString('sv-SE');
    fillCats(); open('txModal');
  };
  $('txForm').onsubmit = async e => {
    e.preventDefault();
    const body = {
      space_id: +$('space').value, type: $('txType').value, category_id: +$('txCat').value,
      amount: +$('txAmount').value, date: $('txDate').value, transaction_date: $('txDate').value, note: $('txNote').value.trim()
    };
    if (!body.category_id || body.amount <= 0) return toast('Chọn danh mục và nhập số tiền lớn hơn 0', true);
    try {
      await post('../api/shared/transactions.php', body);
      $('txModal').classList.remove('show');
      toast('Đã thêm giao dịch vào quỹ');
      loadTx();
    } catch (err) { toast(err.message, true); }
  };

  // ===== Sự kiện chung =====
  document.querySelectorAll('#seg button').forEach(b => b.onclick = () => setMode(b.dataset.mode));
  $('space').onchange = loadTx;

  (async () => {
    me = await initUser();
    try { cats = list(await api('../api/category/list.php')); } catch (e) {}
    setMode('personal');
  })();
})();
