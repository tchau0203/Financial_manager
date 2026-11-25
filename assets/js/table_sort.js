/**
 * Module Sắp Xếp Bảng Dữ Liệu Tăng Dần / Giảm Dần
 * Phụ trách: Ngô Bảo
 * Hướng dẫn sử dụng:
 *   Gắn class "sortable-amount" vào thẻ <th> cột Số tiền.
 *   Khi click, tự động đảo chiều sắp xếp (Cao ➔ Thấp hoặc Thấp ➔ Cao).
 */
(function () {
    'use strict';

    window.TableSorter = {
        /**
         * Sắp xếp danh sách mảng dữ liệu theo số tiền
         * @param {Array} items - Danh sách giao dịch
         * @param {'asc'|'desc'} direction - Hướng sắp xếp
         */
        sortByAmount: function (items, direction = 'desc') {
            if (!Array.isArray(items)) return [];
            return [...items].sort((a, b) => {
                const amountA = Number(a.amount) || 0;
                const amountB = Number(b.amount) || 0;
                return direction === 'asc' ? amountA - amountB : amountB - amountA;
            });
        },

        // TODO (Ngô Bảo): 
        // - Viết hàm gắn sự kiện click vào các thẻ <th> có thuộc tính sortable
        // - Cập nhật icon mũi tên (⇅, ↑, ↓) trên giao diện bảng
        // - Tích hợp vào bảng Thu nhập, Chi tiêu và Sổ giao dịch
    };
})();
