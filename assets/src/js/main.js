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
            // task-final-ui item 2 (CDP-driven finding): `.offcanvas-menu`'s
            // CLOSED state is `visibility:hidden` (main.css) — only
            // `.is-open` flips it to `visibility:visible`. A `visibility:
            // hidden` element can never become `document.activeElement`
            // (the browser silently no-ops the .focus() call), so calling
            // closeBtn.focus() BEFORE this class lands (as this used to do,
            // synchronously in open() itself, a whole frame earlier) left
            // focus stuck on the hamburger toggle — the exact
            // dialog-never-receives-focus bug the comment below already
            // warns about, just reintroduced by the ordering. Moving the
            // focus call to right after `is-open` is added (still
            // synchronous within this same rAF callback, so no extra frame
            // of delay) means `visibility` has already resolved to
            // `visible` by the time `.focus()` runs.
            if (closeBtn) { closeBtn.focus(); }
        });
        panel.setAttribute('aria-hidden', 'false');
        toggle.setAttribute('aria-expanded', 'true');
        toggle.classList.add('is-active');
        document.documentElement.classList.add('offcanvas-open');
        // Moves focus INTO the panel (mirrors initSearchToggle()'s own
        // open() focusing its field) — a `role="dialog"` that never
        // receives focus on open would leave a keyboard user's focus
        // sitting on the now off-screen-again hamburger button while the
        // dialog itself is announced. The close button is the panel's
        // first focusable element in DOM order.
    };

    const close = () => {
        panel.classList.remove('is-open');
        overlay.classList.remove('is-open');
        panel.setAttribute('aria-hidden', 'true');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.classList.remove('is-active');
        document.documentElement.classList.remove('offcanvas-open');
        // Returns focus to the toggle that opened the panel — mirrors
        // initSearchToggle()'s close({refocus:true}) — regardless of which
        // of the 3 close paths (close button, overlay click, Escape)
        // triggered it, since the hamburger is always the sensible next
        // focus target once the dialog is gone.
        toggle.focus();
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
        // i18n strings localized via wp_localize_script() (see
        // Arena\Assets::enqueue()) — `window.arenaI18n` is only absent
        // outside a real WP page load, so the literal pt-BR copy below is
        // strictly a defensive fallback, never the normal path.
        const i18n = window.arenaI18n || {
            expandSubmenuWithLabel: 'Expandir %s',
            expandSubmenu: 'Expandir submenu',
        };
        expandBtn.setAttribute(
            'aria-label',
            label ? i18n.expandSubmenuWithLabel.replace('%s', label) : i18n.expandSubmenu
        );
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

    // Throttled the same way onScroll() is (whole-branch review, minor
    // finding #12): `measure()` forces 3 reflows (unpin()'s style reset,
    // getBoundingClientRect()'s layout read, pin()'s style write) and used
    // to run on EVERY raw `resize` event — which can fire far more than
    // once per animation frame while a window edge is being dragged.
    // Coalescing to at most one `measure()` per frame keeps the cost the
    // same regardless of how many raw events land in that frame.
    let resizeTicking = false;
    const onResize = () => {
        if (!resizeTicking) {
            resizeTicking = true;
            window.requestAnimationFrame(() => {
                resizeTicking = false;
                measure();
            });
        }
    };

    measure();
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onResize);
}

/**
 * Search toggle: the magnifier button on the red menu bar (desktop AND
 * mobile) reveals a minimal search field (`.header-search-form`, already
 * present in the markup, CSS-hidden by default — see
 * `template-parts/header/search.php` + main.css) rather than navigating
 * anywhere itself. Vanilla ES6, mirrors initOffCanvasMenu()'s own
 * open/close/Escape shape: a real <button aria-expanded>, Escape closes
 * and returns focus to the toggle, and clicking outside the widget also
 * closes it (the form has no visible "close" button of its own, unlike the
 * off-canvas panel).
 */
function initSearchToggle() {
    const wrapper = document.querySelector('.header-search');
    const toggle = wrapper ? wrapper.querySelector('.search-toggle') : null;
    const form = wrapper ? wrapper.querySelector('.header-search-form') : null;
    const input = form ? form.querySelector('.search-field') : null;
    if (!wrapper || !toggle || !form) { return; }

    const isOpen = () => toggle.getAttribute('aria-expanded') === 'true';

    const open = () => {
        toggle.setAttribute('aria-expanded', 'true');
        if (input) { input.focus(); }
    };

    const close = ({ refocus = false } = {}) => {
        toggle.setAttribute('aria-expanded', 'false');
        if (refocus) { toggle.focus(); }
    };

    toggle.addEventListener('click', () => {
        if (isOpen()) { close(); } else { open(); }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen()) {
            close({ refocus: true });
        }
    });

    document.addEventListener('click', (event) => {
        if (isOpen() && !wrapper.contains(event.target)) {
            close();
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initOffCanvasMenu();
    initSearchToggle();
    initSmartStickyMenuBar();
});
