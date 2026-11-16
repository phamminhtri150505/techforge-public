// Chỉ mở/đóng giao diện. Không thay lựa chọn, giá hoặc kết quả kỹ thuật trong trình duyệt.
(() => {
    const openReference = (hash, focus = false) => {
        const detail = document.getElementById(hash.slice(1));
        if (!detail?.matches('details[data-reference]')) return;
        detail.open = true;
        if (focus) detail.querySelector(':scope > summary')?.focus();
    };
    document.querySelectorAll('[data-open-reference]').forEach(link => {
        link.addEventListener('click', () => openReference(link.hash, true));
    });
    window.addEventListener('hashchange', () => openReference(window.location.hash));
    openReference(window.location.hash);
    document.querySelectorAll('[data-cancel-reference]').forEach(button => {
        button.addEventListener('click', () => {
            const confirmation = button.closest('.reference-confirm');
            confirmation.open = false;
            confirmation.querySelector(':scope > summary')?.focus();
        });
    });
})();
