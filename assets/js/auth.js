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
(function () {
    const MAP = {
        date: ['ngay', 'ngaygiaodich', 'date', 'transactiondate', 'thoigian'],
        type: ['loai', 'loaigiaodich', 'type', 'transactiontype'],
        cat: ['danhmuc', 'tendanhmuc', 'category', 'categoryname'],
        amount: ['sotien', 'amount', 'tien', 'giatri'],
        method: ['phuongthuc', 'phuongthucthanhtoan', 'method', 'paymentmethod', 'hinhthuc'],
        note: ['ghichu', 'note', 'mota', 'noidung', 'description']
    };
    const MAX_SIZE = 5 * 1024 * 1024;
    const PREVIEW_ROWS = 10;
    const E = (id) => document.getElementById(id);

    const norm = (s) => String(s || '').toLowerCase().normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd').replace(/[^a-z0-9]/g, '');

    function csvToMatrix(text) {
        text = text.replace(/^\uFEFF/, '');
        const first = text.split(/\r?\n/)[0] || '';
        const cnt = (ch) => first.split(ch).length;
        const dl = cnt(';') > cnt(',') ? ';' : (cnt('\t') > cnt(',') ? '\t' : ',');
        const out = []; let row = [], cell = '', q = false;
        for (let i = 0; i < text.length; i++) {
            const ch = text[i];
            if (q) {
                if (ch === '"' && text[i + 1] === '"') { cell += '"'; i++; }
                else if (ch === '"') q = false;
                else cell += ch;
            } else if (ch === '"') q = true;
            else if (ch === dl) { row.push(cell); cell = ''; }
            else if (ch === '\n' || ch === '\r') {
                if (ch === '\r' && text[i + 1] === '\n') i++;
                row.push(cell); out.push(row); row = []; cell = '';
            } else cell += ch;
        }
        if (cell !== '' || row.length) { row.push(cell); out.push(row); }
        return out.filter(r => r.some(v => String(v).trim() !== ''));
    }

    function parseDate(s) {
        s = String(s).trim(); let m, y, mo, d;
        if ((m = s.match(/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/))) { y = +m[1]; mo = +m[2]; d = +m[3]; }
        else if ((m = s.match(/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/))) { d = +m[1]; mo = +m[2]; y = +m[3]; }
        else return null;
        const dt = new Date(y, mo - 1, d);
        if (dt.getFullYear() !== y || dt.getMonth() !== mo - 1 || dt.getDate() !== d) return null;
        return `${y}-${String(mo).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
    }

    function parseAmount(s) {
        s = String(s).trim().replace(/[^\d.,-]/g, '');
        if (!s) return 0;
        if (/^-?\d{1,3}([.,]\d{3})+$/.test(s)) s = s.replace(/[.,]/g, '');
        else if (s.includes('.') && s.includes(','))
            s = s.lastIndexOf(',') > s.lastIndexOf('.') ? s.replace(/\./g, '').replace(',', '.') : s.replace(/,/g, '');
        else s = s.replace(',', '.');
        const n = parseFloat(s);
        return isNaN(n) ? 0 : Math.abs(n);
    }

    function buildRows(matrix, type) {
        const head = matrix[0].map(norm), ix = {};
        Object.keys(MAP).forEach(k => { ix[k] = head.findIndex(h => MAP[k].includes(h)); });
        if (ix.date < 0 || ix.amount < 0)
            throw new Error('Không tìm thấy cột bắt buộc "Ngày" và "Số tiền". Hãy tải file mẫu để xem đúng cấu trúc.');
        const otherWords = type === 'income' ? ['chi', 'chi tiêu', 'expense', '-'] : ['thu', 'thu nhập', 'income', '+'];
        return matrix.slice(1).map((r, i) => {
            const get = (k) => ix[k] >= 0 ? String(r[ix[k]] ?? '').trim() : '';
            const t = get('type').toLowerCase();
            const o = {
                line: i + 2, rawDate: get('date'), date: parseDate(get('date')),
                cat: get('cat') || (type === 'income' ? 'Thu nhập khác' : 'Chi tiêu khác'),
                method: get('method') || 'Tiền mặt', note: get('note'),
                amount: parseAmount(get('amount')), ok: true, msg: 'Hợp lệ'
            };
            if (otherWords.includes(t)) { o.ok = false; o.msg = type === 'income' ? 'Là khoản chi, bỏ qua' : 'Là khoản thu, bỏ qua'; }
            else if (!o.date) { o.ok = false; o.msg = 'Ngày không hợp lệ'; }
            else if (o.amount <= 0) { o.ok = false; o.msg = 'Số tiền không hợp lệ'; }
            return o;
        });
    }

    function rowsToCsv(rows, type) {
        const q = (v) => '"' + String(v ?? '').replace(/"/g, '""') + '"';
        const lines = ['Ngày,Loại,Danh mục,Số tiền,Phương thức,Ghi chú'];
        rows.filter(r => r.ok).forEach(r =>
            lines.push([r.date, type === 'income' ? 'Thu' : 'Chi', q(r.cat), r.amount, q(r.method), q(r.note)].join(',')));
        return '\uFEFF' + lines.join('\r\n');
    }

    const TEMPLATE =
        'Ngày,Loại,Danh mục,Số tiền,Phương thức,Ghi chú\r\n' +
        '2026-11-01,Thu,Lương,15000000,Banking,Lương tháng 11\r\n' +
        '2026-11-05,Chi,Ăn uống,50000,Tiền mặt,Ăn trưa\r\n' +
        '15/11/2026,Chi,Đi lại,"120.000",Ví điện tử,Grab\r\n';

    window.initImport = function ({ type, onDone }) {
        let rows = [], busy = false;

        const setProg = (name, p) => {
            E('impProgress').style.display = 'block';
            E('impProgName').textContent = name;
            E('impProgPct').textContent = Math.round(p) + '%';
            E('impProgFill').style.width = p + '%';
        };
        const reset = () => {
            rows = [];
            E('impPreview').style.display = 'none';
            E('impProgress').style.display = 'none';
            E('impError').textContent = '';
            E('impFile').value = '';
            E('btnConfirmImport').disabled = true;
            E('btnConfirmImport').textContent = 'Xác nhận nhập';
        };
        const open = () => { reset(); E('modalImport').classList.add('show'); };
        const close = () => { if (!busy) E('modalImport').classList.remove('show'); };

        function renderPreview() {
            const ok = rows.filter(r => r.ok).length;
            E('impStats').textContent = `${rows.length} dòng: ${ok} hợp lệ, ${rows.length - ok} lỗi (dòng lỗi sẽ bị bỏ qua)`;
            const trs = rows.slice(0, PREVIEW_ROWS).map(r => {
                const tr = document.createElement('tr');
                const cells = [r.line, r.date ? r.date.split('-').reverse().join('/') : (r.rawDate || '—'), r.cat, r.amount ? formatVND(r.amount) : '—'];
                cells.forEach((v, i) => {
                    const td = document.createElement('td');
                    td.textContent = v;
                    if (i === 3) td.style.textAlign = 'right';
                    tr.appendChild(td);
                });
                const td = document.createElement('td');
                const tag = document.createElement('span');
                tag.className = 'imp-tag ' + (r.ok ? 'ok' : 'bad');
                tag.textContent = r.msg;
                td.appendChild(tag); tr.appendChild(td);
                return tr;
            });
            E('impBody').replaceChildren(...trs);
            E('impMore').textContent = rows.length > PREVIEW_ROWS ? `Đang xem trước ${PREVIEW_ROWS}/${rows.length} dòng.` : '';
            E('impPreview').style.display = 'block';
            E('btnConfirmImport').disabled = ok === 0;
            E('btnConfirmImport').textContent = ok ? `Xác nhận nhập ${ok} giao dịch` : 'Không có dòng hợp lệ';
        }

        function handleFile(file) {
            reset();
            const ext = (file.name.split('.').pop() || '').toLowerCase();
            if (!['csv', 'txt', 'xlsx', 'xls'].includes(ext))
                return (E('impError').textContent = 'Định dạng chưa hỗ trợ. Chọn tệp .csv, .xlsx hoặc .xls.');
            if (file.size > MAX_SIZE)
                return (E('impError').textContent = 'Tệp vượt quá 5MB. Chọn tệp nhỏ hơn.');

            const isExcel = ext === 'xlsx' || ext === 'xls';
            const fr = new FileReader();
            fr.onprogress = (e) => { if (e.lengthComputable) setProg(file.name, e.loaded / e.total * 100); };
            fr.onerror = () => { E('impError').textContent = 'Không đọc được tệp. Thử lại.'; };
            fr.onload = () => {
                setProg(file.name, 100);
                try {
                    let matrix;
                    if (isExcel) {
                        const wb = XLSX.read(fr.result, { type: 'array', cellDates: true });
                        const ws = wb.Sheets[wb.SheetNames[0]];
                        matrix = XLSX.utils.sheet_to_json(ws, { header: 1, raw: false, dateNF: 'yyyy-mm-dd', defval: '' });
                    } else matrix = csvToMatrix(fr.result);
                    if (matrix.length < 2) throw new Error('Tệp rỗng hoặc không có dữ liệu.');
                    rows = buildRows(matrix, type);
                    renderPreview();
                } catch (e) {
                    E('impError').textContent = e.message;
                    E('impProgress').style.display = 'none';
                }
            };
            isExcel ? fr.readAsArrayBuffer(file) : fr.readAsText(file, 'UTF-8');
        }

        function upload() {
            const fd = new FormData();
            fd.append('file', new Blob([rowsToCsv(rows, type)], { type: 'text/csv' }), 'import.csv');
            busy = true;
            E('btnConfirmImport').disabled = true;
            const xhr = new XMLHttpRequest();
            xhr.open('POST', API_BASE + '/import/import_csv.php');
            xhr.withCredentials = true;
            xhr.upload.onprogress = (e) => { if (e.lengthComputable) setProg('Đang tải lên...', e.loaded / e.total * 100); };
            xhr.onload = () => {
                busy = false;
                if (xhr.status === 401) { window.location.href = 'login.html'; return; }
                let res = {};
                try { res = JSON.parse(xhr.responseText); } catch (e) {}
                if (res.success) {
                    showToast('Thành công', res.message, 'success');
                    close();
                    if (onDone) onDone();
                } else {
                    E('impError').textContent = res.message || 'Nhập dữ liệu thất bại.';
                    E('btnConfirmImport').disabled = false;
                }
            };
            xhr.onerror = () => {
                busy = false;
                E('impError').textContent = 'Không kết nối được máy chủ.';
                E('btnConfirmImport').disabled = false;
            };
            xhr.send(fd);
        }

        function downloadTemplate() {
            const a = document.createElement('a');
            a.href = URL.createObjectURL(new Blob(['\uFEFF' + TEMPLATE], { type: 'text/csv;charset=utf-8' }));
            a.download = 'mau_giao_dich.csv';
            a.click();
            setTimeout(() => URL.revokeObjectURL(a.href), 1000);
        }

        E('btnImport').addEventListener('click', open);
        E('btnCloseImport').addEventListener('click', close);
        E('btnCancelImport').addEventListener('click', close);
        E('modalImport').addEventListener('click', (e) => { if (e.target === E('modalImport')) close(); });
        E('impDrop').addEventListener('click', () => E('impFile').click());
        E('impDrop').addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); E('impFile').click(); } });
        E('impFile').addEventListener('change', (e) => { if (e.target.files[0]) handleFile(e.target.files[0]); });
        ['dragenter', 'dragover'].forEach(ev => E('impDrop').addEventListener(ev, (e) => { e.preventDefault(); E('impDrop').classList.add('over'); }));
        ['dragleave', 'drop'].forEach(ev => E('impDrop').addEventListener(ev, (e) => { e.preventDefault(); E('impDrop').classList.remove('over'); }));
        E('impDrop').addEventListener('drop', (e) => { if (e.dataTransfer.files[0]) handleFile(e.dataTransfer.files[0]); });
        E('btnTemplate').addEventListener('click', downloadTemplate);
        E('btnConfirmImport').addEventListener('click', upload);
    };
})();