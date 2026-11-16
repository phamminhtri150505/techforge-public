// Hủy chỉ đóng phần hỏi lại; không gửi yêu cầu xóa, mở hoặc chia sẻ.
(() => {
    document.querySelectorAll('[data-cancel-saved-action]').forEach(button => {
        button.addEventListener('click', () => {
            const panel = button.closest('.saved-action');
            panel.open = false;
            panel.querySelector(':scope > summary')?.focus();
        });
    });
})();
