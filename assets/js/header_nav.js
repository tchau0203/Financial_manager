/**
 * Header User Navigation Component (CashManager)
 * Phụ trách: Quản lý nút "+ Giao dịch" và Dropdown hồ sơ người dùng góc trên bên phải
 */
(function () {
    'use strict';

    // Áp dụng ngay lập tức cấu hình giao diện (Sáng/Tối) & mật độ danh sách toàn hệ thống
    try {
        const savedTheme = localStorage.getItem('app_theme') || 'auto';
        const isDark = savedTheme === 'dark' || (savedTheme === 'auto' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
        if (isDark) document.documentElement.classList.add('dark-theme');
        else document.documentElement.classList.remove('dark-theme');

        const savedDensity = localStorage.getItem('app_density') || 'low';
        document.documentElement.setAttribute('data-density', savedDensity);
        if (document.body) document.body.setAttribute('data-density', savedDensity);
    } catch (_) {}

    // Xác định đường dẫn tương đối tới các trang
    const isPagesDir = window.location.pathname.includes('/pages/');
    const basePath = isPagesDir ? './' : './pages/';
    const apiBasePath = isPagesDir ? '../api/' : './api/';
    const jsBasePath = isPagesDir ? '../assets/js/' : './assets/js/';

    // Tự động tải i18n và áp dụng ngôn ngữ toàn hệ thống
    if (!window.CashManagerI18n) {
        const s = document.createElement('script');
        s.src = `${jsBasePath}i18n.js?v=20261127`;
        s.onload = () => {
            const lang = localStorage.getItem('app_language') || 'vi';
            if (lang !== 'vi' && window.CashManagerI18n) {
                window.CashManagerI18n.applyLanguage(lang);
            }
        };
        document.head.appendChild(s);
    } else {
        const lang = localStorage.getItem('app_language') || 'vi';
        if (lang !== 'vi') {
            window.CashManagerI18n.applyLanguage(lang);
        }
    }

    // Tạo HTML cho Dropdown góc trên bên phải
    function buildHeaderNavHTML(userName = 'Thái Châu', userInitial = 'T', avatarUrl = '') {
        const avatarContent = avatarUrl
            ? `<img src="${avatarUrl}" alt="${userName}" onerror="this.onerror=null; this.parentElement.style.background='#4e342e'; this.parentElement.textContent='${userInitial}';">`
            : userInitial;
        const avatarBg = avatarUrl ? 'transparent' : '#4e342e';

        return `
            <div class="header-user-nav" id="headerUserNav">
                <button type="button" class="btn-quick-tx" id="btnHeaderAddTx" title="Thêm giao dịch mới">
                    <i class="fa-solid fa-plus"></i> <span class="tx-text">Giao dịch</span>
                </button>
                <div class="user-dropdown-wrapper" id="userDropdownWrapper">
                    <button type="button" class="user-profile-btn" id="userProfileBtn" aria-expanded="false" title="Tài khoản">
                        <span class="user-avatar-circle" id="headerAvatar" style="background:${avatarBg};">${avatarContent}</span>
                        <span class="user-profile-name" id="headerUserName">${userName}</span>
                        <i class="fa-solid fa-caret-down user-profile-caret"></i>
                    </button>
                    <div class="user-dropdown-menu" id="userDropdownMenu">
                        <a href="${basePath}account.html" class="user-dropdown-item" id="menuItemSettings">
                            <i class="fa-solid fa-sliders"></i>
                            <span>Cài đặt</span>
                        </a>
                        <a href="${basePath}help.html" target="_blank" class="user-dropdown-item" id="menuItemHelp">
                            <i class="fa-regular fa-circle-question"></i>
                            <span>Trợ giúp</span>
                        </a>
                        <a href="javascript:void(0)" class="user-dropdown-item item-logout" id="menuItemLogout">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i>
                            <span>Đăng xuất</span>
                        </a>
                    </div>
                </div>
            </div>
        `;
    }

    // Modal chọn nhanh giao dịch khi bấm "+ Giao dịch" ở các trang không có sẵn form
    function ensureQuickTxModal() {
        if (document.getElementById('modalQuickChoice')) return;
        const modalHtml = `
            <div id="modalQuickChoice" class="modal">
                <div class="modal-content" style="max-width: 440px;">
                    <div class="modal-header">
                        <h3 style="font-size:1.15rem; margin:0;"><i class="fa-solid fa-circle-plus" style="color:var(--primary);"></i> Thêm giao dịch mới</h3>
                        <button type="button" class="modal-close" id="btnCloseQuickChoice">&times;</button>
                    </div>
                    <div class="modal-body" style="text-align:center; padding:1.5rem 1.25rem;">
                        <p style="color:var(--text-muted); margin-bottom:1.25rem; font-size:0.95rem;">Bạn muốn thêm khoản chi tiêu hay khoản thu nhập?</p>
                        <div class="quick-tx-choice">
                            <a href="${basePath}expense.html?action=new" class="quick-tx-btn expense" id="btnChoiceExpense">
                                <i class="fa-solid fa-arrow-trend-down"></i>
                                <span>Thêm chi tiêu</span>
                            </a>
                            <a href="${basePath}income.html?action=new" class="quick-tx-btn income" id="btnChoiceIncome">
                                <i class="fa-solid fa-arrow-trend-up"></i>
                                <span>Thêm thu nhập</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        const modal = document.getElementById('modalQuickChoice');
        const closeBtn = document.getElementById('btnCloseQuickChoice');
        if (closeBtn) closeBtn.onclick = () => modal.classList.remove('show');
        modal.onclick = (e) => { if (e.target === modal) modal.classList.remove('show'); };
    }

    // Modal popup Nâng cấp Pro
    function showUpgradeModal() {
        let modal = document.getElementById('modalUpgradePro');
        if (!modal) {
            const html = `
                <div id="modalUpgradePro" class="modal">
                    <div class="modal-content" style="max-width: 520px; border-radius: 16px;">
                        <div class="modal-header" style="background: linear-gradient(135deg, #0284c7, #2563eb); color:#fff; border-bottom:none;">
                            <h3 style="color:#fff; margin:0; display:flex; align-items:center; gap:8px;">
                                <i class="fa-solid fa-crown" style="color:#fbbf24;"></i> Nâng cấp CashManager Pro
                            </h3>
                            <button type="button" class="modal-close" id="btnCloseUpgradePro" style="color:#fff;">&times;</button>
                        </div>
                        <div class="modal-body" style="padding: 1.5rem;">
                            <div style="text-align:center; margin-bottom:1.25rem;">
                                <div style="display:inline-block; padding: 6px 14px; background:#e0f2fe; color:#0284c7; border-radius:999px; font-weight:600; font-size:0.85rem;">
                                    Gói thành viên cao cấp
                                </div>
                                <h4 style="font-size:1.4rem; margin:0.75rem 0 0.25rem 0; color:#1e293b;">Mở khóa toàn bộ sức mạnh tài chính</h4>
                                <p style="color:#64748b; font-size:0.9rem;">Trải nghiệm các tính năng độc quyền giúp bạn tự động hóa dòng tiền</p>
                            </div>
                            <div style="display:flex; flex-direction:column; gap:0.75rem; font-size:0.92rem;">
                                <div style="display:flex; align-items:center; gap:10px;"><i class="fa-solid fa-circle-check" style="color:#10b981;"></i> <span><strong>Cố vấn tài chính AI không giới hạn</strong> với mô hình Gemini 1.5</span></div>
                                <div style="display:flex; align-items:center; gap:10px;"><i class="fa-solid fa-circle-check" style="color:#10b981;"></i> <span><strong>Quét hóa đơn OCR tự động</strong> điền chi tiêu từ ảnh chụp</span></div>
                                <div style="display:flex; align-items:center; gap:10px;"><i class="fa-solid fa-circle-check" style="color:#10b981;"></i> <span><strong>Bản đồ nhiệt chi tiêu chi tiết</strong> theo bán kính vị trí thực tế</span></div>
                                <div style="display:flex; align-items:center; gap:10px;"><i class="fa-solid fa-circle-check" style="color:#10b981;"></i> <span><strong>Quyết toán quỹ nhóm tự động qua VietQR</strong> sòng phẳng từng giao dịch</span></div>
                                <div style="display:flex; align-items:center; gap:10px;"><i class="fa-solid fa-circle-check" style="color:#10b981;"></i> <span><strong>Xuất dữ liệu Excel / PDF</strong> không giới hạn và sao lưu bảo mật đám mây</span></div>
                            </div>
                            <div style="background:#f8fafc; border-radius:12px; padding:1rem; margin-top:1.25rem; text-align:center; border:1px dashed #cbd5e1;">
                                <span style="color:#0284c7; font-weight:700; font-size:1.1rem;">Miễn phí trải nghiệm cho tài khoản sớm!</span>
                                <p style="color:#64748b; font-size:0.85rem; margin:4px 0 0 0;">Tài khoản của bạn đã được kích hoạt đầy đủ mọi tính năng thử nghiệm của CashManager.</p>
                            </div>
                        </div>
                        <div class="modal-footer" style="justify-content:center;">
                            <button type="button" class="btn btn-primary" id="btnOkUpgrade" style="padding:0.6rem 2rem; border-radius:999px;">Đã hiểu & Tiếp tục</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', html);
            modal = document.getElementById('modalUpgradePro');
            const closeBtn = document.getElementById('btnCloseUpgradePro');
            const okBtn = document.getElementById('btnOkUpgrade');
            if (closeBtn) closeBtn.onclick = () => modal.classList.remove('show');
            if (okBtn) okBtn.onclick = () => modal.classList.remove('show');
            modal.onclick = (e) => { if (e.target === modal) modal.classList.remove('show'); };
        }
        modal.classList.add('show');
    }

    // Modal popup Trợ giúp & Trung tâm Hướng dẫn toàn diện (Help Center)
    function showHelpModal() {
        let modal = document.getElementById('modalHelpGuide');
        if (!modal) {
            const html = `
                <div id="modalHelpGuide" class="modal">
                    <div class="modal-content help-modal-content">
                        <div class="modal-header help-modal-header">
                            <div>
                                <h3 style="margin:0; font-size:1.18rem; display:flex; align-items:center; gap:10px;">
                                    <i class="fa-solid fa-circle-question" style="color:#fbbf24;"></i> Trung tâm trợ giúp & Hướng dẫn CashManager
                                </h3>
                                <p style="margin:4px 0 0 0; color:rgba(255,255,255,0.85); font-size:0.85rem;">Tìm kiếm giải pháp nhanh và hướng dẫn sử dụng các tính năng tài chính</p>
                            </div>
                            <button type="button" class="modal-close" id="btnCloseHelpGuide" style="color:#fff; font-size:1.5rem; opacity:0.9;">&times;</button>
                        </div>
                        <div class="modal-body help-modal-body">
                            <!-- Thanh tìm kiếm câu hỏi tức thì -->
                            <div class="help-search-wrapper">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" id="helpSearchInput" class="help-search-input" placeholder="Tìm kiếm hướng dẫn (VD: giao dịch, vietqr, bản đồ, dark mode, sao lưu...)">
                            </div>

                            <!-- Nhãn phân loại chủ đề -->
                            <div class="help-category-chips" id="helpCategoryChips">
                                <span class="help-chip active" data-category="all">Tất cả chủ đề</span>
                                <span class="help-chip" data-category="tx"><i class="fa-solid fa-receipt"></i> Giao dịch</span>
                                <span class="help-chip" data-category="map"><i class="fa-solid fa-map-location-dot"></i> Bản đồ GPS</span>
                                <span class="help-chip" data-category="shared"><i class="fa-solid fa-users"></i> Quỹ VietQR</span>
                                <span class="help-chip" data-category="ai"><i class="fa-solid fa-robot"></i> Cố vấn AI</span>
                                <span class="help-chip" data-category="settings"><i class="fa-solid fa-sliders"></i> Cài đặt & Giao diện</span>
                                <span class="help-chip" data-category="pwa"><i class="fa-solid fa-mobile-screen"></i> Di động (PWA)</span>
                            </div>

                            <!-- Danh sách câu hỏi dạng Accordion -->
                            <div class="help-accordion-list" id="helpAccordionList">
                                <!-- 1. Giao dịch -->
                                <div class="help-item" data-categories="tx all">
                                    <div class="help-item-header">
                                        <span><i class="fa-solid fa-circle-plus" style="color:#10b981; margin-right:8px;"></i> Làm thế nào để thêm nhanh một khoản chi tiêu hoặc thu nhập?</span>
                                        <i class="fa-solid fa-chevron-down help-caret"></i>
                                    </div>
                                    <div class="help-item-content">
                                        Bạn có thể bấm trực tiếp nút <strong>"+ Giao dịch"</strong> màu xanh lá ở góc trên bên phải trên bất kỳ màn hình nào. Hệ thống sẽ mở hộp thoại cho phép bạn chọn nhanh giữa <strong>"Thêm chi tiêu"</strong> và <strong>"Thêm thu nhập"</strong> ngay tức thì mà không cần quay lại màn hình chính.
                                    </div>
                                </div>

                                <div class="help-item" data-categories="tx all">
                                    <div class="help-item-header">
                                        <span><i class="fa-solid fa-filter" style="color:#3b82f6; margin-right:8px;"></i> Cách lọc, tìm kiếm và sắp xếp các giao dịch tài chính?</span>
                                        <i class="fa-solid fa-chevron-down help-caret"></i>
                                    </div>
                                    <div class="help-item-content">
                                        Tại các trang <strong>Thu nhập</strong> và <strong>Chi tiêu</strong>, bạn có thể:
                                        <ul style="margin:6px 0 0 18px; padding:0;">
                                            <li>Gõ từ khóa vào ô tìm kiếm để lọc theo ghi chú hoặc tên giao dịch.</li>
                                            <li>Lọc theo danh mục chi tiêu (Ăn uống, Mua sắm, Di chuyển...).</li>
                                            <li>Sắp xếp danh sách theo ngày mới nhất hoặc số tiền tăng/giảm dần.</li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- 2. Bản đồ -->
                                <div class="help-item" data-categories="map all">
                                    <div class="help-item-header">
                                        <span><i class="fa-solid fa-location-dot" style="color:#ef4444; margin-right:8px;"></i> Cách lưu vị trí địa lý của địa điểm chi tiêu?</span>
                                        <i class="fa-solid fa-chevron-down help-caret"></i>
                                    </div>
                                    <div class="help-item-content">
                                        Khi thêm hoặc sửa một khoản chi tiêu:
                                        <ul style="margin:6px 0 0 18px; padding:0;">
                                            <li>Bấm nút <strong>"Lấy vị trí hiện tại"</strong>: Trình duyệt sẽ tự động lấy tọa độ GPS chính xác nơi bạn đang đứng.</li>
                                            <li>Hoặc nhập địa chỉ vào ô <strong>"Vị trí chi tiêu"</strong> rồi bấm <strong>"Tìm"</strong> để ghim vị trí quán cà phê, nhà hàng hoặc cửa hàng trên bản đồ.</li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="help-item" data-categories="map all">
                                    <div class="help-item-header">
                                        <span><i class="fa-solid fa-map" style="color:#6366f1; margin-right:8px;"></i> Xem lại các địa điểm đã chi tiêu trên bản đồ ở đâu?</span>
                                        <i class="fa-solid fa-chevron-down help-caret"></i>
                                    </div>
                                    <div class="help-item-content">
                                        Chọn mục <strong>"Bản đồ thu chi"</strong> trên thanh menu bên trái. Hệ thống sẽ hiển thị toàn bộ các điểm giao dịch trên bản đồ nhiệt tương tác Leaflet, giúp bạn phân tích thói quen tiêu dùng theo từng khu vực địa lý.
                                    </div>
                                </div>

                                <!-- 3. Quỹ nhóm & VietQR -->
                                <div class="help-item" data-categories="shared all">
                                    <div class="help-item-header">
                                        <span><i class="fa-solid fa-users-viewfinder" style="color:#f59e0b; margin-right:8px;"></i> Quỹ chi tiêu chung hoạt động như thế nào?</span>
                                        <i class="fa-solid fa-chevron-down help-caret"></i>
                                    </div>
                                    <div class="help-item-content">
                                        Tính năng <strong>Quỹ chung</strong> được thiết kế cho các chuyến du lịch, nhóm bạn hoặc gia đình:
                                        <ul style="margin:6px 0 0 18px; padding:0;">
                                            <li>Tạo nhóm và thêm các thành viên tham gia.</li>
                                            <li>Ghi lại các khoản chi tiêu chung (ai đã trả tiền và chia đều cho những ai).</li>
                                            <li>Hệ thống tự động tính toán tổng kết sòng phẳng: Ai cần trả cho ai bao nhiêu tiền một cách minh bạch nhất.</li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="help-item" data-categories="shared all">
                                    <div class="help-item-header">
                                        <span><i class="fa-solid fa-qrcode" style="color:#10b981; margin-right:8px;"></i> Quyết toán tiền nợ tự động qua mã VietQR ra sao?</span>
                                        <i class="fa-solid fa-chevron-down help-caret"></i>
                                    </div>
                                    <div class="help-item-content">
                                        Người nhận chỉ cần lưu số tài khoản ngân hàng trong mục <strong>Cài đặt</strong>. Sau đó trong nhóm Quỹ chung, bấm nút <strong>"Quyết toán"</strong>, CashManager sẽ tự động sinh mã VietQR chứa đúng số tài khoản, ngân hàng và chính xác số tiền cần chuyển để quét app ngân hàng thanh toán ngay lập tức.
                                    </div>
                                </div>

                                <!-- 4. Cố vấn AI -->
                                <div class="help-item" data-categories="ai all">
                                    <div class="help-item-header">
                                        <span><i class="fa-solid fa-wand-magic-sparkles" style="color:#8b5cf6; margin-right:8px;"></i> Trợ lý tài chính AI hỗ trợ những gì?</span>
                                        <i class="fa-solid fa-chevron-down help-caret"></i>
                                    </div>
                                    <div class="help-item-content">
                                        Trợ lý AI phân tích các số liệu thu chi thực tế của bạn để:
                                        <ul style="margin:6px 0 0 18px; padding:0;">
                                            <li>Cảnh báo các danh mục chi tiêu vượt định mức.</li>
                                            <li>Đưa ra lời khuyên cắt giảm chi tiêu theo mô hình tài chính 50/30/20.</li>
                                            <li>Giải đáp các thắc mắc về lập kế hoạch tiết kiệm tiền và tối ưu dòng tiền cá nhân.</li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- 5. Cài đặt & Giao diện -->
                                <div class="help-item" data-categories="settings all">
                                    <div class="help-item-header">
                                        <span><i class="fa-solid fa-moon" style="color:#475569; margin-right:8px;"></i> Cách chuyển đổi Dark Mode (chế độ Tối) và đổi ngôn ngữ?</span>
                                        <i class="fa-solid fa-chevron-down help-caret"></i>
                                    </div>
                                    <div class="help-item-content">
                                        Bấm vào menu người dùng ở góc trên bên phải $\rightarrow$ chọn <strong>Cài đặt</strong>:
                                        <ul style="margin:6px 0 0 18px; padding:0;">
                                            <li>Tại phần <strong>Ngôn ngữ của trang web</strong>: Hỗ trợ 6 ngôn ngữ thời gian thực (Tiếng Việt, English, 日本語, 한국어, 简体中文, Français).</li>
                                            <li>Tại phần <strong>Giao diện</strong>: Bạn có thể chọn giữa chế độ <em>Theo trình duyệt</em>, <em>Sáng</em> hoặc <em>Tối</em> và điều chỉnh <em>Mật độ danh sách ghi chép</em>.</li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- 6. Di động PWA -->
                                <div class="help-item" data-categories="pwa all">
                                    <div class="help-item-header">
                                        <span><i class="fa-solid fa-mobile-screen-button" style="color:#0284c7; margin-right:8px;"></i> Cách cài CashManager lên điện thoại không cần App Store?</span>
                                        <i class="fa-solid fa-chevron-down help-caret"></i>
                                    </div>
                                    <div class="help-item-content">
                                        CashManager được xây dựng theo chuẩn <strong>Progressive Web App (PWA)</strong>:
                                        <ul style="margin:6px 0 0 18px; padding:0;">
                                            <li><strong>Trên iPhone/iPad (Safari):</strong> Bấm nút <em>Chia sẻ (Share)</em> $\rightarrow$ chọn <em>"Thêm vào Màn hình chính" (Add to Home Screen)</em>.</li>
                                            <li><strong>Trên Android (Chrome/Cốc Cốc):</strong> Bấm menu 3 chấm (...) $\rightarrow$ chọn <em>"Cài đặt ứng dụng"</em> hoặc <em>"Thêm vào màn hình chính"</em>.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <!-- Khối hỗ trợ kỹ thuật -->
                            <div class="help-support-card">
                                <div style="display:flex; align-items:center; gap:12px;">
                                    <div style="width:42px; height:42px; border-radius:50%; background:#e0e7ff; color:#4f46e5; display:flex; align-items:center; justify-content:center; font-size:1.15rem; flex-shrink:0;">
                                        <i class="fa-solid fa-headset"></i>
                                    </div>
                                    <div>
                                        <strong style="display:block; font-size:13.5px; color:var(--text-main);">Chưa tìm thấy câu trả lời bạn cần?</strong>
                                        <span style="font-size:12.5px; color:var(--text-muted);">Đội ngũ hỗ trợ CashManager luôn sẵn sàng lắng nghe và giải đáp mọi thắc mắc.</span>
                                    </div>
                                </div>
                                <div style="display:flex; gap:8px;">
                                    <a href="mailto:support@cashmanager.vn" class="btn btn-primary" style="padding:7px 14px; font-size:13px; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                                        <i class="fa-solid fa-envelope"></i> Gửi email hỗ trợ
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer" style="padding:12px 24px;">
                            <button type="button" class="btn btn-light" id="btnOkHelp" style="padding:7px 20px; border-radius:8px;">Đóng</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', html);
            modal = document.getElementById('modalHelpGuide');

            // Gắn sự kiện đóng modal
            const closeBtn = document.getElementById('btnCloseHelpGuide');
            const okBtn = document.getElementById('btnOkHelp');
            if (closeBtn) closeBtn.onclick = () => modal.classList.remove('show');
            if (okBtn) okBtn.onclick = () => modal.classList.remove('show');
            modal.onclick = (e) => { if (e.target === modal) modal.classList.remove('show'); };

            // Gắn sự kiện Accordion đóng/mở
            const accordionHeaders = modal.querySelectorAll('.help-item-header');
            accordionHeaders.forEach(header => {
                header.addEventListener('click', () => {
                    const item = header.parentElement;
                    const isOpen = item.classList.contains('open');
                    modal.querySelectorAll('.help-item').forEach(it => it.classList.remove('open'));
                    if (!isOpen) item.classList.add('open');
                });
            });

            // Gắn sự kiện phân loại chủ đề (Category Chips)
            const chips = modal.querySelectorAll('.help-chip');
            chips.forEach(chip => {
                chip.addEventListener('click', () => {
                    chips.forEach(c => c.classList.remove('active'));
                    chip.classList.add('active');
                    const cat = chip.dataset.category;
                    const items = modal.querySelectorAll('.help-item');
                    items.forEach(it => {
                        const cats = (it.dataset.categories || '').split(' ');
                        const match = cat === 'all' || cats.includes(cat);
                        it.style.display = match ? 'block' : 'none';
                    });
                });
            });

            // Gắn sự kiện tìm kiếm câu hỏi tức thì
            const searchInput = document.getElementById('helpSearchInput');
            if (searchInput) {
                searchInput.addEventListener('input', () => {
                    const q = searchInput.value.toLowerCase().trim();
                    const items = modal.querySelectorAll('.help-item');
                    items.forEach(it => {
                        const text = it.textContent.toLowerCase();
                        if (!q || text.includes(q)) {
                            it.style.display = 'block';
                            if (q) it.classList.add('open');
                        } else {
                            it.style.display = 'none';
                        }
                    });
                });
            }
        }

        // Tự động dịch nội dung modal theo ngôn ngữ hiện tại
        if (window.CashManagerI18n && typeof window.CashManagerI18n.applyLanguage === 'function') {
            const lang = localStorage.getItem('app_language') || 'vi';
            if (lang !== 'vi') {
                window.CashManagerI18n.applyLanguage(lang);
            }
        }

        modal.classList.add('show');
    }

    // Modal popup Thử ứng dụng di động (PWA)
    function showPwaModal() {
        if (window.CashManagerPWA && typeof window.CashManagerPWA.promptInstall === 'function') {
            window.CashManagerPWA.promptInstall();
            return;
        }
        let modal = document.getElementById('modalPwaGuide');
        if (!modal) {
            const html = `
                <div id="modalPwaGuide" class="modal">
                    <div class="modal-content" style="max-width: 480px; border-radius:16px;">
                        <div class="modal-header">
                            <h3 style="margin:0; display:flex; align-items:center; gap:8px;">
                                <i class="fa-solid fa-mobile-screen-button" style="color:var(--primary);"></i> Thử ứng dụng di động CashManager
                            </h3>
                            <button type="button" class="modal-close" id="btnClosePwaGuide">&times;</button>
                        </div>
                        <div class="modal-body" style="font-size:0.92rem;">
                            <p style="color:var(--text-muted); margin-bottom:1rem;">CashManager hỗ trợ cài đặt trực tiếp dưới dạng ứng dụng PWA trên điện thoại mà không cần vào App Store hay Google Play:</p>
                            <div style="display:flex; flex-direction:column; gap:0.75rem;">
                                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:0.85rem;">
                                    <strong>📱 Trên iOS (iPhone / Safari):</strong>
                                    <div style="font-size:0.85rem; color:#64748b; margin-top:4px;">
                                        Mở Safari $\rightarrow$ bấm biểu tượng <strong>Chia sẻ (Share)</strong> $\rightarrow$ chọn <strong>"Thêm vào Màn hình chính" (Add to Home Screen)</strong>.
                                    </div>
                                </div>
                                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:0.85rem;">
                                    <strong>🤖 Trên Android (Chrome / Cốc Cốc):</strong>
                                    <div style="font-size:0.85rem; color:#64748b; margin-top:4px;">
                                        Mở trình duyệt $\rightarrow$ bấm menu <strong>Ba chấm (...)</strong> $\rightarrow$ chọn <strong>"Cài đặt ứng dụng" (Install App)</strong> hoặc <strong>"Thêm vào Màn hình chính"</strong>.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" id="btnOkPwa">Đã hiểu</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', html);
            modal = document.getElementById('modalPwaGuide');
            const closeBtn = document.getElementById('btnClosePwaGuide');
            const okBtn = document.getElementById('btnOkPwa');
            if (closeBtn) closeBtn.onclick = () => modal.classList.remove('show');
            if (okBtn) okBtn.onclick = () => modal.classList.remove('show');
            modal.onclick = (e) => { if (e.target === modal) modal.classList.remove('show'); };
        }
        modal.classList.add('show');
    }

    // Đăng xuất tập trung
    async function handleLogout() {
        if (!confirm('Bạn có chắc chắn muốn đăng xuất khỏi CashManager?')) return;
        try {
            await fetch(`${apiBasePath}auth/logout.php`, { credentials: 'include' });
        } catch (e) {
            console.warn('Lỗi khi gọi logout API:', e);
        }
        sessionStorage.clear();
        localStorage.removeItem('user');
        window.location.href = `${basePath}login.html`;
    }

    // Tính chữ cái đầu của tên hiển thị (Ví dụ "Thái Châu" -> "T")
    function getInitialLetter(name) {
        if (!name) return 'T';
        const trimmed = name.trim();
        return trimmed.charAt(0).toUpperCase();
    }

    // Gắn sự kiện cho các thành phần của Header
    function bindHeaderEvents() {
        const wrapper = document.getElementById('userDropdownWrapper');
        const trigger = document.getElementById('userProfileBtn');
        const btnAddTx = document.getElementById('btnHeaderAddTx');

        if (trigger && wrapper && !trigger.dataset.headerBound) {
            trigger.dataset.headerBound = '1';
            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                const isOpen = wrapper.classList.toggle('active');
                trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });

            // Đóng menu khi bấm ra ngoài
            document.addEventListener('click', (e) => {
                if (!wrapper.contains(e.target)) {
                    wrapper.classList.remove('active');
                    trigger.setAttribute('aria-expanded', 'false');
                }
            });

            // Đóng menu khi bấm Escape
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && wrapper.classList.contains('active')) {
                    wrapper.classList.remove('active');
                    trigger.setAttribute('aria-expanded', 'false');
                }
            });
        }

        // Xử lý nút "+ Giao dịch"
        if (btnAddTx && !btnAddTx.dataset.headerBound) {
            btnAddTx.dataset.headerBound = '1';
            btnAddTx.addEventListener('click', (e) => {
                e.preventDefault();
                // 1. Nếu đang ở trang Chi tiêu -> mở form thêm chi tiêu
                const btnAddExpense = document.getElementById('btnAddExpense');
                if (btnAddExpense) {
                    btnAddExpense.click();
                    return;
                }
                // 2. Nếu đang ở trang Thu nhập -> mở form thêm thu nhập
                const btnAddIncome = document.getElementById('btnAddIncome');
                if (btnAddIncome) {
                    btnAddIncome.click();
                    return;
                }
                // 3. Nếu đang ở trang Dashboard -> mở form Thêm nhanh
                const btnQuickAdd = document.getElementById('btnQuickAdd');
                if (btnQuickAdd) {
                    btnQuickAdd.click();
                    return;
                }
                // 4. Nếu ở trang khác -> mở modal chọn loại giao dịch
                ensureQuickTxModal();
                const choiceModal = document.getElementById('modalQuickChoice');
                if (choiceModal) choiceModal.classList.add('show');
            });
        }

        // Gắn sự kiện cho các mục trong menu
        const itemSettings = document.getElementById('menuItemSettings');
        if (itemSettings && !itemSettings.dataset.headerBound) {
            itemSettings.dataset.headerBound = '1';
            itemSettings.addEventListener('click', (e) => {
                if (wrapper) wrapper.classList.remove('active');
            });
        }

        const itemHelp = document.getElementById('menuItemHelp');
        if (itemHelp && !itemHelp.dataset.headerBound) {
            itemHelp.dataset.headerBound = '1';
            itemHelp.addEventListener('click', (e) => {
                if (wrapper) wrapper.classList.remove('active');
                // Mở trang Trợ giúp sang thẻ mới (New Tab) không đè lên giao diện
            });
        }

        const itemLogout = document.getElementById('menuItemLogout');
        if (itemLogout && !itemLogout.dataset.headerBound) {
            itemLogout.dataset.headerBound = '1';
            itemLogout.addEventListener('click', (e) => {
                e.preventDefault();
                handleLogout();
            });
        }
    }

    // Cập nhật tên và avatar trên Header
    function updateHeaderUser(userData) {
        if (!userData) return;
        const name = userData.full_name || userData.name || userData.username || 'Thái Châu';
        const initial = getInitialLetter(name);
        const avatarUrl = userData.avatar_url || userData.picture || userData.avatar || '';

        const nameEl = document.getElementById('headerUserName');
        const avatarEl = document.getElementById('headerAvatar');

        if (nameEl) nameEl.textContent = name;
        if (avatarEl) {
            if (avatarUrl) {
                avatarEl.style.background = 'transparent';
                avatarEl.innerHTML = `<img src="${avatarUrl}" alt="${name}" onerror="this.onerror=null; this.parentElement.style.background='#4e342e'; this.parentElement.textContent='${initial}';">`;
            } else {
                avatarEl.style.background = '#4e342e';
                avatarEl.textContent = initial;
            }
        }

        // Tương thích với các id cũ
        const oldUserEl = document.getElementById('header-username') || document.getElementById('userName');
        if (oldUserEl) oldUserEl.textContent = name;
    }

    // Tự động tải thông tin người dùng từ máy chủ nếu chưa có
    async function fetchCurrentUser() {
        try {
            const res = await fetch(`${apiBasePath}auth/me.php`, { credentials: 'include' });
            if (res.ok) {
                const json = await res.json();
                const u = json.user || json.data;
                if (u) updateHeaderUser(u);
            }
        } catch (e) {
            console.warn('Lỗi khi tải thông tin người dùng:', e);
        }
    }

    // Nâng cấp khối header hiện tại trên trang
    function upgradeHeader() {
        let container = document.getElementById('headerUserNav');
        if (container) {
            bindHeaderEvents();
            return;
        }

        // Tìm kiếm vị trí header bên phải hiện có
        const header = document.querySelector('.header') || document.querySelector('.topbar');
        if (!header) return;

        // Tìm vùng bên phải của header
        let rightZone = header.querySelector('.right') ||
                        header.querySelector('div[style*="align-items: center"]') ||
                        header.lastElementChild;

        if (rightZone && rightZone !== header.firstElementChild) {
            const currentNameEl = rightZone.querySelector('#header-username, #userName');
            const currentName = (currentNameEl && currentNameEl.textContent && currentNameEl.textContent !== 'Đang tải...')
                ? currentNameEl.textContent
                : 'Thái Châu';

            rightZone.innerHTML = buildHeaderNavHTML(currentName, getInitialLetter(currentName));
            bindHeaderEvents();
        }
    }

    // Thiết lập Drawer Sidebar trượt & Thanh điều hướng dưới đáy (Mobile Bottom Navigation Bar)
    function setupMobileDrawerAndBottomNav() {
        // 1. Nút Hamburger góc trái Header
        const header = document.querySelector('.header') || document.querySelector('.topbar');
        let toggleBtn = header ? header.querySelector('#btnSidebarToggle, #btnMenu, .menu-toggle') : null;
        if (header && !toggleBtn) {
            toggleBtn = document.createElement('button');
            toggleBtn.type = 'button';
            toggleBtn.id = 'btnSidebarToggle';
            toggleBtn.className = 'btn-sidebar-toggle';
            toggleBtn.setAttribute('aria-label', 'Mở menu');
            toggleBtn.innerHTML = '<i class="fa-solid fa-bars"></i>';
            header.insertBefore(toggleBtn, header.firstElementChild);
        }

        // 2. Nút đóng X trong Sidebar Brand
        const sidebarBrand = document.querySelector('.sidebar .sidebar-brand');
        let closeBtn = document.getElementById('btnSidebarClose');
        if (sidebarBrand && !closeBtn) {
            closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.id = 'btnSidebarClose';
            closeBtn.className = 'btn-sidebar-close';
            closeBtn.setAttribute('aria-label', 'Đóng menu');
            closeBtn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
            sidebarBrand.appendChild(closeBtn);
        }

        // 3. Lớp phủ mờ Backdrop khi mở menu di động
        let backdrop = document.getElementById('sidebarBackdrop');
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.id = 'sidebarBackdrop';
            backdrop.className = 'sidebar-backdrop';
            document.body.appendChild(backdrop);
        }

        // 4. Thanh điều hướng dưới đáy (Mobile Bottom Navigation Bar)
        if (!document.getElementById('mobileBottomNav')) {
            const currentPath = window.location.pathname.toLowerCase();
            const isDashboard = currentPath.includes('dashboard') || currentPath.endsWith('/') || currentPath.endsWith('index.html');
            const isExpense = currentPath.includes('expense') || currentPath.includes('income');
            const isFeed = currentPath.includes('feed');
            const isReport = currentPath.includes('report');

            const bottomNav = document.createElement('nav');
            bottomNav.id = 'mobileBottomNav';
            bottomNav.className = 'mobile-bottom-nav';
            bottomNav.innerHTML = `
                <a href="${basePath}dashboard.html" class="mb-nav-item ${isDashboard ? 'active' : ''}">
                    <i class="fa-solid fa-house"></i>
                    <span>Tổng quan</span>
                </a>
                <a href="${basePath}expense.html" class="mb-nav-item ${isExpense ? 'active' : ''}">
                    <i class="fa-solid fa-arrow-trend-down"></i>
                    <span>Chi tiêu</span>
                </a>
                <a href="${basePath}feed.html" class="mb-nav-item ${isFeed ? 'active' : ''}">
                    <i class="fa-solid fa-camera-retro"></i>
                    <span>Bảng tin</span>
                </a>
                <a href="${basePath}report.html" class="mb-nav-item ${isReport ? 'active' : ''}">
                    <i class="fa-solid fa-chart-pie"></i>
                    <span>Báo cáo</span>
                </a>
                <button type="button" class="mb-nav-item" id="btnBottomNavMenu">
                    <i class="fa-solid fa-bars"></i>
                    <span>Menu</span>
                </button>
            `;
            document.body.appendChild(bottomNav);
        }

        // 5. Gắn sự kiện mở & đóng Sidebar Drawer mượt mà
        const sidebar = document.querySelector('.sidebar');
        const bottomNavMenu = document.getElementById('btnBottomNavMenu');

        function openSidebar(e) {
            if (e) e.preventDefault();
            if (sidebar) sidebar.classList.add('open');
            if (backdrop) backdrop.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar(e) {
            if (e && e.preventDefault) e.preventDefault();
            if (sidebar) sidebar.classList.remove('open');
            if (backdrop) backdrop.classList.remove('show');
            document.body.style.overflow = '';
        }

        const allToggleBtns = document.querySelectorAll('#btnSidebarToggle, #btnMenu, .menu-toggle, #btnBottomNavMenu');
        allToggleBtns.forEach(btn => {
            if (!btn.dataset.drawerBound) {
                btn.dataset.drawerBound = '1';
                btn.addEventListener('click', openSidebar);
            }
        });

        if (closeBtn && !closeBtn.dataset.bound) {
            closeBtn.dataset.bound = '1';
            closeBtn.addEventListener('click', closeSidebar);
        }
        if (backdrop && !backdrop.dataset.bound) {
            backdrop.dataset.bound = '1';
            backdrop.addEventListener('click', closeSidebar);
        }

        // Đóng khi click vào bất kỳ link nào trong sidebar
        if (sidebar && !sidebar.dataset.linksBound) {
            sidebar.dataset.linksBound = '1';
            sidebar.querySelectorAll('a').forEach(a => {
                a.addEventListener('click', () => {
                    closeSidebar();
                });
            });
        }
    }

        // 2. Nút đóng X trong Sidebar Brand
        const sidebarBrand = document.querySelector('.sidebar .sidebar-brand');
        if (sidebarBrand && !document.getElementById('btnSidebarClose')) {
            const closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.id = 'btnSidebarClose';
            closeBtn.className = 'btn-sidebar-close';
            closeBtn.setAttribute('aria-label', 'Đóng menu');
            closeBtn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
            sidebarBrand.appendChild(closeBtn);
        }

        // 3. Lớp phủ mờ Backdrop khi mở menu di động
        let backdrop = document.getElementById('sidebarBackdrop');
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.id = 'sidebarBackdrop';
            backdrop.className = 'sidebar-backdrop';
            document.body.appendChild(backdrop);
        }

        // 4. Thanh điều hướng dưới đáy (Mobile Bottom Navigation Bar)
        if (!document.getElementById('mobileBottomNav')) {
            const currentPath = window.location.pathname.toLowerCase();
            const isDashboard = currentPath.includes('dashboard') || currentPath.endsWith('/') || currentPath.endsWith('index.html');
            const isExpense = currentPath.includes('expense') || currentPath.includes('income');
            const isFeed = currentPath.includes('feed');
            const isReport = currentPath.includes('report');

            const bottomNav = document.createElement('nav');
            bottomNav.id = 'mobileBottomNav';
            bottomNav.className = 'mobile-bottom-nav';
            bottomNav.innerHTML = `
                <a href="${basePath}dashboard.html" class="mb-nav-item ${isDashboard ? 'active' : ''}">
                    <i class="fa-solid fa-house"></i>
                    <span>Tổng quan</span>
                </a>
                <a href="${basePath}expense.html" class="mb-nav-item ${isExpense ? 'active' : ''}">
                    <i class="fa-solid fa-arrow-trend-down"></i>
                    <span>Chi tiêu</span>
                </a>
                <a href="${basePath}feed.html" class="mb-nav-item ${isFeed ? 'active' : ''}">
                    <i class="fa-solid fa-camera-retro"></i>
                    <span>Bảng tin</span>
                </a>
                <a href="${basePath}report.html" class="mb-nav-item ${isReport ? 'active' : ''}">
                    <i class="fa-solid fa-chart-pie"></i>
                    <span>Báo cáo</span>
                </a>
                <button type="button" class="mb-nav-item" id="btnBottomNavMenu">
                    <i class="fa-solid fa-bars"></i>
                    <span>Menu</span>
                </button>
            `;
            document.body.appendChild(bottomNav);
        }

        // 5. Gắn sự kiện mở & đóng Sidebar Drawer mượt mà
        const sidebar = document.querySelector('.sidebar');
        const toggleBtn = document.getElementById('btnSidebarToggle');
        const closeBtn = document.getElementById('btnSidebarClose');
        const bottomNavMenu = document.getElementById('btnBottomNavMenu');

        function openSidebar(e) {
            if (e) e.preventDefault();
            if (sidebar) sidebar.classList.add('open');
            if (backdrop) backdrop.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar(e) {
            if (e && e.preventDefault) e.preventDefault();
            if (sidebar) sidebar.classList.remove('open');
            if (backdrop) backdrop.classList.remove('show');
            document.body.style.overflow = '';
        }

        if (toggleBtn && !toggleBtn.dataset.bound) {
            toggleBtn.dataset.bound = '1';
            toggleBtn.addEventListener('click', openSidebar);
        }
        if (bottomNavMenu && !bottomNavMenu.dataset.bound) {
            bottomNavMenu.dataset.bound = '1';
            bottomNavMenu.addEventListener('click', openSidebar);
        }
        if (closeBtn && !closeBtn.dataset.bound) {
            closeBtn.dataset.bound = '1';
            closeBtn.addEventListener('click', closeSidebar);
        }
        if (backdrop && !backdrop.dataset.bound) {
            backdrop.dataset.bound = '1';
            backdrop.addEventListener('click', closeSidebar);
        }

        // Đóng khi click vào bất kỳ link nào trong sidebar
        if (sidebar && !sidebar.dataset.linksBound) {
            sidebar.dataset.linksBound = '1';
            sidebar.querySelectorAll('a').forEach(a => {
                a.addEventListener('click', () => {
                    closeSidebar();
                });
            });
        }
    }

    // Khởi chạy khi DOM sẵn sàng
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            upgradeHeader();
            setupMobileDrawerAndBottomNav();
            fetchCurrentUser();
        });
    } else {
        upgradeHeader();
        setupMobileDrawerAndBottomNav();
        fetchCurrentUser();
    }

    // Xuất API toàn cục để các trang có thể nạp dữ liệu người dùng
    window.HeaderNav = {
        upgrade: upgradeHeader,
        updateUser: updateHeaderUser,
        showUpgrade: showUpgradeModal,
        showHelp: showHelpModal,
        showPwa: showPwaModal,
        logout: handleLogout
    };
})();
