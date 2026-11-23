(() => {
    const $ = id => document.getElementById(id);
    const charts = {};
    let timer;
  
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
      } catch (e) {}
      $('logoutBtn').onclick = async () => {
        try { await api('../api/auth/logout.php', { method: 'POST' }); } catch (e) {}
        location.href = 'login.html';
      };
    }
  
    // ===== Báo cáo =====
    const COLORS = ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#06b6d4', '#8b5cf6', '#ec4899', '#84cc16', '#f97316', '#64748b'];
    const ymd = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    const compact = v => new Intl.NumberFormat('vi-VN', { notation: 'compact' }).format(v);
  
    // Khoảng ngày theo kỳ đã chọn
    function range() {
      const tf = $('tf').value, n = new Date(), y = n.getFullYear(), m = n.getMonth();
      if (tf === 'custom') return { from: $('from').value, to: $('to').value };
      if (tf === 'last_month') return { from: ymd(new Date(y, m - 1, 1)), to: ymd(new Date(y, m, 0)) };
      if (tf === 'this_year') return { from: `${y}-01-01`, to: `${y}-12-31` };
      return { from: ymd(new Date(y, m, 1)), to: ymd(new Date(y, m + 1, 0)) };
    }
    const query = () => { const r = range(); return `timeframe=${$('tf').value}&from_date=${r.from}&to_date=${r.to}`; };
  
    function draw(id, cfg, empty) {
      if (charts[id]) { charts[id].destroy(); delete charts[id]; }
      $(id).classList.toggle('hide', empty);
      $(id + 'Empty').classList.toggle('hide', !empty);
      if (!empty) charts[id] = new Chart($(id), cfg);
    }
  
    function pieCfg(type, items) {
      return {
        type,
        data: {
          labels: items.map(c => c.category_name ?? c.name),
          datasets: [{ data: items.map(c => +(c.total ?? c.total_amount ?? c.amount ?? 0)), backgroundColor: COLORS, borderWidth: 2, borderColor: '#fff' }]
        },
        options: {
          maintainAspectRatio: false,
          plugins: {
            legend: { position: 'bottom' },
            tooltip: { callbacks: { label: c => {
              const sum = c.dataset.data.reduce((a, b) => a + b, 0);
              return `${c.label}: ${money(c.parsed)} (${sum ? (c.parsed / sum * 100).toFixed(1) : 0}%)`;
            } } }
          }
        }
      };
    }
  
    async function load() {
      if ($('tf').value === 'custom' && (!$('from').value || !$('to').value)) return toast('Chọn đủ từ ngày và đến ngày', true);
      const q = query();
      try {
        const [sum, exp, inc] = await Promise.all([
          api(`../api/report/summary.php?${q}`),
          api(`../api/report/by_category.php?type=expense&${q}`),
          api(`../api/report/by_category.php?type=income&${q}`)
        ]);
        const s = sum.summary || sum;
        const tin = +(s.total_income ?? s.income ?? 0), tout = +(s.total_expense ?? s.expense ?? 0);
        $('sIn').textContent = money(tin);
        $('sOut').textContent = money(tout);
        $('sNet').textContent = money(s.net_balance ?? s.balance ?? tin - tout);
        $('sRate').textContent = (+(s.savings_rate ?? (tin ? (tin - tout) / tin * 100 : 0))).toFixed(1) + '%';
  
        // Biểu đồ Bar: xu hướng thu chi
        const trend = sum.monthly || sum.trend || sum.monthly_trend || list(sum);
        draw('cTrend', {
          type: 'bar',
          data: {
            labels: trend.map(m => m.month ?? m.label),
            datasets: [
              { label: 'Thu nhập', data: trend.map(m => +(m.income ?? m.total_income ?? 0)), backgroundColor: '#10b981', borderRadius: 4 },
              { label: 'Chi tiêu', data: trend.map(m => +(m.expense ?? m.total_expense ?? 0)), backgroundColor: '#ef4444', borderRadius: 4 }
            ]
          },
          options: {
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: { callback: compact } } },
            plugins: { tooltip: { callbacks: { label: c => `${c.dataset.label}: ${money(c.parsed.y)}` } } }
          }
        }, !trend.length);
  
        // Doughnut: chi tiêu theo danh mục — Pie: nguồn thu nhập
        const e = list(exp), i = list(inc);
        draw('cExp', pieCfg('doughnut', e), !e.length);
        draw('cInc', pieCfg('pie', i), !i.length);
      } catch (err) { toast(err.message, true); }
    }
  
    $('tf').onchange = () => {
      const custom = $('tf').value === 'custom';
      $('fromBox').classList.toggle('hide', !custom);
      $('toBox').classList.toggle('hide', !custom);
      if (!custom) load();
    };
    $('apply').onclick = load;
    $('exportBtn').onclick = () => {
      const r = range();
      if (!r.from || !r.to) return toast('Chọn đủ từ ngày và đến ngày', true);
      location.href = `../api/export/export_csv.php?from_date=${r.from}&to_date=${r.to}`;
    };
  
    initUser(); load();
  })();
  (() => {
  const E = id => document.getElementById(id);
  const oldTitle = document.title;

  window.addEventListener('beforeprint', () => {
    document.querySelectorAll('.chart-box').forEach(box => {
      const cv = box.querySelector('canvas');
      let img = box.querySelector('.chart-img');
      if (!cv || cv.classList.contains('hide')) { if (img) img.remove(); return; }
      if (!img) {
        img = document.createElement('img');
        img.className = 'chart-img';
        img.alt = 'Biểu đồ';
        box.appendChild(img);
      }
      img.src = cv.toDataURL('image/png', 1);
    });
    const tf = E('tf');
    let meta = 'Kỳ: ' + tf.selectedOptions[0].text;
    if (tf.value === 'custom' && E('from').value && E('to').value) meta += ` (${E('from').value} → ${E('to').value})`;
    E('printMeta').textContent = meta + ' · Xuất ngày ' + new Date().toLocaleDateString('vi-VN');
    document.title = 'BaoCao_' + new Date().toISOString().slice(0, 10);
  });

  window.addEventListener('afterprint', () => { document.title = oldTitle; });
  E('pdfBtn').addEventListener('click', () => window.print());
})();