<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Style;

use Symfony\Component\Tui\Widget\AbstractWidget;

/**
 * A collection of style rules with CSS-like selectors.
 *
 * Selectors can be:
 * - FQCN: 'Symfony\Component\Tui\Widget\Input' or Input::class
 * - FQCN with state: 'Symfony\Component\Tui\Widget\Input:focus'
 * - CSS class: '.sidebar'
 * - CSS class with state: '.sidebar:focus'
 * - Standalone pseudo-class: ':root' (matches the root widget)
 * - Universal: '*' (matches all widgets)
 * - Sub-element (pseudo-element): SelectList::class.'::selected'
 * - Sub-element with state: SelectList::class.'::selected:focus'
 * - Class sub-element: '.my-list::selected'
 * - Class sub-element with state: '.my-list::selected:focus'
 *
 * ## Style Inheritance
 *
 * When resolving styles, rules are applied in this order (later rules override earlier):
 * 1. Universal selector ('*')
 * 2. Widget FQCN selector (e.g., Text::class)
 * 3. CSS class selectors (e.g., '.header')
 * 4. State selectors (e.g., ':root', Input::class.':focus')
 * 5. Instance style (widget's own setStyle())
 *
 * All style properties use `null` to mean "inherit from earlier rules":
 *
 *     // This rule sets color but not bold - bold will be inherited
 *     $stylesheet->addRule('.link', new Style()->withColor('blue'));
 *
 * To explicitly override inherited values:
 *
 *     // Explicitly set padding to 0, overriding any inherited padding
 *     $stylesheet->addRule('.no-padding', Style::padding([0]));
 *
 *     // Explicitly disable bold, overriding a parent's bold=true
 *     $stylesheet->addRule('.normal', new Style()->withBold(false));
 *
 * ## Cascading Stylesheets
 *
 * Multiple stylesheets can be merged together like CSS cascading:
 * later rules override earlier ones with the same selector.
 *
 *     $defaults = new StyleSheet([
 *         Overlay::class => new Style()->withBackground('#1e1e2e'),
 *     ]);
 *
 *     $theme = new StyleSheet([
 *         Overlay::class => new Style()->withBorder([1]),
 *     ]);
 *
 *     // Merge theme on top of defaults - theme rules override defaults
 *     $merged = $defaults->merge($theme);
 *
 * ## Responsive Breakpoints
 *
 * Rules can be scoped to terminal width using breakpoints, similar to CSS
 * `@media (min-width: ...)`. Breakpoint rules apply when the terminal has
 * at least the specified number of columns:
 *
 *     $stylesheet->addBreakpoint(120, '.panes', new Style(direction: Direction::Horizontal));
 *     // Below 120 columns: .panes uses default (vertical)
 *     // At 120+ columns: .panes switches to horizontal
 *
 * Multiple breakpoints can be defined. They are evaluated in ascending order
 * of column threshold, so narrower breakpoints are overridden by wider ones
 * when both match.
 *
 * Breakpoint rules are applied after base rules and state selectors but
 * before instance styles in the cascade.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class StyleSheet
{
    /** @var array<int, array<string, Style>> Breakpoint rules keyed by min-columns threshold */
    private array $breakpoints = [];

    /**
     * @param array<string, Style> $rules Map of selectors to styles
     */
    public function __construct(
        private array $rules = [],
    ) {
    }

    /**
     * Add a rule to the stylesheet.
     *
     * @return $this
     */
    public function addRule(string $selector, Style $style): static
    {
        $this->rules[$selector] = $style;

        return $this;
    }

    /**
     * Add a responsive breakpoint rule.
     *
     * The rule applies only when the terminal has at least $minColumns columns.
     * This is equivalent to CSS `@media (min-width: ...)`.
     *
     * @return $this
     */
    public function addBreakpoint(int $minColumns, string $selector, Style $style): static
    {
        $this->breakpoints[$minColumns][$selector] = $style;

        return $this;
    }

    /**
     * Merge another stylesheet's rules into this one.
     *
     * Rules from the other stylesheet override rules in this one
     * for the same selector. This works like CSS cascading: later
     * stylesheets win.
     *
     * @return $this
     */
    public function merge(self $other): static
    {
        foreach ($other->rules as $selector => $style) {
            $this->rules[$selector] = $style;
        }

        foreach ($other->breakpoints as $minColumns => $rules) {
            foreach ($rules as $selector => $style) {
                $this->breakpoints[$minColumns][$selector] = $style;
            }
        }

        return $this;
    }

    /**
     * Merge another stylesheet's rules as defaults (lower priority).
     *
     * Rules from the other stylesheet are added only for selectors
     * that are not already defined in this stylesheet. This is the
     * reverse of merge(): existing rules are preserved.
     *
     * @return $this
     */
    public function mergeDefaults(self $defaults): static
    {
        foreach ($defaults->rules as $selector => $style) {
            if (!isset($this->rules[$selector])) {
                $this->rules[$selector] = $style;
            }
        }

        foreach ($defaults->breakpoints as $minColumns => $rules) {
            foreach ($rules as $selector => $style) {
                if (!isset($this->breakpoints[$minColumns][$selector])) {
                    $this->breakpoints[$minColumns][$selector] = $style;
                }
            }
        }

        return $this;
    }

    /**
     * Get all rules.
     *
     * @return array<string, Style>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Resolve the style for a widget by merging applicable rules.
     *
     * Resolution order (later overrides earlier):
     * 1. Universal selector (*)
     * 2. FQCN selector (widget class and parent classes, parent first)
     * 3. CSS class selectors (.class): only classes from {@see getCssClasses()}
     * 4. State selectors (:focus, :disabled, etc.)
     * 5. Breakpoint rules (ascending min-columns order)
     * 6. Extra styles from subclasses (see {@see resolveExtraStyles()})
     * 7. Instance style (widget's own style)
     *
     * @param int|null $columns Current terminal width (for responsive breakpoints)
     */
    public function resolve(AbstractWidget $widget, ?int $columns = null): Style
    {
        $fqcn = $widget::class;
        $cssClasses = $this->getCssClasses($widget);
        $classHierarchy = static::getClassHierarchy($fqcn);
        $applicableStyles = [];

        // 1. Universal selector
        if (isset($this->rules['*'])) {
            $applicableStyles[] = $this->rules['*'];
        }

        // 2. FQCN selector (walk class hierarchy, parent classes first for lower priority)
        foreach ($classHierarchy as $class) {
            if (isset($this->rules[$class])) {
                $applicableStyles[] = $this->rules[$class];
            }
        }

        // 3. CSS class selectors
        foreach ($cssClasses as $class) {
            $selector = '.'.$class;
            if (isset($this->rules[$selector])) {
                $applicableStyles[] = $this->rules[$selector];
            }
        }

        // 4. State selectors (applied standalone, to FQCN hierarchy and CSS classes)
        foreach ($widget->getStateFlags() as $state) {
            // :state (standalone pseudo-class, e.g. :root)
            if (isset($this->rules[':'.$state])) {
                $applicableStyles[] = $this->rules[':'.$state];
            }

            // FQCN:state (walk class hierarchy, parent classes first)
            foreach ($classHierarchy as $class) {
                $classStateSelector = $class.':'.$state;
                if (isset($this->rules[$classStateSelector])) {
                    $applicableStyles[] = $this->rules[$classStateSelector];
                }
            }

            // .class:state
            foreach ($cssClasses as $class) {
                $classStateSelector = '.'.$class.':'.$state;
                if (isset($this->rules[$classStateSelector])) {
                    $applicableStyles[] = $this->rules[$classStateSelector];
                }
            }
        }

        // 5. Breakpoint rules (ascending min-columns order)
        if (null !== $columns && $this->breakpoints) {
            $applicableStyles = $this->resolveBreakpoints($widget, $columns, $applicableStyles, $cssClasses);
        }

        // 6. Extra styles from subclasses (e.g. utility classes)
        $applicableStyles = $this->resolveExtraStyles($widget, $applicableStyles);

        // 7. Instance style
        if ($widget->getStyle()) {
            $applicableStyles[] = $widget->getStyle();
        }

        // Merge all applicable styles
        return static::mergeStyles($applicableStyles);
    }

    /**
     * Resolve the style for a sub-element of a widget.
     *
     * Sub-elements are parts within a widget (e.g., "selected item", "description")
     * that need independent styling. They use CSS pseudo-element syntax (::).
     *
     * Resolution order (later overrides earlier):
     * 1. FQCN::element (e.g., SelectListWidget::class.'::selected')
     * 2. .class::element (e.g., '.my-list::selected')
     * 3. FQCN::element:state (e.g., SelectListWidget::class.'::selected:focus')
     * 4. .class::element:state (e.g., '.my-list::selected:focus')
     *
     * Example stylesheet rules:
     *
     *     SelectListWidget::class.'::selected'         => new Style()->withBold(),
     *     SelectListWidget::class.'::selected:focus'  => new Style()->withBold()->withColor('cyan'),
     *     '.my-list::selected'                          => new Style()->withColor('green'),
     */
    public function resolveElement(AbstractWidget $widget, string $element): Style
    {
        $fqcn = $widget::class;
        $cssClasses = $this->getCssClasses($widget);
        $classHierarchy = static::getClassHierarchy($fqcn);
        $applicableStyles = [];

        // 1. FQCN::element (walk class hierarchy, parent classes first for lower priority)
        foreach ($classHierarchy as $class) {
            $selector = $class.'::'.$element;
            if (isset($this->rules[$selector])) {
                $applicableStyles[] = $this->rules[$selector];
            }
        }

        // 2. .class::element
        foreach ($cssClasses as $class) {
            $selector = '.'.$class.'::'.$element;
            if (isset($this->rules[$selector])) {
                $applicableStyles[] = $this->rules[$selector];
            }
        }

        // 3. FQCN::element:state and .class::element:state
        foreach ($widget->getStateFlags() as $state) {
            // Walk class hierarchy for state selectors too
            foreach ($classHierarchy as $class) {
                $selector = $class.'::'.$element.':'.$state;
                if (isset($this->rules[$selector])) {
                    $applicableStyles[] = $this->rules[$selector];
                }
            }

            foreach ($cssClasses as $class) {
                $selector = '.'.$class.'::'.$element.':'.$state;
                if (isset($this->rules[$selector])) {
                    $applicableStyles[] = $this->rules[$selector];
                }
            }
        }

        return static::mergeStyles($applicableStyles);
    }

    /**
     * Return the widget classes that participate in CSS selector matching.
     *
     * By default, all style classes are CSS classes. Subclasses may override
     * this to filter out classes handled separately (e.g. utility classes).
     *
     * @return string[]
     */
    protected function getCssClasses(AbstractWidget $widget): array
    {
        return $widget->getStyleClasses();
    }

    /**
     * Hook for subclasses to inject extra styles into the cascade.
     *
     * Called after breakpoint rules and before the instance style.
     * The default implementation returns the styles unchanged.
     *
     * @param Style[] $applicableStyles Current styles in cascade order
     *
     * @return Style[]
     */
    protected function resolveExtraStyles(AbstractWidget $widget, array $applicableStyles): array
    {
        return $applicableStyles;
    }

    /**
     * Resolve breakpoint rules that apply at the given column width.
     *
     * Evaluates breakpoints in ascending order of min-columns threshold.
     * Each matching breakpoint's rules go through the same selector matching
     * as base rules (universal, FQCN, CSS class, state).
     *
     * @param Style[]  $applicableStyles Current styles in cascade order
     * @param string[] $cssClasses       CSS classes to use for selector matching
     *
     * @return Style[]
     */
    protected function resolveBreakpoints(AbstractWidget $widget, int $columns, array $applicableStyles, array $cssClasses): array
    {
        $classHierarchy = static::getClassHierarchy($widget::class);

        // Sort breakpoints by threshold ascending so narrower ones apply first
        $thresholds = array_keys($this->breakpoints);
        sort($thresholds);

        foreach ($thresholds as $minColumns) {
            if ($columns < $minColumns) {
                continue;
            }

            $rules = $this->breakpoints[$minColumns];

            // Apply same selector matching as base rules
            if (isset($rules['*'])) {
                $applicableStyles[] = $rules['*'];
            }

            foreach ($classHierarchy as $class) {
                if (isset($rules[$class])) {
                    $applicableStyles[] = $rules[$class];
                }
            }

            foreach ($cssClasses as $class) {
                $selector = '.'.$class;
                if (isset($rules[$selector])) {
                    $applicableStyles[] = $rules[$selector];
                }
            }

            foreach ($widget->getStateFlags() as $state) {
                if (isset($rules[':'.$state])) {
                    $applicableStyles[] = $rules[':'.$state];
                }

                foreach ($classHierarchy as $class) {
                    $classStateSelector = $class.':'.$state;
                    if (isset($rules[$classStateSelector])) {
                        $applicableStyles[] = $rules[$classStateSelector];
                    }
                }

                foreach ($cssClasses as $class) {
                    $classStateSelector = '.'.$class.':'.$state;
                    if (isset($rules[$classStateSelector])) {
                        $applicableStyles[] = $rules[$classStateSelector];
                    }
                }
            }
        }

        return $applicableStyles;
    }

    /**
     * Get the class hierarchy for a widget class (parent classes first, concrete class last).
     *
     * Stops at AbstractWidget (excluded) since rules should not target it directly.
     *
     * @return string[]
     */
    protected static function getClassHierarchy(string $fqcn): array
    {
        $hierarchy = [];
        $class = $fqcn;

        while ($class && AbstractWidget::class !== $class) {
            $hierarchy[] = $class;
            $class = get_parent_class($class);
        }

        return array_reverse($hierarchy);
    }

    /**
     * Merge multiple styles into one.
     *
     * Later styles override earlier ones for non-null properties.
     * Uses {@see Style::mergeAll()} for a single-pass merge that
     * allocates one Style object instead of N-1 intermediates.
     *
     * @param Style[] $styles
     */
    protected static function mergeStyles(array $styles): Style
    {
        if (!$styles) {
            return new Style();
        }

        if (1 === \count($styles)) {
            return $styles[0];
        }

        return Style::mergeAll($styles);
    }
}
