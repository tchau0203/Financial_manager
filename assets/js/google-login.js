// Đăng nhập bằng Google (Google Identity Services) — dùng chung cho login.html & register.html.
// Cần có <div id="googleBtn"></div> trong trang. Nếu server chưa cấu hình GOOGLE_CLIENT_ID thì ẩn khối đăng nhập Google.
(function () {
    const wrap = document.getElementById('googleLoginWrap');
    const holder = document.getElementById('googleBtn');
    if (!holder) return;

    function hide() { if (wrap) wrap.style.display = 'none'; }

    async function handleCredential(resp) {
        try {
            const res = await apiFetch('/auth/google.php', {
                method: 'POST',
                body: JSON.stringify({ credential: resp.credential })
            });
            if (res && res.success) {
                showToast('Thành công', 'Đăng nhập Google thành công!', 'success');
                const role = (res.user && res.user.role) || (res.data && res.data.role) || 'user';
                if (role === 'admin') {
                    setTimeout(() => window.location.href = 'admin/dashboard.html', 600);
                } else {
                    setTimeout(() => window.location.href = 'portal.html', 600);
                }
            } else {
                showToast('Thất bại', (res && (res.message || res.error)) || 'Không đăng nhập được bằng Google', 'error');
            }
        } catch (e) {
            console.error('Google login error:', e);
        }
    }

    function render(clientId) {
        const s = document.createElement('script');
        s.src = 'https://accounts.google.com/gsi/client';
        s.async = true; s.defer = true;
        s.onload = () => {
            google.accounts.id.initialize({ client_id: clientId, callback: handleCredential });
            google.accounts.id.renderButton(holder, {
                theme: 'outline', size: 'large', text: 'continue_with', shape: 'rectangular',
                width: Math.min(holder.parentElement.clientWidth || 360, 360), locale: 'vi'
            });
        };
        s.onerror = hide;
        document.head.appendChild(s);
    }

    document.addEventListener('DOMContentLoaded', async () => {
        try {
            const j = await apiFetch('/auth/google_config.php');
            if (j && j.client_id) render(j.client_id); else hide();
        } catch (e) { hide(); }
    });
})();
