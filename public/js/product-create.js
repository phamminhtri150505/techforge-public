(() => {
    const picker = document.querySelector('#product-type-picker');
    const form = document.querySelector('#product-create-form');
    if (!form) return;
    if (picker) {
        const choice = picker.querySelector('select');
        const applyType = () => {
            form.querySelector('#product-input-type').value = choice.value;
            form.querySelectorAll('[data-product-fields]').forEach(group => {
                group.disabled = group.dataset.productFields !== choice.value;
                group.hidden = group.disabled;
            });
        };
        picker.addEventListener('submit', event => { event.preventDefault(); applyType(); });
        choice.addEventListener('change', applyType);
        applyType();
    }
    const priceKind = form.querySelector('#price_kind');
    const price = form.querySelector('#price');
    const availability = form.querySelector('#availability');
    const applyPrice = () => {
        const unknown = priceKind.value === 'unknown';
        price.disabled = unknown;
        price.required = !unknown;
        availability.querySelector('option[value="orderable"]').disabled = unknown;
        if (unknown && availability.value === 'orderable') availability.value = 'contact';
    };
    priceKind.addEventListener('change', applyPrice);
    applyPrice();
    form.querySelectorAll('[data-add-cpu-support]').forEach(button => {
        button.hidden = false;
        const container = button.parentElement.querySelector('[data-cpu-support-rows]');
        const sample = container.querySelector('[data-cpu-support-row]').cloneNode(true);
        let nextIndex = Math.max(-1, ...Array.from(container.querySelectorAll('input[name]'), input => {
            const match = input.name.match(/\[cpu_support\]\[(\d+)\]/);
            return match ? Number(match[1]) : -1;
        })) + 1;
        button.addEventListener('click', () => {
            if (container.children.length >= 50) return;
            const row = sample.cloneNode(true);
            const index = nextIndex++;
            row.querySelectorAll('input, select').forEach(input => {
                input.name = input.name.replace(/\[cpu_support\]\[\d+\]/, `[cpu_support][${index}]`);
                const oldId = input.id;
                input.id = oldId.replace(/-\d+-([a-z_]+)$/, `-${index}-$1`);
                row.querySelectorAll('label').forEach(label => { if (label.htmlFor === oldId) label.htmlFor = input.id; });
            });
            row.querySelector('[data-remove-cpu-support]').hidden = false;
            row.querySelector('option[value="supported"]')?.remove();
            row.querySelectorAll('input, select').forEach(input => { input.value = ''; });
            container.append(row);
            if (!picker) row.querySelector('input')?.focus();
        });
    });
    form.querySelectorAll('[data-remove-cpu-support]').forEach(button => { button.hidden = false; });
    form.addEventListener('click', event => {
        const button = event.target.closest('[data-remove-cpu-support]');
        if (!button) return;
        const row = button.closest('[data-cpu-support-row]');
        const nextFocus = row.nextElementSibling?.querySelector('input')
            ?? row.closest('.product-cpu-support').querySelector('[data-add-cpu-support]');
        row.remove();
        if (!picker) nextFocus?.focus();
    });
})();
