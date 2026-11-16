// Kiểm thử JavaScript độc lập, không thay dữ liệu hoặc cấu hình trình duyệt người dùng.
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { runInNewContext } = require('node:vm');
const source = readFileSync(require('node:path').join(__dirname, '../../public/js/news-home.js'), 'utf8');

function openPage({ reduced = false, storage = new Map(), blocked = false } = {}) {
    const handlers = {};
    const attributes = {};
    const body = { dataset: {} };
    const button = { hidden: true, disabled: false, textContent: '', title: '',
        setAttribute: (name, value) => { attributes[name] = value; },
        addEventListener: (name, callback) => { handlers[name] = callback; },
        click() { if (!this.disabled) handlers.click(); },
    };
    let changed;
    const preference = { matches: reduced, addEventListener: (_, callback) => { changed = callback; },
        change(value) { this.matches = value; changed(); },
    };
    runInNewContext(source, {
        document: { body, querySelector: () => button, addEventListener: (_, callback) => callback() },
        window: { matchMedia: () => preference, sessionStorage: {
            getItem(key) { if (blocked) throw new Error('Storage bị chặn'); return storage.get(key) ?? null; },
            setItem(key, value) { if (blocked) throw new Error('Storage bị chặn'); storage.set(key, value); },
        } },
    });
    return { button, attributes, body, preference, storage };
}

test('Tắt chuyển động được giữ khi mở lại trang trong cùng phiên', () => {
    const page = openPage();
    assert.equal(page.attributes['aria-pressed'], 'true');
    page.button.click();
    assert.equal(page.body.dataset.newsMotion, 'off');
    const reopened = openPage({ storage: page.storage });
    assert.equal(reopened.attributes['aria-pressed'], 'false');
    assert.equal(reopened.button.textContent, 'Chuyển động: Tắt');
    reopened.button.click();
    assert.equal(reopened.body.dataset.newsMotion, 'on');
});

test('Giảm chuyển động của hệ thống luôn tắt hiệu ứng, không ghi đè lựa chọn đã tắt', () => {
    const page = openPage({ reduced: true });
    assert.equal(page.button.disabled, true);
    assert.equal(page.attributes['aria-pressed'], 'false');
    assert.equal(page.button.textContent, 'Chuyển động: Đã giảm');
    page.button.click();
    assert.equal(page.body.dataset.newsMotion, 'off');
    page.preference.change(false);
    assert.equal(page.button.disabled, false);
    page.button.click();
    page.preference.change(true);
    page.preference.change(false);
    assert.equal(page.button.textContent, 'Chuyển động: Tắt');
});

test('Trình duyệt chặn lưu trữ vẫn bật tắt được, không làm lỗi trang', () => {
    const page = openPage({ blocked: true });
    assert.doesNotThrow(() => page.button.click());
    assert.equal(page.body.dataset.newsMotion, 'off');
    page.button.click();
    assert.equal(page.attributes['aria-pressed'], 'true');
});
