// Trang Thu nhập (pages/income.html)
// Phụ thuộc: auth.js (apiFetch, showToast, formatVND, checkAuth), Chart.js, Font Awesome 6.
//
// API sử dụng:
//   GET  /category/list.php?type=income
//   GET  /transaction/list.php?type=income&page&limit&search&category_id&payment_method&from_date&to_date
//   POST /transaction/add.php | edit.php | delete.php
//   GET  /report/by_category.php?type=income&timeframe=this_month

const TX_TYPE = 'income';
const PAGE_LIMIT = 10;
const CATEGORY_SELECT_IDS = ['filter_category', 'tx_category_id'];

const PAYMENT_LABELS = {
    cash: 'Tiền mặt', bank: 'Banking', 'e-wallet': 'Ví điện tử', card: 'Thẻ tín dụng'
};

let categoriesCache = [];
let currentPage = 1;
let listSeq = 0; // chống phản hồi cũ ghi đè phản hồi mới khi lọc/gõ nhanh
let incomeChartInstance = null;
let searchTimer = null;

/* ============ Tiện ích ============ */
const formatCurrency = formatVND; // tên gọi theo kế hoạch tuần 1
const $ = (id) => document.getElementById(id);

// Ngày theo giờ máy (tránh lệch múi giờ của toISOString)
function toDateStr(d) {
    const p = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
}

function formatDateVN(str) {
    if (!str) return '';
    const [y, m, d] = String(str).slice(0, 10).split('-');
    return y && m && d ? `${d}/${m}/${y}` : str;
}

function paymentLabel(v) {
    return PAYMENT_LABELS[v] || v || '—';
}

function errorMessage(res, fallback) {
    return res ? (res.message || res.error || fallback) : fallback;
}

/* ============ Danh mục (Tính năng 2.1) ============ */
// CSDL lưu 'fa-utensils' (thiếu tiền tố) nên thêm 'fa-solid' để Font Awesome 6 hiển thị
function normalizeIconClass(icon) {
    const raw = (icon || '').trim();
    if (!/^[a-zA-Z0-9 _-]+$/.test(raw) || raw === '') return 'fa-solid fa-tag';
    return /\bfa-(solid|regular|brands)\b/.test(raw) ? raw : 'fa-solid ' + raw;
}

function safeColor(color, fallback = '#4F46E5') {
    return /^#[0-9a-fA-F]{3,8}$/.test(color || '') ? color : fallback;
}

async function loadCategories(type = TX_TYPE) {
    try {
        const res = await apiFetch('/category/list.php?type=' + encodeURIComponent(type), { method: 'GET' });
        if (!res) return [];
        if (!res.success) {
            showToast('Lỗi', errorMessage(res, 'Không tải được danh mục'), 'error');
            return [];
        }
        const list = Array.isArray(res.data) ? res.data : (res.data && res.data.categories) || [];
        categoriesCache = list.filter(c => !c.type || c.type === type);

        CATEGORY_SELECT_IDS.forEach(id => {
            const select = $(id);
            if (!select) return;
            populateCategorySelect(select, categoriesCache, {
                placeholder: id === 'filter_category' ? 'Tất cả danh mục' : 'Chọn danh mục',
                keepValue: true
            });
        });
        return categoriesCache;
    } catch (error) {
        console.error('Lỗi tải danh mục:', error);
        return [];
    }
}

function populateCategorySelect(select, categories, { placeholder = 'Chọn danh mục', keepValue = false } = {}) {
    const previous = keepValue ? select.value : '';
    const options = [];

    const first = document.createElement('option');
    first.value = '';
    first.textContent = placeholder;
    options.push(first);

    const sorted = [...categories].sort((a, b) =>
        (a.user_id === null ? 0 : 1) - (b.user_id === null ? 0 : 1) || a.name.localeCompare(b.name, 'vi'));

    sorted.forEach(cat => {
        const opt = document.createElement('option');
        opt.value = cat.id;
        opt.textContent = cat.name;
        opt.dataset.icon = normalizeIconClass(cat.icon);
        opt.dataset.color = safeColor(cat.color);
        opt.style.color = opt.dataset.color;
        options.push(opt);
    });

    select.replaceChildren(...options);
    if (previous && [...select.options].some(o => o.value === previous)) select.value = previous;

    if (!select.dataset.previewBound) {
        select.addEventListener('change', () => updateCategoryPreview(select));
        select.dataset.previewBound = '1';
    }
    updateCategoryPreview(select);
}

function updateCategoryPreview(select) {
    const preview = $(select.id + '-preview');
    if (!preview) return;
    const opt = select.selectedOptions[0];
    if (opt && opt.value) {
        preview.className = opt.dataset.icon;
        preview.style.color = opt.dataset.color;
    } else {
        preview.className = 'fa-solid fa-tag';
        preview.style.color = 'var(--text-muted)';
    }
}

function selectCategory(selectId, categoryId) {
    const select = $(selectId);
    if (!select) return;
    select.value = categoryId == null ? '' : String(categoryId);
    updateCategoryPreview(select);
}

/* ============ Thẻ chỉ số: hôm nay / tháng / năm ============ */
async function fetchIncomeTotal(from, to) {
    const qs = new URLSearchParams({ type: TX_TYPE, page: 1, limit: 1, from_date: from, to_date: to });
    const res = await apiFetch('/transaction/list.php?' + qs.toString(), { method: 'GET' });
    if (!res || !res.success) return 0;
    const d = res.data || {};
    const s = d.summary || d.totals || d;
    return Number(s.total_income ?? s.total_amount ?? s.total ?? 0);
}

async function loadStats() {
    const now = new Date();
    const today = toDateStr(now);
    const monthStart = toDateStr(new Date(now.getFullYear(), now.getMonth(), 1));
    const yearStart = toDateStr(new Date(now.getFullYear(), 0, 1));
    try {
        const [t, m, y] = await Promise.all([
            fetchIncomeTotal(today, today),
            fetchIncomeTotal(monthStart, today),
            fetchIncomeTotal(yearStart, today)
        ]);
        $('stat-today').textContent = formatCurrency(t);
        $('stat-month').textContent = formatCurrency(m);
        $('stat-year').textContent = formatCurrency(y);
    } catch (e) {
        console.error('Lỗi tải chỉ số:', e);
    }
}

/* ============ Biểu đồ cơ cấu nguồn thu ============ */
async function loadIncomeChart() {
    const canvas = $('incomeChart');
    const empty = $('chart-empty');
    if (!canvas) return;
    try {
        const res = await apiFetch('/report/by_category.php?type=income&timeframe=this_month', { method: 'GET' });
        const data = res && res.success && Array.isArray(res.data) ? res.data : [];
        if (incomeChartInstance) { incomeChartInstance.destroy(); incomeChartInstance = null; }
        canvas.style.display = data.length ? '' : 'none';
        empty.style.display = data.length ? 'none' : '';
        if (!data.length) return;

        incomeChartInstance = new Chart(canvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: data.map(i => i.category_name),
                datasets: [{
                    data: data.map(i => Number(i.total_amount)),
                    backgroundColor: data.map(i => safeColor(i.color, '#10b981'))
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { tooltip: { callbacks: { label: (c) => ` ${c.label}: ${formatCurrency(c.parsed)}` } } }
            }
        });
    } catch (e) {
        console.error('Lỗi tải biểu đồ:', e);
    }
}

/* ============ Danh sách + lọc + phân trang ============ */
function getFilters() {
    return {
        search: $('filter_search').value.trim(),
        category_id: $('filter_category').value,
        payment_method: $('filter_method').value,
        from_date: $('filter_from').value,
        to_date: $('filter_to').value
    };
}

function renderEmptyRow(tbody, text, isError = false) {
    const tr = document.createElement('tr');
    tr.className = 'empty-row';
    const td = document.createElement('td');
    td.colSpan = 6;
    td.textContent = text;
    if (isError) td.style.color = 'var(--danger)';
    tr.appendChild(td);
    tbody.replaceChildren(tr);
}

function buildRow(item) {
    const tr = document.createElement('tr');

    const dateTd = document.createElement('td');
    dateTd.textContent = formatDateVN(item.transaction_date);

    const catTd = document.createElement('td');
    const wrap = document.createElement('span');
    wrap.className = 'cat-cell';
    const dot = document.createElement('span');
    dot.className = 'cat-dot';
    const icon = document.createElement('i');
    icon.className = normalizeIconClass(item.icon);
    icon.style.color = safeColor(item.color, '#10b981');
    dot.appendChild(icon);
    wrap.append(dot, document.createTextNode(item.category_name || ''));
    catTd.appendChild(wrap);

    const noteTd = document.createElement('td');
    noteTd.className = 'note-cell';
    noteTd.textContent = item.note || '—';
    if (item.note) noteTd.title = item.note;

    const methodTd = document.createElement('td');
    methodTd.textContent = paymentLabel(item.payment_method);

    const amountTd = document.createElement('td');
    amountTd.style.cssText = 'text-align:right;font-weight:600;color:var(--success);white-space:nowrap;';
    amountTd.textContent = '+' + formatCurrency(item.amount);

    const actionTd = document.createElement('td');
    actionTd.style.textAlign = 'right';
    actionTd.style.whiteSpace = 'nowrap';
    const btnEdit = document.createElement('button');
    btnEdit.type = 'button';
    btnEdit.className = 'btn btn-icon';
    btnEdit.title = 'Sửa';
    btnEdit.innerHTML = '<i class="fa-solid fa-pen"></i>';
    btnEdit.addEventListener('click', () => openModal(item));
    const btnDel = document.createElement('button');
    btnDel.type = 'button';
    btnDel.className = 'btn btn-icon danger';
    btnDel.title = 'Xóa';
    btnDel.style.marginLeft = '0.35rem';
    btnDel.innerHTML = '<i class="fa-solid fa-trash"></i>';
    btnDel.addEventListener('click', () => deleteIncome(item));
    actionTd.append(btnEdit, btnDel);

    tr.append(dateTd, catTd, noteTd, methodTd, amountTd, actionTd);
    return tr;
}

async function loadIncomeList(page = 1) {
    const tbody = $('income-tbody');
    currentPage = page;
    const filters = getFilters();
    const seq = ++listSeq;

    if (filters.from_date && filters.to_date && filters.from_date > filters.to_date) {
        renderEmptyRow(tbody, '"Từ ngày" phải trước hoặc bằng "Đến ngày".', true);
        renderPager(1, 1, 0);
        return;
    }
    const qs = new URLSearchParams({ type: TX_TYPE, page, limit: PAGE_LIMIT });
    Object.entries(filters).forEach(([k, v]) => { if (v) qs.set(k, v); });

    try {
        const res = await apiFetch('/transaction/list.php?' + qs.toString(), { method: 'GET' });
        if (seq !== listSeq) return; // đã có yêu cầu mới hơn
        if (!res) return;
        if (!res.success) {
            renderEmptyRow(tbody, errorMessage(res, 'Không tải được danh sách.'), true);
            renderPager(1, 1, 0);
            return;
        }
        const d = res.data || {};
        const items = d.transactions || [];
        const pg = d.pagination || d;
        const totalPages = Number(pg.total_pages) || 1;
        const totalRecords = Number(pg.total_records ?? items.length);

        // Trang hiện tại vượt quá tổng trang (ví dụ vừa xóa mục cuối) -> lùi lại
        if (items.length === 0 && page > 1 && totalPages >= 1) {
            return loadIncomeList(Math.min(page - 1, totalPages));
        }

        if (items.length === 0) {
            renderEmptyRow(tbody, 'Không có khoản thu nào phù hợp.');
        } else {
            tbody.replaceChildren(...items.map(buildRow));
        }
        renderPager(Number(pg.current_page) || page, totalPages, totalRecords);
    } catch (e) {
        console.error('Lỗi tải danh sách thu nhập:', e);
        renderEmptyRow(tbody, 'Lỗi tải danh sách thu nhập.', true);
    }
}

function renderPager(current, totalPages, totalRecords) {
    $('page-info').textContent = totalRecords > 0
        ? `Trang ${current}/${totalPages} • ${totalRecords} khoản thu`
        : '';

    const pager = $('pager');
    pager.replaceChildren();
    if (totalPages <= 1) return;

    const mk = (label, page, { disabled = false, active = false, aria } = {}) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.textContent = label;
        b.disabled = disabled;
        if (aria) b.setAttribute('aria-label', aria);
        if (active) { b.classList.add('current'); b.setAttribute('aria-current', 'page'); }
        if (!disabled && !active) b.addEventListener('click', () => loadIncomeList(page));
        return b;
    };

    pager.appendChild(mk('‹', current - 1, { disabled: current <= 1, aria: 'Trang trước' }));
    const start = Math.max(1, Math.min(current - 2, totalPages - 4));
    const end = Math.min(totalPages, start + 4);
    for (let p = start; p <= end; p++) pager.appendChild(mk(String(p), p, { active: p === current }));
    pager.appendChild(mk('›', current + 1, { disabled: current >= totalPages, aria: 'Trang sau' }));
}

/* ============ Modal Thêm / Sửa ============ */
function openModal(item = null) {
    const form = $('formTx');
    form.reset();
    $('tx_id').value = item ? item.id : '';
    $('modalTitle').textContent = item ? 'Sửa thu nhập' : 'Thêm thu nhập';

    if (item) {
        selectCategory('tx_category_id', item.category_id);
        $('tx_amount').value = Math.round(Number(item.amount));
        $('tx_date').value = String(item.transaction_date || '').slice(0, 10);
        // Hỗ trợ cả giá trị cũ (cash/bank/e-wallet) lẫn nhãn tiếng Việt
        const method = PAYMENT_LABELS[item.payment_method] || item.payment_method;
        const exists = [...$('tx_method').options].some(o => o.value === method);
        $('tx_method').value = exists ? method : 'Tiền mặt';
        $('tx_note').value = item.note || '';
    } else {
        selectCategory('tx_category_id', '');
        $('tx_date').value = toDateStr(new Date());
    }
    $('modalTx').classList.add('show');
    $('tx_category_id').focus();
}

function closeModal() {
    $('modalTx').classList.remove('show');
}

async function saveIncome(e) {
    e.preventDefault();
    const id = $('tx_id').value;
    const amount = parseFloat($('tx_amount').value);
    const categoryId = parseInt($('tx_category_id').value, 10);

    if (!categoryId) return showToast('Lỗi', 'Vui lòng chọn danh mục', 'error');
    if (!(amount > 0)) return showToast('Lỗi', 'Số tiền phải lớn hơn 0', 'error');

    const payload = {
        type: TX_TYPE,
        category_id: categoryId,
        amount,
        transaction_date: $('tx_date').value,
        payment_method: $('tx_method').value,
        note: $('tx_note').value.trim()
    };
    if (id) payload.id = parseInt(id, 10);

    const btn = $('btnSaveTx');
    btn.disabled = true;
    try {
        const res = await apiFetch(id ? '/transaction/edit.php' : '/transaction/add.php', {
            method: 'POST',
            body: JSON.stringify(payload)
        });
        if (res && res.success) {
            showToast('Thành công', id ? 'Đã cập nhật khoản thu' : 'Đã thêm khoản thu', 'success');
            closeModal();
            await refreshAll(id ? currentPage : 1);
        } else if (res) {
            showToast('Lỗi', errorMessage(res, 'Không thể lưu khoản thu'), 'error');
        }
    } catch (error) {
        console.error('Lỗi lưu thu nhập:', error);
    } finally {
        btn.disabled = false;
    }
}

/* ============ Xóa ============ */
async function deleteIncome(item) {
    const label = `${formatCurrency(item.amount)} (${item.category_name || 'khoản thu'}) ngày ${formatDateVN(item.transaction_date)}`;
    if (!confirm(`Xóa khoản thu ${label}?\nThao tác này không thể hoàn tác.`)) return;
    try {
        const res = await apiFetch('/transaction/delete.php', {
            method: 'POST',
            body: JSON.stringify({ id: item.id })
        });
        if (res && res.success) {
            showToast('Thành công', 'Đã xóa khoản thu', 'success');
            await refreshAll(currentPage);
        } else if (res) {
            showToast('Lỗi', errorMessage(res, 'Không thể xóa khoản thu'), 'error');
        }
    } catch (error) {
        console.error('Lỗi xóa thu nhập:', error);
    }
}

function refreshAll(page = currentPage) {
    return Promise.all([loadIncomeList(page), loadStats(), loadIncomeChart()]);
}

/* ============ Khởi tạo ============ */
document.addEventListener('DOMContentLoaded', async () => {
    const user = await checkAuth();
    if (!user) return;

    // Menu hamburger trên di động (sidebar bị ẩn khi <= 768px)
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

    // Modal
    $('btnAddIncome').addEventListener('click', () => openModal());
    $('btnCloseTx').addEventListener('click', closeModal);
    $('btnCancelTx').addEventListener('click', closeModal);
    $('modalTx').addEventListener('click', (e) => { if (e.target === $('modalTx')) closeModal(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });
    $('formTx').addEventListener('submit', saveIncome);

    // Bộ lọc: ô tìm kiếm có debounce, còn lại lọc ngay khi đổi
    $('filter_search').addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadIncomeList(1), 300);
    });
    ['filter_category', 'filter_method', 'filter_from', 'filter_to'].forEach(id =>
        $(id).addEventListener('change', () => loadIncomeList(1)));
    $('btnResetFilter').addEventListener('click', () => {
        ['filter_search', 'filter_category', 'filter_method', 'filter_from', 'filter_to'].forEach(id => { $(id).value = ''; });
        updateCategoryPreview($('filter_category'));
        loadIncomeList(1);
    });

    await loadCategories(TX_TYPE);
    refreshAll(1);
});