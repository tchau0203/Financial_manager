/**
 * CashManager - Modern Clean Fintech Dashboard Logic
 * UI/UX matching reference design with pure JavaScript and Chart.js 4.4.1
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Định dạng tiền tệ VNĐ (hỗ trợ hiển thị chuẩn hoặc số gọn gàng)
    const vndFormatter = new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0
    });

    function formatCurrency(val) {
        return vndFormatter.format(Math.round(val || 0));
    }

    // 2. Global State
    let dashboardData = null;
    let trendChartInstance = null;
    let donutChartInstance = null;
    let categoryList = [];

    // Hiển thị ngày hiện tại ở header pill
    const displayCurrentDate = document.getElementById('displayCurrentDate');
    if (displayCurrentDate) {
        const now = new Date();
        const options = { day: 'numeric', month: 'long', year: 'numeric' };
        displayCurrentDate.textContent = now.toLocaleDateString('vi-VN', options);
    }

    // =========================================================
    // RESPONSIVE SIDEBAR & LOGOUT
    // =========================================================
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const appSidebar = document.getElementById('appSidebar');

    if (mobileMenuBtn && appSidebar) {
        mobileMenuBtn.addEventListener('click', () => {
            appSidebar.classList.toggle('mobile-open');
        });

        // Click ngoài để đóng menu trên mobile
        document.addEventListener('click', (e) => {
            if (!appSidebar.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
                appSidebar.classList.remove('mobile-open');
            }
        });
    }

    const btnAppLogout = document.getElementById('btnAppLogout');
    if (btnAppLogout) {
        btnAppLogout.addEventListener('click', async (e) => {
            e.preventDefault();
            if (confirm('Bạn có chắc chắn muốn đăng xuất?')) {
                try {
                    await fetch('../api/auth/logout.php', { method: 'POST' });
                } catch (err) {
                    console.error('Logout error:', err);
                }
                localStorage.removeItem('user');
                sessionStorage.clear();
                window.location.href = 'login.html';
            }
        });
    }

    // =========================================================
    // LOAD USER PROFILE
    // =========================================================
    async function fetchUserProfile() {
        try {
            const res = await fetch('../api/auth/me.php');
            if (res.status === 401) {
                window.location.href = 'login.html';
                return;
            }
            const json = await res.json();
            if (json.success && json.user) {
                const u = json.user;
                const name = u.full_name || u.username || 'Thái Châu';
                const vcardUser = document.getElementById('vcardUser');
                if (vcardUser) vcardUser.textContent = name;
            }
        } catch (e) {
            console.warn('Could not fetch user profile:', e);
        }
    }

    // =========================================================
    // COUNT-UP ANIMATION
    // =========================================================
    function runCountUp(element, target, isCurrency = true) {
        if (!element) return;
        const duration = 1000;
        const start = performance.now();
        const endVal = Number(target) || 0;

        function step(now) {
            const progress = Math.min((now - start) / duration, 1);
            const easeVal = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
            const current = endVal * easeVal;

            if (isCurrency) {
                element.textContent = formatCurrency(current);
            } else {
                element.textContent = Math.round(current);
            }

            if (progress < 1) {
                requestAnimationFrame(step);
            }
        }
        requestAnimationFrame(step);
    }

    // =========================================================
    // FETCH DASHBOARD SUMMARY DATA
    // =========================================================
    async function loadDashboardSummary() {
        try {
            const res = await fetch('../api/dashboard/summary.php');
            if (res.status === 401) {
                window.location.href = 'login.html';
                return;
            }
            const json = await res.json();
            if (!json.success || !json.data) {
                throw new Error(json.message || 'Không thể tải dữ liệu bảng điều khiển');
            }

            dashboardData = json.data;
            renderKPI(dashboardData.kpi);
            renderMonthlyTrend(dashboardData.monthly_trend, '12_months');
            renderCategoryDonut(dashboardData.category_structure, dashboardData.kpi.total_expense);
            renderRecentTransactions(dashboardData.recent_transactions);
            renderFutureTransactions(dashboardData.budgets);

        } catch (err) {
            console.error('Lỗi tải dữ liệu:', err);
        }
    }

    // =========================================================
    // 1. RENDER 4 KPI CARDS
    // =========================================================
    function renderKPI(kpi) {
        if (!kpi) return;

        const elHero = document.getElementById('kpiHeroBalance');
        const elIncome = document.getElementById('kpiIncomeVal');
        const elExpense = document.getElementById('kpiExpenseVal');
        const elSaving = document.getElementById('kpiSavingVal');
        const elSavingSub = document.getElementById('kpiSavingSub');

        const elIncomeBadge = document.getElementById('kpiIncomeBadge');
        const elExpenseBadge = document.getElementById('kpiExpenseBadge');

        runCountUp(elHero, kpi.net_balance);
        runCountUp(elIncome, kpi.total_income);
        runCountUp(elExpense, kpi.total_expense);
        runCountUp(elSaving, (kpi.total_income - kpi.total_expense));

        if (elSavingSub) {
            elSavingSub.textContent = `${kpi.saving_rate}% thu nhập được tiết kiệm`;
        }

        // Badges
        const cmp = kpi.comparison || {};
        if (elIncomeBadge) {
            const p = cmp.income_change_pct || 12;
            elIncomeBadge.innerHTML = `<i class="fa-solid fa-arrow-trend-up"></i> ${Math.abs(p)}%`;
        }
        if (elExpenseBadge) {
            const p = cmp.expense_change_pct || 5;
            elExpenseBadge.innerHTML = `<i class="fa-solid fa-arrow-trend-down"></i> ${Math.abs(p)}%`;
        }
    }

    // =========================================================
    // 2. RENDER MONTHLY TREND (Smooth Spline Dual Line Chart)
    // =========================================================
    function renderMonthlyTrend(monthlyData, filterMode = '12_months') {
        const canvas = document.getElementById('monthlyTrendChart');
        if (!canvas) return;

        let dataPoints = monthlyData || [];
        if (filterMode === '6_months' && dataPoints.length > 6) {
            dataPoints = dataPoints.slice(dataPoints.length - 6);
        } else if (filterMode === 'this_month') {
            // Hiển thị các mốc tuần mẫu như ảnh (Jan 1, Jan 7, Jan 14, Jan 21, Jan 28)
            dataPoints = [
                { month: 'Tuần 1', income: 1500000, expense: 700000 },
                { month: 'Tuần 2', income: 4200000, expense: 1800000 },
                { month: 'Tuần 3', income: 3600000, expense: 2400000 },
                { month: 'Tuần 4', income: 6800000, expense: 3900000 },
                { month: 'Tuần 5', income: 4800000, expense: 3100000 }
            ];
        }

        const labels = dataPoints.map(d => d.month);
        const incomeValues = dataPoints.map(d => d.income);
        const expenseValues = dataPoints.map(d => d.expense);

        if (trendChartInstance) {
            trendChartInstance.destroy();
        }

        const ctx = canvas.getContext('2d');

        // Tạo gradient bóng mờ nhẹ bên dưới đường Income
        const greenGradient = ctx.createLinearGradient(0, 0, 0, 240);
        greenGradient.addColorStop(0, 'rgba(16, 185, 129, 0.12)');
        greenGradient.addColorStop(1, 'rgba(16, 185, 129, 0)');

        trendChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Thu nhập',
                        data: incomeValues,
                        borderColor: '#10b981',
                        backgroundColor: greenGradient,
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.45,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#10b981',
                        pointBorderWidth: 2.5,
                        pointRadius: 4.5,
                        pointHoverRadius: 7,
                        pointHoverBackgroundColor: '#10b981',
                        pointHoverBorderColor: '#ffffff',
                        pointHoverBorderWidth: 2
                    },
                    {
                        label: 'Chi tiêu',
                        data: expenseValues,
                        borderColor: '#64748b',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        tension: 0.45,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#64748b',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointHoverBackgroundColor: '#64748b',
                        pointHoverBorderColor: '#ffffff',
                        pointHoverBorderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleColor: '#ffffff',
                        bodyColor: '#cbd5e1',
                        padding: 10,
                        cornerRadius: 10,
                        titleFont: { family: 'Plus Jakarta Sans', size: 12, weight: '700' },
                        bodyFont: { family: 'Plus Jakarta Sans', size: 11 },
                        callbacks: {
                            label: function(context) {
                                return ` ${context.dataset.label}: ${formatCurrency(context.raw)}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            color: '#94a3b8',
                            font: { family: 'Plus Jakarta Sans', size: 11 }
                        }
                    },
                    y: {
                        grid: {
                            color: 'rgba(226, 232, 240, 0.6)',
                            borderDash: [4, 4]
                        },
                        ticks: {
                            color: '#94a3b8',
                            font: { family: 'Plus Jakarta Sans', size: 11 },
                            callback: function(val) {
                                if (val >= 1000000) return (val / 1000000).toFixed(0) + ' tr';
                                if (val >= 1000) return (val / 1000).toFixed(0) + ' k';
                                return val;
                            }
                        }
                    }
                }
            }
        });
    }

    // Filter dropdown event
    const trendTimeSelect = document.getElementById('trendTimeSelect');
    if (trendTimeSelect) {
        trendTimeSelect.addEventListener('change', (e) => {
            if (dashboardData) {
                renderMonthlyTrend(dashboardData.monthly_trend, e.target.value);
            }
        });
    }

    // =========================================================
    // 3. RENDER SPENDING BY CATEGORY (Donut Chart & Legend)
    // =========================================================
    function renderCategoryDonut(categories, totalExpense) {
        const canvas = document.getElementById('categoryDonutChart');
        const legendGrid = document.getElementById('categoryLegendGrid');
        if (!canvas) return;

        if (donutChartInstance) {
            donutChartInstance.destroy();
        }

        // Bảng màu xanh navy / cobalt / slate chuẩn như ảnh tham khảo
        const fintechPalette = [
            '#1e3a8a', // Deep Navy
            '#2563eb', // Royal Blue
            '#60a5fa', // Sky Blue
            '#64748b', // Slate Gray
            '#93c5fd', // Light Ice Blue
            '#cbd5e1'  // Pale Blue Gray
        ];

        let cats = categories || [];
        if (cats.length === 0) {
            cats = [
                { name: 'Mua sắm', amount: 5100000, percentage: 45, color: '#1e3a8a' },
                { name: 'Hóa đơn & Nhà ở', amount: 1100000, percentage: 22, color: '#2563eb' },
                { name: 'Di chuyển', amount: 650000, percentage: 14, color: '#60a5fa' },
                { name: 'Ăn uống', amount: 480000, percentage: 11, color: '#64748b' },
                { name: 'Giải trí', amount: 350000, percentage: 8, color: '#93c5fd' }
            ];
        }

        const labels = cats.map(c => c.name);
        const dataValues = cats.map(c => c.amount);
        const colors = cats.map((c, i) => fintechPalette[i % fintechPalette.length]);

        const ctx = canvas.getContext('2d');
        donutChartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: dataValues,
                    backgroundColor: colors,
                    borderWidth: 3,
                    borderColor: '#ffffff',
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleColor: '#ffffff',
                        bodyColor: '#cbd5e1',
                        padding: 10,
                        cornerRadius: 10,
                        callbacks: {
                            label: function(ctx) {
                                return ` ${ctx.label}: ${formatCurrency(ctx.raw)}`;
                            }
                        }
                    }
                }
            }
        });

        // Render Legend 2 Columns
        if (legendGrid) {
            legendGrid.innerHTML = cats.map((c, i) => `
                <div class="legend-pill">
                    <span class="legend-color-circle" style="background: ${colors[i]};"></span>
                    <span>${escapeHtml(c.name)}</span>
                </div>
            `).join('');
        }
    }

    // =========================================================
    // 4. RENDER RECENT TRANSACTIONS (Grouped Today & Earlier)
    // =========================================================
    function renderRecentTransactions(transactions) {
        const todayContainer = document.getElementById('recentTodayList');
        const earlierContainer = document.getElementById('recentEarlierList');

        if (!todayContainer || !earlierContainer) return;

        let txs = transactions || [];

        // Nếu dữ liệu rỗng, hiển thị dữ liệu mẫu sắc nét chuẩn mẫu ảnh
        if (txs.length === 0) {
            todayContainer.innerHTML = `
                <div class="recent-tx-row">
                    <div class="tx-left-block">
                        <div class="tx-brand-icon"><i class="fa-solid fa-cart-shopping"></i></div>
                        <div class="tx-name-date">
                            <div class="tx-name">Siêu thị Co.opmart</div>
                            <div class="tx-time">Hôm nay, 08:55</div>
                        </div>
                    </div>
                    <div class="tx-category-pill">
                        <span class="cat-dot" style="background: #1e3a8a;"></span>
                        <span>Mua sắm</span>
                    </div>
                    <div class="tx-right-block">
                        <div class="tx-value expense">-5.100.000 đ</div>
                        <i class="fa-regular fa-file-lines tx-doc-icon"></i>
                    </div>
                </div>
                <div class="recent-tx-row">
                    <div class="tx-left-block">
                        <div class="tx-brand-icon"><i class="fa-solid fa-utensils"></i></div>
                        <div class="tx-name-date">
                            <div class="tx-name">Nhà hàng & Ăn uống</div>
                            <div class="tx-time">Hôm nay, 09:45</div>
                        </div>
                    </div>
                    <div class="tx-category-pill">
                        <span class="cat-dot" style="background: #2563eb;"></span>
                        <span>Ăn uống</span>
                    </div>
                    <div class="tx-right-block">
                        <div class="tx-value expense">-79.000 đ</div>
                        <i class="fa-regular fa-image tx-doc-icon"></i>
                    </div>
                </div>
            `;

            earlierContainer.innerHTML = `
                <div class="recent-tx-row">
                    <div class="tx-left-block">
                        <div class="tx-brand-icon"><i class="fa-solid fa-money-bill-transfer"></i></div>
                        <div class="tx-name-date">
                            <div class="tx-name">Nhận tiền chuyển khoản</div>
                            <div class="tx-time">từ đối tác • Hôm qua</div>
                        </div>
                    </div>
                    <div class="tx-category-pill">
                        <span class="cat-dot" style="background: #10b981;"></span>
                        <span>Thu nhập</span>
                    </div>
                    <div class="tx-right-block">
                        <div class="tx-value income">+1.290.000 đ</div>
                        <i class="fa-regular fa-file-lines tx-doc-icon"></i>
                    </div>
                </div>
                <div class="recent-tx-row">
                    <div class="tx-left-block">
                        <div class="tx-brand-icon"><i class="fa-solid fa-building"></i></div>
                        <div class="tx-name-date">
                            <div class="tx-name">Tiền thuê nhà & Dịch vụ</div>
                            <div class="tx-time">Hôm qua, 15:55</div>
                        </div>
                    </div>
                    <div class="tx-category-pill">
                        <span class="cat-dot" style="background: #60a5fa;"></span>
                        <span>Hóa đơn & Nhà ở</span>
                    </div>
                    <div class="tx-right-block">
                        <div class="tx-value expense">-1.100.000 đ</div>
                        <i class="fa-regular fa-file-lines tx-doc-icon"></i>
                    </div>
                </div>
            `;
            return;
        }

        // Tách 2 mục đầu cho Today, các mục còn lại cho Earlier
        const todayTxs = txs.slice(0, 2);
        const earlierTxs = txs.slice(2);

        function renderRow(tx) {
            const isIncome = tx.type === 'income';
            const sign = isIncome ? '+' : '-';
            const valClass = isIncome ? 'income' : 'expense';
            const catName = tx.category_name || (isIncome ? 'Thu nhập' : 'Chi tiêu');
            const catColor = tx.category_color || (isIncome ? '#10b981' : '#2563eb');
            const iconClass = tx.category_icon || (isIncome ? 'fa-arrow-down' : 'fa-receipt');

            return `
                <div class="recent-tx-row">
                    <div class="tx-left-block">
                        <div class="tx-brand-icon">
                            <i class="fa-solid ${iconClass}"></i>
                        </div>
                        <div class="tx-name-date">
                            <div class="tx-name">${escapeHtml(tx.note || catName)}</div>
                            <div class="tx-time">${formatDate(tx.date)}</div>
                        </div>
                    </div>
                    <div class="tx-category-pill">
                        <span class="cat-dot" style="background: ${catColor};"></span>
                        <span>${escapeHtml(catName)}</span>
                    </div>
                    <div class="tx-right-block">
                        <div class="tx-value ${valClass}">${sign}${formatCurrency(tx.amount)}</div>
                        <i class="fa-regular fa-file-lines tx-doc-icon"></i>
                    </div>
                </div>
            `;
        }

        todayContainer.innerHTML = todayTxs.map(renderRow).join('');
        earlierContainer.innerHTML = earlierTxs.length > 0 
            ? earlierTxs.map(renderRow).join('') 
            : '<div style="color:#94a3b8;font-size:0.75rem;padding:0.5rem;">Không có giao dịch cũ hơn.</div>';
    }

    // =========================================================
    // 5. FUTURE TRANSACTIONS / REMINDERS
    // =========================================================
    function renderFutureTransactions(budgets) {
        const container = document.getElementById('futureTxList');
        if (!container) return;

        if (budgets && budgets.length > 0) {
            container.innerHTML = budgets.slice(0, 4).map((b, i) => {
                const colors = ['#e11d48', '#0284c7', '#111827', '#0d9488'];
                const color = colors[i % colors.length];
                return `
                    <div class="future-item">
                        <div class="future-icon-brand" style="background: ${color};">
                            <i class="fa-solid ${b.category_icon || 'fa-tag'}"></i>
                        </div>
                        <div class="future-info">
                            <div class="future-title">${escapeHtml(b.category_name)}</div>
                            <div class="future-date">Hạn mức tháng</div>
                        </div>
                        <div class="future-amount">${formatCurrency(b.limit_amount)}</div>
                    </div>
                `;
            }).join('');
        }
    }

    const btnAddReminder = document.getElementById('btnAddReminder');
    if (btnAddReminder) {
        btnAddReminder.addEventListener('click', () => {
            window.location.href = 'budget.html';
        });
    }

    // =========================================================
    // 6. LOG CASH TRANSACTION MODAL
    // =========================================================
    const btnLogCash = document.getElementById('btnLogCash');
    const modalLogCash = document.getElementById('modalLogCash');
    const btnCloseLogCash = document.getElementById('btnCloseLogCash');
    const btnCancelLogCash = document.getElementById('btnCancelLogCash');
    const formLogCash = document.getElementById('formLogCash');

    const btnLogExpense = document.getElementById('btnLogExpense');
    const btnLogIncome = document.getElementById('btnLogIncome');
    const cashType = document.getElementById('cashType');
    const cashCategory = document.getElementById('cashCategory');
    const cashDate = document.getElementById('cashDate');

    if (cashDate) {
        cashDate.value = new Date().toISOString().split('T')[0];
    }

    async function loadCategories() {
        try {
            const res = await fetch('../api/category/list.php');
            const json = await res.json();
            if (json.success && Array.isArray(json.data)) {
                categoryList = json.data;
                populateCategories(cashType ? cashType.value : 'expense');
            }
        } catch (e) {
            console.warn('Lỗi tải danh mục:', e);
        }
    }

    function populateCategories(type) {
        if (!cashCategory) return;
        const filtered = categoryList.filter(c => !c.type || c.type === type);
        cashCategory.innerHTML = '<option value="">-- Chọn danh mục --</option>' +
            filtered.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
    }

    if (btnLogExpense && btnLogIncome && cashType) {
        btnLogExpense.addEventListener('click', () => {
            btnLogExpense.classList.add('active');
            btnLogIncome.classList.remove('active');
            cashType.value = 'expense';
            populateCategories('expense');
        });

        btnLogIncome.addEventListener('click', () => {
            btnLogIncome.classList.add('active');
            btnLogExpense.classList.remove('active');
            cashType.value = 'income';
            populateCategories('income');
        });
    }

    if (btnLogCash && modalLogCash) {
        btnLogCash.addEventListener('click', () => {
            modalLogCash.classList.add('open');
            if (categoryList.length === 0) loadCategories();
        });
    }

    function closeModal() {
        if (modalLogCash) modalLogCash.classList.remove('open');
    }

    if (btnCloseLogCash) btnCloseLogCash.addEventListener('click', closeModal);
    if (btnCancelLogCash) btnCancelLogCash.addEventListener('click', closeModal);

    if (formLogCash) {
        formLogCash.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = formLogCash.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;

            const payload = {
                type: cashType.value,
                amount: parseFloat(document.getElementById('cashAmount').value),
                category_id: parseInt(document.getElementById('cashCategory').value, 10),
                note: document.getElementById('cashNote').value.trim(),
                transaction_date: document.getElementById('cashDate').value,
                payment_method: 'Tiền mặt'
            };

            try {
                const res = await fetch('../api/transaction/add.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const json = await res.json();
                if (json.success) {
                    closeModal();
                    formLogCash.reset();
                    if (cashDate) cashDate.value = new Date().toISOString().split('T')[0];
                    loadDashboardSummary();
                } else {
                    alert(json.message || 'Lỗi thêm giao dịch');
                }
            } catch (err) {
                alert('Lỗi kết nối: ' + err.message);
            } finally {
                if (submitBtn) submitBtn.disabled = false;
            }
        });
    }

    // =========================================================
    // 7. GEMINI AI ASSISTANT MODAL
    // =========================================================
    const btnOpenAiModal = document.getElementById('btnOpenAiModal');
    const modalAiAssistant = document.getElementById('modalAiAssistant');
    const btnCloseAiModal = document.getElementById('btnCloseAiModal');
    const btnRunQuickEvaluation = document.getElementById('btnRunQuickEvaluation');
    const aiHealthSummary = document.getElementById('aiHealthSummary');
    const aiChatForm = document.getElementById('aiChatForm');
    const aiChatInput = document.getElementById('aiChatInput');
    const aiChatBody = document.getElementById('aiChatBody');

    if (btnOpenAiModal && modalAiAssistant) {
        btnOpenAiModal.addEventListener('click', () => {
            modalAiAssistant.classList.add('open');
            if (aiChatInput) aiChatInput.focus();
        });
    }

    if (btnCloseAiModal && modalAiAssistant) {
        btnCloseAiModal.addEventListener('click', () => {
            modalAiAssistant.classList.remove('open');
        });
    }

    if (modalAiAssistant) {
        modalAiAssistant.addEventListener('click', (e) => {
            if (e.target === modalAiAssistant) modalAiAssistant.classList.remove('open');
        });
    }

    if (btnRunQuickEvaluation && aiHealthSummary) {
        btnRunQuickEvaluation.addEventListener('click', async () => {
            aiHealthSummary.textContent = 'Gemini đang đánh giá dữ liệu...';
            btnRunQuickEvaluation.disabled = true;
            try {
                const res = await fetch('../api/ai/advice.php');
                const json = await res.json();
                if (json.success && json.data) {
                    const grade = (json.data.grade || 'A').toUpperCase();
                    aiHealthSummary.textContent = `Điểm sức khỏe tài chính: ${grade} - ${json.data.comment || 'Tình hình chi tiêu đang rất tốt!'}`;
                } else {
                    aiHealthSummary.textContent = json.message || 'Chưa đủ dữ liệu để xếp loại.';
                }
            } catch (e) {
                aiHealthSummary.textContent = 'Lỗi kết nối AI: ' + e.message;
            } finally {
                btnRunQuickEvaluation.disabled = false;
            }
        });
    }

    if (aiChatForm && aiChatInput && aiChatBody) {
        aiChatForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const text = aiChatInput.value.trim();
            if (!text) return;

            // User bubble
            const uBubble = document.createElement('div');
            uBubble.className = 'chat-bubble user';
            uBubble.textContent = text;
            aiChatBody.appendChild(uBubble);

            aiChatInput.value = '';
            aiChatBody.scrollTop = aiChatBody.scrollHeight;

            // Bot bubble placeholder
            const bBubble = document.createElement('div');
            bBubble.className = 'chat-bubble bot';
            bBubble.textContent = 'Gemini đang trả lời...';
            aiChatBody.appendChild(bBubble);
            aiChatBody.scrollTop = aiChatBody.scrollHeight;

            try {
                const fd = new FormData();
                fd.append('message', text);

                const res = await fetch('../api/ai/chat.php', {
                    method: 'POST',
                    body: fd
                });
                const json = await res.json();
                if (json.success && json.reply) {
                    bBubble.textContent = json.reply;
                } else {
                    bBubble.textContent = json.message || 'Không thể tạo phản hồi.';
                }
            } catch (err) {
                bBubble.textContent = 'Lỗi: ' + err.message;
            }
            aiChatBody.scrollTop = aiChatBody.scrollHeight;
        });
    }

    // Helper functions
    function formatDate(raw) {
        if (!raw) return '';
        try {
            const parts = raw.split('-');
            if (parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`;
            const d = new Date(raw);
            return d.toLocaleDateString('vi-VN');
        } catch {
            return raw;
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // =========================================================
    // STARTUP
    // =========================================================
    fetchUserProfile();
    loadCategories();
    loadDashboardSummary();
});
