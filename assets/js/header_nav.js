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
                    <i class="fa-solid fa-plus"></i> Giao dịch
                </button>
                <div class="user-dropdown-wrapper" id="userDropdownWrapper">
                    <button type="button" class="user-profile-btn" id="userProfileBtn" aria-expanded="false" title="Tài khoản">
                        <span class="user-avatar-circle" id="headerAvatar" style="background:${avatarBg};">${avatarContent}</span>
                        <span class="user-profile-name" id="headerUserName">${userName}</span>
                        <i class="fa-solid fa-caret-down user-profile-caret"></i>
                    </button>
                    <div class="user-dropdown-menu" id="userDropdownMenu">
                        <a href="${basePath}account.html?tab=account" class="user-dropdown-item" id="menuItemAccount">
                            <i class="fa-solid fa-user"></i>
                            <span>Tài khoản</span>
                        </a>
                        <a href="${basePath}account.html?tab=settings" class="user-dropdown-item" id="menuItemSettings">
                            <i class="fa-solid fa-sliders"></i>
                            <span>Cài đặt</span>
                        </a>
                        <a href="javascript:void(0)" class="user-dropdown-item" id="menuItemHelp">
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

    // Modal popup Trợ giúp
    function showHelpModal() {
        let modal = document.getElementById('modalHelpGuide');
        if (!modal) {
            const html = `
                <div id="modalHelpGuide" class="modal">
                    <div class="modal-content" style="max-width: 520px; border-radius:16px;">
                        <div class="modal-header">
                            <h3 style="margin:0; display:flex; align-items:center; gap:8px;">
                                <i class="fa-solid fa-circle-question" style="color:var(--primary);"></i> Trung tâm trợ giúp CashManager
                            </h3>
                            <button type="button" class="modal-close" id="btnCloseHelpGuide">&times;</button>
                        </div>
                        <div class="modal-body" style="font-size:0.92rem; line-height:1.6;">
                            <div style="margin-bottom:1rem;">
                                <strong style="color:var(--text-main);">📌 Các tính năng chính:</strong>
                                <ul style="margin:0.4rem 0 0 1.25rem; padding:0; color:var(--text-muted);">
                                    <li><strong>Quản lý chi tiêu & thu nhập:</strong> Thêm, sửa, xóa, tìm kiếm, sắp xếp và lọc theo khoảng giá.</li>
                                    <li><strong>Bản đồ chi tiêu:</strong> Bấm nút định vị hoặc gõ địa chỉ để ghim vị trí giao dịch trên bản đồ Leaflet.</li>
                                    <li><strong>Quỹ chung:</strong> Tạo nhóm quản lý chi tiêu chung, chia đều hóa đơn và tạo mã VietQR chuyển khoản.</li>
                                    <li><strong>Cố vấn AI:</strong> Hỏi đáp tài chính trực tiếp với AI được cá nhân hóa theo ví tiền của bạn.</li>
                                </ul>
                            </div>
                            <div style="background:#f1f5f9; padding:0.85rem; border-radius:8px; font-size:0.85rem;">
                                💡 <strong>Mẹo thao tác nhanh:</strong> Bạn có thể nhấn nút <strong>"+ Giao dịch"</strong> màu xanh lá ở góc trên bên phải ở bất kỳ trang nào để thêm khoản thu/chi nhanh chóng!
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" id="btnOkHelp">Đóng</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', html);
            modal = document.getElementById('modalHelpGuide');
            const closeBtn = document.getElementById('btnCloseHelpGuide');
            const okBtn = document.getElementById('btnOkHelp');
            if (closeBtn) closeBtn.onclick = () => modal.classList.remove('show');
            if (okBtn) okBtn.onclick = () => modal.classList.remove('show');
            modal.onclick = (e) => { if (e.target === modal) modal.classList.remove('show'); };
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
        const itemAccount = document.getElementById('menuItemAccount');
        if (itemAccount && !itemAccount.dataset.headerBound) {
            itemAccount.dataset.headerBound = '1';
            itemAccount.addEventListener('click', (e) => {
                if (wrapper) wrapper.classList.remove('active');
                if (window.location.pathname.includes('account.html')) {
                    e.preventDefault();
                    const tabBtn = document.querySelector('.tabs button[data-tab="account"]');
                    if (tabBtn) tabBtn.click();
                    history.replaceState(null, '', 'account.html?tab=account');
                }
            });
        }

        const itemSettings = document.getElementById('menuItemSettings');
        if (itemSettings && !itemSettings.dataset.headerBound) {
            itemSettings.dataset.headerBound = '1';
            itemSettings.addEventListener('click', (e) => {
                if (wrapper) wrapper.classList.remove('active');
                if (window.location.pathname.includes('account.html')) {
                    e.preventDefault();
                    const tabBtn = document.querySelector('.tabs button[data-tab="settings"]');
                    if (tabBtn) tabBtn.click();
                    history.replaceState(null, '', 'account.html?tab=settings');
                }
            });
        }

        const itemHelp = document.getElementById('menuItemHelp');
        if (itemHelp && !itemHelp.dataset.headerBound) {
            itemHelp.dataset.headerBound = '1';
            itemHelp.addEventListener('click', (e) => {
                e.preventDefault();
                if (wrapper) wrapper.classList.remove('active');
                showHelpModal();
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

    // Khởi chạy khi DOM sẵn sàng
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            upgradeHeader();
            fetchCurrentUser();
        });
    } else {
        upgradeHeader();
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
