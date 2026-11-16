// Chỉ phản hồi giao diện khi gửi form; kiểm tra và xác nhận luôn ở máy chủ.
document.addEventListener('DOMContentLoaded', () => {
    const forms = document.querySelectorAll('.product-detail-page form');
    forms.forEach((form) => {
        const button = form.querySelector('button[type="submit"], button:not([type])');
        if (!button) return;
        const label = button.textContent;
        form.addEventListener('submit', (event) => {
            if (button.disabled) { event.preventDefault(); return; }
            button.disabled = true;
            button.textContent = 'Đang xử lý…';
            form.setAttribute('aria-busy', 'true');
        });
        // Trở về bằng nút Back vẫn có thể gửi lại; không giữ nút disabled từ cache.
        window.addEventListener('pageshow', () => {
            button.disabled = false;
            button.textContent = label;
            form.removeAttribute('aria-busy');
        });
    });
});
