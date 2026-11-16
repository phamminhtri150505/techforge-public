(() => {
    'use strict';
    const links = [...document.querySelectorAll('.article-outline a[href^="#"]')];
    if (!links.length) return;
    const sections = links.map(link => document.getElementById(link.hash.slice(1)));
    const header = document.querySelector('.site-header');
    let pending = false;
    function update() {
        pending = false;
        const scrollPadding = parseFloat(getComputedStyle(document.documentElement).scrollPaddingTop) || 0;
        const offset = Math.max((header?.getBoundingClientRect().height || 90) + 35, scrollPadding + 24);
        let active = -1;
        sections.forEach((section, index) => {
            if (section && section.getBoundingClientRect().top <= offset) active = index;
        });
        links.forEach((link, index) => {
            if (index === active) link.setAttribute('aria-current', 'location');
            else link.removeAttribute('aria-current');
        });
    }
    function scheduleUpdate() {
        if (!pending) { pending = true; requestAnimationFrame(update); }
    }
    window.addEventListener('scroll', scheduleUpdate, { passive: true });
    window.addEventListener('resize', scheduleUpdate, { passive: true });
    update();
})();
