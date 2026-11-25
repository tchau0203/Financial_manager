/**
 * Module Chuông Thông Báo & Lời Mời Quỹ Chung (Header Dropdown)
 * Phụ trách: Hoàng Nam
 */
(function () {
    'use strict';

    // TODO (Hoàng Nam):
    // 1. Render icon chuông thông báo trên Header của tất cả các trang:
    //    <div class="notif-wrapper">
    //       <button id="btnNotifBell" class="notif-bell"><i class="fa-solid fa-bell"></i><span class="notif-badge">0</span></button>
    //       <div id="notifDropdown" class="notif-dropdown">...</div>
    //    </div>
    // 2. Tải danh sách thông báo từ /api/auth/me.php hoặc /api/notification/check_daily.php.
    // 3. Tải danh sách lời mời quỹ chung từ /api/notification/invitations.php.
    // 4. Bổ sung nút [Đồng ý] / [Từ chối] trên mỗi thẻ lời mời quỹ chung.
    // 5. Đánh dấu đã đọc qua /api/notification/read.php.

    window.NotificationCenter = {
        fetchCount: async function () {
            // Lấy số lượng thông báo chưa đọc
        }
    };
})();
