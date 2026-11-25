/**
 * CashManager - Progressive Web App (PWA) & Web Push Reminder
 * Phụ trách: Ngô Bảo (Frontend) - Nhóm 3
 * Chức năng:
 *   1. Đăng ký Service Worker (sw.js)
 *   2. Banner thông minh gợi ý cài đặt PWA (Android / Chrome / iOS Safari)
 *   3. Tích hợp Web Notifications API & Nhắc nhở ghi chép chi tiêu buổi tối
 */

(function () {
    'use strict';

    // Xác định đường dẫn gốc của ứng dụng
    const isPagesDir = window.location.pathname.includes('/pages/');
    const swPath = isPagesDir ? '../sw.js' : './sw.js';
    const appBasePath = isPagesDir ? '../' : './';

    // =========================================================================
    // 1. ĐĂNG KÝ SERVICE WORKER
    // =========================================================================
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register(swPath)
                .then((registration) => {
                    console.log('[PWA] Service Worker đã đăng ký thành công với scope:', registration.scope);
                })
                .catch((err) => {
                    console.warn('[PWA] Đăng ký Service Worker thất bại (hoặc sw.js chưa có nội dung):', err);
                });
        });
    }

    // =========================================================================
    // 2. BANNER THÔNG MINH GỢI Ý CÀI ĐẶT PWA (SMART INSTALL BANNER)
    // =========================================================================
    let deferredPrompt = null;

    // Kiểm tra xem ứng dụng đã chạy dưới dạng Standalone PWA hay chưa
    function isRunningStandalone() {
        return (window.matchMedia('(display-mode: standalone)').matches) ||
               (window.navigator.standalone === true) ||
               document.referrer.includes('android-app://');
    }

    // Kiểm tra thiết bị iOS Safari
    function isIosSafari() {
        const ua = window.navigator.userAgent;
        const isIos = /iPhone|iPad|iPod/i.test(ua);
        const isWebkit = /WebKit/i.test(ua);
        const isOtherBrowser = /CriOS|FxiOS|OPiOS|mercury/i.test(ua);
        return isIos && isWebkit && !isOtherBrowser;
    }

    // Tạo Banner cài đặt trên giao diện
    function createInstallBanner(isIos = false) {
        if (isRunningStandalone()) return; // Đã cài đặt rồi thì không hiện

        // Đã tắt banner gần đây (trong 24 giờ qua) thì không làm phiền
        const dismissedTime = localStorage.getItem('pwa_install_dismissed');
        if (dismissedTime && (Date.now() - Number(dismissedTime) < 24 * 60 * 60 * 1000)) {
            return;
        }

        // Tránh tạo trùng banner
        if (document.getElementById('pwa-install-banner')) return;

        const banner = document.createElement('div');
        banner.id = 'pwa-install-banner';
        banner.className = 'pwa-install-banner';
        banner.setAttribute('role', 'dialog');
        banner.setAttribute('aria-label', 'Cài đặt ứng dụng CashManager');

        if (isIos) {
            // Hướng dẫn cài đặt riêng cho iOS Safari
            banner.innerHTML = `
                <div class="pwa-banner-content">
                    <div class="pwa-banner-icon">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                    <div class="pwa-banner-text">
                        <strong>Cài đặt CashManager</strong>
                        <p>Bấm nút <strong>Chia sẻ</strong> <i class="fa-solid fa-arrow-up-from-bracket"></i> rồi chọn <strong>"Thêm vào MH chính"</strong> <i class="fa-regular fa-square-plus"></i> để dùng như App.</p>
                    </div>
                    <button type="button" class="pwa-banner-close" id="btnDismissPwa" aria-label="Đóng">&times;</button>
                </div>
            `;
        } else {
            // Banner cài đặt cho Android / Chrome / Edge / Cốc Cốc
            banner.innerHTML = `
                <div class="pwa-banner-content">
                    <div class="pwa-banner-icon">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                    <div class="pwa-banner-text">
                        <strong>Cài đặt CashManager về máy</strong>
                        <p>Mở nhanh từ màn hình chính, tải siêu mượt và không dính thanh địa chỉ.</p>
                    </div>
                    <div class="pwa-banner-actions">
                        <button type="button" class="btn-pwa-install" id="btnInstallPwa">
                            <i class="fa-solid fa-download"></i> Cài đặt ngay
                        </button>
                        <button type="button" class="pwa-banner-close" id="btnDismissPwa" aria-label="Đóng">&times;</button>
                    </div>
                </div>
            `;
        }

        document.body.appendChild(banner);

        // Sự kiện nút cài đặt (Android / Desktop Chrome)
        const btnInstall = document.getElementById('btnInstallPwa');
        if (btnInstall && deferredPrompt) {
            btnInstall.addEventListener('click', async () => {
                banner.remove();
                deferredPrompt.prompt();
                const choiceResult = await deferredPrompt.userChoice;
                if (choiceResult.outcome === 'accepted') {
                    console.log('[PWA] Người dùng đã chấp nhận cài đặt');
                } else {
                    console.log('[PWA] Người dùng đã từ chối cài đặt');
                }
                deferredPrompt = null;
            });
        }

        // Sự kiện nút đóng / bỏ qua
        const btnDismiss = document.getElementById('btnDismissPwa');
        if (btnDismiss) {
            btnDismiss.addEventListener('click', () => {
                banner.classList.add('dismissing');
                setTimeout(() => banner.remove(), 250);
                localStorage.setItem('pwa_install_dismissed', String(Date.now()));
            });
        }
    }

    // Bắt sự kiện beforeinstallprompt (Android / Chrome)
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        // Hiển thị banner sau 1.5 giây để người dùng không bị ngợp
        setTimeout(() => createInstallBanner(false), 1500);
    });

    // Kiểm tra iOS Safari để hiện gợi ý
    window.addEventListener('DOMContentLoaded', () => {
        if (isIosSafari() && !isRunningStandalone()) {
            setTimeout(() => createInstallBanner(true), 2500);
        }
    });

    window.addEventListener('appinstalled', () => {
        console.log('[PWA] CashManager đã được cài đặt thành công');
        const banner = document.getElementById('pwa-install-banner');
        if (banner) banner.remove();
        deferredPrompt = null;
    });

    // =========================================================================
    // 3. HỆ THỐNG THÔNG BÁO NHẮC NHỞ GHI CHÉP (WEB NOTIFICATIONS)
    // =========================================================================

    // Xin quyền gửi thông báo từ trình duyệt
    async function requestNotificationPermission() {
        if (!('Notification' in window)) {
            alert('Trình duyệt của bạn không hỗ trợ tính năng thông báo Notification API.');
            return 'unsupported';
        }

        if (Notification.permission === 'granted') {
            return 'granted';
        }

        if (Notification.permission !== 'denied') {
            const permission = await Notification.requestPermission();
            return permission;
        }

        return 'denied';
    }

    // Gửi thông báo nổi của hệ thống
    function sendPushNotification(title, options = {}) {
        if (!('Notification' in window) || Notification.permission !== 'granted') {
            return false;
        }

        const defaultOptions = {
            icon: `${appBasePath}assets/css/style.css`, // fallback icon
            badge: `${appBasePath}assets/css/style.css`,
            body: 'Hôm nay bạn có khoản chi tiêu nào chưa ghi lại không? Hãy cập nhật ngay nhé!',
            tag: 'cashmanager-daily-reminder',
            renotify: true,
            requireInteraction: false,
            ...options
        };

        try {
            const notif = new Notification(title, defaultOptions);
            notif.onclick = function () {
                window.focus();
                this.close();
                // Chuyển hướng tới trang ghi nhận chi tiêu nếu cần
                if (!window.location.pathname.includes('expense.html')) {
                    window.location.href = isPagesDir ? 'expense.html' : 'pages/expense.html';
                }
            };
            return true;
        } catch (err) {
            console.warn('[Notification] Không thể gửi thông báo trực tiếp qua Notification constructor, thử ServiceWorkerRegistration:', err);
            if ('serviceWorker' in navigator && navigator.serviceWorker.ready) {
                navigator.serviceWorker.ready.then(reg => {
                    reg.showNotification(title, defaultOptions);
                });
                return true;
            }
            return false;
        }
    }

    // Kiểm tra định kỳ giờ nhắc nhở trong ngày
    function checkDailyReminderSchedule() {
        const enabled = localStorage.getItem('reminder_enabled') === 'true';
        if (!enabled) return;
        if (!('Notification' in window) || Notification.permission !== 'granted') return;

        const reminderTime = localStorage.getItem('reminder_time') || '21:00';
        const [targetHour, targetMinute] = reminderTime.split(':').map(Number);

        const now = new Date();
        const currentHour = now.getHours();
        const currentMinute = now.getMinutes();

        const todayKey = `${now.getFullYear()}-${now.getMonth() + 1}-${now.getDate()}`;
        const lastNotified = localStorage.getItem('reminder_last_date');

        // Nếu đã qua hoặc đúng giờ hẹn và hôm nay chưa gửi thông báo
        const isTimePassed = (currentHour > targetHour) || (currentHour === targetHour && currentMinute >= targetMinute);
        if (isTimePassed && lastNotified !== todayKey) {
            sendPushNotification('Nhắc nhở chi tiêu - CashManager', {
                body: 'Hôm nay bạn có khoản chi tiêu nào chưa ghi lại không? Hãy cập nhật ngay nhé!',
                icon: 'https://cdn-icons-png.flaticon.com/512/2953/2953363.png'
            });
            localStorage.setItem('reminder_last_date', todayKey);
        }
    }

    // Khởi chạy kiểm tra nhắc nhở
    setInterval(checkDailyReminderSchedule, 60 * 1000); // Mỗi 1 phút kiểm tra 1 lần
    setTimeout(checkDailyReminderSchedule, 2000);        // Kiểm tra ngay sau khi mở trang

    // Đưa các hàm cần thiết ra global window.PWA
    window.PWA = {
        requestNotificationPermission,
        sendPushNotification,
        isRunningStandalone,
        checkDailyReminderSchedule
    };

})();
