// Chỉ điều khiển chuyển động trang tin, không can thiệp dữ liệu/Builder.
document.addEventListener('DOMContentLoaded', () => {
    const button = document.querySelector('.news-motion');
    if (!button) return;
    const preference = window.matchMedia('(prefers-reduced-motion: reduce)');
    const storageKey = 'techforge.home.motion';
    let userEnabled = true;
    try { userEnabled = window.sessionStorage.getItem(storageKey) !== 'off'; } catch { /* Tùy chọn vẫn dùng được khi trình duyệt chặn lưu trữ. */ }
    const update = () => {
        const enabled = userEnabled && !preference.matches;
        document.body.dataset.newsMotion = enabled ? 'on' : 'off';
        button.setAttribute('aria-pressed', String(enabled));
        button.disabled = preference.matches;
        button.textContent = preference.matches ? 'Chuyển động: Đã giảm' : `Chuyển động: ${enabled ? 'Bật' : 'Tắt'}`;
        button.title = preference.matches ? 'Đã tắt theo tùy chọn giảm chuyển động của hệ thống.' : 'Bật hoặc tắt hiệu ứng khi xem bài viết.';
    };
    button.hidden = false;
    button.addEventListener('click', () => {
        if (preference.matches) return;
        userEnabled = !userEnabled;
        try { window.sessionStorage.setItem(storageKey, userEnabled ? 'on' : 'off'); } catch { /* Không làm gián đoạn trang tin. */ }
        update();
    });
    preference.addEventListener('change', update);
    update();
});
