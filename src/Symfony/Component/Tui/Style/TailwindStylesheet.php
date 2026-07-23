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
 * A stylesheet that supports Tailwind-like utility classes.
 *
 * Utility classes are parsed from widget style classes and dynamically
 * converted to Style objects. They coexist with regular CSS-like rules
 * and take precedence over them in the cascade (they are "immutable").
 *
 * ## Supported utility classes
 *
 * ### Padding
 *     p-{n}               All sides
 *     px-{n}              Left and right
 *     py-{n}              Top and bottom
 *     pt-{n} pr-{n} pb-{n} pl-{n}   Individual sides
 *
 * ### Border
 *     border               All sides, width 1
 *     border-{n}           All sides, width n
 *     border-t border-r border-b border-l             Individual side, width 1
 *     border-t-{n} border-r-{n} border-b-{n} border-l-{n}  Individual side, width n
 *     border-none          Remove border
 *     border-{pattern}     Pattern: normal, rounded, double, tall, wide, tall-medium, wide-medium, tall-large, wide-large
 *     border-{color}       Color: {family}-{shade}, [#hex], or palette index
 *
 * ### Background color
 *     bg-{family}-{shade}  Tailwind shade (e.g., bg-red-300, bg-emerald-700)
 *     bg-[#hex]            Hex color (e.g., bg-[#ff5500], bg-[#f50])
 *     bg-{0-255}           256-palette index
 *
 * ### Text color
 *     text-{family}-{shade} Tailwind shade (e.g., text-blue-700, text-sky-400)
 *     text-[#hex]           Hex color
 *     text-{0-255}          256-palette index
 *
 * Color families: slate, gray, zinc, neutral, stone, red, orange, amber,
 * yellow, lime, green, emerald, teal, cyan, sky, blue, indigo, violet,
 * purple, fuchsia, pink, rose. Shade numbers: 50–950.
 *
 * ### Text decoration
 *     bold / not-bold
 *     dim / not-dim
 *     italic / not-italic
 *     underline / no-underline
 *     line-through / no-line-through
 *     reverse / no-reverse
 *
 * ### Text alignment
 *     text-left             Left-align text (default)
 *     text-center           Center-align text
 *     text-right            Right-align text
 *
 * ### Font
 *     font-{name}           FIGlet font (big, small, slant, standard, mini, or path)
 *
 * ### Layout
 *     flex-row              Horizontal direction
 *     flex-col              Vertical direction
 *     flex-{n}              Flex grow weight (0 = intrinsic width, 1+ = proportional)
 *     gap-{n}               Gap between children
 *     hidden               Hide widget
 *     visible              Show widget
 *     align-left            Left-align child widgets (default)
 *     align-center          Center child widgets horizontally
 *     align-right           Right-align child widgets
 *     valign-top            Top-align child widgets
 *     valign-center         Center child widgets vertically
 *     valign-bottom         Bottom-align child widgets (default)
 *
 * ## Cascade order
 *
 * Inherits the standard {@see StyleSheet} cascade, with utility classes
 * injected at step 6 (above breakpoints, below instance style):
 * 1. Universal selector (*)
 * 2. FQCN selector
 * 3. CSS class selectors (.class): utility classes excluded
 * 4. State selectors (:focus)
 * 5. Breakpoint rules
 * 6. **Utility class styles** (immutable, override all above)
 * 7. Instance style (widget's own setStyle())
 *
 * ## Composability
 *
 * Multiple utility classes compose naturally:
 *
 *     $widget->addStyleClass('p-2')
 *            ->addStyleClass('bg-red-500')
 *            ->addStyleClass('bold')
 *            ->addStyleClass('border')
 *            ->addStyleClass('border-rounded')
 *            ->addStyleClass('border-cyan-400');
 *
 * Border-related classes (width, pattern, color) are combined into
 * a single Border object. When the same property is set twice,
 * the last class wins (e.g., `p-2 p-4` results in padding 4).
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TailwindStylesheet extends StyleSheet
{
    /**
     * Tailwind color palette: base (500) hex values.
     *
     * These are the official Tailwind CSS v4 color families.
     * Shade variants (e.g. red-300) are computed from these by
     * tinting toward white or shading toward black.
     */
    private const TAILWIND_COLORS = [
        'slate' => '#64748b',
        'gray' => '#6b7280',
        'zinc' => '#71717a',
        'neutral' => '#737373',
        'stone' => '#78716c',
        'red' => '#ef4444',
        'orange' => '#f97316',
        'amber' => '#f59e0b',
        'yellow' => '#eab308',
        'lime' => '#84cc16',
        'green' => '#22c55e',
        'emerald' => '#10b981',
        'teal' => '#14b8a6',
        'cyan' => '#06b6d4',
        'sky' => '#0ea5e9',
        'blue' => '#3b82f6',
        'indigo' => '#6366f1',
        'violet' => '#8b5cf6',
        'purple' => '#a855f7',
        'fuchsia' => '#d946ef',
        'pink' => '#ec4899',
        'rose' => '#f43f5e',
    ];

    /**
     * Maps Tailwind shade numbers to scale() percentages.
     *
     * Negative = tint (lighter), positive = shade (darker), 0 = base.
     */
    private const SHADE_SCALE = [
        50 => -95,
        100 => -80,
        200 => -60,
        300 => -40,
        400 => -20,
        500 => 0,
        600 => 20,
        700 => 40,
        800 => 60,
        900 => 80,
        950 => 90,
    ];

    protected function getCssClasses(AbstractWidget $widget): array
    {
        return $this->partitionClasses($widget)['css'];
    }

    protected function resolveExtraStyles(AbstractWidget $widget, array $applicableStyles): array
    {
        $utilityClasses = $this->partitionClasses($widget)['utility'];

        if (!$utilityClasses) {
            return $applicableStyles;
        }

        $utilityStyle = $this->resolveUtilityClasses($utilityClasses);
        if (null !== $utilityStyle) {
            $applicableStyles[] = $utilityStyle;
        }

        return $applicableStyles;
    }

    /**
     * Partition widget classes into CSS classes and utility classes.
     *
     * @return array{css: string[], utility: string[]}
     */
    private function partitionClasses(AbstractWidget $widget): array
    {
        $css = [];
        $utility = [];

        foreach ($widget->getStyleClasses() as $class) {
            if (null !== $this->parseSingleUtility($class)) {
                $utility[] = $class;
            } else {
                $css[] = $class;
            }
        }

        return ['css' => $css, 'utility' => $utility];
    }

    /**
     * Resolve all utility classes into a single combined Style.
     *
     * @param string[] $classes Utility class names
     */
    private function resolveUtilityClasses(array $classes): ?Style
    {
        /** @var array<string, mixed> $slots */
        $slots = [];
        foreach ($classes as $class) {
            $parsed = $this->parseSingleUtility($class);
            if (null !== $parsed) {
                $slots = array_merge($slots, $parsed);
            }
        }

        if (!$slots) {
            return null;
        }

        return $this->buildStyleFromSlots($slots);
    }

    /**
     * Parse a single utility class name into property slots.
     *
     * Returns null if the class is not a recognized utility.
     *
     * @return array<string, mixed>|null
     */
    private function parseSingleUtility(string $class): ?array
    {
        // === PADDING ===
        if (preg_match('/^p-(\d+)$/', $class, $m)) {
            $v = (int) $m[1];

            return ['pt' => $v, 'pr' => $v, 'pb' => $v, 'pl' => $v];
        }
        if (preg_match('/^px-(\d+)$/', $class, $m)) {
            $v = (int) $m[1];

            return ['pr' => $v, 'pl' => $v];
        }
        if (preg_match('/^py-(\d+)$/', $class, $m)) {
            $v = (int) $m[1];

            return ['pt' => $v, 'pb' => $v];
        }
        if (preg_match('/^pt-(\d+)$/', $class, $m)) {
            return ['pt' => (int) $m[1]];
        }
        if (preg_match('/^pr-(\d+)$/', $class, $m)) {
            return ['pr' => (int) $m[1]];
        }
        if (preg_match('/^pb-(\d+)$/', $class, $m)) {
            return ['pb' => (int) $m[1]];
        }
        if (preg_match('/^pl-(\d+)$/', $class, $m)) {
            return ['pl' => (int) $m[1]];
        }

        // === BORDER ===
        if ('border' === $class) {
            return ['bt' => 1, 'br' => 1, 'bb' => 1, 'bl' => 1];
        }
        if ('border-none' === $class) {
            return ['bt' => 0, 'br' => 0, 'bb' => 0, 'bl' => 0, 'border-pattern' => 'none'];
        }
        if (preg_match('/^border-(\d+)$/', $class, $m)) {
            $v = (int) $m[1];

            return ['bt' => $v, 'br' => $v, 'bb' => $v, 'bl' => $v];
        }
        if (preg_match('/^border-(t|r|b|l)$/', $class, $m)) {
            return ['b'.$m[1] => 1];
        }
        if (preg_match('/^border-(t|r|b|l)-(\d+)$/', $class, $m)) {
            return ['b'.$m[1] => (int) $m[2]];
        }
        if (preg_match('/^border-(normal|rounded|double|tall|wide|tall-medium|wide-medium|tall-large|wide-large)$/', $class, $m)) {
            return ['border-pattern' => $m[1]];
        }
        if (preg_match('/^border-(.+)$/', $class, $m)) {
            $color = $this->parseColorValue($m[1]);
            if (null !== $color) {
                return ['border-color' => $color];
            }
        }

        // === BACKGROUND ===
        if (preg_match('/^bg-(.+)$/', $class, $m)) {
            $color = $this->parseColorValue($m[1]);
            if (null !== $color) {
                return ['bg' => $color];
            }
        }

        // === TEXT ALIGNMENT ===
        $textAlign = match ($class) {
            'text-left' => TextAlign::Left,
            'text-center' => TextAlign::Center,
            'text-right' => TextAlign::Right,
            default => null,
        };
        if (null !== $textAlign) {
            return ['text_align' => $textAlign];
        }

        // === TEXT COLOR ===
        if (preg_match('/^text-(.+)$/', $class, $m)) {
            $color = $this->parseColorValue($m[1]);
            if (null !== $color) {
                return ['fg' => $color];
            }
        }

        // === GAP ===
        if (preg_match('/^gap-(\d+)$/', $class, $m)) {
            return ['gap' => (int) $m[1]];
        }

        // === FLEX WEIGHT ===
        if (preg_match('/^flex-(\d+)$/', $class, $m)) {
            return ['flex' => (int) $m[1]];
        }

        // === FONT ===
        if (preg_match('/^font-(.+)$/', $class, $m)) {
            return ['font' => $m[1]];
        }

        // === ALIGN ===
        $align = match ($class) {
            'align-left' => Align::Left,
            'align-center' => Align::Center,
            'align-right' => Align::Right,
            default => null,
        };
        if (null !== $align) {
            return ['align' => $align];
        }

        // === VERTICAL ALIGN ===
        $verticalAlign = match ($class) {
            'valign-top' => VerticalAlign::Top,
            'valign-center' => VerticalAlign::Center,
            'valign-bottom' => VerticalAlign::Bottom,
            default => null,
        };
        if (null !== $verticalAlign) {
            return ['vertical_align' => $verticalAlign];
        }

        // === SIMPLE KEYWORDS ===
        return match ($class) {
            'bold' => ['bold' => true],
            'not-bold' => ['bold' => false],
            'dim' => ['dim' => true],
            'not-dim' => ['dim' => false],
            'italic' => ['italic' => true],
            'not-italic' => ['italic' => false],
            'underline' => ['underline' => true],
            'no-underline' => ['underline' => false],
            'line-through' => ['strikethrough' => true],
            'no-line-through' => ['strikethrough' => false],
            'reverse' => ['reverse' => true],
            'no-reverse' => ['reverse' => false],
            'flex-col' => ['direction' => Direction::Vertical],
            'flex-row' => ['direction' => Direction::Horizontal],
            'hidden' => ['hidden' => true],
            'visible' => ['hidden' => false],
            default => null,
        };
    }

    /**
     * Parse a color value from a utility class suffix.
     *
     * Supports:
     * - Tailwind shade: red-300, emerald-700, sky-400, etc.
     * - Hex with brackets: [#ff5500], [#f50]
     * - 256-palette index: 0-255
     */
    private function parseColorValue(string $value): Color|string|int|null
    {
        // Bracket syntax for arbitrary hex: [#ff5500]
        if (preg_match('/^\[#([0-9a-fA-F]{3,6})]$/', $value, $m)) {
            return '#'.$m[1];
        }

        // Numeric palette: 0-255
        if (preg_match('/^\d+$/', $value)) {
            $index = (int) $value;
            if ($index >= 0 && $index <= 255) {
                return $index;
            }
        }

        // Tailwind shade syntax: {family}-{shade}
        if (preg_match('/^([a-z]+)-(\d+)$/', $value, $m)) {
            $shade = (int) $m[2];
            if (isset(self::TAILWIND_COLORS[$m[1]]) && isset(self::SHADE_SCALE[$shade])) {
                return Color::hex(self::TAILWIND_COLORS[$m[1]])->scale(self::SHADE_SCALE[$shade]);
            }
        }

        return null;
    }

    /**
     * Build a Style from accumulated property slots.
     *
     * @param array<string, mixed> $slots
     */
    private function buildStyleFromSlots(array $slots): Style
    {
        $padding = null;
        if (isset($slots['pt']) || isset($slots['pr']) || isset($slots['pb']) || isset($slots['pl'])) {
            $padding = new Padding(
                $slots['pt'] ?? 0,
                $slots['pr'] ?? 0,
                $slots['pb'] ?? 0,
                $slots['pl'] ?? 0,
            );
        }

        $border = null;
        if (isset($slots['bt']) || isset($slots['br']) || isset($slots['bb']) || isset($slots['bl']) || isset($slots['border-pattern']) || isset($slots['border-color'])) {
            $border = new Border(
                $slots['bt'] ?? 0,
                $slots['br'] ?? 0,
                $slots['bb'] ?? 0,
                $slots['bl'] ?? 0,
                $slots['border-pattern'] ?? null,
                $slots['border-color'] ?? null,
            );
        }

        return new Style(
            padding: $padding,
            border: $border,
            background: $slots['bg'] ?? null,
            color: $slots['fg'] ?? null,
            bold: $slots['bold'] ?? null,
            dim: $slots['dim'] ?? null,
            italic: $slots['italic'] ?? null,
            strikethrough: $slots['strikethrough'] ?? null,
            underline: $slots['underline'] ?? null,
            reverse: $slots['reverse'] ?? null,
            direction: $slots['direction'] ?? null,
            gap: $slots['gap'] ?? null,
            hidden: $slots['hidden'] ?? null,
            textAlign: $slots['text_align'] ?? null,
            font: $slots['font'] ?? null,
            align: $slots['align'] ?? null,
            verticalAlign: $slots['vertical_align'] ?? null,
            flex: $slots['flex'] ?? null,
        );
    }
}
