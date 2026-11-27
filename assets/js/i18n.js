/**
 * CashManager Multi-Language Engine (i18n)
 * Hỗ trợ chuyển đổi đa ngôn ngữ:
 * - vi: Tiếng Việt (Mặc định)
 * - en: English (Tiếng Anh)
 * - ja: 日本語 (Tiếng Nhật)
 * - ko: 한국어 (Tiếng Hàn)
 * - zh: 简体中文 (Tiếng Trung)
 * - fr: Français (Tiếng Pháp)
 */
(function () {
    'use strict';

    const DICTIONARIES = {
        // ===== TIẾNG ANH (en) =====
        en: {
            "Bảng điều khiển": "Dashboard",
            "Thu nhập": "Income",
            "Chi tiêu": "Expense",
            "Ngân sách": "Budget",
            "Báo cáo": "Reports",
            "Bản đồ thu chi": "Finance Map",
            "Bản đồ chi tiêu": "Expense Map",
            "Quỹ chung": "Shared Funds",
            "Tài khoản": "Account",
            "Giao dịch": "Transaction",
            "+ Giao dịch": "+ Transaction",
            "Thêm giao dịch mới": "Add new transaction",
            "Cài đặt": "Settings",
            "Trợ giúp": "Help",
            "Đăng xuất": "Logout",
            "Nâng cấp": "Upgrade",
            "Thử ứng dụng di động": "Mobile App",
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
            "Bạn muốn thêm khoản chi tiêu hay khoản thu nhập?": "Would you like to add an expense or an income?",
            "Thêm chi tiêu": "Add Expense",
            "Thêm thu nhập": "Add Income",
            "Trung tâm trợ giúp CashManager": "CashManager Help Center",
            "📌 Các tính năng chính:": "📌 Main Features:",
            "Đóng": "Close",
            "Đã hiểu": "Understood",
            "Bạn có chắc chắn muốn đăng xuất khỏi CashManager?": "Are you sure you want to log out of CashManager?"
        },

        // ===== TIẾNG NHẬT (ja) =====
        ja: {
            "Bảng điều khiển": "ダッシュボード",
            "Thu nhập": "収入",
            "Chi tiêu": "支出",
            "Ngân sách": "予算",
            "Báo cáo": "レポート",
            "Bản đồ thu chi": "収支マップ",
            "Bản đồ chi tiêu": "支出マップ",
            "Quỹ chung": "共有ウォレット",
            "Tài khoản": "アカウント",
            "Giao dịch": "取引",
            "+ Giao dịch": "+ 取引追加",
            "Thêm giao dịch mới": "新しい取引を追加",
            "Cài đặt": "設定",
            "Trợ giúp": "ヘルプ",
            "Đăng xuất": "ログアウト",
            "Nâng cấp": "アップグレード",
            "Thử ứng dụng di động": "モバイルアプリ",
            "Cài đặt tài khoản": "アカウント設定",
            "Thông tin cá nhân": "個人情報",
            "Họ và tên": "氏名",
            "Lưu thay đổi": "変更を保存",
            "Đổi mật khẩu": "パスワード変更",
            "Mật khẩu hiện tại": "現在のパスワード",
            "Mật khẩu mới": "新しいパスワード",
            "Nhập lại mật khẩu mới": "新しいパスワード（確認）",
            "Ảnh đại diện": "プロフィール写真",
            "Tải ảnh từ máy": "画像をアップロード",
            "Nhập link ảnh": "画像URLを入力",
            "Xóa ảnh": "写真を削除",
            "Ngôn ngữ của trang web": "ウェブサイトの言語",
            "Giao diện": "外観とテーマ",
            "Chế độ sáng và tối": "ダーク＆ライトモード",
            "Theo trình duyệt": "システム設定に従う",
            "Sáng": "ライト",
            "Tối": "ダーク",
            "Mật độ danh sách ghi chép": "記録リストの密度",
            "Thấp - mặc định": "低 - デフォルト",
            "Thấp": "低",
            "- mặc định": "- デフォルト",
            "Trung bình": "中",
            "Cao": "高",
            "Cài đặt Đa tiền tệ": "複数通貨設定",
            "Đơn vị tiền tệ chính": "基本通貨",
            "Tỷ giá tham chiếu:": "参考為替レート:",
            "Lưu tiền tệ": "通貨を保存",
            "Nhận email cảnh báo khi ngân sách đạt 80%": "予算80%到達時にメール通知",
            "Nhắc nhở ghi chép hàng ngày (Web Push)": "毎日の記録リマインダー (Web Push)",
            "Giờ nhắc nhở mỗi tối:": "毎晩のリマインダー時間:",
            "Trạng thái quyền thông báo:": "通知権限ステータス:",
            "Gửi thử thông báo": "テスト通知を送信",
            "Lưu cài đặt": "設定を保存",
            "Thông tin tài khoản ngân hàng": "銀行口座情報",
            "Ngân hàng": "銀行",
            "Số tài khoản": "口座番号",
            "Lưu tài khoản ngân hàng": "銀行口座を保存",
            "Danh mục của tôi": "マリカテゴリ",
            "Thêm danh mục": "カテゴリを追加",
            "Tên danh mục": "カテゴリ名",
            "Loại": "タイプ",
            "Thao tác": "操作",
            "Sao lưu & Dữ liệu": "バックアップ＆データ",
            "Sao lưu dữ liệu": "データをバックアップ",
            "Khôi phục dữ liệu từ file": "ファイルから復元",
            "Kéo thả file sao lưu (.json) vào đây": "バックアップファイル (.json) をここにドラッグ＆ドロップ",
            "hoặc bấm để chọn file từ máy": "または端末からファイルを選択",
            "Khôi phục dữ liệu": "データを復元",
            "Hủy": "キャンセル",
            "Lưu danh mục": "カテゴリを保存",
            "Tổng quan tài chính": "財務概要",
            "TỔNG THU THÁNG NÀY": "今月の総収入",
            "TỔNG CHI THÁNG NÀY": "今月の総支出",
            "SỐ DƯ RÒNG": "純残高",
            "TỶ LỆ TIẾT KIỆM": "貯蓄率",
            "Tiến độ ngân sách tháng": "今月の予算進捗",
            "Chưa thiết lập ngân sách": "予算未設定",
            "Trợ lý tài chính AI": "AI財務アシスタント",
            "Hỏi chuyên gia AI": "AI専門家に質問",
            "Xu hướng thu chi 12 tháng": "12ヶ月の収支傾向",
            "Cơ cấu chi tiêu tháng này": "今月の支出内訳",
            "Giao dịch gần đây": "最近の取引",
            "Xem tất cả": "すべて見る",
            "Ngày": "日付",
            "Danh mục": "カテゴリ",
            "Số tiền": "金額",
            "Ghi chú": "メモ",
            "Chưa có giao dịch nào": "記録された取引はありません",
            "Thêm chi tiêu": "支出を追加",
            "Thêm thu nhập": "収入を追加",
            "Trung tâm trợ giúp CashManager": "CashManager ヘルプセンター",
            "Đóng": "閉じる",
            "Đã hiểu": "了解",
            "Bạn có chắc chắn muốn đăng xuất khỏi CashManager?": "CashManager からログアウトしますか？"
        },

        // ===== TIẾNG HÀN (ko) =====
        ko: {
            "Bảng điều khiển": "대시보드",
            "Thu nhập": "수입",
            "Chi tiêu": "지출",
            "Ngân sách": "예산",
            "Báo cáo": "보고서",
            "Bản đồ thu chi": "수입/지출 지도",
            "Bản đồ chi tiêu": "지출 지도",
            "Quỹ chung": "공동 자금",
            "Tài khoản": "계정",
            "Giao dịch": "거래",
            "+ Giao dịch": "+ 거래 추가",
            "Thêm giao dịch mới": "새 거래 추가",
            "Cài đặt": "설정",
            "Trợ giúp": "도움말",
            "Đăng xuất": "로그아웃",
            "Nâng cấp": "업그레이드",
            "Thử ứng dụng di động": "모바일 앱",
            "Cài đặt tài khoản": "계정 설정",
            "Thông tin cá nhân": "개인 정보",
            "Họ và tên": "성명",
            "Lưu thay đổi": "변경사항 저장",
            "Đổi mật khẩu": "비밀번호 변경",
            "Mật khẩu hiện tại": "현재 비밀번호",
            "Mật khẩu mới": "새 비밀번호",
            "Nhập lại mật khẩu mới": "새 비밀번호 확인",
            "Ảnh đại diện": "프로필 사진",
            "Tải ảnh từ máy": "사진 업로드",
            "Nhập link ảnh": "이미지 URL 입력",
            "Xóa ảnh": "사진 삭제",
            "Ngôn ngữ của trang web": "웹사이트 언어",
            "Giao diện": "화면 스타일",
            "Chế độ sáng và tối": "다크 & 라이트 모드",
            "Theo trình duyệt": "시스템 기본값",
            "Sáng": "라이트",
            "Tối": "다크",
            "Mật độ danh sách ghi chép": "목록 밀도",
            "Thấp - mặc định": "낮음 - 기본값",
            "Thấp": "낮음",
            "- mặc định": "- 기본값",
            "Trung bình": "보통",
            "Cao": "높음",
            "Cài đặt Đa tiền tệ": "다중 통화 설정",
            "Đơn vị tiền tệ chính": "기본 통화",
            "Tỷ giá tham chiếu:": "참조 환율:",
            "Lưu tiền tệ": "통화 저장",
            "Nhận email cảnh báo khi ngân sách đạt 80%": "예산 80% 도달 시 이메일 알림",
            "Nhắc nhở ghi chép hàng ngày (Web Push)": "일일 기록 알림 (Web Push)",
            "Giờ nhắc nhở mỗi tối:": "매일 저녁 알림 시간:",
            "Trạng thái quyền thông báo:": "알림 권한 상태:",
            "Gửi thử thông báo": "테스트 알림 전송",
            "Lưu cài đặt": "설정 저장",
            "Thông tin tài khoản ngân hàng": "은행 계좌 정보",
            "Ngân hàng": "은행",
            "Số tài khoản": "계좌 번호",
            "Lưu tài khoản ngân hàng": "은행 계좌 저장",
            "Danh mục của tôi": "나의 카테고리",
            "Thêm danh mục": "카테고리 추가",
            "Tên danh mục": "카테고리 이름",
            "Loại": "유형",
            "Thao tác": "작업",
            "Sao lưu & Dữ liệu": "백업 & 데이터",
            "Sao lưu dữ liệu": "데이터 백업",
            "Khôi phục dữ liệu từ file": "파일에서 복원",
            "Kéo thả file sao lưu (.json) vào đây": "백업 파일 (.json)을 여기에 드래그하세요",
            "hoặc bấm để chọn file từ máy": "또는 기기에서 파일 선택",
            "Khôi phục dữ liệu": "데이터 복원",
            "Hủy": "취소",
            "Lưu danh mục": "카테고리 저장",
            "Tổng quan tài chính": "재정 개요",
            "TỔNG THU THÁNG NÀY": "이번 달 총 수입",
            "TỔNG CHI THÁNG NÀY": "이번 달 총 지출",
            "SỐ DƯ RÒNG": "순 잔액",
            "TỶ LỆ TIẾT KIỆM": "저축률",
            "Tiến độ ngân sách tháng": "월간 예산 진행률",
            "Chưa thiết lập ngân sách": "예산 미설정",
            "Trợ lý tài chính AI": "AI 재정 도우미",
            "Hỏi chuyên gia AI": "AI 전문가에게 질문",
            "Xu hướng thu chi 12 tháng": "12개월 수입/지출 추세",
            "Cơ cấu chi tiêu tháng này": "이번 달 지출 구성",
            "Giao dịch gần đây": "최근 거래",
            "Xem tất cả": "모두 보기",
            "Ngày": "날짜",
            "Danh mục": "카테고리",
            "Số tiền": "금액",
            "Ghi chú": "메모",
            "Chưa có giao dịch nào": "기록된 거래가 없습니다",
            "Thêm chi tiêu": "지출 추가",
            "Thêm thu nhập": "수입 추가",
            "Trung tâm trợ giúp CashManager": "CashManager 고객 지원 센터",
            "Đóng": "닫기",
            "Đã hiểu": "확인",
            "Bạn có chắc chắn muốn đăng xuất khỏi CashManager?": "CashManager에서 로그아웃하시겠습니까?"
        },

        // ===== TIẾNG TRUNG (zh - 简体中文) =====
        zh: {
            "Bảng điều khiển": "控制面板",
            "Thu nhập": "收入",
            "Chi tiêu": "支出",
            "Ngân sách": "预算",
            "Báo cáo": "财务报表",
            "Bản đồ thu chi": "收支地图",
            "Bản đồ chi tiêu": "支出地图",
            "Quỹ chung": "共享基金",
            "Tài khoản": "账户",
            "Giao dịch": "交易",
            "+ Giao dịch": "+ 记账",
            "Thêm giao dịch mới": "添加新交易",
            "Cài đặt": "设置",
            "Trợ giúp": "帮助",
            "Đăng xuất": "退出登录",
            "Nâng cấp": "升级会员",
            "Thử ứng dụng di động": "移动应用",
            "Cài đặt tài khoản": "账户设置",
            "Thông tin cá nhân": "个人资料",
            "Họ và tên": "姓名",
            "Lưu thay đổi": "保存更改",
            "Đổi mật khẩu": "修改密码",
            "Mật khẩu hiện tại": "当前密码",
            "Mật khẩu mới": "新密码",
            "Nhập lại mật khẩu mới": "确认新密码",
            "Ảnh đại diện": "头像",
            "Tải ảnh từ máy": "本地上传",
            "Nhập link ảnh": "图片网址",
            "Xóa ảnh": "删除头像",
            "Ngôn ngữ của trang web": "网站语言",
            "Giao diện": "外观界面",
            "Chế độ sáng và tối": "深色与浅色模式",
            "Theo trình duyệt": "跟随系统",
            "Sáng": "浅色",
            "Tối": "深色",
            "Mật độ danh sách ghi chép": "记录列表密度",
            "Thấp - mặc định": "宽松 - 默认",
            "Thấp": "宽松",
            "- mặc định": "- 默认",
            "Trung bình": "适中",
            "Cao": "紧凑",
            "Cài đặt Đa tiền tệ": "多币种设置",
            "Đơn vị tiền tệ chính": "基准货币",
            "Tỷ giá tham chiếu:": "参考汇率:",
            "Lưu tiền tệ": "保存货币",
            "Nhận email cảnh báo khi ngân sách đạt 80%": "预算达到80%时发送邮件提醒",
            "Nhắc nhở ghi chép hàng ngày (Web Push)": "每日记账提醒 (Web Push)",
            "Giờ nhắc nhở mỗi tối:": "每晚提醒时间:",
            "Trạng thái quyền thông báo:": "通知权限状态:",
            "Gửi thử thông báo": "发送测试通知",
            "Lưu cài đặt": "保存设置",
            "Thông tin tài khoản ngân hàng": "银行账户信息",
            "Ngân hàng": "银行",
            "Số tài khoản": "银行账号",
            "Lưu tài khoản ngân hàng": "保存银行账户",
            "Danh mục của tôi": "我的分类",
            "Thêm danh mục": "添加分类",
            "Tên danh mục": "分类名称",
            "Loại": "类型",
            "Thao tác": "操作",
            "Sao lưu & Dữ liệu": "备份与数据",
            "Sao lưu dữ liệu": "备份数据",
            "Khôi phục dữ liệu từ file": "从文件恢复",
            "Kéo thả file sao lưu (.json) vào đây": "将备份文件 (.json) 拖拽到此处",
            "hoặc bấm để chọn file từ máy": "或点击选择本地文件",
            "Khôi phục dữ liệu": "恢复数据",
            "Hủy": "取消",
            "Lưu danh mục": "保存分类",
            "Tổng quan tài chính": "财务概览",
            "TỔNG THU THÁNG NÀY": "本月总收入",
            "TỔNG CHI THÁNG NÀY": "本月总支出",
            "SỐ DƯ RÒNG": "净余额",
            "TỶ LỆ TIẾT KIỆM": "储蓄率",
            "Tiến độ ngân sách tháng": "本月预算进度",
            "Chưa thiết lập ngân sách": "未设置预算",
            "Trợ lý tài chính AI": "AI财务顾问",
            "Hỏi chuyên gia AI": "咨询AI专家",
            "Xu hướng thu chi 12 tháng": "12个月收支趋势",
            "Cơ cấu chi tiêu tháng này": "本月支出结构",
            "Giao dịch gần đây": "最近交易",
            "Xem tất cả": "查看全部",
            "Ngày": "日期",
            "Danh mục": "分类",
            "Số tiền": "金额",
            "Ghi chú": "备注",
            "Chưa có giao dịch nào": "暂无交易记录",
            "Thêm chi tiêu": "添加支出",
            "Thêm thu nhập": "添加收入",
            "Trung tâm trợ giúp CashManager": "CashManager 帮助中心",
            "Đóng": "关闭",
            "Đã hiểu": "明白",
            "Bạn có chắc chắn muốn đăng xuất khỏi CashManager?": "您确定要退出 CashManager 吗？"
        },

        // ===== TIẾNG PHÁP (fr) =====
        fr: {
            "Bảng điều khiển": "Tableau de bord",
            "Thu nhập": "Revenus",
            "Chi tiêu": "Dépenses",
            "Ngân sách": "Budget",
            "Báo cáo": "Rapports",
            "Bản đồ thu chi": "Carte financière",
            "Bản đồ chi tiêu": "Carte des dépenses",
            "Quỹ chung": "Fonds partagés",
            "Tài khoản": "Compte",
            "Giao dịch": "Transaction",
            "+ Giao dịch": "+ Transaction",
            "Thêm giao dịch mới": "Ajouter une transaction",
            "Cài đặt": "Paramètres",
            "Trợ giúp": "Aide",
            "Đăng xuất": "Déconnexion",
            "Nâng cấp": "Mettre à niveau",
            "Thử ứng dụng di động": "Application Mobile",
            "Cài đặt tài khoản": "Paramètres du compte",
            "Thông tin cá nhân": "Informations personnelles",
            "Họ và tên": "Nom et prénom",
            "Lưu thay đổi": "Enregistrer",
            "Đổi mật khẩu": "Changer le mot de passe",
            "Mật khẩu hiện tại": "Mot de passe actuel",
            "Mật khẩu mới": "Nouveau mot de passe",
            "Nhập lại mật khẩu mới": "Confirmer le mot de passe",
            "Ảnh đại diện": "Photo de profil",
            "Tải ảnh từ máy": "Téléverser une image",
            "Nhập link ảnh": "Lien de l'image",
            "Xóa ảnh": "Supprimer la photo",
            "Ngôn ngữ của trang web": "Langue du site",
            "Giao diện": "Apparence",
            "Chế độ sáng và tối": "Mode Clair / Sombre",
            "Theo trình duyệt": "Système par défaut",
            "Sáng": "Clair",
            "Tối": "Sombre",
            "Mật độ danh sách ghi chép": "Densité de la liste",
            "Thấp - mặc định": "Faible - défaut",
            "Thấp": "Faible",
            "- mặc định": "- défaut",
            "Trung bình": "Moyenne",
            "Cao": "Élevée",
            "Cài đặt Đa tiền tệ": "Devises multiples",
            "Đơn vị tiền tệ chính": "Devise principale",
            "Tỷ giá tham chiếu:": "Taux de change de référence:",
            "Lưu tiền tệ": "Enregistrer la devise",
            "Nhận email cảnh báo khi ngân sách đạt 80%": "Alerte e-mail lorsque le budget atteint 80%",
            "Nhắc nhở ghi chép hàng ngày (Web Push)": "Rappel d'enregistrement quotidien (Web Push)",
            "Giờ nhắc nhở mỗi tối:": "Heure de rappel chaque soir:",
            "Trạng thái quyền thông báo:": "Statut de permission de notification:",
            "Gửi thử thông báo": "Envoyer une notification test",
            "Lưu cài đặt": "Enregistrer les paramètres",
            "Thông tin tài khoản ngân hàng": "Coordonnées bancaires",
            "Ngân hàng": "Banque",
            "Số tài khoản": "Numéro de compte",
            "Lưu tài khoản ngân hàng": "Enregistrer le compte bancaire",
            "Danh mục của tôi": "Mes catégories",
            "Thêm danh mục": "Ajouter une catégorie",
            "Tên danh mục": "Nom de la catégorie",
            "Loại": "Type",
            "Thao tác": "Actions",
            "Sao lưu & Dữ liệu": "Sauvegarde & Données",
            "Sao lưu dữ liệu": "Sauvegarder les données",
            "Khôi phục dữ liệu từ file": "Restaurer un fichier",
            "Kéo thả file sao lưu (.json) vào đây": "Glissez et déposez le fichier de sauvegarde (.json) ici",
            "hoặc bấm để chọn file từ máy": "ou cliquez pour choisir un fichier",
            "Khôi phục dữ liệu": "Restaurer les données",
            "Hủy": "Annuler",
            "Lưu danh mục": "Enregistrer la catégorie",
            "Tổng quan tài chính": "Aperçu financier",
            "TỔNG THU THÁNG NÀY": "REVENUS DU MOIS",
            "TỔNG CHI THÁNG NÀY": "DÉPENSES DU MOIS",
            "SỐ DƯ RÒNG": "SOLDE NET",
            "TỶ LỆ TIẾT KIỆM": "TAUX D'ÉPARGNE",
            "Tiến độ ngân sách tháng": "Progression du budget",
            "Chưa thiết lập ngân sách": "Aucun budget défini",
            "Trợ lý tài chính AI": "Assistant Financier IA",
            "Hỏi chuyên gia AI": "Consulter l'IA",
            "Xu hướng thu chi 12 tháng": "Tendance sur 12 mois",
            "Cơ cấu chi tiêu tháng này": "Répartition des dépenses",
            "Giao dịch gần đây": "Transactions récentes",
            "Xem tất cả": "Voir tout",
            "Ngày": "Date",
            "Danh mục": "Catégorie",
            "Số tiền": "Montant",
            "Ghi chú": "Note",
            "Chưa có giao dịch nào": "Aucune transaction enregistrée",
            "Thêm chi tiêu": "Ajouter une dépense",
            "Thêm thu nhập": "Ajouter un revenu",
            "Trung tâm trợ giúp CashManager": "Centre d'aide CashManager",
            "Đóng": "Fermer",
            "Đã hiểu": "Compris",
            "Bạn có chắc chắn muốn đăng xuất khỏi CashManager?": "Êtes-vous sûr de vouloir vous déconnecter de CashManager ?"
        }
    };

    function translateTextNode(node, targetLang) {
        if (!node || node.nodeType !== Node.TEXT_NODE) return;
        const text = node.nodeValue;
        if (!text || !text.trim()) return;

        // Lưu bản gốc tiếng Việt lần đầu tiên
        if (typeof node._cmOrigText === 'undefined') {
            node._cmOrigText = text;
        }

        const orig = node._cmOrigText;
        const trimmedOrig = orig.trim();

        if (targetLang === 'vi') {
            node.nodeValue = orig;
            return;
        }

        const dict = DICTIONARIES[targetLang];
        if (dict && dict[trimmedOrig]) {
            node.nodeValue = orig.replace(trimmedOrig, dict[trimmedOrig]);
        }
    }

    function translatePlaceholders(targetLang) {
        document.querySelectorAll('input[placeholder], textarea[placeholder]').forEach(el => {
            const ph = el.getAttribute('placeholder');
            if (!ph) return;

            if (!el.dataset.origPh) {
                el.dataset.origPh = ph;
            }

            const origPh = el.dataset.origPh;
            const trimmed = origPh.trim();

            if (targetLang === 'vi') {
                el.setAttribute('placeholder', origPh);
                return;
            }

            const dict = DICTIONARIES[targetLang];
            if (dict && dict[trimmed]) {
                el.setAttribute('placeholder', dict[trimmed]);
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
            if (typeof document._cmOrigTitle === 'undefined') {
                document._cmOrigTitle = document.title;
            }
            if (targetLang === 'vi') {
                document.title = document._cmOrigTitle;
            } else {
                let t = document._cmOrigTitle;
                const dict = DICTIONARIES[targetLang];
                if (dict) {
                    for (const [vi, trans] of Object.entries(dict)) {
                        if (t.includes(vi)) {
                            t = t.replace(vi, trans);
                        }
                    }
                    document.title = t;
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
            if (currentLang !== 'vi') {
                applyLanguage(currentLang);
            }
        });
    } else {
        if (currentLang !== 'vi') {
            applyLanguage(currentLang);
        }
    }

    // Xuất API toàn cục
    window.CashManagerI18n = {
        applyLanguage: applyLanguage,
        getLanguage: () => localStorage.getItem('app_language') || 'vi',
        dictionaries: DICTIONARIES,
        languages: [
            { code: 'vi', name: 'Tiếng Việt', flag: '🇻🇳' },
            { code: 'en', name: 'English', flag: '🇬🇧' },
            { code: 'ja', name: '日本語', flag: '🇯🇵' },
            { code: 'ko', name: '한국어', flag: '🇰🇷' },
            { code: 'zh', name: '简体中文', flag: '🇨🇳' },
            { code: 'fr', name: 'Français', flag: '🇫🇷' }
        ]
    };
})();
