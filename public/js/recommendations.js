(() => {
    const form = document.querySelector('[data-requirements-form]');
    if (!form) return;
    const button = form.querySelector('[data-recommendation-submit]');
    const progress = form.querySelector('[data-recommendation-progress]');
    let submitting = false;

    form.addEventListener('submit', (event) => {
        if (submitting) {
            event.preventDefault();
            return;
        }
        if (event.submitter !== button) return;
        submitting = true;
        // Để trình duyệt gửi cả name/value của nút đã bấm.
        button.setAttribute('aria-disabled', 'true');
        progress.hidden = false;
    });
    window.addEventListener('pageshow', () => {
        if (!submitting) return;
        submitting = false;
        button.removeAttribute('aria-disabled');
        progress.hidden = true;
    });
})();
