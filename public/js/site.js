// Nội dung luôn hiển thị khi JavaScript không chạy. Chuyển động chỉ xuất hiện một lần.
document.addEventListener('DOMContentLoaded', () => {
    const rateLimitNotice = document.querySelector('[data-rate-limit-notice]');
    const closeNotice = rateLimitNotice?.querySelector('button');
    if (closeNotice) {
        closeNotice.hidden = false;
        closeNotice.addEventListener('click', () => {
            rateLimitNotice.remove();
            document.querySelector('#main-content')?.focus({ preventScroll: true });
        });
    }
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    if (!motion.matches && 'IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(({ target, isIntersecting }) => {
                if (!isIntersecting) return;
                if (!motion.matches) target.classList.add('arrived');
                observer.unobserve(target);
            });
        }, { threshold: 0.08 });
        document.querySelectorAll('[data-reveal]').forEach((element) => observer.observe(element));
    }
    // Trên màn hình nhỏ, thu gọn bộ lọc để sản phẩm xuất hiện sớm; form vẫn dùng được khi tắt JS.
    const filters = document.querySelector('.catalog-filter-panel');
    if (filters) {
        const compact = window.matchMedia('(max-width: 767px)');
        const updateFilters = () => { filters.open = !compact.matches; };
        updateFilters();
        compact.addEventListener('change', updateFilters);
    }
    const menu = document.querySelector('.mobile-navigation');
    menu?.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && menu.open) {
            menu.open = false;
            menu.querySelector('summary').focus();
        }
    });
    document.addEventListener('click', (event) => {
        if (menu?.open && !menu.contains(event.target)) menu.open = false;
    });
});
