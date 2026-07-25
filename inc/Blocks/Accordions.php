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
     * @param array<string, mixed>|string $atts
     */
    public static function renderAccordions(array|string $atts, ?string $content = null): string {
        $atts = shortcode_atts(['title' => ''], $atts, 'accordions');
        $title = trim((string) $atts['title']);

        $heading = $title !== ''
            ? '<div class="arena-accordion__heading">' . esc_html($title) . '</div>'
            : '';

        $inner = $content !== null ? do_shortcode($content) : '';

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
