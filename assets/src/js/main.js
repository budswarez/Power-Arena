// Ponto de entrada JS do tema (ES6+, sem jQuery).
import '../css/main.css';

document.documentElement.classList.add('arena-ready');

/**
 * Mobile mega-menu toggle: flips `.is-open` on the `.main-menu-bar` band so
 * the (otherwise `display:none` below the mobile breakpoint) menu list
 * shows/hides. Pure vanilla JS, no framework — the desktop hover/
 * focus-within dropdown behaviour is CSS-only and untouched by this.
 */
function initMobileMenuToggle() {
    const bar = document.querySelector('.main-menu-bar');
    const toggle = document.querySelector('.mobile-menu-toggle');
    if (!bar || !toggle) { return; }

    toggle.addEventListener('click', () => {
        const isOpen = bar.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
}

document.addEventListener('DOMContentLoaded', initMobileMenuToggle);
