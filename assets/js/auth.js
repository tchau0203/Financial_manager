// Module tiện ích & xác thực dùng chung Frontend
const APP_BASE_PATH = new URL('../../', document.currentScript.src).pathname;
const API_BASE = `${APP_BASE_PATH}api`;

function formatVND(amount) {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0);
}

function showToast(title, message, type = 'info') {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    const heading = document.createElement('strong');
    heading.textContent = title;
    toast.append(heading, document.createTextNode(`: ${message}`));
    container.appendChild(toast);
    setTimeout(() => {
        toast.remove();
        if (container.children.length === 0) container.remove();
    }, 3000);
}

async function apiFetch(endpoint, options = {}) {
    const config = {
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        credentials: 'include', // Truyền Cookie Session PHP
        ...options
    };

    try {
        const response = await fetch(`${API_BASE}${endpoint}`, config);
        if (response.status === 401 && endpoint !== '/auth/login.php') {
            window.location.href = `${APP_BASE_PATH}pages/login.html`;
            return null;
        }
        const body = await response.text();
        try {
            return JSON.parse(body);
        } catch {
            throw new Error(`API trả về dữ liệu không hợp lệ (HTTP ${response.status})`);
        }
    } catch (error) {
        console.error('API Error:', error);
        showToast('Lỗi kết nối', 'Không thể kết nối tới máy chủ', 'error');
        throw error;
    }
}

async function checkAuth() {
    try {
        const res = await apiFetch('/auth/me.php', { method: 'GET' });
        if (res && res.success) {
            const userEl = document.getElementById('header-username');
            if (userEl) userEl.textContent = res.data.full_name || res.data.username;
            return res.data;
        }
        if (res) showToast('Không thể xác thực', res.message || res.error || 'Máy chủ không xác nhận được phiên đăng nhập', 'error');
        return null;
    } catch (error) {
        console.error('Lỗi xác thực phiên:', error);
        return null;
    }
}
