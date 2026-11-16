(() => {
    const picker = document.querySelector('[data-promotion-picker]');
    const search = picker?.querySelector('#promotion-product-search');
    if (!search) return;

    const normalize = value => value.toLowerCase().normalize('NFD').replace(/\p{M}/gu, '').replace(/đ/g, 'd');
    const groups = [...picker.querySelectorAll('[data-promotion-group]')].map(group => ({
        element: group,
        details: group.querySelector('details'),
        products: [...group.querySelectorAll('[data-promotion-product]')].map(element => ({
            element,
            input: element.querySelector('input'),
            name: element.querySelector('[data-product-name]').textContent,
            code: element.querySelector('[data-product-code]').textContent,
            text: normalize(element.querySelector('label').textContent),
        })),
    }));
    const products = groups.flatMap(group => group.products);
    const count = picker.querySelector('[data-promotion-count]');
    const selectedList = picker.querySelector('[data-promotion-selected]');
    const noResults = picker.querySelector('[data-promotion-no-results]');
    let savedOpen = null;

    function filterProducts() {
        const terms = normalize(search.value).trim().split(/\s+/).filter(Boolean);
        if (terms.length && savedOpen === null) savedOpen = groups.map(group => group.details.open);

        groups.forEach((group, index) => {
            group.products.forEach(product => {
                product.element.hidden = !terms.every(term => product.text.includes(term));
            });
            group.element.hidden = group.products.every(product => product.element.hidden);
            if (terms.length) group.details.open = !group.element.hidden;
            else if (savedOpen !== null) group.details.open = savedOpen[index];
        });
        if (!terms.length) savedOpen = null;
        noResults.hidden = groups.some(group => !group.element.hidden);
    }

    function showSelection() {
        const selected = products.filter(product => product.input.checked);
        count.textContent = `Đã chọn ${selected.length}/8 linh kiện (tối thiểu 2)`;
        picker.querySelector('[data-promotion-limit]').hidden = selected.length < 8;
        products.forEach(product => { product.input.disabled = selected.length >= 8 && !product.input.checked; });

        selectedList.replaceChildren(...selected.map(product => {
            const item = document.createElement('li');
            item.className = 'list-inline-item mb-1';
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-sm btn-outline-dark text-break text-start';
            const label = `${product.name} (${product.code})`;
            button.textContent = `${label} ×`;
            button.setAttribute('aria-label', `Bỏ chọn ${label}`);
            button.addEventListener('click', () => {
                product.input.checked = false;
                showSelection();
                search.focus();
            });
            item.append(button);
            return item;
        }));
    }

    search.addEventListener('input', filterProducts);
    search.addEventListener('keydown', event => {
        if (event.key === 'Enter') event.preventDefault();
    });
    picker.addEventListener('change', event => {
        if (event.target.matches('input[type=checkbox]')) showSelection();
    });
    picker.querySelector('[data-promotion-search]').hidden = false;
    picker.querySelector('[data-promotion-selection]').hidden = false;
    filterProducts();
    showSelection();
})();
