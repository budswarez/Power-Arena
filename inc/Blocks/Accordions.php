<?php
declare(strict_types=1);

namespace Arena\Blocks;

/**
 * Reproduces Publisher's own `[accordions]`/`[accordion]` shortcodes —
 * unregistered by Arena until now, so they rendered as literal shortcode
 * text at the very top of nearly every imported article (measured on the
 * production content: `[accordion` appears 818x across 846 published
 * posts). This is the "Resumo da matéria" (article summary) box.
 *
 * Clean-room re-implementation: the reference site's own markup is a
 * Bootstrap `.panel-collapse.collapse` toggled by `data-toggle="collapse"`
 * jQuery/Bootstrap JS, which this theme deliberately carries neither of.
 * `<details>`/`<summary>` gives the exact same disclosure behaviour
 * (collapsed vs. expanded, keyboard accessible via native Enter/Space on
 * `<summary>`, degrades to always-visible content with CSS/JS disabled)
 * with zero script.
 */
final class Accordions {
    public static function register(): void {
        add_shortcode('accordions', [self::class, 'renderAccordions']);
        add_shortcode('accordion', [self::class, 'renderAccordion']);
    }

    /**
     * Wrapper: an optional heading (only when `title` is non-empty — most
     * production usage passes `title=""`, see the doc-comment above) plus
     * a container for one or more `[accordion]` panels. `$content` holds
     * the raw, not-yet-processed inner shortcodes; `do_shortcode()` here is
     * what expands the nested `[accordion]` tags into their own markup.
     *
     * Whole-branch review, minor finding #8 — two fixes to `$content`
     * before it's expanded:
     *
     * 1. Stray `<br />`: `the_content` runs `wpautop()` (priority 10)
     *    BEFORE `do_shortcode()` (priority 11), so by the time this handler
     *    runs, wpautop has already turned the newline right after
     *    `[accordions title=""]` and right before `[/accordions]` into a
     *    literal `<br />` — measured surviving into the rendered summary
     *    box on ~96% of imported articles. Stripped here (leading/trailing
     *    only — never touches whitespace/`<br />` in the MIDDLE of the
     *    content, i.e. between sibling `[accordion]` tags).
     * 2. Sanitizing: unlike `renderAccordion()` below, this runs
     *    `wp_kses_post()` on the RAW text, deliberately BEFORE
     *    `do_shortcode()`, not after. Doing it after would strip the
     *    `<details>`/`<summary>` markup the nested `[accordion]` shortcode
     *    itself produces (neither tag is in `wp_kses_post()`'s default
     *    allowed-tags list) — i.e. it would delete the very panels this
     *    shortcode exists to render. Running it on the raw text instead
     *    only strips disallowed HTML placed directly inside
     *    `[accordions]...[/accordions]` OUTSIDE any `[accordion]` panel —
     *    previously the one gap left entirely unsanitized here — while
     *    leaving the `[accordion ...]` bracket syntax itself untouched
     *    (kses only acts on `<...>` HTML tags, not `[...]` shortcode text).
     *
     * @param array<string, mixed>|string $atts
     */
    public static function renderAccordions(array|string $atts, ?string $content = null): string {
        $atts = shortcode_atts(['title' => ''], $atts, 'accordions');
        $title = trim((string) $atts['title']);

        $heading = $title !== ''
            ? '<div class="arena-accordion__heading">' . esc_html($title) . '</div>'
            : '';

        $raw = $content ?? '';
        $raw = (string) preg_replace('/^(?:\s|<br\s*\/?>)+/i', '', $raw);
        $raw = (string) preg_replace('/(?:\s|<br\s*\/?>)+$/i', '', $raw);
        $raw = wp_kses_post($raw);

        $inner = do_shortcode($raw);

        return '<div class="arena-accordion">' . $heading . $inner . '</div>';
    }

    /**
     * One collapsible panel. `load="hide"` is the ONLY value that starts
     * the panel collapsed (matches the reference's own convention) — any
     * other value, or the attribute missing entirely, renders it open by
     * default so a summary box degrades to fully-readable content.
     *
     * Panel body: nested shortcodes are expanded first (`do_shortcode()`),
     * then sanitized (`wp_kses_post()` — this is post content, not a
     * hardcoded string, so `esc_html()` would be wrong here: it would
     * escape the intentional HTML producing literal tags on the page),
     * then `wpautop()` turns the raw `- item` newline-separated lines from
     * the reference content into readable paragraphs/line breaks.
     *
     * Risk noted in whole-branch review, minor finding #8: because
     * `do_shortcode()` runs BEFORE `wp_kses_post()`, a nested embed
     * shortcode (`[embed]`/`[video]`) inside a panel's body that expands to
     * an `<iframe>` would have that `<iframe>` stripped by the
     * `wp_kses_post()` pass right after (its default allowed-tags list has
     * no `<iframe>`). Assessed as a low/theoretical risk for THIS content,
     * not fixed: these boxes are the "Resumo da matéria" bullet-point
     * summary box (see the class doc-comment — newline-separated `- item`
     * lines lifted straight from the reference content), never a place the
     * reference site puts video embeds. Kept the safe-by-default order
     * (strip anything unexpected) rather than widen the allowed-tags list
     * for a use case that doesn't occur in the actual content — revisit if
     * that ever changes.
     *
     * @param array<string, mixed>|string $atts
     */
    public static function renderAccordion(array|string $atts, ?string $content = null): string {
        $atts = shortcode_atts(['title' => '', 'load' => ''], $atts, 'accordion');
        $title = trim((string) $atts['title']);
        $isCollapsed = strtolower(trim((string) $atts['load'])) === 'hide';

        $body = $content !== null ? do_shortcode($content) : '';
        $body = wp_kses_post($body);
        $body = wpautop($body);

        return '<details class="arena-accordion__item"' . ($isCollapsed ? '' : ' open') . '>'
            . '<summary class="arena-accordion__title">' . esc_html($title) . '</summary>'
            . '<div class="arena-accordion__body">' . $body . '</div>'
            . '</details>';
    }
}
