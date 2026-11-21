// Module tiện ích & xác thực dùng chung Frontend
const APP_BASE_PATH = new URL('../../', document.currentScript.src).pathname;
const API_BASE = `${APP_BASE_PATH}api`;

// ========================================================
// HỖ TRỢ ĐA TIỀN TỆ (VND, USD, EUR, JPY) - Ngô Bảo (FE)
// ========================================================
let appCurrentCurrency = localStorage.getItem('app_currency') || 'VND';

function getAppCurrency() {
    return appCurrentCurrency || 'VND';
}

function setAppCurrency(curr) {
    if (curr) {
        appCurrentCurrency = String(curr).toUpperCase().trim();
        localStorage.setItem('app_currency', appCurrentCurrency);
    }
}

/**
 * Nâng cấp hàm formatCurrency(amount, currency) tự động hiển thị:
 * - VND: 15.000.000 đ
 * - USD: $1,500.00
 * - EUR: €1,200.00
 * - JPY: ¥150,000
 */
function formatCurrency(amount, currency = null) {
    const curr = (currency || appCurrentCurrency || 'VND').toUpperCase().trim();
    const val = Number(amount) || 0;

    switch (curr) {
        case 'USD':
            return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(val);
        case 'EUR':
            return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(val);
        case 'JPY':
            return new Intl.NumberFormat('ja-JP', { style: 'currency', currency: 'JPY' }).format(val);
        case 'VND':
        default:
            return new Intl.NumberFormat('vi-VN').format(Math.round(val)) + ' đ';
    }
}

function formatVND(amount) {
    return formatCurrency(amount, appCurrentCurrency);
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
            const userData = res.data || res.user || {};
            if (userData.currency) {
                setAppCurrency(userData.currency);
            }
            const userEl = document.getElementById('header-username');
            if (userEl) userEl.textContent = userData.full_name || userData.username || 'Người dùng';
            return userData;
        }
        if (res) showToast('Không thể xác thực', res.message || res.error || 'Máy chủ không xác nhận được phiên đăng nhập', 'error');
        return null;
    } catch (error) {
        console.error('Lỗi xác thực phiên:', error);
        return null;
    }
}
