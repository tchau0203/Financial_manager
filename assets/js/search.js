/**
 * Module Tìm kiếm & Bộ lọc Nâng cao (Advanced Search & Filter)
 * Tác giả: Ngô Bảo (FE) - Nhóm 2
 * Hỗ trợ: Drawer/Collapse lọc đa tiêu chí, Price Range Slider, lọc ngày nhanh, Reset bộ lọc
 */

(function () {
    // Khởi tạo Bộ lọc nâng cao cho trang
    function initAdvancedSearch(options = {}) {
        const onFilterChange = options.onFilterChange || function () {};
        const onFilterReset  = options.onFilterReset  || function () {};

        const toggleBtn    = document.getElementById('btnToggleAdvancedSearch');
        const drawer       = document.getElementById('advancedSearchDrawer');
        const minInput     = document.getElementById('filter_min_amount');
        const maxInput     = document.getElementById('filter_max_amount');
        const rangeSlider  = document.getElementById('filter_price_slider');
        const priceDisplay = document.getElementById('filter_price_display');
        const resetBtn     = document.getElementById('btnResetFilter');
        const fromDate     = document.getElementById('filter_from');
        const toDate       = document.getElementById('filter_to');

        // 1. Toggle Drawer / Collapse
        if (toggleBtn && drawer) {
            toggleBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const isOpen = drawer.classList.toggle('open');
                drawer.style.display = isOpen ? 'block' : 'none';
                toggleBtn.classList.toggle('active', isOpen);
                const icon = toggleBtn.querySelector('i');
                if (icon) {
                    icon.className = isOpen ? 'fa-solid fa-chevron-up' : 'fa-solid fa-sliders';
                }
            });
        }

        // 2. Cập nhật nhãn hiển thị số tiền khoảng lọc
        function updatePriceDisplay() {
            if (!priceDisplay) return;
            const minVal = Number(minInput?.value) || 0;
            const maxVal = Number(maxInput?.value) || 0;

            if (minVal === 0 && maxVal === 0) {
                priceDisplay.textContent = 'Mọi mức giá';
            } else if (minVal > 0 && maxVal > 0) {
                priceDisplay.textContent = `Từ ${formatCurrency(minVal)} đến ${formatCurrency(maxVal)}`;
            } else if (minVal > 0) {
                priceDisplay.textContent = `Tối thiểu ${formatCurrency(minVal)}`;
            } else if (maxVal > 0) {
                priceDisplay.textContent = `Tối đa ${formatCurrency(maxVal)}`;
            }
        }

        // 3. Đồng bộ Slider với Max Input
        if (rangeSlider && maxInput) {
            rangeSlider.addEventListener('input', () => {
                const val = Number(rangeSlider.value);
                maxInput.value = val > 0 ? val : '';
                updatePriceDisplay();
                onFilterChange();
            });

            maxInput.addEventListener('input', () => {
                const val = Number(maxInput.value) || 0;
                rangeSlider.value = Math.min(val, Number(rangeSlider.max) || 100000000);
                updatePriceDisplay();
                onFilterChange();
            });
        }

        if (minInput) {
            minInput.addEventListener('input', () => {
                updatePriceDisplay();
                onFilterChange();
            });
        }

        // 4. Các nút phím tắt chọn nhanh khoảng thời gian
        const quickDateBtns = document.querySelectorAll('[data-quick-date]');
        quickDateBtns.forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                quickDateBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                const type = btn.dataset.quickDate;
                const now = new Date();
                const pad = (n) => String(n).padStart(2, '0');
                const fmt = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

                let start = '';
                let end = fmt(now);

                if (type === 'today') {
                    start = end;
                } else if (type === '7days') {
                    const d = new Date();
                    d.setDate(d.getDate() - 7);
                    start = fmt(d);
                } else if (type === 'this_month') {
                    start = fmt(new Date(now.getFullYear(), now.getMonth(), 1));
                } else if (type === 'last_month') {
                    start = fmt(new Date(now.getFullYear(), now.getMonth() - 1, 1));
                    end   = fmt(new Date(now.getFullYear(), now.getMonth(), 0));
                } else if (type === 'this_year') {
                    start = fmt(new Date(now.getFullYear(), 0, 1));
                }

                if (fromDate) fromDate.value = start;
                if (toDate) toDate.value = end;
                onFilterChange();
            });
        });

        // 5. Nút Reset toàn bộ bộ lọc
        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                if (minInput) minInput.value = '';
                if (maxInput) maxInput.value = '';
                if (rangeSlider) rangeSlider.value = 0;
                quickDateBtns.forEach(b => b.classList.remove('active'));
                updatePriceDisplay();
                onFilterReset();
            });
        }

        updatePriceDisplay();
    }

    // Đưa hàm ra phạm vi toàn cục
    window.initAdvancedSearch = initAdvancedSearch;
})();
