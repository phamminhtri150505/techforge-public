// Hủy bước xác nhận chỉ đóng phần đang mở; không gửi yêu cầu hoặc đổi giỏ.
document.querySelectorAll('[data-cancel-cart-action]').forEach((button) => {
    button.addEventListener('click', () => {
        const confirmation = button.closest('details');
        if (!confirmation) return;
        confirmation.open = false;
        confirmation.querySelector('summary')?.focus();
    });
});
