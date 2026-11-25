/**
 * Sổ Giao Dịch & Báo Cáo - Module tích hợp
 * Phụ trách: Lê Quốc Anh
 */
(function () {
    'use strict';

    // 1. Chuyển đổi qua lại giữa 4 Tabs
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-tab');

            tabButtons.forEach(b => b.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));

            btn.classList.add('active');
            const targetContent = document.getElementById(targetId);
            if (targetContent) targetContent.classList.add('active');
        });
    });

    // TODO (Lê Quốc Anh): 
    // - Nạp danh sách giao dịch tổng hợp (kết hợp thu và chi)
    // - Tích hợp modal Thêm/Sửa/Xóa giao dịch
    // - Tích hợp biểu đồ thống kê Chart.js cho Tab 4 Báo cáo
    // - Tích hợp xuất file CSV/Excel
})();
