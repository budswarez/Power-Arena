<?php
declare(strict_types=1);

/**
 * Guards CSS-source regressions that a plain PHPUnit markup assertion can't
 * see on its own — main.css is the single source of truth for the
 * theme's visual output and has no automated (Lighthouse/visual) coverage
 * in this suite, so a couple of its rules are asserted directly here.
 *
 * BUG (task-uifix): `.content-container` is used for TWO unrelated things —
 * the page-shell landmark (`<main id="content" class="content-container">`,
 * template-parts/layout/content-open.php) AND every card partial's own
 * inner text wrapper (`<div class="content-container">`, card/featured.php,
 * card/hero.php, card/list.php). A bare `.content-container{background:#fff}`
 * selector matches BOTH — painting every card's title/meta block with the
 * shell's own white background, regardless of what section the card sits
 * in. Invisible on an ordinary light-scheme listing (white-on-white is the
 * same white), but inside `.bs-listing.bs-dark-scheme` ("Últimas notícias")
 * it produced a solid white rectangle behind each card's WHITE title text —
 * completely unreadable (white-on-white).
 */
final class StyleGuardTest extends WP_UnitTestCase {
    private function css(): string {
        $path = ARENA_DIR . '/assets/src/css/main.css';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /**
     * Extracts every LEAF rule (a selector list with no further nested
     * braces, i.e. not an `@layer`/`@media` wrapper itself) as
     * [selectors (trimmed, comma-split), declarations block].
     *
     * @return array<int, array{0: string[], 1: string}>
     */
    private function rules(string $css): array {
        // Strip comments FIRST: this file's own comments are full of
        // illustrative CSS-looking snippets (e.g. "`.foo{bar:baz}`"), whose
        // braces would otherwise throw off naive brace-pair matching below.
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);

        preg_match_all('/([^{}]+)\{([^{}]*)\}/s', $css, $matches, PREG_SET_ORDER);

        return array_map(
            static fn (array $m): array => [
                array_map('trim', explode(',', trim($m[1]))),
                $m[2],
            ],
            $matches
        );
    }

    /**
     * Root-cause fix: the page-shell rule must be scoped to the `<main>`
     * element (`main.content-container`), not a bare `.content-container`
     * class selector — the tag qualifier is what stops it from also
     * matching a card's own same-named inner `<div>`. Parses actual CSS
     * rules (selector-list -> declarations) rather than a single regex, so
     * a legitimate SCOPED descendant rule like `.bs-listing.bs-dark-scheme
     * .content-container { … }` is correctly told apart from a bare,
     * unscoped `.content-container { … }` rule matching every element with
     * that class anywhere in the document.
     */
    public function test_page_shell_surface_rule_is_scoped_to_the_main_element(): void {
        $css = $this->css();
        $rules = $this->rules($css);

        $shellRuleFound = false;
        foreach ($rules as [$selectors, $declarations]) {
            if (in_array('main.content-container', $selectors, true) && str_contains($declarations, 'background')) {
                $shellRuleFound = true;
            }

            $this->assertNotContains(
                '.content-container',
                $selectors,
                "A bare `.content-container { … }` selector (no `main` tag qualifier) also matches " .
                    "every card's own inner `.content-container` div — including inside a dark-scheme " .
                    "listing, hiding the white card title behind a white card background. Offending " .
                    "declarations: {$declarations}"
            );
        }

        $this->assertTrue($shellRuleFound, 'Expected a `main.content-container { background: … }` rule.');
    }

    /**
     * Defensive/robustness rule (belt-and-suspenders): even if a future
     * change reintroduces a light card surface some other way (a new
     * `--arena-surface`-style token applied generically to `.content-
     * container`, a shared "card" component class, etc.), a card rendered
     * inside `.bs-listing.bs-dark-scheme` must still win the cascade back
     * to a transparent background — by SPECIFICITY (3 classes), not by
     * source order, so it can't be silently defeated by a later rule of
     * equal-or-lower specificity added anywhere else in the file.
     */
    public function test_dark_scheme_cards_force_their_content_container_transparent(): void {
        $rules = $this->rules($this->css());

        $guardFound = false;
        foreach ($rules as [$selectors, $declarations]) {
            if (
                in_array('.bs-listing.bs-dark-scheme .content-container', $selectors, true)
                && preg_match('/background:\s*transparent/', $declarations) === 1
            ) {
                $guardFound = true;
            }
        }

        $this->assertTrue(
            $guardFound,
            'Expected a `.bs-listing.bs-dark-scheme .content-container { background: transparent; … }` ' .
                'guard rule, at a specificity no later light-surface rule can casually outrank.'
        );
    }

    /**
     * BUG 2 (task-uifix): the reference's "Últimas notícias" row is a plain
     * `vc_row wpb_row vc_row-fluid` + `vc_col-sm-12 vc_col-has-fill` — no
     * `vc_row-full-width`/`data-vc-full-width` — so its dark fill sits
     * INSIDE the same 1200px boxed column as the white content above it. An
     * earlier revision broke it out full-bleed (`width:100vw` + a negative
     * `margin-inline` trick); that must not come back.
     */
    public function test_dark_scheme_row_is_boxed_not_full_bleed(): void {
        $rules = $this->rules($this->css());

        $found = false;
        foreach ($rules as [$selectors, $declarations]) {
            if (!in_array('.bs-listing.bs-dark-scheme', $selectors, true)) {
                continue;
            }
            // This exact selector also carries an unrelated 1-declaration
            // rule (`margin-bottom: 0`, the "meets the footer" rhythm
            // fix) — only the block that also paints the dark fill itself
            // is the one this test cares about.
            if (!str_contains($declarations, 'background')) {
                continue;
            }
            $found = true;

            $this->assertDoesNotMatchRegularExpression(
                '/width:\s*100vw/',
                $declarations,
                'The dark-scheme row must not break out to full viewport width (width:100vw) — it must ' .
                    'stay boxed inside the same 1200px column as the rest of the page.'
            );
            $this->assertDoesNotMatchRegularExpression(
                '/margin-inline:\s*calc\(\s*50%/',
                $declarations,
                'The dark-scheme row must not use the `calc(50% - 50vw)` full-bleed break-out trick.'
            );
            $this->assertMatchesRegularExpression(
                '/padding-inline:\s*15px/',
                $declarations,
                'The dark-scheme row must use the SAME 15px inline padding as `.bs-listing.bs-light-scheme` ' .
                    'so its left/right edges line up with the white content block above it.'
            );
        }

        $this->assertTrue($found, 'Expected a `.bs-listing.bs-dark-scheme { … }` rule.');
    }

    /**
     * BUG 3 (task-uifix): `<body>`'s background is pure black and
     * `.main-content` carries no trailing bottom spacing of its own — a
     * positive `margin-top` on `.site-footer` opens a solid BLACK band
     * between the last content block and the footer on every page (this
     * theme fixed the mirror-image WHITE-band regression once already; a
     * margin on either side of that boundary reintroduces a coloured gap
     * from the opposite direction).
     */
    public function test_site_footer_has_no_top_margin_gap(): void {
        $rules = $this->rules($this->css());

        $found = false;
        foreach ($rules as [$selectors, $declarations]) {
            if (!in_array('.site-footer', $selectors, true)) {
                continue;
            }
            if (!str_contains($declarations, 'margin-top')) {
                continue;
            }
            $found = true;

            $this->assertMatchesRegularExpression(
                '/margin-top:\s*0\b/',
                $declarations,
                'The footer must not carry a positive `margin-top` — that paints as a stray black band ' .
                    'against the black body background, between the last content block and the footer.'
            );
        }

        $this->assertTrue($found, 'Expected `.site-footer { margin-top: 0; … }`.');
    }
}
