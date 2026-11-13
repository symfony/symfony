<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Style;

use Symfony\Component\Tui\Exception\InvalidArgumentException;

/**
 * Defines border pattern character and strategy matrices.
 *
 * The 3x3 strategy matrix is used by renderers to decide how to swap colors
 * for block-style borders:
 * - 0: border color on inner background (standard border rendering)
 * - 1: border color on outer background (blend with outer background)
 * - 2: reverse video border/outer — filled part shows outer bg, empty part shows border color
 * - 3: reverse video border/inner — filled part shows inner bg, empty part shows border color
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class BorderPattern
{
    public const NONE = 'none';
    public const NORMAL = 'normal';
    public const ROUNDED = 'rounded';
    public const DOUBLE = 'double';
    public const HEAVY = 'heavy';
    public const DASHED = 'dashed';
    public const DOTTED = 'dotted';
    public const TALL = 'tall';
    public const WIDE = 'wide';
    public const TALL_MEDIUM = 'tall-medium';
    public const WIDE_MEDIUM = 'wide-medium';
    public const TALL_LARGE = 'tall-large';
    public const WIDE_LARGE = 'wide-large';
    /** A thin line hugging a box from outside it, like the ring of a box-shadow spread around it */
    public const RING = 'ring';
    /** A thick line hugging a box from outside it */
    public const RING_THICK = 'ring-thick';
    /** A thin line hugging a box from inside it, along its edge cells, like the ring of a box-shadow spread inside */
    public const RING_INSET = 'ring-inset';

    /**
     * @param array<int, array<int, string>> $chars
     * @param array<int, array<int, int>>    $strategies
     */
    public function __construct(private array $chars = [['', '', ''], ['', '', ''], ['', '', '']], private array $strategies = [[0, 0, 0], [0, 0, 0], [0, 0, 0]])
    {
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function getChars(): array
    {
        return $this->chars;
    }

    /**
     * @return array<int, array<int, int>>
     */
    public function getStrategies(): array
    {
        return $this->strategies;
    }

    public function applyBorderSegment(string $segment, int $strategy, Style $outerStyle, Style $innerStyle, ?Color $borderColor = null): string
    {
        $segment = '' !== $segment ? $segment : ' ';
        $outerForeground = $outerStyle->getColor();
        $outerBackground = $outerStyle->getBackground();
        $innerBackground = $innerStyle->getBackground();

        return match ($strategy) {
            1 => $this->applyColors($segment, $borderColor, $outerBackground, $outerForeground, $outerBackground),
            2 => $this->applyColorsReversed($segment, $borderColor, $outerBackground, $outerForeground, $outerBackground),
            3 => $this->applyColorsReversed($segment, $borderColor, $innerBackground, $outerForeground, $outerBackground),
            default => $this->applyColors($segment, $borderColor, $innerBackground, $outerForeground, $outerBackground),
        };
    }

    public function applyInnerSegment(string $segment, Style $outerStyle, Style $innerStyle): string
    {
        return $this->applyColors($segment, $innerStyle->getColor(), $innerStyle->getBackground(), $outerStyle->getColor(), $outerStyle->getBackground());
    }

    /**
     * The corner joining a horizontal side and a vertical one of other patterns, like ┍ for a heavy top and a light left side.
     *
     * Without a glyph for the pair, the corner is that of the horizontal side.
     *
     * @param 0|2 $row    The top or bottom row
     * @param 0|2 $column The left or right column
     */
    public static function corner(self $horizontal, self $vertical, int $row, int $column): string
    {
        $own = $horizontal->getChars()[$row][$column];
        $weights = [self::weight($horizontal->getChars()[0][1]), self::weight($vertical->getChars()[1][0])];
        if ($weights[0] === $weights[1] || \in_array(null, $weights, true)) {
            return $own;
        }
        // Top left, top right, bottom left, bottom right
        $corners = match ($weights) {
            ['heavy', 'light'] => ['┍', '┑', '┕', '┙'],
            ['light', 'heavy'] => ['┎', '┒', '┖', '┚'],
            ['double', 'light'] => ['╒', '╕', '╘', '╛'],
            ['light', 'double'] => ['╓', '╖', '╙', '╜'],
            default => null,
        };

        return null === $corners ? $own : $corners[$row + ($column >> 1)];
    }

    public function isNone(): bool
    {
        foreach ($this->chars as $row) {
            foreach ($row as $char) {
                if ('' !== $char) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return array{string, int}
     */
    public function getTop(): array
    {
        return [$this->chars[0][1], $this->strategies[0][1]];
    }

    public function top(string $char, int $strategy = 0): static
    {
        $t = clone $this;
        $t->chars[0][1] = $char;
        $t->strategies[0][1] = $strategy;

        return $t;
    }

    /**
     * @return array{string, int}
     */
    public function getBottom(): array
    {
        return [$this->chars[2][1], $this->strategies[2][1]];
    }

    public function bottom(string $char, int $strategy = 0): static
    {
        $t = clone $this;
        $t->chars[2][1] = $char;
        $t->strategies[2][1] = $strategy;

        return $t;
    }

    /**
     * @return array{string, int}
     */
    public function getLeft(): array
    {
        return [$this->chars[1][0], $this->strategies[1][0]];
    }

    public function left(string $char, int $strategy = 0): static
    {
        $t = clone $this;
        $t->chars[1][0] = $char;
        $t->strategies[1][0] = $strategy;

        return $t;
    }

    /**
     * @return array{string, int}
     */
    public function getRight(): array
    {
        return [$this->chars[1][2], $this->strategies[1][2]];
    }

    public function right(string $char, int $strategy = 0): static
    {
        $t = clone $this;
        $t->chars[1][2] = $char;
        $t->strategies[1][2] = $strategy;

        return $t;
    }

    public function topLeft(string $char, int $strategy = 0): static
    {
        $t = clone $this;
        $t->chars[0][0] = $char;
        $t->strategies[0][0] = $strategy;

        return $t;
    }

    public function topRight(string $char, int $strategy = 0): static
    {
        $t = clone $this;
        $t->chars[0][2] = $char;
        $t->strategies[0][2] = $strategy;

        return $t;
    }

    public function bottomRight(string $char, int $strategy = 0): static
    {
        $t = clone $this;
        $t->chars[2][2] = $char;
        $t->strategies[2][2] = $strategy;

        return $t;
    }

    public function bottomLeft(string $char, int $strategy = 0): static
    {
        $t = clone $this;
        $t->chars[2][0] = $char;
        $t->strategies[2][0] = $strategy;

        return $t;
    }

    public static function fromName(string $style): self
    {
        return match ($style) {
            self::NONE => new self(),
            self::NORMAL => self::normal(),
            self::ROUNDED => self::rounded(),
            self::DOUBLE => self::double(),
            self::HEAVY => self::heavy(),
            self::DASHED => self::dashed(),
            self::DOTTED => self::dotted(),
            self::TALL => self::tall(),
            self::WIDE => self::wide(),
            self::TALL_MEDIUM => self::tallMedium(),
            self::WIDE_MEDIUM => self::wideMedium(),
            self::TALL_LARGE => self::tallLarge(),
            self::WIDE_LARGE => self::wideLarge(),
            self::RING => new self([['▁', '▁', '▁'], ['▕', ' ', '▏'], ['▔', '▔', '▔']], [[1, 1, 1], [1, 0, 1], [1, 1, 1]]),
            self::RING_THICK => new self([['▗', '▄', '▖'], ['▐', ' ', '▌'], ['▝', '▀', '▘']], [[1, 1, 1], [1, 0, 1], [1, 1, 1]]),
            self::RING_INSET => new self([['▔', '▔', '▔'], ['▏', ' ', '▕'], ['▁', '▁', '▁']], [[1, 1, 1], [1, 0, 1], [1, 1, 1]]),
            default => throw new InvalidArgumentException(\sprintf('Unknown border pattern "%s".', $style)),
        };
    }

    public static function normal(): self
    {
        return new self([['┌', '─', '┐'], ['│', ' ', '│'], ['└', '─', '┘']]);
    }

    public static function rounded(): self
    {
        return new self([['╭', '─', '╮'], ['│', ' ', '│'], ['╰', '─', '╯']]);
    }

    public static function double(): self
    {
        return new self([['╔', '═', '╗'], ['║', ' ', '║'], ['╚', '═', '╝']]);
    }

    public static function heavy(): self
    {
        return new self([['┏', '━', '┓'], ['┃', ' ', '┃'], ['┗', '━', '┛']]);
    }

    public static function dashed(): self
    {
        return new self([['┌', '╌', '┐'], ['╎', ' ', '╎'], ['└', '╌', '┘']]);
    }

    public static function dotted(): self
    {
        return new self([['┌', '┈', '┐'], ['┊', ' ', '┊'], ['└', '┈', '┘']]);
    }

    public static function tall(): self
    {
        return new self([['▊', '▔', '▎'], ['▊', ' ', '▎'], ['▊', '▁', '▎']], [[2, 0, 1], [2, 0, 1], [2, 0, 1]]);
    }

    public static function wide(): self
    {
        return new self([['▁', '▁', '▁'], ['▎', ' ', '▊'], ['▔', '▔', '▔']], [[1, 1, 1], [0, 1, 3], [1, 1, 1]]);
    }

    /**
     * Visually uniform 4px border with tall-style corners.
     *
     * Terminal cells are ~2× taller than wide, so 2px vertical (▆/▂) is
     * paired with 4px horizontal (▌) for a balanced appearance (2px vertical
     * appears as ~4px perceived with the 1:2 cell aspect ratio). Uses the
     * same technique as tall(): left-aligned block character (▌) with
     * strategy 2 for the left column (fg=outer fills left half, bg=border
     * fills right half) and strategy 1 for the right column (fg=border
     * fills left half, bg=outer fills right half). Top uses ▆ (lower 6/8)
     * with strategy 3 (fg=inner, bg=border) to place the 2px border at the
     * top of the cell. Bottom uses ▂ (lower 2/8) with strategy 0 (fg=border,
     * bg=inner) to place the 2px border at the bottom. The side character
     * extends through all rows including corners.
     */
    public static function tallMedium(): self
    {
        return new self([['▌', '▆', '▌'], ['▌', ' ', '▌'], ['▌', '▂', '▌']], [[2, 3, 1], [2, 0, 1], [2, 0, 1]]);
    }

    /**
     * Visually uniform ~4px border with wide-style corners.
     *
     * Terminal cells are ~2× taller than wide, so 2px vertical (▂/▆) is
     * paired with 4px horizontal (▌) for a balanced appearance (2px vertical
     * appears as ~4px perceived with the 1:2 cell aspect ratio). Uses the
     * same technique as wide(): the horizontal bar character extends through
     * all columns including corners. Top uses ▂ (lower 2/8) with strategy 1
     * (fg=border, bg=outer) to place the 2px border at the bottom of the
     * cell. Bottom uses ▆ (lower 6/8) with strategy 2 (fg=outer, bg=border)
     * to place the 2px border at the top. Left uses ▌ (left 4/8) with
     * strategy 0 (fg=border, bg=inner). Right uses ▌ with strategy 3
     * (fg=inner, bg=border).
     */
    public static function wideMedium(): self
    {
        return new self([['▂', '▂', '▂'], ['▌', ' ', '▌'], ['▆', '▆', '▆']], [[1, 1, 1], [0, 0, 3], [2, 2, 2]]);
    }

    /**
     * Visually uniform ~8px border with tall-style corners.
     *
     * Terminal cells are ~2× taller than wide, so 4px vertical (▀/▄) is
     * paired with 8px horizontal (█) for a balanced appearance (4px vertical
     * appears as ~8px perceived with the 1:2 cell aspect ratio). Uses the
     * same technique as tall(): the side character extends through all rows
     * including corners. Since the sides are full-cell width (█), the corners
     * are solid border color, differing from wide-large where corners show
     * outer background in the non-bar half. Top/bottom use strategy 0
     * (fg=border, bg=inner) so that no outer background bleeds into the
     * top/bottom bars: top uses ▀ (upper half = border, lower half = inner)
     * and bottom uses ▄ (lower half = border, upper half = inner).
     */
    public static function tallLarge(): self
    {
        return new self([['█', '▀', '█'], ['█', ' ', '█'], ['█', '▄', '█']], [[1, 0, 1], [1, 0, 1], [1, 0, 1]]);
    }

    /**
     * Visually uniform ~8px border with wide-style corners.
     *
     * Terminal cells are ~2× taller than wide, so 4px vertical (▄/▀) is paired
     * with 8px horizontal (█) for a balanced appearance. Corners repeat the
     * horizontal bar character of their row since the side bars are full-cell
     * width and naturally fill the corners. The outer background shows through
     * the non-bar half of the corner cells.
     */
    public static function wideLarge(): self
    {
        return new self([['▄', '▄', '▄'], ['█', ' ', '█'], ['▀', '▀', '▀']], [[1, 1, 1], [1, 0, 1], [1, 1, 1]]);
    }

    /**
     * The weight of a box-drawing line: light, heavy or double; null for another glyph, like a block.
     */
    private static function weight(string $line): ?string
    {
        return match ($line) {
            '─', '│', '╌', '╎', '┈', '┊', '┄', '┆' => 'light',
            '━', '┃', '╍', '╏', '┉', '┋', '┅', '┇' => 'heavy',
            '═', '║' => 'double',
            default => null,
        };
    }

    private function applyColors(string $segment, ?Color $foreground, ?Color $background, ?Color $outerForeground, ?Color $outerBackground): string
    {
        return $this->foregroundCode($foreground).$this->backgroundCode($background).$segment.$this->foregroundCode($outerForeground).$this->backgroundCode($outerBackground);
    }

    /**
     * Like applyColors but uses ANSI reverse video so that borderColor lands in the FG slot.
     * This ensures null borderColor falls back to the terminal's default text color (visible)
     * rather than the terminal's default background color (invisible on dark terminals).
     * The block character's filled portion shows $background, the empty portion shows $foreground.
     */
    private function applyColorsReversed(string $segment, ?Color $foreground, ?Color $background, ?Color $outerForeground, ?Color $outerBackground): string
    {
        return $this->foregroundCode($foreground).$this->backgroundCode($background)."\x1b[7m".$segment."\x1b[27m".$this->foregroundCode($outerForeground).$this->backgroundCode($outerBackground);
    }

    private function foregroundCode(?Color $color): string
    {
        return $color?->toForegroundCode() ?? Color::resetForeground();
    }

    private function backgroundCode(?Color $color): string
    {
        return $color?->toBackgroundCode() ?? Color::resetBackground();
    }
}
