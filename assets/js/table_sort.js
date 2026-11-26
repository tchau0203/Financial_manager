/**
 * Module Sắp xếp Bảng Giao dịch (Table Sort)
 * Phụ trách: Ngô Bảo (Frontend) - Nhóm 3
 * Chức năng:
 *   - Thêm icon sắp xếp (⇅) vào tiêu đề cột "Số tiền" (và các cột data-sort)
 *   - Chu kỳ 3 trạng thái khi click:
 *       1. Mặc định: Sắp xếp theo ngày mới nhất (icon ⇅)
 *       2. Click lần 1: Sắp xếp Giảm dần - từ lớn đến bé (icon ↓)
 *       3. Click lần 2: Sắp xếp Tăng dần - từ bé đến lớn (icon ↑)
 *       4. Click lần 3: Quay về Mặc định (icon ⇅)
 *   - Hỗ trợ client-side sort nhanh + truyền tham số sort_by & order cho API
 */

(function () {
    'use strict';

    function parseDateValue(str) {
        if (!str) return '';
        const s = String(str).trim();
        const vnMatch = s.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})/);
        if (vnMatch) {
            return `${vnMatch[3]}-${vnMatch[2].padStart(2, '0')}-${vnMatch[1].padStart(2, '0')}`;
        }
        return s.slice(0, 10);
    }

    function parseAmountValue(text) {
        let s = String(text || '').trim().replace(/[^\d.,]/g, '');
        if (!s) return 0;
        if (/^\d{1,3}([.,]\d{3})+$/.test(s)) {
            s = s.replace(/[.,]/g, '');
        } else if (s.includes('.') && s.includes(',')) {
            s = s.lastIndexOf(',') > s.lastIndexOf('.')
                ? s.replace(/\./g, '').replace(',', '.')
                : s.replace(/,/g, '');
        } else if (s.includes(',')) {
            s = /,\d{1,2}$/.test(s) ? s.replace(',', '.') : s.replace(/,/g, '');
        }
        const n = parseFloat(s);
        return isNaN(n) ? 0 : n;
    }

    /**
     * Khởi tạo tính năng sắp xếp cho một bảng
     * @param {Object} options
     *   - tbodyId: id của <tbody> cần sắp xếp
     *   - theadId: id của <thead> chứa các <th data-sort>
     *   - tableEl: (tuỳ chọn) phần tử <table> nếu không truyền theadId/tbodyId
     *   - onSortChange: callback(sortKey, sortDir) khi trạng thái sort thay đổi
     */
    function initTableSort(options = {}) {
        const tableEl = options.tableEl || null;
        const container = options.theadId
            ? document.getElementById(options.theadId)
            : (tableEl ? tableEl.querySelector('thead') : document.querySelector('table thead'));

        if (!container || container.dataset.sortBound === '1') return null;

        const sortHeaders = container.querySelectorAll('th[data-sort]');
        if (!sortHeaders.length) return null;

        container.dataset.sortBound = '1';

        const getTbody = () => {
            if (options.tbodyId) return document.getElementById(options.tbodyId);
            const tbl = container.closest('table');
            return tbl ? tbl.querySelector('tbody') : null;
        };

        let currentSortKey = null; // null | 'amount' | 'date' | 'category'
        let currentSortDir = null; // null | 'desc' | 'asc'

        function updateHeaderIcons(activeKey, dir) {
            sortHeaders.forEach(th => {
                const key = th.dataset.sort;
                const oldIcon = th.querySelector('.sort-icon');
                if (oldIcon) oldIcon.remove();

                const icon = document.createElement('span');
                icon.className = 'sort-icon';

                if (key === activeKey && dir === 'desc') {
                    icon.textContent = ' ↓';
                    icon.style.cssText = 'color: var(--primary, #4f46e5); font-weight: 700;';
                    th.style.color = 'var(--primary, #4f46e5)';
                    th.setAttribute('aria-sort', 'descending');
                } else if (key === activeKey && dir === 'asc') {
                    icon.textContent = ' ↑';
                    icon.style.cssText = 'color: var(--primary, #4f46e5); font-weight: 700;';
                    th.style.color = 'var(--primary, #4f46e5)';
                    th.setAttribute('aria-sort', 'ascending');
                } else {
                    icon.textContent = ' ⇅';
                    icon.style.cssText = 'color: #94a3b8; font-weight: 500;';
                    th.style.color = '';
                    th.setAttribute('aria-sort', 'none');
                }
                th.appendChild(icon);
            });
        }

        function sortTableRows(key, dir) {
            const tbody = getTbody();
            if (!tbody) return;

            const rows = Array.from(tbody.querySelectorAll('tr:not(.empty-row)'));
            if (rows.length <= 1) return;

            // Lưu thứ tự gốc nếu chưa có
            rows.forEach((row, idx) => {
                if (row.dataset.origIndex === undefined) {
                    row.dataset.origIndex = String(idx);
                }
            });

            if (!key || !dir) {
                // Trạng thái 1 (Mặc định): Sắp xếp theo ngày mới nhất (hoặc thứ tự gốc từ server)
                rows.sort((a, b) => {
                    const dateA = parseDateValue(a.dataset.date);
                    const dateB = parseDateValue(b.dataset.date);
                    if (dateA && dateB && dateA !== dateB) {
                        return dateB.localeCompare(dateA);
                    }
                    return Number(a.dataset.origIndex || 0) - Number(b.dataset.origIndex || 0);
                });
                rows.forEach(row => tbody.appendChild(row));
                return;
            }

            const targetTh = Array.from(sortHeaders).find(th => th.dataset.sort === key);
            const colIndex = targetTh ? targetTh.cellIndex : -1;

            rows.sort((a, b) => {
                if (key === 'amount') {
                    const valA = a.dataset.amount !== undefined
                        ? Math.abs(Number(a.dataset.amount))
                        : parseAmountValue(colIndex >= 0 ? a.cells[colIndex]?.textContent : '');
                    const valB = b.dataset.amount !== undefined
                        ? Math.abs(Number(b.dataset.amount))
                        : parseAmountValue(colIndex >= 0 ? b.cells[colIndex]?.textContent : '');
                    return dir === 'asc' ? valA - valB : valB - valA;
                }

                if (key === 'date') {
                    const valA = parseDateValue(a.dataset.date || (colIndex >= 0 ? a.cells[colIndex]?.textContent : ''));
                    const valB = parseDateValue(b.dataset.date || (colIndex >= 0 ? b.cells[colIndex]?.textContent : ''));
                    return dir === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
                }

                if (key === 'category') {
                    const valA = (colIndex >= 0 ? a.cells[colIndex]?.textContent : '').trim();
                    const valB = (colIndex >= 0 ? b.cells[colIndex]?.textContent : '').trim();
                    return dir === 'asc' ? valA.localeCompare(valB, 'vi') : valB.localeCompare(valA, 'vi');
                }

                return 0;
            });

            rows.forEach(row => tbody.appendChild(row));
        }

        sortHeaders.forEach(th => {
            th.style.cursor = 'pointer';
            th.style.userSelect = 'none';
            th.setAttribute('title', `Click để sắp xếp theo ${th.textContent.trim()} (Mặc định → Giảm dần → Tăng dần)`);

            th.addEventListener('click', () => {
                const key = th.dataset.sort;

                if (currentSortKey !== key) {
                    // Click lần 1 vào cột: Giảm dần (↓)
                    currentSortKey = key;
                    currentSortDir = 'desc';
                } else if (currentSortDir === 'desc') {
                    // Click lần 2: Tăng dần (↑)
                    currentSortDir = 'asc';
                } else {
                    // Click lần 3: Quay về Mặc định (⇅)
                    currentSortKey = null;
                    currentSortDir = null;
                }

                updateHeaderIcons(currentSortKey, currentSortDir);
                sortTableRows(currentSortKey, currentSortDir);

                if (typeof options.onSortChange === 'function') {
                    options.onSortChange(currentSortKey, currentSortDir);
                }
            });
        });

        // Trạng thái mặc định ban đầu: hiển thị icon ⇅
        updateHeaderIcons(null, null);

        return {
            resort: function () {
                if (currentSortKey && currentSortDir) {
                    sortTableRows(currentSortKey, currentSortDir);
                }
            },
            reset: function () {
                currentSortKey = null;
                currentSortDir = null;
                updateHeaderIcons(null, null);
            },
            getState: function () {
                return {
                    sort_by: currentSortKey,
                    order: currentSortDir
                };
            }
        };
    }

    // Tự động khởi tạo cho các bảng có th[data-sort] nhưng chưa được init thủ công (VD: dashboard.html, shared.html)
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            document.querySelectorAll('table').forEach(tbl => {
                const thead = tbl.querySelector('thead');
                if (thead && thead.dataset.sortBound !== '1' && thead.querySelector('th[data-sort]')) {
                    initTableSort({ tableEl: tbl });
                }
            });
        }, 0);
    });

    window.initTableSort = initTableSort;
})();
