(() => {
    const $ = id => document.getElementById(id);
    const now = new Date();
    let items = [], timer;
  
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
      try {
        const d = await api('../api/auth/me.php'), u = d.user || d;
        $('userName').textContent = u.full_name || u.name || u.username || '';
        if (window.HeaderNav) window.HeaderNav.updateUser(u);
      } catch (e) {}
      $('logoutBtn').onclick = async () => {
        try { await api('../api/auth/logout.php', { method: 'POST' }); } catch (e) {}
        location.href = 'login.html';
      };
    }
  
    // ===== Ngân sách =====
    const MAP = { normal: ['ok', 'Bình thường'], warning: ['warn', 'Sắp chạm ngưỡng'], exceeded: ['over', 'Vượt hạn mức'] };
    const calc = b => {
      const amount = +b.amount || 0, spent = +b.spent_amount || 0;
      const p = +b.percentage || (amount ? spent / amount * 100 : 0);
      const th = +b.alert_threshold || 80;
      return {
        amount, spent, p,
        left: b.remaining_amount ?? amount - spent,
        s: MAP[b.status] || (p >= 100 ? MAP.exceeded : p >= th ? MAP.warning : MAP.normal)
      };
    };
    const setBar = (el, p, th = 80) => {
      el.style.width = Math.min(p, 100) + '%';
      el.className = p >= 100 ? 'over' : p >= th ? 'warn' : 'ok';
    };
  
    // Bộ chọn tháng (1-12) và năm (hiện tại ± 1)
    for (let i = 1; i <= 12; i++) $('month').add(new Option('Tháng ' + i, i));
    for (let y = now.getFullYear() - 1; y <= now.getFullYear() + 1; y++) $('year').add(new Option(y, y));
    $('month').value = now.getMonth() + 1;
    $('year').value = now.getFullYear();
  
    const card = b => {
      const c = calc(b);
      return `<div class="card" style="border-radius: 20px; border: 1px solid rgba(226, 232, 240, 0.8); box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.04); background: #ffffff;">
        <div class="row"><b style="font-size: 1.05rem; color: var(--text-heading);">${esc(b.category_name ?? b.name)}</b><span class="tag ${c.s[0]}">${c.s[1]}</span></div>
        <div class="bar" style="height: 8px; border-radius: 99px; background: #e2e8f0; overflow: hidden; margin: 12px 0 8px;"><i class="${c.s[0]}" style="width:${Math.min(c.p, 100)}%; height: 100%; border-radius: 99px;"></i></div>
        <div class="row muted" style="font-size: 0.85rem; margin-bottom: 4px;"><span>Đã chi ${money(c.spent)}</span><span style="font-weight: 700;">${c.p.toFixed(0)}%</span></div>
        <div class="row muted" style="font-size: 0.85rem; margin-bottom: 12px;"><span>Hạn mức ${money(c.amount)}</span><span style="font-weight: 600;">Còn ${money(c.left)}</span></div>
        <div class="row act" style="display: flex; gap: 8px; justify-content: flex-end;">
          <button class="btn ghost" data-e="${b.category_id}" style="border-radius: 8px; padding: 6px 14px; font-size: 0.82rem;"><i class="fa-solid fa-pen"></i> Sửa</button>
          <button class="btn out" data-d="${b.category_id}" style="border-radius: 8px; padding: 6px 14px; font-size: 0.82rem;"><i class="fa-solid fa-trash"></i> Xóa</button>
        </div></div>`;
    };
  
    function render() {
      const total = items.reduce((s, b) => s + calc(b).amount, 0);
      const spent = items.reduce((s, b) => s + calc(b).spent, 0);
      const p = total ? spent / total * 100 : 0;
      $('sumText').textContent = total ? `Đã chi ${money(spent)} / ${money(total)} (${p.toFixed(0)}%)` : 'Chưa thiết lập hạn mức tháng này.';
      setBar($('sumBar'), p);
      $('grid').innerHTML = items.length ? items.map(card).join('')
        : '<div class="card empty" style="grid-column:1/-1">Chưa có ngân sách nào cho tháng này. Chọn "Đặt ngân sách" để bắt đầu.</div>';
    }
  
    async function load() {
      try {
        items = list(await api(`../api/budget/get.php?month=${$('month').value}&year=${$('year').value}`));
        render();
      } catch (e) { toast(e.message, true); }
    }
  
    async function loadCats() {
      try {
        const cats = list(await api('../api/category/list.php?type=expense'));
        $('cat').innerHTML = cats.map(c => `<option value="${c.id ?? c.category_id}">${esc(c.name ?? c.category_name)}</option>`).join('');
      } catch (e) { toast(e.message, true); }
    }
  
    // Modal đặt / sửa ngân sách
    function openModal(b) {
      $('form').reset();
      $('mTitle').textContent = `${b ? 'Sửa' : 'Đặt'} ngân sách tháng ${$('month').value}/${$('year').value}`;
      $('cat').disabled = !!b;
      $('thr').value = b ? (b.alert_threshold || 80) : 80;
      if (b) { $('cat').value = b.category_id; $('amount').value = Math.round(b.amount); }
      $('modal').classList.add('show');
    }
    const closeModal = () => $('modal').classList.remove('show');
  
    $('form').onsubmit = async e => {
      e.preventDefault();
      const body = {
        category_id: +$('cat').value, amount: +$('amount').value,
        month: +$('month').value, year: +$('year').value, alert_threshold: +$('thr').value || 80
      };
      if (!body.category_id || body.amount <= 0) return toast('Chọn danh mục và nhập hạn mức lớn hơn 0', true);
      try {
        await api('../api/budget/set.php', { method: 'POST', body });
        closeModal(); toast('Đã lưu ngân sách'); load();
      } catch (err) { toast(err.message, true); }
    };
  
    $('grid').onclick = async e => {
      const btn = e.target.closest('button');
      if (!btn) return;
      if (btn.dataset.e) return openModal(items.find(b => b.category_id == btn.dataset.e));
      if (btn.dataset.d && confirm('Xóa ngân sách của danh mục này?')) {
        const b = items.find(x => x.category_id == btn.dataset.d);
        try {
          await api('../api/budget/delete.php', { method: 'POST', body: { id: b.id ?? b.budget_id, category_id: b.category_id, month: +$('month').value, year: +$('year').value } });
          toast('Đã xóa ngân sách'); load();
        } catch (err) { toast(err.message, true); }
      }
    };
  
    $('addBtn').onclick = () => openModal();
    $('cancel').onclick = closeModal;
    $('modal').onclick = e => { if (e.target === $('modal')) closeModal(); };
    $('month').onchange = $('year').onchange = load;

    const btnSendReport = $('btnSendReport');
    if (btnSendReport) {
      btnSendReport.onclick = async () => {
        btnSendReport.disabled = true;
        btnSendReport.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang gửi mail...';
        try {
          const res = await api(`../api/budget/send_report.php?month=${$('month').value}&year=${$('year').value}`);
          toast(res.message || 'Đã gửi báo cáo ngân sách đến email của bạn!');
        } catch (err) {
          toast(err.message || 'Không thể gửi email lúc này', true);
        } finally {
          btnSendReport.disabled = false;
          btnSendReport.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Gửi báo cáo qua Mail';
        }
      };
    }
  
    initUser(); loadCats(); load();
  })();