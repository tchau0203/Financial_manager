// Logic Bảng điều khiển
let trendChartInstance = null;
let categoryChartInstance = null;

document.addEventListener('DOMContentLoaded', async () => {
    const user = await checkAuth();
    if (!user) return;

    const btnLogout = document.getElementById('btnLogout');
    if (btnLogout) {
        btnLogout.addEventListener('click', async () => {
            try {
                await apiFetch('/auth/logout.php', { method: 'POST' });
            } catch (error) {
                console.error('Lỗi đăng xuất:', error);
            }
            window.location.href = 'login.html';
        });
    }

    const btnQuickAdd = document.getElementById('btnQuickAdd');
    const modalQuickAdd = document.getElementById('modalQuickAdd');
    const btnCloseModal = document.getElementById('btnCloseModal');
    const formQuickAdd = document.getElementById('formQuickAdd');

    if (btnQuickAdd && modalQuickAdd) {
        btnQuickAdd.addEventListener('click', () => modalQuickAdd.classList.add('show'));
    }
    if (btnCloseModal && modalQuickAdd) {
        btnCloseModal.addEventListener('click', () => modalQuickAdd.classList.remove('show'));
    }

    if (formQuickAdd) {
        formQuickAdd.addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {
                type: document.getElementById('quick_type').value,
                category_id: parseInt(document.getElementById('quick_category_id').value),
                amount: parseFloat(document.getElementById('quick_amount').value),
                transaction_date: document.getElementById('quick_date').value,
                payment_method: document.getElementById('quick_method').value,
                note: document.getElementById('quick_note').value
            };

            try {
                const res = await apiFetch('/transaction/add.php', {
                    method: 'POST',
                    body: JSON.stringify(payload)
                });

                if (res && res.success) {
                    showToast('Thành công', 'Thêm giao dịch thành công!', 'success');
                    modalQuickAdd.classList.remove('show');
                    formQuickAdd.reset();
                    loadDashboardData();
                } else {
                    showToast('Lỗi', res ? (res.message || res.error) : 'Không thể thêm giao dịch', 'error');
                }
            } catch (error) {
                console.error('Lỗi thêm giao dịch:', error);
            }
        });
    }

    loadDashboardData();
});

async function loadDashboardData() {
    loadKPISummary();
    loadCategoryChart();
    loadRecentTransactions();
    loadBudgetWidget();
}

async function loadKPISummary() {
    try {
        const res = await apiFetch('/report/summary.php?timeframe=this_month');
        if (res && res.success) {
            const d = res.data;
            document.getElementById('kpi-income').textContent = formatVND(d.total_income);
            document.getElementById('kpi-expense').textContent = formatVND(d.total_expense);
            document.getElementById('kpi-balance').textContent = formatVND(d.net_balance);
            document.getElementById('kpi-saving').textContent = (d.saving_rate || 0) + '%';

            if (d.monthly_trend) {
                const ctx = document.getElementById('trendChart').getContext('2d');
                if (trendChartInstance) trendChartInstance.destroy();

                trendChartInstance = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: d.monthly_trend.map(i => i.month),
                        datasets: [
                            { label: 'Thu nhập', data: d.monthly_trend.map(i => i.income), backgroundColor: '#10b981' },
                            { label: 'Chi tiêu', data: d.monthly_trend.map(i => i.expense), backgroundColor: '#ef4444' }
                        ]
                    },
                    options: { responsive: true, maintainAspectRatio: false }
                });
            }
        }
    } catch (e) {
        console.error('Lỗi load summary:', e);
    }
}

async function loadCategoryChart() {
    try {
        const res = await apiFetch('/report/by_category.php?type=expense');
        if (res && res.success && res.data) {
            const ctx = document.getElementById('categoryChart').getContext('2d');
            if (categoryChartInstance) categoryChartInstance.destroy();

            categoryChartInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: res.data.map(i => i.category_name),
                    datasets: [{
                        data: res.data.map(i => i.total_amount),
                        backgroundColor: res.data.map(i => i.color || '#3b82f6')
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }
    } catch (e) {
        console.error('Lỗi load category chart:', e);
    }
}

async function loadRecentTransactions() {
    const tbody = document.getElementById('recent-tx-tbody');
    if (!tbody) return;
    try {
        const res = await apiFetch('/transaction/list.php?limit=5');
        if (res && res.success && res.data.transactions.length > 0) {
            tbody.replaceChildren(...res.data.transactions.map(item => {
                const isIncome = item.type === 'income';
                const row = document.createElement('tr');
                const typeCell = document.createElement('td');
                const badge = document.createElement('span');
                badge.className = `badge ${isIncome ? 'badge-income' : 'badge-expense'}`;
                badge.textContent = isIncome ? 'Thu' : 'Chi';
                typeCell.appendChild(badge);

                const categoryCell = document.createElement('td');
                const icon = document.createElement('i');
                const iconClass = /^[a-zA-Z0-9 _-]+$/.test(item.icon || '') ? item.icon : 'fa-solid fa-tag';
                icon.className = iconClass;
                categoryCell.append(icon, document.createTextNode(` ${item.category_name || ''}`));

                const dateCell = document.createElement('td');
                dateCell.textContent = item.transaction_date || '';
                const noteCell = document.createElement('td');
                noteCell.textContent = item.note || '—';
                const amountCell = document.createElement('td');
                amountCell.style.textAlign = 'right';
                amountCell.style.fontWeight = '600';
                amountCell.style.color = isIncome ? 'var(--success)' : 'var(--danger)';
                amountCell.textContent = `${isIncome ? '+' : '-'}${formatVND(item.amount)}`;

                row.append(typeCell, categoryCell, dateCell, noteCell, amountCell);
                return row;
            }));
        } else {
            tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--text-muted);">Chưa có giao dịch gần đây.</td></tr>`;
        }
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--danger);">Lỗi tải danh sách giao dịch.</td></tr>`;
    }
}

async function loadBudgetWidget() {
    const bar = document.getElementById('budget-progress-bar');
    const text = document.getElementById('budget-progress-text');
    if (!bar || !text) return;
    try {
        const now = new Date();
        const res = await apiFetch(`/budget/get.php?month=${now.getMonth() + 1}&year=${now.getFullYear()}`);
        if (res && res.success && res.data.length > 0) {
            const overall = res.data.find(b => b.category_id === null) || res.data[0];
            const pct = Math.min(overall.percentage || 0, 100);
            bar.style.width = pct + '%';
            bar.className = 'progress-bar ' + (overall.status === 'exceeded' ? 'exceeded' : overall.status === 'warning' ? 'warning' : '');
            text.textContent = `Đã dùng ${formatVND(overall.spent_amount)} / ${formatVND(overall.amount)} (${overall.percentage}%)`;
        } else {
            text.textContent = 'Chưa thiết lập hạn mức ngân sách tháng này.';
            bar.style.width = '0%';
        }
    } catch (e) {
        console.error('Lỗi load budget widget:', e);
    }
}
