// Ponto de entrada JS do tema (ES6+, sem jQuery).
import '../css/main.css';

document.documentElement.classList.add('arena-ready');

/**
 * Mobile off-canvas menu: opens `#offcanvas-menu` (the SAME main-menu,
 * re-rendered server-side inside it as `.offcanvas-menu-list`) sliding in
 * from the left over a dimmed `#offcanvas-overlay`, with body scroll
 * locked while open. The hamburger handler (`.mobile-menu-toggle`) is a
 * real <button aria-expanded/aria-controls>; the panel's own close button,
 * the overlay, and Escape all close it. Pure vanilla ES6, no jQuery — the
 * desktop hover/focus-within mega-menu dropdown is CSS-only and untouched
 * by any of this.
 *
 * A second pass below wires up the nested accordion: each parent item
 * inside the off-canvas copy gets a tap-to-expand toggle injected next to
 * its link (the link itself keeps navigating normally on tap).
 */
function initOffCanvasMenu() {
    const toggle = document.querySelector('.mobile-menu-toggle');
    const panel = document.getElementById('offcanvas-menu');
    const overlay = document.getElementById('offcanvas-overlay');
    if (!toggle || !panel || !overlay) { return; }

    const closeBtn = panel.querySelector('.offcanvas-menu__close');

    const isOpen = () => panel.classList.contains('is-open');

    const open = () => {
        overlay.hidden = false;
        // One frame so the `hidden` removal takes effect before the
        // opacity transition starts (`hidden` and a 0-duration style
        // change in the same frame would otherwise skip the transition).
        window.requestAnimationFrame(() => {
            panel.classList.add('is-open');
            overlay.classList.add('is-open');
        });
        panel.setAttribute('aria-hidden', 'false');
        toggle.setAttribute('aria-expanded', 'true');
        toggle.classList.add('is-active');
        document.documentElement.classList.add('offcanvas-open');
    };

    const close = () => {
        panel.classList.remove('is-open');
        overlay.classList.remove('is-open');
        panel.setAttribute('aria-hidden', 'true');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.classList.remove('is-active');
        document.documentElement.classList.remove('offcanvas-open');
        window.setTimeout(() => {
            if (!isOpen()) { overlay.hidden = true; }
        }, 400);
    };

    toggle.addEventListener('click', () => {
        if (isOpen()) { close(); } else { open(); }
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', close);
    }

    overlay.addEventListener('click', close);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen()) {
            close();
        }
    });

    panel.querySelectorAll('.menu-item-has-children').forEach((item) => {
        const link = item.querySelector('a');
        const submenu = item.querySelector('.sub-menu');
        if (!link || !submenu) { return; }

        if (!submenu.id) {
            submenu.id = `offcanvas-submenu-${Math.random().toString(36).slice(2, 9)}`;
        }

        const expandBtn = document.createElement('button');
        expandBtn.type = 'button';
        expandBtn.className = 'offcanvas-item-toggle';
        expandBtn.setAttribute('aria-expanded', 'false');
        expandBtn.setAttribute('aria-controls', submenu.id);
        const label = link.textContent ? link.textContent.trim() : '';
        expandBtn.setAttribute('aria-label', label ? `Expandir ${label}` : 'Expandir submenu');
        expandBtn.innerHTML = '<span aria-hidden="true"></span>';

        item.insertBefore(expandBtn, submenu);

        expandBtn.addEventListener('click', () => {
            const expanded = item.classList.toggle('is-expanded');
            expandBtn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        });
    });
}

/**
 * "Smart" sticky main-menu bar: stays in normal flow until the page has
 * scrolled past the header, then pins to the viewport top (`.is-pinned`,
 * `position:fixed` — see main.css); while pinned, scrolling down hides it
 * (`.is-hidden`, `transform:translateY(-100%)`) and scrolling back up
 * reveals it again — measured against the reference's
 * `.bs-pinning-block.smart` behaviour. A spacer element reserves the bar's
 * own height while pinned so the page never jumps. `prefers-reduced-motion`
 * skips the hide/reveal transform (the bar simply stays pinned + visible).
 */
function initSmartStickyMenuBar() {
    const bar = document.querySelector('.main-menu-bar');
    const spacer = document.querySelector('.main-menu-bar-spacer');
    if (!bar || !spacer) { return; }

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    let triggerPoint = 0;
    let lastScrollY = window.scrollY;
    let ticking = false;

    const pin = () => {
        spacer.style.height = `${bar.offsetHeight}px`;
        spacer.classList.add('is-active');
        bar.classList.add('is-pinned');
    };

    const unpin = () => {
        bar.classList.remove('is-pinned', 'is-hidden');
        spacer.classList.remove('is-active');
        spacer.style.height = '';
    };

    const measure = () => {
        const wasPinned = bar.classList.contains('is-pinned');
        if (wasPinned) { unpin(); }
        triggerPoint = bar.getBoundingClientRect().top + window.scrollY;
        if (wasPinned) { pin(); }
    };

    const update = () => {
        ticking = false;
        const scrollY = window.scrollY;
        const shouldPin = scrollY > triggerPoint;
        const pinned = bar.classList.contains('is-pinned');

        if (shouldPin && !pinned) {
            pin();
        } else if (!shouldPin && pinned) {
            unpin();
        }

        if (bar.classList.contains('is-pinned') && !reduceMotion) {
            const scrollingDown = scrollY > lastScrollY;
            if (scrollingDown && scrollY > bar.offsetHeight) {
                bar.classList.add('is-hidden');
            } else {
                bar.classList.remove('is-hidden');
            }
        }

        lastScrollY = scrollY;
    };

    const onScroll = () => {
        if (!ticking) {
            ticking = true;
            window.requestAnimationFrame(update);
        }
    };

    measure();
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', measure);
}

document.addEventListener('DOMContentLoaded', () => {
    initOffCanvasMenu();
    initSmartStickyMenuBar();
});
