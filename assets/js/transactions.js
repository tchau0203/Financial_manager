/**
 * CashManager - Sổ Giao Dịch & Báo Cáo Controller
 * Tech Stack: Vanilla JS (Clean, Modular, No Framework)
 */
(function () {
    'use strict';

    // Helper DOM selectors
    const $ = id => document.getElementById(id);
    const $$ = selector => document.querySelectorAll(selector);

    // App State
    let currentTab = 'all'; // 'all' | 'expense' | 'income' | 'report'
    let categories = [];
    let currentModalType = 'expense'; // 'expense' | 'income'
    let trendChartInstance = null;
    let donutChartInstance = null;

    // Filters for tabs
    const filters = {
        all: { page: 1, limit: 15, category_id: '', from_date: '', to_date: '', payment_method: '', search: '' },
        expense: { page: 1, limit: 15, category_id: '', from_date: '', to_date: '', payment_method: '', search: '' },
        income: { page: 1, limit: 15, category_id: '', from_date: '', to_date: '', payment_method: '', search: '' }
    };

    // Format tiền tệ VNĐ
    function formatMoney(amount) {
        return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(Number(amount) || 0);
    }

    // Format ngày DD/MM/YYYY
    function formatDate(dateStr) {
        if (!dateStr) return '';
        const parts = String(dateStr).split('-');
        if (parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`;
        return dateStr;
    }

    // Escape HTML chống XSS
    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, m => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[m]));
    }

    // =========================================================================
    // 1. TẢI DANH MỤC & KHỞI TẠO BỘ CHỌN
    // =========================================================================
    async function loadCategories() {
        try {
            const res = await fetch('../api/category/list.php', { credentials: 'include' });
            if (res.status === 401) {
                window.location.href = 'login.html';
                return;
            }
            const json = await res.json();
            categories = json.data || (Array.isArray(json) ? json : []);
            renderCategorySelects();
        } catch (err) {
            console.error('Không thể tải danh mục:', err);
        }
    }

    function renderCategorySelects() {
        const catAll = $('filterCatAll');
        const catExp = $('filterCatExpense');
        const catInc = $('filterCatIncome');

        if (catAll) {
            catAll.innerHTML = '<option value="">Tất cả danh mục</option>' +
                categories.map(c => `<option value="${c.id}">${escapeHtml(c.name)} (${c.type === 'income' ? 'Thu' : 'Chi'})</option>`).join('');
        }
        if (catExp) {
            catExp.innerHTML = '<option value="">Tất cả danh mục chi</option>' +
                categories.filter(c => c.type === 'expense').map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
        }
        if (catInc) {
            catInc.innerHTML = '<option value="">Tất cả nguồn thu</option>' +
                categories.filter(c => c.type === 'income').map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
        }
        updateModalCategorySelect();
    }

    function updateModalCategorySelect() {
        const select = $('tx_category_id');
        if (!select) return;
        const filtered = categories.filter(c => c.type === currentModalType);
        select.innerHTML = '<option value="">Chọn danh mục</option>' +
            filtered.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
    }

    // =========================================================================
    // 2. CHUYỂN ĐỔI TAB (Tất cả / Khoản chi / Khoản thu / Báo cáo)
    // =========================================================================
    function switchTab(tabId) {
        currentTab = tabId;

        // Cập nhật Tab buttons
        $$('.tx-tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tabId);
        });

        // Cập nhật Tab panes
        $$('.tx-tab-pane').forEach(pane => {
            pane.classList.toggle('active', pane.id === `pane-${tabId}`);
        });

        // Lưu param URL
        const url = new URL(window.location);
        url.searchParams.set('tab', tabId);
        window.history.replaceState({}, '', url);

        // Tải dữ liệu tương ứng
        if (tabId === 'report') {
            loadReportTab();
        } else {
            loadTransactions(tabId);
        }
    }

    // =========================================================================
    // 3. TẢI DỮ LIỆU GIAO DỊCH & RENDER BẢNG
    // =========================================================================
    async function loadTransactions(tab) {
        const f = filters[tab] || filters.all;
        const tbodyId = tab === 'all' ? 'tbodyAll' : (tab === 'expense' ? 'tbodyExpense' : 'tbodyIncome');
        const tbody = $(tbodyId);
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 2rem; color: var(--text-muted);"><i class="fa-solid fa-spinner fa-spin"></i> Đang tải dữ liệu...</td></tr>';

        const params = new URLSearchParams({
            page: f.page,
            limit: f.limit
        });

        if (tab === 'expense') params.set('type', 'expense');
        else if (tab === 'income') params.set('type', 'income');

        if (f.category_id) params.set('category_id', f.category_id);
        if (f.from_date) params.set('from_date', f.from_date);
        if (f.to_date) params.set('to_date', f.to_date);
        if (f.payment_method) params.set('payment_method', f.payment_method);
        if (f.search) params.set('search', f.search);

        try {
            const res = await fetch(`../api/transaction/list.php?${params.toString()}`, { credentials: 'include' });
            if (res.status === 401) {
                window.location.href = 'login.html';
                return;
            }
            const json = await res.json();
            if (!json.success) throw new Error(json.message || 'Lỗi tải dữ liệu');

            const data = json.data || {};
            const list = data.transactions || [];
            const summary = data.summary || {};
            const pagination = data.pagination || { page: 1, total_pages: 1, total_records: 0 };

            // Cập nhật KPI mini bar
            if ($('kpiTotalIncome')) $('kpiTotalIncome').textContent = formatMoney(summary.total_income);
            if ($('kpiTotalExpense')) $('kpiTotalExpense').textContent = formatMoney(summary.total_expense);
            if ($('kpiNetBalance')) {
                const net = summary.net_balance;
                $('kpiNetBalance').textContent = (net >= 0 ? '+' : '') + formatMoney(net);
                $('kpiNetBalance').style.color = net >= 0 ? '#10b981' : '#ef4444';
            }
            if ($('kpiSavingRate')) {
                const inc = Number(summary.total_income) || 0;
                const net = Number(summary.net_balance) || 0;
                const rate = inc > 0 ? Math.max(0, Math.round((net / inc) * 100)) : 0;
                $('kpiSavingRate').textContent = `${rate}%`;
            }

            renderTableRows(tab, tbody, list);
            renderPagination(tab, pagination);
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding: 2rem; color: #ef4444;"><i class="fa-solid fa-triangle-exclamation"></i> ${escapeHtml(err.message)}</td></tr>`;
        }
    }

    function renderTableRows(tab, tbody, list) {
        if (!list || list.length === 0) {
            const cols = tab === 'all' ? 7 : 6;
            tbody.innerHTML = `<tr><td colspan="${cols}" style="text-align:center; padding: 2.5rem; color: var(--text-muted);"><i class="fa-regular fa-folder-open" style="font-size: 1.75rem; margin-bottom: 0.5rem; display:block; opacity: 0.5;"></i>Chưa có giao dịch nào phù hợp với bộ lọc.</td></tr>`;
            return;
        }

        let html = '';
        list.forEach(item => {
            const isInc = item.type === 'income';
            const catColor = item.color || (isInc ? '#10b981' : '#6366f1');
            const catIcon = item.icon || (isInc ? 'fa-arrow-trend-up' : 'fa-tag');

            html += `<tr>
                <td><strong>${formatDate(item.transaction_date)}</strong></td>`;

            if (tab === 'all') {
                html += `<td>
                    <span style="font-size: 0.78rem; font-weight: 700; padding: 0.25rem 0.6rem; border-radius: 99px; ${isInc ? 'background:#ecfdf5; color:#10b981;' : 'background:#fef2f2; color:#ef4444;'}">
                        ${isInc ? 'Thu nhập' : 'Chi tiêu'}
                    </span>
                </td>`;
            }

            html += `<td>
                    <div class="cat-badge">
                        <span class="cat-icon-circle" style="background: ${catColor};">
                            <i class="fa-solid ${catIcon}"></i>
                        </span>
                        <span>${escapeHtml(item.category_name || 'Khác')}</span>
                    </div>
                </td>
                <td style="max-width: 260px; overflow: hidden; text-overflow: ellipsis;" title="${escapeHtml(item.note)}">
                    ${escapeHtml(item.note) || '<span style="color:#94a3b8;">Không có ghi chú</span>'}
                </td>
                <td><span style="color: var(--text-muted);">${escapeHtml(item.payment_method || 'Tiền mặt')}</span></td>
                <td style="text-align: right;">
                    <span class="amt-badge ${isInc ? 'income' : 'expense'}">
                        ${isInc ? '+' : '-'}${formatMoney(item.amount)}
                    </span>
                </td>
                <td style="text-align: center;">
                    <button type="button" class="tx-action-btn" title="Chỉnh sửa" onclick="window.editTransaction(${item.id})">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <button type="button" class="tx-action-btn del" title="Xóa" onclick="window.deleteTransaction(${item.id})">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </td>
            </tr>`;
        });

        tbody.innerHTML = html;
    }

    function renderPagination(tab, pagination) {
        const infoEl = $(tab === 'all' ? 'pageInfoAll' : (tab === 'expense' ? 'pageInfoExpense' : 'pageInfoIncome'));
        const pagerEl = $(tab === 'all' ? 'pagerAll' : (tab === 'expense' ? 'pagerExpense' : 'pagerIncome'));
        if (!infoEl || !pagerEl) return;

        const page = pagination.page || 1;
        const totalPages = pagination.total_pages || 1;
        const totalRecords = pagination.total_records || 0;

        infoEl.textContent = `Trang ${page} / ${totalPages} (${totalRecords} giao dịch)`;

        let pagerHtml = '';
        pagerHtml += `<button type="button" ${page <= 1 ? 'disabled' : ''} onclick="window.changePage('${tab}', ${page - 1})"><i class="fa-solid fa-chevron-left"></i></button>`;

        let start = Math.max(1, page - 2);
        let end = Math.min(totalPages, page + 2);
        for (let i = start; i <= end; i++) {
            pagerHtml += `<button type="button" class="${i === page ? 'current' : ''}" onclick="window.changePage('${tab}', ${i})">${i}</button>`;
        }

        pagerHtml += `<button type="button" ${page >= totalPages ? 'disabled' : ''} onclick="window.changePage('${tab}', ${page + 1})"><i class="fa-solid fa-chevron-right"></i></button>`;
        pagerEl.innerHTML = pagerHtml;
    }

    window.changePage = function (tab, page) {
        if (!filters[tab]) return;
        filters[tab].page = page;
        loadTransactions(tab);
    };

    // =========================================================================
    // 4. TAB 4: BÁO CÁO & XUẤT FILE (Chart.js + Excel/CSV/PDF)
    // =========================================================================
    async function loadReportTab() {
        const timeframe = $('reportTimeframe') ? $('reportTimeframe').value : 'this_month';

        try {
            // 1. Tải cơ cấu chi tiêu cho Donut Chart
            const resCat = await fetch(`../api/report/by_category.php?type=expense`, { credentials: 'include' });
            const jsonCat = await resCat.json();
            const catData = jsonCat.data || [];
            renderDonutChart(catData);

            // 2. Tải xu hướng thu chi cho Bar Chart
            const resSummary = await fetch(`../api/dashboard/summary.php`, { credentials: 'include' });
            const jsonSummary = await resSummary.json();
            const history = jsonSummary.data?.monthly_history || [];
            renderTrendChart(history);
        } catch (e) {
            console.error('Lỗi khi vẽ biểu đồ báo cáo:', e);
        }
    }

    function renderDonutChart(catList) {
        const ctx = $('chartCatDonut');
        if (!ctx) return;

        if (donutChartInstance) donutChartInstance.destroy();

        if (!catList || catList.length === 0) {
            donutChartInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Chưa có dữ liệu'],
                    datasets: [{ data: [1], backgroundColor: ['#e2e8f0'] }]
                },
                options: { cutout: '75%', plugins: { legend: { display: false } } }
            });
            return;
        }

        const labels = catList.map(c => c.category_name);
        const data = catList.map(c => c.total_amount);
        const colors = [
            '#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
            '#06b6d4', '#ec4899', '#f97316', '#14b8a6', '#64748b'
        ];

        donutChartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors.slice(0, data.length),
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return ` ${context.label}: ${formatMoney(context.raw)}`;
                            }
                        }
                    }
                }
            }
        });

        // Legend Grid
        const legendGrid = $('chartLegendGrid');
        if (legendGrid) {
            legendGrid.innerHTML = catList.map((c, i) => `
                <div style="display:flex; align-items:center; gap:0.4rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    <span style="width:10px; height:10px; border-radius:50%; background:${colors[i % colors.length]}; flex-shrink:0;"></span>
                    <span style="color:var(--text-body); font-weight:600;">${escapeHtml(c.category_name)}:</span>
                    <span style="color:var(--text-muted);">${c.percentage}%</span>
                </div>
            `).join('');
        }
    }

    function renderTrendChart(historyList) {
        const ctx = $('chartTrendBar');
        if (!ctx) return;

        if (trendChartInstance) trendChartInstance.destroy();

        const labels = (historyList || []).map(h => h.month_label || h.month);
        const incomeData = (historyList || []).map(h => h.income || 0);
        const expenseData = (historyList || []).map(h => h.expense || 0);

        trendChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels.length ? labels : ['Tháng 9', 'Tháng 10', 'Tháng 11'],
                datasets: [
                    {
                        label: 'Thu nhập',
                        data: incomeData.length ? incomeData : [5000000, 7500000, 10000000],
                        backgroundColor: '#10b981',
                        borderRadius: 6
                    },
                    {
                        label: 'Chi tiêu',
                        data: expenseData.length ? expenseData : [3500000, 4200000, 6800000],
                        backgroundColor: '#ef4444',
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        grid: { color: 'rgba(226, 232, 240, 0.6)' },
                        ticks: {
                            callback: function (val) {
                                if (val >= 1000000) return (val / 1000000) + 'tr';
                                if (val >= 1000) return (val / 1000) + 'k';
                                return val;
                            }
                        }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return ` ${context.dataset.label}: ${formatMoney(context.raw)}`;
                            }
                        }
                    }
                }
            }
        });
    }

    // =========================================================================
    // 5. XUẤT FILE: EXCEL (.xlsx), CSV, PDF
    // =========================================================================
    async function exportExcel() {
        try {
            const res = await fetch('../api/transaction/list.php?limit=2000', { credentials: 'include' });
            const json = await res.json();
            const txs = json.data?.transactions || [];
            if (!txs.length) {
                alert('Không có giao dịch nào để xuất.');
                return;
            }

            const exportData = txs.map((t, idx) => ({
                'STT': idx + 1,
                'Ngày': t.transaction_date,
                'Loại': t.type === 'income' ? 'Thu nhập' : 'Chi tiêu',
                'Danh mục': t.category_name,
                'Số tiền (VNĐ)': Number(t.amount),
                'Phương thức': t.payment_method || 'Tiền mặt',
                'Ghi chú': t.note || ''
            }));

            if (window.XLSX) {
                const ws = window.XLSX.utils.json_to_sheet(exportData);
                const wb = window.XLSX.utils.book_new();
                window.XLSX.utils.book_append_sheet(wb, ws, 'SoGiaoDich');
                window.XLSX.writeFile(wb, `So_Giao_Dich_${new Date().toISOString().slice(0, 10)}.xlsx`);
            } else {
                window.location.href = '../api/export/export_csv.php';
            }
        } catch (e) {
            alert('Lỗi khi xuất file Excel: ' + e.message);
        }
    }

    function exportCSV() {
        window.location.href = '../api/export/export_csv.php';
    }

    function exportPDF() {
        const d = new Date();
        window.open(`../api/export/export_pdf.php?month=${d.getMonth() + 1}&year=${d.getFullYear()}`, '_blank');
    }

    // =========================================================================
    // 6. MODAL THÊM / SỬA GIAO DỊCH & QUÉT HÓA ĐƠN OCR
    // =========================================================================
    function openModalTx(type = 'expense', editData = null) {
        currentModalType = type;
        const modal = $('modalTx');
        const form = $('formTx');
        if (!modal || !form) return;

        form.reset();
        $('tx_id').value = editData ? editData.id : '';
        $('tx_date').value = editData ? editData.transaction_date : new Date().toISOString().slice(0, 10);
        $('tx_method').value = editData ? editData.payment_method : 'Tiền mặt';
        $('tx_amount').value = editData ? editData.amount : '';
        $('tx_note').value = editData ? editData.note : '';

        if ($('ocrPreviewWrap')) $('ocrPreviewWrap').style.display = 'none';
        $('ocrStatusText').textContent = '';

        setModalType(type);

        if (editData && editData.category_id) {
            $('tx_category_id').value = editData.category_id;
        }

        // Khởi tạo bản đồ vị trí (LocationPicker)
        if (window.LocationPicker) {
            window.LocationPicker.open(editData);
        }

        $('modalTxTitle').textContent = editData ? 'Chỉnh sửa giao dịch' : (type === 'income' ? 'Thêm khoản thu nhập' : 'Thêm khoản chi tiêu');
        modal.classList.add('show');
        if (window.LocationPicker) {
            setTimeout(() => {
                if (window.LocationPicker.open) window.LocationPicker.open(editData);
            }, 120);
        }
    }

    function closeModalTx() {
        const modal = $('modalTx');
        if (modal) modal.classList.remove('show');
    }

    function setModalType(type) {
        currentModalType = type;
        const btnExp = $('btnTypeExpense');
        const btnInc = $('btnTypeIncome');

        if (btnExp && btnInc) {
            if (type === 'expense') {
                btnExp.style.background = '#ef4444';
                btnExp.style.color = '#fff';
                btnInc.style.background = 'transparent';
                btnInc.style.color = 'var(--text-muted)';
            } else {
                btnInc.style.background = '#10b981';
                btnInc.style.color = '#fff';
                btnExp.style.background = 'transparent';
                btnExp.style.color = 'var(--text-muted)';
            }
        }
        updateModalCategorySelect();
    }

    // Quét hóa đơn OCR tự động điền form
    async function handleOcrUpload(file) {
        if (!file) return;
        const previewWrap = $('ocrPreviewWrap');
        const previewImg = $('ocrPreviewImg');
        const statusText = $('ocrStatusText');

        if (previewWrap && previewImg && statusText) {
            previewWrap.style.display = 'flex';
            previewImg.src = URL.createObjectURL(file);
            statusText.textContent = 'AI đang quét hóa đơn, vui lòng đợi...';
        }

        try {
            const fd = new FormData();
            fd.append('receipt', file);
            const res = await fetch('../api/ai/ocr_receipt.php', { method: 'POST', body: fd, credentials: 'include' });
            const json = await res.json();
            if (!json.success && json.error) throw new Error(json.message || json.error);

            const d = json.data || json;
            if (d.amount) $('tx_amount').value = String(d.amount).replace(/[^\d]/g, '');
            if (d.date) {
                const m = String(d.date).match(/^(\d{4})-(\d{2})-(\d{2})/) || String(d.date).match(/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4})/);
                if (m) $('tx_date').value = m[1].length === 4 ? `${m[1]}-${m[2]}-${m[3]}` : `${m[3]}-${m[2].padStart(2, '0')}-${m[1].padStart(2, '0')}`;
            }
            if (d.note) $('tx_note').value = d.note;

            setModalType('expense');

            if (d.category_suggestion) {
                const norm = s => String(s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
                const want = norm(d.category_suggestion);
                const opt = [...$('tx_category_id').options].find(o => norm(o.textContent).includes(want) || want.includes(norm(o.textContent)));
                if (opt) $('tx_category_id').value = opt.value;
            }

            statusText.textContent = '✓ AI đã điền dữ liệu xong, hãy kiểm tra lại.';
        } catch (err) {
            if (statusText) statusText.textContent = err.message || 'Không thể phân tích ảnh.';
        }
    }

    // Submit lưu giao dịch (Thêm hoặc Sửa)
    async function handleSaveTransaction(e) {
        e.preventDefault();
        const txId = $('tx_id').value;
        const payload = {
            type: currentModalType,
            category_id: Number($('tx_category_id').value),
            amount: Number($('tx_amount').value),
            transaction_date: $('tx_date').value,
            payment_method: $('tx_method').value,
            note: $('tx_note').value.trim()
        };

        // Thu thập tọa độ vị trí & tên địa điểm nếu người dùng chọn trên bản đồ
        if (window.LocationPicker) {
            const loc = window.LocationPicker.get();
            if (loc && loc.latitude != null && loc.longitude != null) {
                payload.latitude = loc.latitude;
                payload.longitude = loc.longitude;
                payload.location_name = loc.location_name;
            } else {
                payload.latitude = null;
                payload.longitude = null;
                payload.location_name = null;
            }
        }

        if (txId) payload.id = Number(txId);

        const endpoint = txId ? '../api/transaction/edit.php' : '../api/transaction/add.php';

        try {
            const res = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
                credentials: 'include'
            });
            const json = await res.json();
            if (!json.success) throw new Error(json.message || 'Lỗi lưu giao dịch');

            closeModalTx();
            loadTransactions(currentTab);
        } catch (err) {
            alert('Lỗi: ' + err.message);
        }
    }

    // Xóa giao dịch
    window.deleteTransaction = async function (id) {
        if (!confirm('Bạn có chắc chắn muốn xóa giao dịch này?')) return;
        try {
            const res = await fetch('../api/transaction/delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: Number(id) }),
                credentials: 'include'
            });
            const json = await res.json();
            if (!json.success) throw new Error(json.message || 'Lỗi xóa giao dịch');
            loadTransactions(currentTab);
        } catch (err) {
            alert('Lỗi: ' + err.message);
        }
    };

    // Chỉnh sửa giao dịch
    window.editTransaction = async function (id) {
        try {
            const res = await fetch(`../api/transaction/list.php?search=&limit=100`, { credentials: 'include' });
            const json = await res.json();
            const tx = (json.data?.transactions || []).find(t => Number(t.id) === Number(id));
            if (tx) {
                openModalTx(tx.type, tx);
            }
        } catch (e) {
            console.error(e);
        }
    };

    // =========================================================================
    // 7. NHẬN XÉT CHUYÊN GIA AI
    // =========================================================================
    async function requestAiAdvice() {
        const box = $('aiAdviceContent');
        const btn = $('btnRequestAiAdvice');
        if (!box) return;

        if (btn) btn.disabled = true;
        box.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Chuyên gia AI đang đọc số liệu thu chi của bạn...';

        try {
            const res = await fetch('../api/ai/advice.php', { credentials: 'include' });
            const json = await res.json();
            const d = json.data || json;
            const text = d.summary || d.advice || d.comment || 'Tỷ lệ tiết kiệm của bạn đang ở mức tốt. Hãy tiếp tục duy trì!';
            const tips = d.actions || d.tips || [];

            let tipsHtml = '';
            if (tips.length) {
                tipsHtml = '<ul style="margin:0.75rem 0 0 1.25rem; padding:0;">' +
                    tips.map(t => `<li style="margin-bottom:0.35rem;">${escapeHtml(typeof t === 'string' ? t : (t.title || t.text || ''))}</li>`).join('') +
                    '</ul>';
            }

            box.innerHTML = `<strong>Đánh giá:</strong> ${escapeHtml(text)}${tipsHtml}`;
        } catch (e) {
            box.textContent = e.message || 'Không thể kết nối trợ lý AI lúc này.';
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    // =========================================================================
    // 8. BẮT SỰ KIỆN & KHỞI TẠO TOÀN TRANG
    // =========================================================================
    function initEvents() {
        // Tab buttons
        $$('.tx-tab-btn').forEach(btn => {
            btn.addEventListener('click', () => switchTab(btn.dataset.tab));
        });

        // Quick Add buttons
        if ($('btnAddIncomeQuick')) $('btnAddIncomeQuick').onclick = () => openModalTx('income');
        if ($('btnAddExpenseQuick')) $('btnAddExpenseQuick').onclick = () => openModalTx('expense');
        if ($('btnQuickAddTx')) $('btnQuickAddTx').onclick = () => openModalTx('expense');

        // OCR button
        if ($('btnScanOcrQuick')) $('btnScanOcrQuick').onclick = () => {
            openModalTx('expense');
            $('ocrFileInput').click();
        };
        if ($('btnModalOcr')) $('btnModalOcr').onclick = () => $('ocrFileInput').click();
        if ($('ocrFileInput')) $('ocrFileInput').onchange = e => handleOcrUpload(e.target.files[0]);

        // Modal type switch
        if ($('btnTypeExpense')) $('btnTypeExpense').onclick = () => setModalType('expense');
        if ($('btnTypeIncome')) $('btnTypeIncome').onclick = () => setModalType('income');
        if ($('btnCloseModalTx')) $('btnCloseModalTx').onclick = closeModalTx;
        if ($('btnCancelTx')) $('btnCancelTx').onclick = closeModalTx;
        if ($('formTx')) $('formTx').onsubmit = handleSaveTransaction;

        // Modal click outside to close
        window.addEventListener('click', e => {
            if (e.target === $('modalTx')) closeModalTx();
        });

        // Search pill on header
        if ($('globalTxSearch')) {
            let debounceTimer = null;
            $('globalTxSearch').addEventListener('input', e => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    const q = e.target.value.trim();
                    if (filters[currentTab]) {
                        filters[currentTab].search = q;
                        filters[currentTab].page = 1;
                        loadTransactions(currentTab);
                    }
                }, 300);
            });
        }

        // Tab 1 Filters
        if ($('filterCatAll')) $('filterCatAll').onchange = e => { filters.all.category_id = e.target.value; filters.all.page = 1; loadTransactions('all'); };
        if ($('filterDateFromAll')) $('filterDateFromAll').onchange = e => { filters.all.from_date = e.target.value; filters.all.page = 1; loadTransactions('all'); };
        if ($('filterDateToAll')) $('filterDateToAll').onchange = e => { filters.all.to_date = e.target.value; filters.all.page = 1; loadTransactions('all'); };
        if ($('filterMethodAll')) $('filterMethodAll').onchange = e => { filters.all.payment_method = e.target.value; filters.all.page = 1; loadTransactions('all'); };
        if ($('btnResetFilterAll')) $('btnResetFilterAll').onclick = () => {
            $('filterCatAll').value = '';
            $('filterDateFromAll').value = '';
            $('filterDateToAll').value = '';
            $('filterMethodAll').value = '';
            filters.all = { page: 1, limit: 15, category_id: '', from_date: '', to_date: '', payment_method: '', search: '' };
            loadTransactions('all');
        };

        // Tab 2 Filters (Expense)
        if ($('filterCatExpense')) $('filterCatExpense').onchange = e => { filters.expense.category_id = e.target.value; filters.expense.page = 1; loadTransactions('expense'); };
        if ($('filterDateFromExpense')) $('filterDateFromExpense').onchange = e => { filters.expense.from_date = e.target.value; filters.expense.page = 1; loadTransactions('expense'); };
        if ($('filterDateToExpense')) $('filterDateToExpense').onchange = e => { filters.expense.to_date = e.target.value; filters.expense.page = 1; loadTransactions('expense'); };
        if ($('filterMethodExpense')) $('filterMethodExpense').onchange = e => { filters.expense.payment_method = e.target.value; filters.expense.page = 1; loadTransactions('expense'); };
        if ($('btnResetFilterExpense')) $('btnResetFilterExpense').onclick = () => {
            $('filterCatExpense').value = '';
            $('filterDateFromExpense').value = '';
            $('filterDateToExpense').value = '';
            $('filterMethodExpense').value = '';
            filters.expense = { page: 1, limit: 15, category_id: '', from_date: '', to_date: '', payment_method: '', search: '' };
            loadTransactions('expense');
        };

        // Tab 3 Filters (Income)
        if ($('filterCatIncome')) $('filterCatIncome').onchange = e => { filters.income.category_id = e.target.value; filters.income.page = 1; loadTransactions('income'); };
        if ($('filterDateFromIncome')) $('filterDateFromIncome').onchange = e => { filters.income.from_date = e.target.value; filters.income.page = 1; loadTransactions('income'); };
        if ($('filterDateToIncome')) $('filterDateToIncome').onchange = e => { filters.income.to_date = e.target.value; filters.income.page = 1; loadTransactions('income'); };
        if ($('filterMethodIncome')) $('filterMethodIncome').onchange = e => { filters.income.payment_method = e.target.value; filters.income.page = 1; loadTransactions('income'); };
        if ($('btnResetFilterIncome')) $('btnResetFilterIncome').onclick = () => {
            $('filterCatIncome').value = '';
            $('filterDateFromIncome').value = '';
            $('filterDateToIncome').value = '';
            $('filterMethodIncome').value = '';
            filters.income = { page: 1, limit: 15, category_id: '', from_date: '', to_date: '', payment_method: '', search: '' };
            loadTransactions('income');
        };

        // Tab 4 (Report)
        if ($('reportTimeframe')) $('reportTimeframe').onchange = loadReportTab;
        if ($('btnExportExcel')) $('btnExportExcel').onclick = exportExcel;
        if ($('btnExportCSV')) $('btnExportCSV').onclick = exportCSV;
        if ($('btnExportPDF')) $('btnExportPDF').onclick = exportPDF;
        if ($('btnRequestAiAdvice')) $('btnRequestAiAdvice').onclick = requestAiAdvice;

        // Mobile Sidebar Toggle
        const btnMenu = $('btnMenu');
        const sidebar = $('appSidebar');
        if (btnMenu && sidebar) {
            btnMenu.addEventListener('click', e => {
                e.stopPropagation();
                sidebar.classList.toggle('open');
            });
            document.addEventListener('click', e => {
                if (!sidebar.contains(e.target) && e.target !== btnMenu) {
                    sidebar.classList.remove('open');
                }
            });
        }

        // Cập nhật ngày tháng trên header
        if ($('currentDateText')) {
            const now = new Date();
            const days = ['Chủ Nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'];
            $('currentDateText').textContent = `${days[now.getDay()]}, ${now.getDate()} Th${now.getMonth() + 1}, ${now.getFullYear()}`;
        }
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', async () => {
        initEvents();
        await loadCategories();

        // Check query param ?tab=
        const urlParams = new URLSearchParams(window.location.search);
        const tabParam = urlParams.get('tab');
        if (['all', 'expense', 'income', 'report'].includes(tabParam)) {
            switchTab(tabParam);
        } else {
            switchTab('all');
        }

        // Check query param ?action=
        const actionParam = urlParams.get('action');
        if (actionParam === 'new_expense' || actionParam === 'expense') openModalTx('expense');
        else if (actionParam === 'new_income' || actionParam === 'income') openModalTx('income');
    });

})();
