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

/**
 * Represents styling options for widgets including padding, borders, background, and text formatting.
 *
 * This class is an immutable value object. All with*() methods return a new
 * instance rather than modifying the existing one. This design allows styles
 * to be safely shared and reused without risk of unintended side effects.
 *
 * ## Nullable Properties for Style Inheritance
 *
 * All style properties are nullable to distinguish between "not set" and "explicitly set":
 *
 * - `null` means "not set" - the value will be inherited from parent styles during merge
 * - An explicit value (even if zero/false) means "explicitly set" - overrides inheritance
 *
 * This applies to:
 * - `padding` - null (inherit) vs Padding instance (explicit, even if all zeros)
 * - `border` - null (inherit) vs Border instance (explicit, even if all zeros)
 * - `background` - null (inherit) vs Color instance (explicit color)
 * - `color` - null (inherit) vs Color instance (explicit color)
 * - `bold`, `dim`, `italic`, `strikethrough`, `underline`, `reverse` - null (inherit) vs bool (explicit true/false)
 * - `hidden` - null (inherit) vs bool (true = hidden, false = explicitly visible)
 *
 * Examples:
 *
 *     // Color only - other properties will inherit from parent rules
 *     $style = new Style()->withColor('red');
 *     $style->getPadding(); // null - will inherit
 *     $style->getBold();    // null - will inherit
 *
 *     // Explicit bold=false to override a parent's bold=true
 *     $style = new Style()->withBold(false);
 *     $style->getBold();    // false - explicitly disabled
 *
 *     // Explicit zero padding - will override inherited padding
 *     $style = Style::padding([0]);
 *     $style->getPadding(); // Padding(0, 0, 0, 0) - explicit zero
 *
 * Compare with Tui, which is a stateful service that returns $this from fluent
 * methods to maintain object identity across the application.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Style
{
    /**
     * The SGR code that resets the attribute set by each SGR code.
     */
    private const RESET_CODES = [
        '1' => 22, '2' => 22, '3' => 23, '4' => 24, '4:1' => 24, '4:2' => 24, '4:3' => 24, '4:4' => 24, '4:5' => 24, '21' => 24, '7' => 27, '9' => 29,
        '30' => 39, '31' => 39, '32' => 39, '33' => 39, '34' => 39, '35' => 39, '36' => 39, '37' => 39, '38' => 39,
        '90' => 39, '91' => 39, '92' => 39, '93' => 39, '94' => 39, '95' => 39, '96' => 39, '97' => 39,
        '40' => 49, '41' => 49, '42' => 49, '43' => 49, '44' => 49, '45' => 49, '46' => 49, '47' => 49, '48' => 49,
        '100' => 49, '101' => 49, '102' => 49, '103' => 49, '104' => 49, '105' => 49, '106' => 49, '107' => 49,
        '58' => 59,
    ];

    private ?Color $backgroundColor;
    private ?Color $foregroundColor;
    private ?string $ansiPrefix = null;
    private ?string $ansiSuffix = null;
    /** @var array<int|string, string> The codes of each attribute, keyed by the SGR code that resets it */
    private array $restoreCodes = [];
    /** Matches the SGR sequences that set or reset an attribute of this style */
    private ?string $restorePattern = null;

    /**
     * @param Padding|null          $padding       Padding specification (null = not set, see Padding::from())
     * @param Border|null           $border        Border specification (null = not set, see Border::from())
     * @param Color|string|int|null $background    Background color (null = not set)
     * @param Color|string|int|null $color         Foreground color (null = not set)
     * @param bool|null             $bold          Bold text (null = not set, true/false = explicit)
     * @param bool|null             $dim           Dim/faint text (null = not set, true/false = explicit)
     * @param bool|null             $italic        Italic text (null = not set, true/false = explicit)
     * @param bool|null             $strikethrough Strikethrough text (null = not set, true/false = explicit)
     * @param bool|null             $underline     Underlined text (null = not set, true/false = explicit)
     * @param bool|null             $reverse       Reverse video (null = not set, true/false = explicit)
     * @param Direction|null        $direction     Layout direction for containers (null = not set, defaults to vertical)
     * @param int|null              $gap           Gap between children for containers (null = not set, defaults to 0)
     * @param bool|null             $hidden        Whether the widget is hidden (null = not set, true = hidden, false = visible)
     * @param CursorShape|null      $cursorShape   Cursor shape for ::cursor sub-elements (null = not set)
     * @param TextAlign|null        $textAlign     Text alignment (null = not set, defaults to left)
     * @param string|null           $font          FIGlet font name or path (null = not set, defaults to normal text)
     * @param int|null              $maxColumns    Maximum width in columns (null = not set, no constraint)
     * @param Align|null            $align         Horizontal alignment of child widgets (null = not set, defaults to left)
     * @param VerticalAlign|null    $verticalAlign Vertical alignment of child widgets (null = not set, defaults to top)
     * @param int|null              $flex          Flex grow weight for horizontal layouts (null = not set, 0 = intrinsic width, 1+ = proportional)
     */
    public function __construct(
        private ?Padding $padding = null,
        private ?Border $border = null,
        Color|string|int|null $background = null,
        Color|string|int|null $color = null,
        private ?bool $bold = null,
        private ?bool $dim = null,
        private ?bool $italic = null,
        private ?bool $strikethrough = null,
        private ?bool $underline = null,
        private ?bool $reverse = null,
        private ?Direction $direction = null,
        private ?int $gap = null,
        private ?bool $hidden = null,
        private ?CursorShape $cursorShape = null,
        private ?TextAlign $textAlign = null,
        private ?string $font = null,
        private ?int $maxColumns = null,
        private ?Align $align = null,
        private ?VerticalAlign $verticalAlign = null,
        private ?int $flex = null,
    ) {
        $this->backgroundColor = null !== $background ? Color::from($background) : null;
        $this->foregroundColor = null !== $color ? Color::from($color) : null;
        $this->gap = null !== $gap ? max(0, $gap) : null;
        $this->flex = null !== $flex ? max(0, $flex) : null;
    }

    public function __clone(): void
    {
        $this->ansiPrefix = null;
        $this->ansiSuffix = null;
        $this->restoreCodes = [];
        $this->restorePattern = null;
    }

    /**
     * Get the background color.
     */
    public function getBackground(): ?Color
    {
        return $this->backgroundColor;
    }

    /**
     * Get the foreground color.
     */
    public function getColor(): ?Color
    {
        return $this->foregroundColor;
    }

    /**
     * Get the padding.
     */
    public function getPadding(): ?Padding
    {
        return $this->padding;
    }

    /**
     * Get the border.
     */
    public function getBorder(): ?Border
    {
        return $this->border;
    }

    /**
     * Get the bold flag.
     */
    public function getBold(): ?bool
    {
        return $this->bold;
    }

    /**
     * Get the dim flag.
     */
    public function getDim(): ?bool
    {
        return $this->dim;
    }

    /**
     * Get the italic flag.
     */
    public function getItalic(): ?bool
    {
        return $this->italic;
    }

    /**
     * Get the strikethrough flag.
     */
    public function getStrikethrough(): ?bool
    {
        return $this->strikethrough;
    }

    /**
     * Get the underline flag.
     */
    public function getUnderline(): ?bool
    {
        return $this->underline;
    }

    /**
     * Get the reverse flag.
     */
    public function getReverse(): ?bool
    {
        return $this->reverse;
    }

    /**
     * Get the layout direction.
     */
    public function getDirection(): ?Direction
    {
        return $this->direction;
    }

    /**
     * Get the gap between children.
     */
    public function getGap(): ?int
    {
        return $this->gap;
    }

    /**
     * Get the hidden flag.
     */
    public function getHidden(): ?bool
    {
        return $this->hidden;
    }

    /**
     * Get the cursor shape.
     */
    public function getCursorShape(): ?CursorShape
    {
        return $this->cursorShape;
    }

    /**
     * Get the text alignment.
     */
    public function getTextAlign(): ?TextAlign
    {
        return $this->textAlign;
    }

    /**
     * Get the FIGlet font name or path.
     */
    public function getFont(): ?string
    {
        return $this->font;
    }

    /**
     * Get the maximum width in columns.
     */
    public function getMaxColumns(): ?int
    {
        return $this->maxColumns;
    }

    /**
     * Get the horizontal alignment of child widgets.
     */
    public function getAlign(): ?Align
    {
        return $this->align;
    }

    /**
     * Get the vertical alignment of child widgets.
     */
    public function getVerticalAlign(): ?VerticalAlign
    {
        return $this->verticalAlign;
    }

    /**
     * Get the flex grow weight for horizontal layouts.
     *
     * - null: not set (inherits default behavior: equal distribution)
     * - 0: use intrinsic (content) width
     * - 1+: proportional weight (higher values get more space)
     */
    public function getFlex(): ?int
    {
        return $this->flex;
    }

    /**
     * Check whether the style applies no visual formatting.
     *
     * @internal
     */
    public function isPlain(): bool
    {
        return null === $this->backgroundColor
            && null === $this->foregroundColor
            && null === $this->bold
            && null === $this->dim
            && null === $this->italic
            && null === $this->strikethrough
            && null === $this->underline
            && null === $this->reverse;
    }

    /**
     * Create a style with padding only.
     *
     * @param Padding|array<int> $padding Padding specification (see Padding::from())
     */
    public static function padding(Padding|array $padding): self
    {
        return new self(padding: Padding::from($padding));
    }

    /**
     * Create a style with border only.
     *
     * @param Border|array<int>         $border  Border specification (see Border::from())
     * @param BorderPattern|string|null $pattern Border pattern (see BorderPattern::fromName())
     * @param Color|string|int|null     $color   Border color
     */
    public static function border(Border|array $border, BorderPattern|string|null $pattern = null, Color|string|int|null $color = null): self
    {
        return new self(border: Border::from($border, $pattern, $color));
    }

    /**
     * Merge multiple styles into one in a single pass.
     *
     * Later styles override earlier ones for non-null properties.
     * Allocates a single Style object regardless of the number of inputs.
     *
     * @param Style[] $styles
     */
    public static function mergeAll(array $styles): self
    {
        $padding = null;
        $border = null;
        $background = null;
        $color = null;
        $bold = null;
        $dim = null;
        $italic = null;
        $strikethrough = null;
        $underline = null;
        $reverse = null;
        $direction = null;
        $gap = null;
        $hidden = null;
        $cursorShape = null;
        $textAlign = null;
        $font = null;
        $maxColumns = null;
        $align = null;
        $verticalAlign = null;
        $flex = null;

        foreach ($styles as $style) {
            $padding = $style->padding ?? $padding;
            $border = $style->border ?? $border;
            $background = $style->backgroundColor ?? $background;
            $color = $style->foregroundColor ?? $color;
            $bold = $style->bold ?? $bold;
            $dim = $style->dim ?? $dim;
            $italic = $style->italic ?? $italic;
            $strikethrough = $style->strikethrough ?? $strikethrough;
            $underline = $style->underline ?? $underline;
            $reverse = $style->reverse ?? $reverse;
            $direction = $style->direction ?? $direction;
            $gap = $style->gap ?? $gap;
            $hidden = $style->hidden ?? $hidden;
            $cursorShape = $style->cursorShape ?? $cursorShape;
            $textAlign = $style->textAlign ?? $textAlign;
            $font = $style->font ?? $font;
            $maxColumns = $style->maxColumns ?? $maxColumns;
            $align = $style->align ?? $align;
            $verticalAlign = $style->verticalAlign ?? $verticalAlign;
            $flex = $style->flex ?? $flex;
        }

        return new self(
            $padding,
            $border,
            $background,
            $color,
            $bold,
            $dim,
            $italic,
            $strikethrough,
            $underline,
            $reverse,
            $direction,
            $gap,
            $hidden,
            $cursorShape,
            $textAlign,
            $font,
            $maxColumns,
            $align,
            $verticalAlign,
            $flex,
        );
    }

    /**
     * Create new style with different padding.
     *
     * @param Padding|array<int> $padding Padding specification (see Padding::from())
     */
    public function withPadding(Padding|array $padding): self
    {
        $clone = clone $this;
        $clone->padding = Padding::from($padding);

        return $clone;
    }

    /**
     * Create new style with different border.
     *
     * @param Border|array<int>         $border  Border specification (see Border::from())
     * @param BorderPattern|string|null $pattern Border pattern (see BorderPattern::fromName())
     * @param Color|string|int|null     $color   Border color
     */
    public function withBorder(Border|array $border, BorderPattern|string|null $pattern = null, Color|string|int|null $color = null): self
    {
        $clone = clone $this;
        $clone->border = Border::from($border, $pattern, $color);

        return $clone;
    }

    /**
     * Create new style with a different border pattern.
     */
    public function withBorderPattern(BorderPattern|string|null $pattern): self
    {
        $border = $this->border ?? new Border(0, 0, 0, 0);

        return $this->withBorder($border->withPattern($pattern));
    }

    /**
     * Create new style with a different border color.
     */
    public function withBorderColor(Color|string|int|null $color): self
    {
        $border = $this->border ?? new Border(0, 0, 0, 0);

        return $this->withBorder($border->withColor($color));
    }

    /**
     * Create new style with background color.
     *
     * @param Color|string|int|null $background Color specification:
     *                                          - Color instance
     *                                          - string starting with '#' -> hex color
     *                                          - string -> named color ('red', 'blue', etc.)
     *                                          - int -> 256-palette index (0-255)
     *                                          - null -> no background
     */
    public function withBackground(Color|string|int|null $background): self
    {
        $clone = clone $this;
        $clone->backgroundColor = null !== $background ? Color::from($background) : null;

        return $clone;
    }

    /**
     * Create new style with foreground color.
     *
     * @param Color|string|int|null $color Color specification:
     *                                     - Color instance
     *                                     - string starting with '#' -> hex color
     *                                     - string -> named color ('red', 'blue', etc.)
     *                                     - int -> 256-palette index (0-255)
     *                                     - null -> no color
     */
    public function withColor(Color|string|int|null $color): self
    {
        $clone = clone $this;
        $clone->foregroundColor = null !== $color ? Color::from($color) : null;

        return $clone;
    }

    /**
     * Create new style with bold enabled.
     */
    public function withBold(bool $bold = true): self
    {
        $clone = clone $this;
        $clone->bold = $bold;

        return $clone;
    }

    /**
     * Create new style with dim/faint enabled.
     */
    public function withDim(bool $dim = true): self
    {
        $clone = clone $this;
        $clone->dim = $dim;

        return $clone;
    }

    /**
     * Create new style with italic enabled.
     */
    public function withItalic(bool $italic = true): self
    {
        $clone = clone $this;
        $clone->italic = $italic;

        return $clone;
    }

    /**
     * Create new style with strikethrough enabled.
     */
    public function withStrikethrough(bool $strikethrough = true): self
    {
        $clone = clone $this;
        $clone->strikethrough = $strikethrough;

        return $clone;
    }

    /**
     * Create new style with underline enabled.
     */
    public function withUnderline(bool $underline = true): self
    {
        $clone = clone $this;
        $clone->underline = $underline;

        return $clone;
    }

    /**
     * Create new style with reverse video enabled.
     */
    public function withReverse(bool $reverse = true): self
    {
        $clone = clone $this;
        $clone->reverse = $reverse;

        return $clone;
    }

    /**
     * Create new style with layout direction.
     */
    public function withDirection(Direction $direction): self
    {
        $clone = clone $this;
        $clone->direction = $direction;

        return $clone;
    }

    /**
     * Create new style with gap between children.
     */
    public function withGap(int $gap): self
    {
        $clone = clone $this;
        $clone->gap = max(0, $gap);

        return $clone;
    }

    /**
     * Create new style with hidden flag.
     *
     * Hidden widgets are skipped during rendering; they produce no output
     * and take no space, similar to CSS `display: none`.
     */
    public function withHidden(bool $hidden = true): self
    {
        $clone = clone $this;
        $clone->hidden = $hidden;

        return $clone;
    }

    /**
     * Create new style with a cursor shape.
     */
    public function withCursorShape(CursorShape $cursorShape): self
    {
        $clone = clone $this;
        $clone->cursorShape = $cursorShape;

        return $clone;
    }

    /**
     * Create new style with text alignment.
     */
    public function withTextAlign(TextAlign $textAlign): self
    {
        $clone = clone $this;
        $clone->textAlign = $textAlign;

        return $clone;
    }

    /**
     * Create new style with a FIGlet font.
     *
     * @param string|null $font Bundled font name (big, small, slant, standard, mini) or path to a .flf file, or null to clear
     */
    public function withFont(?string $font): self
    {
        $clone = clone $this;
        $clone->font = $font;

        return $clone;
    }

    /**
     * Create new style with a maximum column width.
     *
     * @param int|null $maxColumns Maximum width in columns, or null to clear
     */
    public function withMaxColumns(?int $maxColumns): self
    {
        $clone = clone $this;
        $clone->maxColumns = $maxColumns;

        return $clone;
    }

    /**
     * Create new style with horizontal alignment for child widgets.
     */
    public function withAlign(Align $align): self
    {
        $clone = clone $this;
        $clone->align = $align;

        return $clone;
    }

    /**
     * Create new style with vertical alignment for child widgets.
     */
    public function withVerticalAlign(VerticalAlign $verticalAlign): self
    {
        $clone = clone $this;
        $clone->verticalAlign = $verticalAlign;

        return $clone;
    }

    /**
     * Create new style with a flex grow weight for horizontal layouts.
     *
     * @param int|null $flex 0 = intrinsic width, 1+ = proportional weight, null = clear
     */
    public function withFlex(?int $flex): self
    {
        $clone = clone $this;
        $clone->flex = null !== $flex ? max(0, $flex) : null;

        return $clone;
    }

    /**
     * Create a copy with only visual formatting and content properties.
     *
     * Strips layout properties that the Renderer owns: padding, border,
     * gap, direction, hidden, cursorShape, textAlign, maxColumns, align,
     * verticalAlign, and flex.
     * Used by the Renderer to build the inner context for leaf widgets,
     * enforcing a clear contract: the Renderer owns layout, widgets own
     * content styling.
     *
     * Content properties like font are preserved because widgets need them
     * during render() to produce the correct output.
     *
     * @internal
     */
    public function withoutLayoutProperties(): self
    {
        if (null === $this->padding && null === $this->border
            && null === $this->gap && null === $this->direction
            && null === $this->hidden && null === $this->cursorShape
            && null === $this->textAlign && null === $this->maxColumns
            && null === $this->align && null === $this->verticalAlign
            && null === $this->flex) {
            return $this;
        }

        $clone = clone $this;
        $clone->padding = null;
        $clone->border = null;
        $clone->gap = null;
        $clone->direction = null;
        $clone->hidden = null;
        $clone->cursorShape = null;
        $clone->textAlign = null;
        $clone->maxColumns = null;
        $clone->align = null;
        $clone->verticalAlign = null;
        $clone->flex = null;

        return $clone;
    }

    /**
     * Get the ANSI codes that activate this style's formatting.
     *
     * This returns only the "turn on" codes (foreground, background, bold, etc.)
     * without any corresponding reset codes. Useful for restoring a parent style
     * after a child style's reset codes have run.
     *
     * Returns an empty string if no formatting properties are set.
     */
    public function getAnsiRestore(): string
    {
        if (null === $this->ansiPrefix) {
            $this->computeAnsiCodes();
        }

        return $this->ansiPrefix;
    }

    /**
     * Apply all formatting styles to a string.
     *
     * Applies color, background, bold, dim, italic, strikethrough, and underline.
     * Padding and borders are not applied (that's a layout concern for the widget).
     *
     * Uses attribute-specific reset codes to preserve other styles that may
     * be set by parent containers.
     *
     * When a boolean property is explicitly false (not null), it emits a reset
     * code to cancel any inherited styling from parent containers.
     *
     * When a styled span in the text resets an attribute, this style applies
     * its own code for that attribute again after the span.
     */
    public function apply(string $text): string
    {
        if (null === $this->ansiPrefix) {
            $this->computeAnsiCodes();
        }

        if (null !== $this->restorePattern && str_contains($text, "\x1b[")) {
            $text = $this->restoreAfterResets($text, $this->restorePattern);
        }

        return $this->ansiPrefix.$text.$this->ansiSuffix;
    }

    /**
     * Compute and cache the ANSI prefix/suffix codes for this style.
     * Called lazily on first apply().
     */
    private function computeAnsiCodes(): void
    {
        $prefix = '';
        $suffix = '';

        if (null !== $this->foregroundColor) {
            $prefix .= $this->restoreCodes[39] = $this->foregroundColor->toForegroundCode();
            $suffix = Color::resetForeground().$suffix;
        }
        if (null !== $this->backgroundColor) {
            $prefix .= $this->restoreCodes[49] = $this->backgroundColor->toBackgroundCode();
            $suffix = Color::resetBackground().$suffix;
        }
        // Bold (SGR 1) and dim (SGR 2) share the same reset code (SGR 22),
        // so they must be handled together. Emit the reset first, then
        // re-enable whichever attributes should be active.
        $intensity = false === $this->bold || false === $this->dim ? "\x1b[22m" : '';
        if (true === $this->bold) {
            $intensity .= "\x1b[1m";
        }
        if (true === $this->dim) {
            $intensity .= "\x1b[2m";
        }
        if ('' !== $intensity) {
            $prefix .= $this->restoreCodes[22] = $intensity;
        }
        if (true === $this->bold || true === $this->dim) {
            $suffix = "\x1b[22m".$suffix;
        }
        if (true === $this->italic) {
            $prefix .= $this->restoreCodes[23] = "\x1b[3m";
            $suffix = "\x1b[23m".$suffix;
        } elseif (false === $this->italic) {
            $prefix .= $this->restoreCodes[23] = "\x1b[23m";
        }
        if (true === $this->strikethrough) {
            $prefix .= $this->restoreCodes[29] = "\x1b[9m";
            $suffix = "\x1b[29m".$suffix;
        } elseif (false === $this->strikethrough) {
            $prefix .= $this->restoreCodes[29] = "\x1b[29m";
        }
        if (true === $this->underline) {
            $prefix .= $this->restoreCodes[24] = "\x1b[4m";
            $suffix = "\x1b[24m".$suffix;
        } elseif (false === $this->underline) {
            $prefix .= $this->restoreCodes[24] = "\x1b[24m";
        }
        if (true === $this->reverse) {
            $prefix .= $this->restoreCodes[27] = "\x1b[7m";
            $suffix = "\x1b[27m".$suffix;
        } elseif (false === $this->reverse) {
            $prefix .= $this->restoreCodes[27] = "\x1b[27m";
        }

        $this->ansiPrefix = $prefix;
        $this->ansiSuffix = $suffix;

        if ($this->restoreCodes) {
            $codes = [...array_keys($this->restoreCodes), '4:0'];
            foreach (self::RESET_CODES as $code => $reset) {
                if (isset($this->restoreCodes[$reset])) {
                    $codes[] = $code;
                }
            }
            $this->restorePattern = '/\x1b\[((?:[\d:]*;)*(?:0?|'.implode('|', $codes).')(?:;[\d;:]*)?)m/';
        }
    }

    /**
     * A reset counts when it resets everything or when it ends a span opened in
     * the text. A reset that comes first cancels an inherited attribute on purpose,
     * like a nested style with bold set to false.
     */
    private function restoreAfterResets(string $text, string $pattern): string
    {
        $opened = [];

        return preg_replace_callback($pattern, function (array $m) use (&$opened): string {
            $restore = [];
            $params = explode(';', $m[1]);

            for ($i = 0, $count = \count($params); $i < $count; ++$i) {
                $param = '4:0' === $params[$i] ? '24' : $params[$i];

                if ('' === $param || '0' === $param) {
                    $opened = [];
                    $restore = $this->restoreCodes;
                } elseif (isset($this->restoreCodes[$param])) {
                    if (isset($opened[$param])) {
                        unset($opened[$param]);
                        $restore[$param] = $this->restoreCodes[$param];
                    } else {
                        $opened[$param] = true;
                    }
                } elseif (null !== $reset = self::RESET_CODES[$param] ?? null) {
                    if (isset($this->restoreCodes[$reset])) {
                        $opened[$reset] = true;
                        unset($restore[$reset]);
                    }

                    // Skip the arguments of 256 and true colors, like 38;5;N or 48;2;R;G;B
                    if ('38' === $param || '48' === $param || '58' === $param) {
                        $i += match ($params[$i + 1] ?? '') {
                            '5' => 2,
                            '2' => 4,
                            default => 0,
                        };
                    }
                }
            }

            return $m[0].implode('', $restore);
        }, $text) ?? $text;
    }
}
