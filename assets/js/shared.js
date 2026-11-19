/**
 * Logic Quản lý Quỹ Chung & Chia Sẻ Tài Chính
 * Phụ trách: Trần Gia Huy (Frontend)
 */

document.addEventListener('DOMContentLoaded', async () => {
    const user = await checkAuth();
    if (!user) return;

    const displayName = document.getElementById('user-display-name');
    if (displayName) {
        displayName.textContent = user.full_name || user.username;
    }

    // TODO (Gia Huy):
    // 1. Gọi apiFetch('/shared/list.php') để lấy các quỹ chung
    // 2. Render các card không gian quỹ nhóm
    // 3. Xử lý mở modal tạo quỹ mới và mời thành viên bằng email
    console.log('[Shared Module] Trang quỹ chung đã sẵn sàng');
});
