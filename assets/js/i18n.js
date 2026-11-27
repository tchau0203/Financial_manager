/**
 * CashManager Multi-Language Engine (i18n)
 * Hỗ trợ chuyển đổi song ngữ Tiếng Việt (vi) & Tiếng Anh (en)
 */
(function () {
    'use strict';

    const DICTIONARY = {
        // --- Sidebar Menu ---
        "Bảng điều khiển": "Dashboard",
        "Thu nhập": "Income",
        "Chi tiêu": "Expense",
        "Ngân sách": "Budget",
        "Báo cáo": "Reports",
        "Bản đồ thu chi": "Finance Map",
        "Bản đồ chi tiêu": "Expense Map",
        "Quỹ chung": "Shared Funds",
        "Tài khoản": "Account",

        // --- Header & Dropdown ---
        "Giao dịch": "Transaction",
        "+ Giao dịch": "+ Transaction",
        "Thêm giao dịch mới": "Add new transaction",
        "Cài đặt": "Settings",
        "Trợ giúp": "Help",
        "Đăng xuất": "Logout",
        "Nâng cấp": "Upgrade",
        "Thử ứng dụng di động": "Mobile App",

        // --- Trang Cài đặt tài khoản (account.html) ---
        "Cài đặt tài khoản": "Account Settings",
        "Thông tin cá nhân": "Personal Information",
        "Họ và tên": "Full Name",
        "Lưu thay đổi": "Save Changes",
        "Đổi mật khẩu": "Change Password",
        "Mật khẩu hiện tại": "Current Password",
        "Mật khẩu mới": "New Password",
        "Nhập lại mật khẩu mới": "Confirm New Password",
        "Ảnh đại diện": "Profile Picture",
        "Hỗ trợ ảnh từ tài khoản Google hoặc tải lên ảnh đại diện tùy thích từ máy (JPG, PNG, WEBP tối đa 5MB).": "Supports Google account photo or upload custom avatar from your device (JPG, PNG, WEBP max 5MB).",
        "Tải ảnh từ máy": "Upload from device",
        "Nhập link ảnh": "Enter image URL",
        "Xóa ảnh": "Delete photo",
        "Ngôn ngữ của trang web": "Website Language",
        "Giao diện": "Appearance",
        "Chế độ sáng và tối": "Dark & Light Mode",
        "Theo trình duyệt": "System Default",
        "Sáng": "Light",
        "Tối": "Dark",
        "Mật độ danh sách ghi chép": "Record List Density",
        "Thấp - mặc định": "Low - default",
        "Thấp": "Low",
        "- mặc định": "- default",
        "Trung bình": "Medium",
        "Cao": "High",
        "Cài đặt Đa tiền tệ": "Multi-Currency Settings",
        "Đơn vị tiền tệ chính": "Base Currency",
        "Tỷ giá tham chiếu:": "Reference Exchange Rates:",
        "Lưu tiền tệ": "Save Currency",
        "Nhận email cảnh báo khi ngân sách đạt 80%": "Receive email alert when budget reaches 80%",
        "Gửi tới email đăng ký tài khoản khi ngân sách của một danh mục đạt 80% hoặc vượt hạn mức.": "Sent to your registered email when category budget reaches 80% or exceeds limit.",
        "Nhắc nhở ghi chép hàng ngày (Web Push)": "Daily Record Reminder (Web Push)",
        "Nhận thông báo nổi trên trình duyệt/điện thoại mỗi tối để nhắc nhở ghi chép chi tiêu trong ngày.": "Receive push notifications on browser/phone every evening to record your daily expenses.",
        "Giờ nhắc nhở mỗi tối:": "Evening reminder time:",
        "Trạng thái quyền thông báo:": "Notification permission status:",
        "Gửi thử thông báo": "Send Test Notification",
        "Lưu cài đặt": "Save Settings",
        "Thông tin tài khoản ngân hàng": "Bank Account Information",
        "Dùng để tạo mã VietQR khi thành viên khác chuyển tiền quyết toán cho bạn.": "Used to generate VietQR code when other members transfer payment settlements to you.",
        "Ngân hàng": "Bank",
        "Số tài khoản": "Account Number",
        "Lưu tài khoản ngân hàng": "Save Bank Account",
        "Danh mục của tôi": "My Categories",
        "Thêm danh mục": "Add Category",
        "Tên danh mục": "Category Name",
        "Loại": "Type",
        "Thao tác": "Actions",
        "Sao lưu & Dữ liệu": "Backup & Data",
        "Sao lưu dữ liệu": "Backup Data",
        "Tải toàn bộ dữ liệu của bạn (thông tin tài khoản, danh mục riêng, giao dịch, ngân sách) về máy dưới dạng file .json.": "Download all your data (account info, custom categories, transactions, budgets) as a .json file.",
        "Tải bản sao lưu (.json)": "Download Backup (.json)",
        "Khôi phục dữ liệu từ file": "Restore Data from File",
        "Kéo thả file sao lưu (.json) vào đây": "Drag and drop backup file (.json) here",
        "hoặc bấm để chọn file từ máy": "or click to choose file from device",
        "Giữ nguyên dữ liệu cũ": "Keep existing data",
        "Chỉ bổ sung dữ liệu có trong file mà hiện chưa có.": "Only import data from file that does not already exist.",
        "Ghi đè dữ liệu hiện tại": "Overwrite current data",
        "Thay toàn bộ dữ liệu hiện có bằng dữ liệu trong file.": "Replace all existing data with data from file.",
        "Khôi phục dữ liệu": "Restore Data",
        "Hủy": "Cancel",
        "Lưu danh mục": "Save Category",
        "Nhập liên kết ảnh đại diện": "Enter Profile Picture URL",
        "Đường dẫn ảnh (URL)": "Image URL",
        "Dán đường link ảnh đại diện (hỗ trợ JPG, PNG, WEBP, Google avatar link...)": "Paste avatar image link (supports JPG, PNG, WEBP, Google avatar link...)",
        "Lưu ảnh đại diện": "Save Profile Picture",

        // --- Trang Dashboard & Thống kê ---
        "Tổng quan tài chính": "Financial Overview",
        "TỔNG THU THÁNG NÀY": "TOTAL INCOME THIS MONTH",
        "TỔNG CHI THÁNG NÀY": "TOTAL EXPENSES THIS MONTH",
        "SỐ DƯ RÒNG": "NET BALANCE",
        "TỶ LỆ TIẾT KIỆM": "SAVINGS RATE",
        "Tiến độ ngân sách tháng": "Monthly Budget Progress",
        "Chưa thiết lập ngân sách": "No budget set",
        "Trợ lý tài chính AI": "AI Financial Assistant",
        "Hỏi chuyên gia AI": "Ask AI Expert",
        "Xu hướng thu chi 12 tháng": "12-Month Cashflow Trend",
        "Cơ cấu chi tiêu tháng này": "This Month Expense Breakdown",
        "Giao dịch gần đây": "Recent Transactions",
        "Xem tất cả": "View All",
        "Ngày": "Date",
        "Danh mục": "Category",
        "Số tiền": "Amount",
        "Ghi chú": "Note",
        "Chưa có giao dịch nào": "No transactions recorded",

        // --- Modal & Thông báo ---
        "Bạn muốn thêm khoản chi tiêu hay khoản thu nhập?": "Would you like to add an expense or an income?",
        "Thêm chi tiêu": "Add Expense",
        "Thêm thu nhập": "Add Income",
        "Trung tâm trợ giúp CashManager": "CashManager Help Center",
        "📌 Các tính năng chính:": "📌 Main Features:",
        "Đóng": "Close",
        "Đã hiểu": "Understood",
        "Bạn có chắc chắn muốn đăng xuất khỏi CashManager?": "Are you sure you want to log out of CashManager?"
    };

    // Tạo từ điển ngược để chuyển lại tiếng Việt
    const REVERSE_DICT = {};
    for (const [vi, en] of Object.entries(DICTIONARY)) {
        REVERSE_DICT[en] = vi;
    }

    function translateTextNode(node, targetLang) {
        if (!node || node.nodeType !== Node.TEXT_NODE) return;
        const text = node.nodeValue;
        if (!text || !text.trim()) return;

        const trimmed = text.trim();

        if (targetLang === 'en') {
            if (DICTIONARY[trimmed]) {
                if (typeof node._cmOrigText === 'undefined') {
                    node._cmOrigText = text;
                }
                node.nodeValue = text.replace(trimmed, DICTIONARY[trimmed]);
            }
        } else {
            // Chuyển lại tiếng Việt
            if (typeof node._cmOrigText !== 'undefined') {
                node.nodeValue = node._cmOrigText;
            } else if (REVERSE_DICT[trimmed]) {
                node.nodeValue = text.replace(trimmed, REVERSE_DICT[trimmed]);
            }
        }
    }

    function translatePlaceholders(targetLang) {
        document.querySelectorAll('input[placeholder], textarea[placeholder]').forEach(el => {
            const ph = el.getAttribute('placeholder');
            if (!ph) return;
            const trimmed = ph.trim();
            if (targetLang === 'en') {
                if (DICTIONARY[trimmed]) {
                    if (!el.dataset.origPh) el.dataset.origPh = ph;
                    el.setAttribute('placeholder', DICTIONARY[trimmed]);
                }
            } else {
                if (el.dataset.origPh) {
                    el.setAttribute('placeholder', el.dataset.origPh);
                } else if (REVERSE_DICT[trimmed]) {
                    el.setAttribute('placeholder', REVERSE_DICT[trimmed]);
                }
            }
        });
    }

    function translateDOM(targetLang) {
        const root = document.body;
        if (!root) return;

        const walker = document.createTreeWalker(
            root,
            NodeFilter.SHOW_TEXT,
            {
                acceptNode: function (node) {
                    const tag = node.parentElement ? node.parentElement.tagName.toLowerCase() : '';
                    if (tag === 'script' || tag === 'style' || tag === 'noscript' || tag === 'textarea') {
                        return NodeFilter.FILTER_REJECT;
                    }
                    return NodeFilter.FILTER_ACCEPT;
                }
            },
            false
        );

        const nodes = [];
        let currNode;
        while ((currNode = walker.nextNode())) {
            nodes.push(currNode);
        }

        nodes.forEach(n => translateTextNode(n, targetLang));
        translatePlaceholders(targetLang);

        // Cập nhật title của trang
        if (document.title) {
            const trimmedTitle = document.title.trim();
            if (targetLang === 'en') {
                for (const [vi, en] of Object.entries(DICTIONARY)) {
                    if (document.title.includes(vi)) {
                        document.title = document.title.replace(vi, en);
                    }
                }
            }
        }

        document.documentElement.setAttribute('lang', targetLang);
    }

    // Áp dụng ngôn ngữ hiện tại
    function applyLanguage(lang) {
        const current = lang || localStorage.getItem('app_language') || 'vi';
        localStorage.setItem('app_language', current);
        translateDOM(current);
    }

    // Khởi chạy khi load trang
    const currentLang = localStorage.getItem('app_language') || 'vi';
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            if (currentLang === 'en') {
                applyLanguage('en');
            }
        });
    } else {
        if (currentLang === 'en') {
            applyLanguage('en');
        }
    }

    // Xuất API toàn cục
    window.CashManagerI18n = {
        applyLanguage: applyLanguage,
        getLanguage: () => localStorage.getItem('app_language') || 'vi',
        dict: DICTIONARY
    };
})();
