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

use Symfony\Component\Tui\Ansi\AnsiUtils;

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

    /** The columns added to the percentage width, like calc(100% - 20ch) */
    private int $widthOffset = 0;
    /** Whether the percentage width is a bound only, like max-width: 75%, not a share of a row */
    private bool $widthPercentMax = false;
    /** Whether a growing widget starts from its content, like flex-basis: auto in CSS, not from no columns */
    private bool $contentBasis = false;
    /** The most columns of an item of a row, whose width only starts it there, like a flex item: PHP_INT_MAX for no bound, null when its maxColumns */
    private ?int $rowMaxColumns = null;
    /** The color of the caret of ::cursor sub-elements, like caret-color (null = auto, the color of the terminal cursor) */
    private Color|false|null $caretColor = null;
    /** The vertical writing mode of the text, like writing-mode: vertical-rl (null = horizontal) */
    private ?string $writingMode = null;
    /** How many times as large its text is drawn, like font-size: 2em (null = 1) */
    private ?int $fontScale = null;
    /** @var array{BlendMode, Color|null}|null How the widget and what it contains blend with the color behind it, like mix-blend-mode, and that color (null = the terminal background) */
    private ?array $blend = null;
    /** How the background image or gradient blends with the background color, like background-blend-mode (null = normal) */
    private ?BlendMode $backgroundBlend = null;
    /** @var array{bool, bool, bool, bool}|null The sides, top, right, bottom and left, with a negative margin of less than a cell, like -1px */
    private ?array $marginHairline = null;

    /** @var array{int, int} The rows added to the least and the most height as percentages, like calc(100% - 2rem) */
    private array $rowsPercentOffsets = [0, 0];

    /** Whether its text is drawn as spaces, its backgrounds kept, like CSS color: transparent (null = not set) */
    private ?bool $concealed = null;
    /** Whether the widget is a stacking context, ordering the positioned widgets inside it among themselves, like isolation: isolate, opacity below 1 or z-index on a positioned element (null = not set, only for a z-index other than 0) */
    private ?bool $isolated = null;

    /** The z-index of a flex or grid item that is not positioned, painting it over the items it overlaps of a lower one, like CSS z-index on a static grid item (null = not set, auto) */
    private ?int $zIndex = null;

    /** Whether a column is left before the widget in a row it is not the first of on its line, like the space between two words, dropped where the line wraps (null = not set, none) */
    private ?bool $spaceBefore = null;

    /** Whether the widget takes a line of its own in a row that wraps, like a block among inline boxes (null = not set, false) */
    private ?bool $ownLine = null;

    /** Whether the least height of the widget is the height of a document its content overflows, like the height: 100% of a <body>, which passes it to the percentages in it (null = not set, false) */
    private ?bool $documentHeight = null;

    /** The floats its ::before, an empty block, clears: left, right or both, its content below them, its box where it is, like CSS (null = not set, none) */
    private ?string $beforeClear = null;
    /** Whether a box cut across columns has its border and padding on both sides of the cut, like box-decoration-break: clone (null = not set, slice) */
    private ?bool $cloneDecoration = null;
    private ?FloatShape $floatShape = null;
    private ?bool $imageFromImage = null;
    private ?bool $pixelated = null;
    /** @var array{list<array{0: Gradient|RasterImage, 1: BackgroundLayout, 2?: ?BlendMode}>, Color}|null */
    private ?array $backgroundLayers = null;
    private ?float $shapeMargin = null;
    private ?float $shapeImageThreshold = null;
    private ?Color $foregroundColor;
    private ?Color $underlineColor;
    private ?string $ansiPrefix = null;
    private ?string $ansiSuffix = null;
    /** @var array<int|string, string> The codes of each attribute, keyed by the SGR code that resets it */
    private array $restoreCodes = [];
    /** Matches the SGR sequences that set or reset an attribute of this style */
    private ?string $restorePattern = null;
    private bool $cssCascade = false;

    /**
     * @param Padding|null                                   $padding               Padding specification (null = not set, see Padding::from())
     * @param Border|null                                    $border                Border specification (null = not set, see Border::from())
     * @param Color|string|int|null                          $background            Background color (null = not set)
     * @param Color|string|int|null                          $color                 Foreground color (null = not set)
     * @param bool|null                                      $bold                  Bold text (null = not set, true/false = explicit)
     * @param bool|null                                      $dim                   Dim/faint text (null = not set, true/false = explicit)
     * @param bool|null                                      $italic                Italic text (null = not set, true/false = explicit)
     * @param bool|null                                      $strikethrough         Strikethrough text (null = not set, true/false = explicit)
     * @param bool|null                                      $underline             Underlined text (null = not set, true/false = explicit)
     * @param bool|null                                      $reverse               Reverse video (null = not set, true/false = explicit)
     * @param Direction|null                                 $direction             Layout direction for containers (null = not set, defaults to vertical)
     * @param int|null                                       $gap                   Gap between children for containers (null = not set, defaults to 0)
     * @param bool|null                                      $hidden                Whether the widget is hidden (null = not set, true = hidden, false = visible)
     * @param CursorShape|null                               $cursorShape           Cursor shape for ::cursor sub-elements (null = not set)
     * @param TextAlign|null                                 $textAlign             Text alignment (null = not set, defaults to left)
     * @param string|null                                    $font                  FIGlet font name or path (null = not set, defaults to normal text)
     * @param int|null                                       $maxColumns            Maximum width in columns (null = not set, no constraint)
     * @param Align|null                                     $align                 Horizontal alignment of child widgets (null = not set, defaults to left)
     * @param VerticalAlign|null                             $verticalAlign         Vertical alignment of child widgets (null = not set, defaults to top)
     * @param int|float|null                                 $flex                  Flex grow weight for horizontal layouts (null = not set, 0 = intrinsic width, above = proportional, like 0.5 or 2)
     * @param Padding|null                                   $margin                Space around the border, painted like the parent (null = not set)
     * @param int|null                                       $widthPercent          Width as a percentage of the container, 1-100 (null = not set)
     * @param int|null                                       $minRows               Minimum number of content rows (null = not set)
     * @param int|null                                       $maxRows               Maximum number of content rows, the rest is clipped (null = not set)
     * @param int|float|null                                 $shrink                How much the widget gives way when a row is too narrow, 0 = never (null = not set, 1)
     * @param Justify|null                                   $justify               How the free columns of a row are shared between children (null = not set, alignment applies)
     * @param AutoMargin|null                                $autoMargin            The horizontal margins that take the free columns (null = not set)
     * @param int|null                                       $minColumns            Minimum width in columns, when the room allows (null = not set)
     * @param int|null                                       $order                 Position among the siblings in the layout, lowest first (null = not set, 0)
     * @param VerticalAlign|null                             $alignSelf             Vertical alignment of the widget in a row, over the row's (null = not set)
     * @param bool|null                                      $wrap                  Whether a row breaks into several lines when its children do not fit (null = not set, false)
     * @param int|null                                       $rowGap                Rows between the lines of a wrapping row (null = not set, 0)
     * @param bool|null                                      $reverseOrder          Whether children are laid out from the last to the first (null = not set, false)
     * @param bool|null                                      $invisible             Whether the widget keeps its room but draws nothing, like CSS visibility: hidden (null = not set, false)
     * @param int|null                                       $flexBasis             Width in columns in a row before growing or shrinking (null = not set, from the content or the flex weight)
     * @param int|null                                       $flexBasisPercent      The same, as a percentage of the row, 0-100 (null = not set)
     * @param int|null                                       $minRowsPercent        The least height, as a percentage of a parent of definite height (null = not set)
     * @param int|null                                       $maxRowsPercent        The most height, as a percentage of a parent of definite height (null = not set)
     * @param TableRole|null                                 $tableRole             The part the widget plays in a table (null = not set, none)
     * @param int|null                                       $columnSpan            How many table columns a cell spans (null = not set, 1)
     * @param int|null                                       $rowSpan               How many table rows a cell spans (null = not set, 1)
     * @param UnderlineStyle|null                            $underlineStyle        The shape of the underline (null = not set, single)
     * @param Color|string|int|null                          $underlineColor        The color of the underline (null = not set, the text color)
     * @param GridTemplate|null                              $gridTemplate          The columns of a grid: the children are placed in rows of them (null = not set, no grid)
     * @param GridPlacement|null                             $gridPlacement         Where the widget goes on the grid of its parent (null = not set, placed automatically)
     * @param Scroll|null                                    $scroll                Whether a container scrolls the content taller than its height (null = not set, clipped)
     * @param bool|null                                      $spanAll               Whether the widget spans all the columns of a multi-column parent, like column-span: all (null = not set, in its column)
     * @param bool|null                                      $collapse              Whether a table draws the borders of its cells as one grid, like border-collapse: collapse (null = not set, separate)
     * @param bool|null                                      $captionBottom         Whether a table caption is below the table, like caption-side: bottom (null = not set, above)
     * @param bool|null                                      $overline              A line over the text, like text-decoration: overline (null = not set, true/false = explicit)
     * @param string|null                                    $justifySelf           Where a grid item is in its area along the row: start, center, end or stretch, like justify-self (null = not set, the container's justify-items)
     * @param string|null                                    $justifyItems          Where the items of a grid are in their areas along the row, like justify-items (null = not set, stretch)
     * @param bool|null                                      $contents              Whether the widget has no box: its children are laid out in its parent, like display: contents (null = not set, a box)
     * @param float|null                                     $aspectRatio           The width of the box over its height, like aspect-ratio: its height follows its width (null = not set, its content)
     * @param string|null                                    $objectFit             How an image fills its box, like object-fit: fill, contain, cover, none or scale-down (null = not set, fill)
     * @param string|null                                    $float                 The side a widget is put at in its block container, the siblings after it beside it: left or right, like float (null = not set, none)
     * @param string|null                                    $clear                 The floats a widget goes below: left, right or both, like clear (null = not set, none)
     * @param string|null                                    $anchorName            The name absolute widgets are placed next to the widget by, like anchor-name: --menu (null = not set, none)
     * @param array{float, float, 2?: int, 3?: int}|null     $objectPosition        Where an image is in its box when object-fit leaves room or cuts it, like object-position: fractions of the room left, from the left and the top, then cells further (null = not set, centered)
     * @param array{Color, int}|null                         $translucentBackground The background as declared when it is translucent, before it is blended over what is behind: its color and its opacity, 1 to 99 (null = opaque or none)
     * @param Gradient|null                                  $gradient              A background going from color to color, like CSS linear-gradient(), over the background color (null = none)
     * @param array{int, int}|array{int, int, int, int}|null $scrollMargin          The rows kept above and below the element when it is scrolled into view, then the columns at its left and right, like scroll-margin (null = none)
     * @param array{bool, bool, 2?: bool, 3?: bool}|null     $columnBreaks          Whether a column starts before the element and after it in a multi-column container, like break-before and break-after: column, then whether no column starts there, like avoid (null = none)
     * @param bool|null                                      $fixedLayout           Whether a table of a set width sizes its columns from its columns and its first row only, like table-layout: fixed (null = not set, by every cell)
     * @param bool|null                                      $hideEmpty             Whether a table cell without content draws no border nor background, like empty-cells: hide (null = not set, drawn)
     * @param string|null                                    $alignContent          How the lines of a wrapped row, or the rows of a grid, share the free rows of a container of a set height: start, center, end, space-between, space-around, space-evenly or stretch, like align-content (null = not set, stretch for wrapped lines, start for grid rows)
     * @param Outline|false|null                             $outline               A line around the box, over what is next to it, like CSS outline (null = not set, false = none)
     * @param Padding|null                                   $marginPull            The rows and columns the box is pulled by, like negative margins: top and bottom ones overlap what is before and after it (null = not set, none)
     * @param Shadow|null                                    $insetShadow           A shadow inside the box, along its edges, like box-shadow: inset (null = not set, none)
     * @param array{string, bool}|null                       $snapAlign             Where its scroll container snaps to it, start, center or end, and whether a scroll stops at it, like scroll-snap-align and scroll-snap-stop: always (null = not set, none)
     * @param Filter|false|null                              $filter                The color filters of the widget and of what it contains, like CSS filter (null = not set, false = none)
     * @param Shadow|null                                    $shadow                A drop shadow, like CSS box-shadow (null = not set, none)
     * @param bool|null                                      $avoidBreak            Whether the widget is kept in one column of a multi-column parent, like break-inside: avoid (null = not set, it can be cut)
     * @param Columns|null                                   $columns               Flows the content into columns, like CSS columns (null = not set, one column)
     * @param Position|null                                  $position              How the widget is positioned: in the flow, or over it (null = not set, static)
     * @param int|null                                       $stickyTop             The rows from the top of the scroll container where the widget sticks while its parent is in view, like CSS position: sticky (null = not set, scrolls away)
     * @param bool|null                                      $wrapReverse           Whether the lines of a wrapping row go from the bottom up, like flex-wrap: wrap-reverse (null = not set, top down)
     * @param string|null                                    $readingFlow           The order Tab follows through the children of the widget, like reading-flow: flex-visual, flex-flow, grid-rows, grid-columns, grid-order or source-order (null = not set, normal)
     * @param int|null                                       $readingOrder          Where the widget comes in the reading flow of its parent, before its siblings of a greater one, like reading-order (null = not set, 0)
     * @param bool|null                                      $contentHidden         Whether the content of the widget is skipped, its box drawn, like CSS content-visibility: hidden (null = not set, false)
     * @param RasterImage|null                               $backgroundImage       The image of the background, like CSS background-image: url(), drawn over its color (null = none)
     * @param Gradient|RasterImage|false|null                $borderImage           What colors the lines of the border, cell by cell, like CSS border-image-source (null = not set, false = none)
     * @param 'fit-content'|'min-content'|false|null         $contentWidth          Whether the widget is as wide as its content, like CSS width: fit-content, or as narrow as it can be, like min-content (null = not set, false = the room it gets)
     * @param bool|null                                      $transparent           Whether nothing of the widget is drawn, what is behind it showing, though it keeps the focus and the pointer, like CSS opacity: 0 (null = not set, false)
     * @param bool|null                                      $inert                 Whether the widget takes neither the focus nor the pointer, like CSS interactivity: inert and the HTML inert attribute (null = not set, false)
     * @param array{bool, bool}|null                         $autoMarginRows        Whether its top and bottom margins take the free rows of a flex column of a set height, like CSS margin-top: auto (null = neither)
     * @param float|null                                     $minWidthPercent       The least width, a percentage of the container, of a box sized by its content, like CSS min-width: 100% on a table (null = not set)
     * @param BackgroundLayout|null                          $backgroundLayout      Where the background is painted, and how its gradient is sized, placed and repeated, like CSS background-clip, -origin, -size, -position and -repeat (null = not set, the defaults)
     * @param list<string>|null                              $anchorScope           The anchor names that only the descendants of the widget see in it, all for every name, like anchor-scope (null = not set, [] = none)
     * @param int|null                                       $stickyBottom          The rows from the bottom of the scroll container where the widget sticks while its parent is in view, like CSS position: sticky with bottom (null = not set)
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
        private int|float|null $flex = null,
        private ?Padding $margin = null,
        private ?int $widthPercent = null,
        private ?int $minRows = null,
        private ?int $maxRows = null,
        private int|float|null $shrink = null,
        private ?Justify $justify = null,
        private ?AutoMargin $autoMargin = null,
        private ?int $minColumns = null,
        private ?int $order = null,
        private ?VerticalAlign $alignSelf = null,
        private ?bool $wrap = null,
        private ?int $rowGap = null,
        private ?bool $reverseOrder = null,
        private ?bool $invisible = null,
        private ?int $flexBasis = null,
        private ?int $flexBasisPercent = null,
        private ?int $minRowsPercent = null,
        private ?int $maxRowsPercent = null,
        private ?TableRole $tableRole = null,
        private ?int $columnSpan = null,
        private ?int $rowSpan = null,
        private ?UnderlineStyle $underlineStyle = null,
        Color|string|int|null $underlineColor = null,
        private ?GridTemplate $gridTemplate = null,
        private ?GridPlacement $gridPlacement = null,
        private ?Scroll $scroll = null,
        private ?int $stickyTop = null,
        private ?Position $position = null,
        private ?Columns $columns = null,
        private ?bool $avoidBreak = null,
        private ?Shadow $shadow = null,
        private ?bool $spanAll = null,
        private ?bool $collapse = null,
        private ?bool $captionBottom = null,
        private ?bool $overline = null,
        private ?string $justifySelf = null,
        private ?string $justifyItems = null,
        private ?bool $contents = null,
        private ?float $aspectRatio = null,
        private ?string $objectFit = null,
        private ?string $float = null,
        private ?string $clear = null,
        private ?string $anchorName = null,
        private ?array $objectPosition = null,
        private ?array $translucentBackground = null,
        private ?Gradient $gradient = null,
        private ?array $scrollMargin = null,
        private ?array $columnBreaks = null,
        private ?bool $fixedLayout = null,
        private ?bool $hideEmpty = null,
        private Filter|false|null $filter = null,
        private ?string $alignContent = null,
        private Outline|false|null $outline = null,
        private ?Padding $marginPull = null,
        private ?Shadow $insetShadow = null,
        private ?array $snapAlign = null,
        private ?int $stickyBottom = null,
        private ?bool $wrapReverse = null,
        private ?array $anchorScope = null,
        private ?string $readingFlow = null,
        private ?int $readingOrder = null,
        private ?bool $contentHidden = null,
        private ?BackgroundLayout $backgroundLayout = null,
        private RasterImage|false|null $backgroundImage = null,
        private Gradient|RasterImage|false|null $borderImage = null,
        private string|false|null $contentWidth = null,
        private ?bool $transparent = null,
        private ?bool $inert = null,
        private ?array $autoMarginRows = null,
        private ?float $minWidthPercent = null,
    ) {
        $this->stickyTop = null !== $stickyTop ? max(0, $stickyTop) : null;
        $this->underlineColor = null !== $underlineColor ? Color::from($underlineColor) : null;
        $this->rowSpan = null !== $rowSpan ? max(1, min(65_534, $rowSpan)) : null;
        $this->columnSpan = null !== $columnSpan ? max(1, min(1000, $columnSpan)) : null;
        $this->flexBasisPercent = null !== $flexBasisPercent ? max(0, min(100, $flexBasisPercent)) : null;
        $this->flexBasis = null !== $flexBasis ? max(0, $flexBasis) : null;
        $this->rowGap = null !== $rowGap ? max(0, $rowGap) : null;
        $this->minColumns = null !== $minColumns ? max(0, $minColumns) : null;
        $this->shrink = self::factor($shrink);
        $this->widthPercent = null !== $widthPercent ? max(1, min(100, $widthPercent)) : null;
        $this->minRows = null !== $minRows ? max(0, $minRows) : null;
        $this->maxRows = null !== $maxRows ? max(0, $maxRows) : null;
        $this->backgroundColor = null !== $background ? Color::from($background) : null;
        $this->foregroundColor = null !== $color ? Color::from($color) : null;
        $this->gap = null !== $gap ? max(0, $gap) : null;
        $this->flex = self::factor($flex);
    }

    /**
     * A flex factor: never negative, and a whole number as an int, so that 0 stays 0.
     */
    private static function factor(int|float|null $factor): int|float|null
    {
        if (null === $factor) {
            return null;
        }
        $factor = max(0, $factor);

        return (float) (int) $factor === (float) $factor ? (int) $factor : (float) $factor;
    }

    /**
     * Whether another style has the same values, the codes computed from them apart.
     *
     * @internal
     */
    public function equals(?self $other): bool
    {
        return $this === $other || (null !== $other && self::same(clone $this, clone $other));
    }

    /**
     * Strict equality, through the properties of objects: 0 is not null, nor 1.0 is 1, like ===.
     */
    private static function same(mixed $a, mixed $b): bool
    {
        if (\is_array($a)) {
            if (!\is_array($b) || array_keys($a) !== array_keys($b)) {
                return false;
            }
            foreach ($a as $key => $value) {
                if (!self::same($value, $b[$key])) {
                    return false;
                }
            }

            return true;
        }
        if (\is_object($a) && !$a instanceof \UnitEnum && !$a instanceof \Closure) {
            if (!\is_object($b) || $a::class !== $b::class) {
                return false;
            }
            // A nested style without the codes computed from it
            if ($a instanceof self && $a !== $b) {
                $a = clone $a;
                $b = clone $b;
            }

            return $a === $b || self::same((array) $a, (array) $b);
        }

        return $a === $b;
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
     * Get the width as a percentage of the container.
     */
    public function getWidthPercent(): ?int
    {
        return $this->widthPercent;
    }

    public function getShrink(): int|float|null
    {
        return $this->shrink;
    }

    public function getJustify(): ?Justify
    {
        return $this->justify;
    }

    public function getAutoMargin(): ?AutoMargin
    {
        return $this->autoMargin;
    }

    public function getMinColumns(): ?int
    {
        return $this->minColumns;
    }

    public function getOrder(): ?int
    {
        return $this->order;
    }

    public function getAlignSelf(): ?VerticalAlign
    {
        return $this->alignSelf;
    }

    public function getWrap(): ?bool
    {
        return $this->wrap;
    }

    public function getWrapReverse(): ?bool
    {
        return $this->wrapReverse;
    }

    public function getRowGap(): ?int
    {
        return $this->rowGap;
    }

    public function getReverseOrder(): ?bool
    {
        return $this->reverseOrder;
    }

    public function getInvisible(): ?bool
    {
        return $this->invisible;
    }

    public function getFlexBasis(): ?int
    {
        return $this->flexBasis;
    }

    public function getFlexBasisPercent(): ?int
    {
        return $this->flexBasisPercent;
    }

    public function getTableRole(): ?TableRole
    {
        return $this->tableRole;
    }

    public function getColumnSpan(): ?int
    {
        return $this->columnSpan;
    }

    public function getRowSpan(): ?int
    {
        return $this->rowSpan;
    }

    public function getGridTemplate(): ?GridTemplate
    {
        return $this->gridTemplate;
    }

    public function getGridPlacement(): ?GridPlacement
    {
        return $this->gridPlacement;
    }

    public function getScroll(): ?Scroll
    {
        return $this->scroll;
    }

    public function getStickyTop(): ?int
    {
        return $this->stickyTop;
    }

    public function getPosition(): ?Position
    {
        return $this->position;
    }

    public function getColumns(): ?Columns
    {
        return $this->columns;
    }

    public function getAvoidBreak(): ?bool
    {
        return $this->avoidBreak;
    }

    public function getShadow(): ?Shadow
    {
        return $this->shadow;
    }

    public function getSpanAll(): ?bool
    {
        return $this->spanAll;
    }

    public function getCollapse(): ?bool
    {
        return $this->collapse;
    }

    public function getCaptionBottom(): ?bool
    {
        return $this->captionBottom;
    }

    public function getFixedLayout(): ?bool
    {
        return $this->fixedLayout;
    }

    public function getHideEmpty(): ?bool
    {
        return $this->hideEmpty;
    }

    public function getAlignContent(): ?string
    {
        return $this->alignContent;
    }

    public function getInsetShadow(): ?Shadow
    {
        return $this->insetShadow;
    }

    /**
     * The sides, top, right, bottom and left, with a negative margin of less than a cell, like the -1px that overlaps
     * the borders of neighbors.
     *
     * @return array{bool, bool, bool, bool}|null
     */
    public function getMarginHairline(): ?array
    {
        return $this->marginHairline;
    }

    /**
     * @param array{bool, bool, bool, bool}|null $sides
     */
    public function withMarginHairline(?array $sides): self
    {
        $clone = clone $this;
        $clone->marginHairline = null !== $sides && \in_array(true, $sides, true) ? $sides : null;

        return $clone;
    }

    public function getMarginPull(): ?Padding
    {
        return $this->marginPull;
    }

    /**
     * @return Outline|false|null False for outline: none, null when not set
     */
    public function getOutline(): Outline|false|null
    {
        return $this->outline;
    }

    /**
     * @return Filter|false|null False for filter: none, null when not set
     */
    public function getFilter(): Filter|false|null
    {
        return $this->filter;
    }

    public function getOverline(): ?bool
    {
        return $this->overline;
    }

    public function getJustifySelf(): ?string
    {
        return $this->justifySelf;
    }

    public function getJustifyItems(): ?string
    {
        return $this->justifyItems;
    }

    public function getContents(): ?bool
    {
        return $this->contents;
    }

    public function getAspectRatio(): ?float
    {
        return $this->aspectRatio;
    }

    public function getObjectFit(): ?string
    {
        return $this->objectFit;
    }

    /**
     * @return array{float, float, 2?: int, 3?: int}|null
     */
    public function getObjectPosition(): ?array
    {
        return $this->objectPosition;
    }

    /**
     * @return array{Color, int}|null
     */
    public function getTranslucentBackground(): ?array
    {
        return $this->translucentBackground;
    }

    public function getGradient(): ?Gradient
    {
        return $this->gradient;
    }

    /**
     * @return array{int, int}|array{int, int, int, int}|null The rows above and below, then the columns at the left and right
     */
    public function getScrollMargin(): ?array
    {
        return $this->scrollMargin;
    }

    /**
     * @return array{'start'|'center'|'end'|null, bool, 2?: 'start'|'center'|'end'|null}|null Where its scroll container snaps to it down, whether a scroll stops at it, and where it snaps to it sideways
     */
    public function getSnapAlign(): ?array
    {
        return $this->snapAlign;
    }

    /**
     * @return array{bool, bool}|null Before and after
     */
    public function getColumnBreaks(): ?array
    {
        return $this->columnBreaks;
    }

    public function getFloat(): ?string
    {
        return $this->float;
    }

    public function getClear(): ?string
    {
        return $this->clear;
    }

    public function getAnchorName(): ?string
    {
        return $this->anchorName;
    }

    public function getUnderlineStyle(): ?UnderlineStyle
    {
        return $this->underlineStyle;
    }

    public function getUnderlineColor(): ?Color
    {
        return $this->underlineColor;
    }

    public function getMinRowsPercent(): ?int
    {
        return $this->minRowsPercent;
    }

    public function getMaxRowsPercent(): ?int
    {
        return $this->maxRowsPercent;
    }

    /**
     * The rows added to the least and the most height as percentages, like the - 2rem of calc(100% - 2rem).
     *
     * @return array{int, int}
     */
    public function getRowsPercentOffsets(): array
    {
        return $this->rowsPercentOffsets;
    }

    /**
     * Returns the basis in columns for a row of that many columns, null when it comes from the content.
     */
    public function resolveFlexBasis(int $columns): ?int
    {
        return null !== $this->flexBasisPercent ? intdiv($columns * $this->flexBasisPercent, 100) : $this->flexBasis;
    }

    public function getMinRows(): ?int
    {
        return $this->minRows;
    }

    public function getMaxRows(): ?int
    {
        return $this->maxRows;
    }

    /**
     * Get the margin: the space around the border.
     */
    public function getMargin(): ?Padding
    {
        return $this->margin;
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
     * The vertical writing mode of the text, like writing-mode: vertical-rl, vertical-lr, sideways-rl or sideways-lr;
     * null when horizontal.
     */
    public function getWritingMode(): ?string
    {
        return 'horizontal-tb' === $this->writingMode ? null : $this->writingMode;
    }

    /**
     * How many times as large its text is drawn, like font-size: 2em, where the terminal can; null for once.
     */
    public function getFontScale(): ?int
    {
        return 1 === $this->fontScale ? null : $this->fontScale;
    }

    public function withFontScale(?int $fontScale): self
    {
        $clone = clone $this;
        // Once as large: over a scale set before, when merged
        $clone->fontScale = null !== $fontScale && $fontScale > 1 ? min(7, $fontScale) : 1;

        return $clone;
    }

    /**
     * Create new style whose text is written vertically, like writing-mode: vertical-rl; null for horizontal.
     */
    public function withWritingMode(?string $writingMode): self
    {
        $clone = clone $this;
        // Set back to horizontal: over a vertical mode set before, when merged
        $clone->writingMode = $writingMode ?? 'horizontal-tb';

        return $clone;
    }

    /**
     * Get the color of the caret, null for the color of the terminal cursor.
     */
    public function getCaretColor(): ?Color
    {
        return $this->caretColor ?: null;
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
    public function getFlex(): int|float|null
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
            && null === $this->strikethrough && null === $this->overline && null === $this->concealed
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
        $cssCascade = false;
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
        $margin = null;
        $widthPercent = null;
        $widthOffset = 0;
        $widthPercentMax = false;
        $contentBasis = false;
        $rowMaxColumns = null;
        $caretColor = null;
        $writingMode = null;
        $fontScale = null;
        $blend = null;
        $isolated = null;
        $zIndex = null;
        $spaceBefore = null;
        $ownLine = null;
        $documentHeight = null;
        $beforeClear = null;
        $cloneDecoration = null;
        $floatShape = null;
        $imageFromImage = null;
        $pixelated = null;
        $backgroundLayers = null;
        $shapeMargin = null;
        $shapeImageThreshold = null;
        $backgroundBlend = null;
        $minRows = null;
        $maxRows = null;
        $shrink = null;
        $justify = null;
        $autoMargin = null;
        $minColumns = null;
        $order = null;
        $alignSelf = null;
        $wrap = null;
        $rowGap = null;
        $reverseOrder = null;
        $invisible = null;
        $flexBasis = null;
        $flexBasisPercent = null;
        $minRowsPercent = null;
        $maxRowsPercent = null;
        $rowsPercentOffsets = [0, 0];
        $tableRole = null;
        $columnSpan = null;
        $rowSpan = null;
        $underlineStyle = null;
        $underlineColor = null;
        $gridTemplate = null;
        $gridPlacement = null;
        $scroll = null;
        $stickyTop = null;
        $position = null;
        $columns = null;
        $avoidBreak = null;
        $shadow = null;
        $spanAll = null;

        $collapse = null;
        $captionBottom = null;
        $overline = null;
        $justifySelf = null;
        $justifyItems = null;
        $contents = null;
        $aspectRatio = null;
        $objectFit = null;
        $float = null;
        $clear = null;
        $anchorName = null;
        $readingOrder = null;
        $contentHidden = null;
        $backgroundLayout = null;
        $backgroundImage = null;
        $borderImage = null;
        $contentWidth = null;
        $transparent = null;
        $inert = null;
        $autoMarginRows = null;
        $minWidthPercent = null;
        $readingFlow = null;
        $anchorScope = null;
        $objectPosition = null;
        $translucentBackground = null;
        $gradient = null;
        $scrollMargin = null;
        $columnBreaks = null;
        $fixedLayout = null;
        $hideEmpty = null;
        $filter = null;
        $alignContent = null;
        $outline = null;
        $marginPull = null;
        $marginHairline = null;
        $concealed = null;
        $insetShadow = null;
        $snapAlign = null;
        $stickyBottom = null;
        $wrapReverse = null;
        foreach ($styles as $style) {
            $cssCascade = $cssCascade || $style->cssCascade;
            $wrapReverse = $style->wrapReverse ?? $wrapReverse;
            $stickyBottom = $style->stickyBottom ?? $stickyBottom;
            $insetShadow = $style->insetShadow ?? $insetShadow;
            $snapAlign = $style->snapAlign ?? $snapAlign;
            $marginPull = $style->marginPull ?? $marginPull;
            // Set with the margin: a style that sets the margin also says which sides are less than a cell
            $marginHairline = null !== $style->margin ? $style->marginHairline : ($style->marginHairline ?? $marginHairline);
            $concealed = $style->concealed ?? $concealed;
            $alignContent = $style->alignContent ?? $alignContent;
            $outline = $style->outline ?? $outline;
            $columnBreaks = $style->columnBreaks ?? $columnBreaks;
            $fixedLayout = $style->fixedLayout ?? $fixedLayout;
            $hideEmpty = $style->hideEmpty ?? $hideEmpty;
            $filter = $style->filter ?? $filter;
            $scrollMargin = $style->scrollMargin ?? $scrollMargin;
            $gradient = $style->gradient ?? $gradient;
            // A later background replaces it, as declared
            $translucentBackground = null !== $style->backgroundColor ? $style->translucentBackground : $translucentBackground;
            $objectPosition = $style->objectPosition ?? $objectPosition;
            $anchorName = $style->anchorName ?? $anchorName;
            $readingOrder = $style->readingOrder ?? $readingOrder;
            $contentHidden = $style->contentHidden ?? $contentHidden;
            $backgroundLayout = $style->backgroundLayout ?? $backgroundLayout;
            $backgroundImage = $style->backgroundImage ?? $backgroundImage;
            $borderImage = $style->borderImage ?? $borderImage;
            $contentWidth = $style->contentWidth ?? $contentWidth;
            $transparent = $style->transparent ?? $transparent;
            $inert = $style->inert ?? $inert;
            $autoMarginRows = $style->autoMarginRows ?? $autoMarginRows;
            $minWidthPercent = $style->minWidthPercent ?? $minWidthPercent;
            $readingFlow = $style->readingFlow ?? $readingFlow;
            $anchorScope = $style->anchorScope ?? $anchorScope;
            $clear = $style->clear ?? $clear;
            $float = $style->float ?? $float;
            $objectFit = $style->objectFit ?? $objectFit;
            $aspectRatio = $style->aspectRatio ?? $aspectRatio;
            $contents = $style->contents ?? $contents;
            $justifyItems = $style->justifyItems ?? $justifyItems;
            $justifySelf = $style->justifySelf ?? $justifySelf;
            $overline = $style->overline ?? $overline;
            $captionBottom = $style->captionBottom ?? $captionBottom;
            $collapse = $style->collapse ?? $collapse;
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
            $caretColor = $style->caretColor ?? $caretColor;
            $writingMode = $style->writingMode ?? $writingMode;
            $fontScale = $style->fontScale ?? $fontScale;
            $blend = $style->blend ?? $blend;
            $isolated = $style->isolated ?? $isolated;
            $zIndex = $style->zIndex ?? $zIndex;
            $spaceBefore = $style->spaceBefore ?? $spaceBefore;
            $ownLine = $style->ownLine ?? $ownLine;
            $documentHeight = $style->documentHeight ?? $documentHeight;
            $beforeClear = $style->beforeClear ?? $beforeClear;
            $cloneDecoration = $style->cloneDecoration ?? $cloneDecoration;
            $floatShape = $style->floatShape ?? $floatShape;
            $imageFromImage = $style->imageFromImage ?? $imageFromImage;
            $pixelated = $style->pixelated ?? $pixelated;
            $backgroundLayers = $style->backgroundLayers ?? $backgroundLayers;
            $shapeMargin = $style->shapeMargin ?? $shapeMargin;
            $shapeImageThreshold = $style->shapeImageThreshold ?? $shapeImageThreshold;
            $backgroundBlend = $style->backgroundBlend ?? $backgroundBlend;
            $textAlign = $style->textAlign ?? $textAlign;
            $font = $style->font ?? $font;
            if (null !== $style->maxColumns) {
                $maxColumns = $style->maxColumns;
                $rowMaxColumns = $style->rowMaxColumns;
            }
            $align = $style->align ?? $align;
            $verticalAlign = $style->verticalAlign ?? $verticalAlign;
            $flex = $style->flex ?? $flex;
            $margin = $style->margin ?? $margin;
            if (null !== $style->widthPercent) {
                $widthPercent = $style->widthPercent;
                $widthOffset = $style->widthOffset;
                $widthPercentMax = $style->widthPercentMax;
            }
            $minRows = $style->minRows ?? $minRows;
            $maxRows = $style->maxRows ?? $maxRows;
            $shrink = $style->shrink ?? $shrink;
            $justify = $style->justify ?? $justify;
            // Set with the margin: a style that sets the margin also says which sides are auto
            $autoMargin = null !== $style->margin ? $style->autoMargin : ($style->autoMargin ?? $autoMargin);
            $minColumns = $style->minColumns ?? $minColumns;
            $order = $style->order ?? $order;
            $alignSelf = $style->alignSelf ?? $alignSelf;
            $wrap = $style->wrap ?? $wrap;
            $rowGap = $style->rowGap ?? $rowGap;
            $reverseOrder = $style->reverseOrder ?? $reverseOrder;
            $invisible = $style->invisible ?? $invisible;
            // Set together: a basis in columns replaces a percentage, and back
            $rowsPercentOffsets = [null !== $style->minRowsPercent ? $style->rowsPercentOffsets[0] : $rowsPercentOffsets[0], null !== $style->maxRowsPercent ? $style->rowsPercentOffsets[1] : $rowsPercentOffsets[1]];
            $minRowsPercent = $style->minRowsPercent ?? $minRowsPercent;
            $maxRowsPercent = $style->maxRowsPercent ?? $maxRowsPercent;
            $tableRole = $style->tableRole ?? $tableRole;
            $columnSpan = $style->columnSpan ?? $columnSpan;
            $rowSpan = $style->rowSpan ?? $rowSpan;
            $underlineStyle = $style->underlineStyle ?? $underlineStyle;
            $underlineColor = $style->underlineColor ?? $underlineColor;
            $gridTemplate = $style->gridTemplate ?? $gridTemplate;
            $gridPlacement = $style->gridPlacement ?? $gridPlacement;
            $scroll = $style->scroll ?? $scroll;
            $stickyTop = $style->stickyTop ?? $stickyTop;
            $position = $style->position ?? $position;
            $columns = $style->columns ?? $columns;
            $avoidBreak = $style->avoidBreak ?? $avoidBreak;
            $shadow = $style->shadow ?? $shadow;
            $spanAll = $style->spanAll ?? $spanAll;
            if (null !== $style->flexBasis || null !== $style->flexBasisPercent || $style->contentBasis) {
                $flexBasis = $style->flexBasis;
                $flexBasisPercent = $style->flexBasisPercent;
                $contentBasis = $style->contentBasis;
            }
        }

        $merged = new self(
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
            $margin,
            $widthPercent,
            $minRows,
            $maxRows,
            $shrink,
            $justify,
            $autoMargin,
            $minColumns,
            $order,
            $alignSelf,
            $wrap,
            $rowGap,
            $reverseOrder,
            $invisible,
            $flexBasis,
            $flexBasisPercent,
            $minRowsPercent,
            $maxRowsPercent,
            $tableRole,
            $columnSpan,
            $rowSpan,
            $underlineStyle,
            $underlineColor,
            $gridTemplate,
            $gridPlacement,
            $scroll,
            $stickyTop,
            $position,
            $columns,
            $avoidBreak,
            $shadow,
            $spanAll,
            $collapse,
            $captionBottom,
            $overline,
            $justifySelf,
            $justifyItems,
            $contents,
            $aspectRatio,
            $objectFit,
            $float,
            $clear,
            $anchorName,
            $objectPosition,
            $translucentBackground,
            $gradient,
            $scrollMargin,
            $columnBreaks,
            $fixedLayout,
            $hideEmpty,
            $filter,
            $alignContent,
            $outline,
            $marginPull,
            $insetShadow,
            $snapAlign,
            $stickyBottom,
            $wrapReverse,
            $anchorScope,
            $readingFlow,
            $readingOrder,
            $contentHidden,
            $backgroundLayout,
            $backgroundImage,
            $borderImage,
            $contentWidth,
            $transparent,
            $inert,
            $autoMarginRows,
            $minWidthPercent,
        );
        $merged->widthOffset = $widthOffset;
        $merged->widthPercentMax = $widthPercentMax;
        $merged->contentBasis = $contentBasis;
        $merged->rowMaxColumns = $rowMaxColumns;
        $merged->caretColor = $caretColor;
        $merged->writingMode = $writingMode;
        $merged->fontScale = $fontScale;
        $merged->blend = $blend;
        $merged->isolated = $isolated;
        $merged->zIndex = $zIndex;
        $merged->spaceBefore = $spaceBefore;
        $merged->ownLine = $ownLine;
        $merged->documentHeight = $documentHeight;
        $merged->beforeClear = $beforeClear;
        $merged->cloneDecoration = $cloneDecoration;
        $merged->floatShape = $floatShape;
        $merged->imageFromImage = $imageFromImage;
        $merged->pixelated = $pixelated;
        $merged->backgroundLayers = $backgroundLayers;
        $merged->shapeMargin = $shapeMargin;
        $merged->shapeImageThreshold = $shapeImageThreshold;
        $merged->backgroundBlend = $backgroundBlend;
        $merged->marginHairline = $marginHairline;
        $merged->concealed = $concealed;
        $merged->rowsPercentOffsets = $rowsPercentOffsets;

        $merged->cssCascade = $cssCascade;

        return $merged;
    }

    /**
     * Create new style with a width relative to the container.
     *
     * In a horizontal layout, the widget takes that share of the row before
     * flexible siblings; elsewhere, it is a maximum width.
     */
    public function withWidthPercent(?int $percent, int $offset = 0): self
    {
        $clone = clone $this;
        $clone->widthPercent = null !== $percent ? max(1, min(100, $percent)) : null;
        $clone->widthOffset = null !== $percent ? max(-10_000, min(10_000, $offset)) : 0;
        $clone->widthPercentMax = false;

        return $clone;
    }

    /**
     * Create new style whose percentage width is a bound only, like max-width: 75%: in a row, the widget is as wide
     * as its content up to it, not that share of the row.
     */
    public function withWidthPercentMax(bool $max = true): self
    {
        $clone = clone $this;
        $clone->widthPercentMax = $max && null !== $this->widthPercent;

        return $clone;
    }

    public function isWidthPercentMax(): bool
    {
        return $this->widthPercentMax;
    }

    /**
     * The columns added to the percentage width, like the -20ch of calc(100% - 20ch).
     */
    public function getWidthOffset(): int
    {
        return $this->widthOffset;
    }

    /**
     * The width of a percentage width in some room, its offset included: at least one column; null without one.
     */
    public function resolveWidthPercent(int $room): ?int
    {
        return null === $this->widthPercent ? null : max(1, intdiv(max(0, $room) * $this->widthPercent, 100) + $this->widthOffset);
    }

    /**
     * Create new style with a shrink factor, like CSS flex-shrink.
     *
     * When the intrinsic widths of a row do not fit, widgets give way in
     * proportion to their width times this factor; 0 never gives way, unless
     * nothing else can.
     */
    public function withShrink(int|float|null $shrink): self
    {
        $clone = clone $this;
        $clone->shrink = self::factor($shrink);

        return $clone;
    }

    /**
     * Create new style with the width a widget starts from in a row, like CSS
     * flex-basis: a widget sized by its content takes it instead, and a
     * growing one adds its share of the free columns to it.
     */
    public function withFlexBasis(?int $columns): self
    {
        $clone = clone $this;
        $clone->flexBasis = null !== $columns ? max(0, $columns) : null;
        $clone->flexBasisPercent = null;
        $clone->contentBasis = false;

        return $clone;
    }

    /**
     * Create new style whose widget, when it grows, starts from its content width, like the flex-basis: auto of CSS
     * of flex-grow: 1 or flex: 1 1 auto, instead of sharing the whole row with its siblings.
     */
    public function withContentBasis(bool $content = true): self
    {
        $clone = clone $this;
        $clone->flexBasis = null;
        $clone->flexBasisPercent = null;
        $clone->contentBasis = $content;

        return $clone;
    }

    public function hasContentBasis(): bool
    {
        return $this->contentBasis;
    }

    /**
     * Create new style that makes the widget a table, a row group, a row or a cell, like CSS display: table...
     */
    public function withTableRole(?TableRole $tableRole): self
    {
        $clone = clone $this;
        $clone->tableRole = $tableRole;

        return $clone;
    }

    /**
     * Create new style with the number of table columns a cell spans, like colspan.
     */
    public function withColumnSpan(?int $columnSpan): self
    {
        $clone = clone $this;
        $clone->columnSpan = null !== $columnSpan ? max(1, min(1000, $columnSpan)) : null;

        return $clone;
    }

    /**
     * Create new style that lays the children out on a grid, like CSS display: grid: row after
     * row of the columns of the template, an item spanning as many as its column span says.
     */
    public function withGridTemplate(?GridTemplate $gridTemplate): self
    {
        $clone = clone $this;
        $clone->gridTemplate = $gridTemplate;

        return $clone;
    }

    /**
     * Create new style that places the widget on the grid of its parent, like CSS grid-area, grid-column and grid-row.
     */
    public function withGridPlacement(?GridPlacement $gridPlacement): self
    {
        $clone = clone $this;
        $clone->gridPlacement = $gridPlacement;

        return $clone;
    }

    /**
     * Create new style that scrolls the content taller than the container, like CSS overflow: auto.
     */
    public function withScroll(?Scroll $scroll): self
    {
        $clone = clone $this;
        $clone->scroll = $scroll;

        return $clone;
    }

    /**
     * Create new style that flows the content of a container into columns, like CSS columns.
     */
    public function withColumns(?Columns $columns): self
    {
        $clone = clone $this;
        $clone->columns = $columns;

        return $clone;
    }

    /**
     * Create new style that spans all the columns of a multi-column parent, like column-span: all.
     */
    public function withSpanAll(?bool $spanAll = true): self
    {
        $clone = clone $this;
        $clone->spanAll = $spanAll;

        return $clone;
    }

    /**
     * Create new style that draws the borders of a table and of its cells as one grid, like border-collapse: collapse.
     */
    public function withCollapse(?bool $collapse = true): self
    {
        $clone = clone $this;
        $clone->collapse = $collapse;

        return $clone;
    }

    /**
     * Create new style for a table that sizes its columns from its columns and its first row only, like table-layout: fixed:
     * the rows after it are not measured, and the columns without a width share the width of the table left.
     */
    public function withFixedLayout(?bool $fixedLayout = true): self
    {
        $clone = clone $this;
        $clone->fixedLayout = $fixedLayout;

        return $clone;
    }

    /**
     * The style with its percentage padding and margins resolved against the width of the containing block.
     *
     * @internal
     */
    public function withResolvedBox(int $containingColumns): self
    {
        $style = $this;
        if (true === $this->padding?->hasPercents()) {
            $style = $style->withPadding($this->padding->resolve($containingColumns));
        }
        if (true === $this->margin?->hasPercents()) {
            $style = $style->withMargin($this->margin->resolve($containingColumns));
        }

        return $style;
    }

    /**
     * Create new style that filters the colors of the widget and of what it contains, like CSS filter.
     *
     * @param Filter|false|null $filter False for none, over a filter set before
     */
    public function withFilter(Filter|false|null $filter): self
    {
        $clone = clone $this;
        $clone->filter = $filter;

        return $clone;
    }

    /**
     * Create new style for a table cell that draws no border nor background when it has no content, like empty-cells: hide;
     * it keeps its room. The cells of a collapsed table are always drawn.
     */
    public function withHideEmpty(?bool $hideEmpty = true): self
    {
        $clone = clone $this;
        $clone->hideEmpty = $hideEmpty;

        return $clone;
    }

    /**
     * Create new style that shares the free rows of a container of a set height between its wrapped lines or grid rows, like CSS align-content.
     *
     * @param 'start'|'center'|'end'|'space-between'|'space-around'|'space-evenly'|'stretch'|null $alignContent
     */
    public function withAlignContent(?string $alignContent): self
    {
        $clone = clone $this;
        $clone->alignContent = $alignContent;

        return $clone;
    }

    /**
     * Create new style with a shadow inside the box, along the edges it is offset from, like box-shadow: inset.
     */
    public function withInsetShadow(?Shadow $insetShadow): self
    {
        $clone = clone $this;
        $clone->insetShadow = $insetShadow;

        return $clone;
    }

    /**
     * Create new style with negative margins, as the positive rows and columns the box is pulled by: its top and bottom
     * ones overlap what is before and after it in a block layout, like CSS.
     */
    public function withMarginPull(?Padding $marginPull): self
    {
        $clone = clone $this;
        $clone->marginPull = $marginPull;

        return $clone;
    }

    /**
     * Create new style with a line around the box, over what is next to it, like CSS outline; false for none.
     */
    public function withOutline(Outline|false|null $outline): self
    {
        $clone = clone $this;
        $clone->outline = $outline;

        return $clone;
    }

    /**
     * Create new style that puts a table caption below the table, like caption-side: bottom.
     */
    public function withCaptionBottom(?bool $captionBottom = true): self
    {
        $clone = clone $this;
        $clone->captionBottom = $captionBottom;

        return $clone;
    }

    /**
     * Whether its text is drawn as spaces, like CSS color: transparent.
     */
    public function getConcealed(): ?bool
    {
        return $this->concealed;
    }

    /**
     * Create new style whose text is drawn as spaces, its backgrounds kept, like CSS color: transparent; false draws it
     * again, like another color.
     */
    public function withConcealed(?bool $concealed = true): self
    {
        $clone = clone $this;
        $clone->concealed = $concealed;

        return $clone;
    }

    /**
     * Create new style with a line over the text, like CSS text-decoration: overline.
     */
    public function withOverline(?bool $overline = true): self
    {
        $clone = clone $this;
        $clone->overline = $overline;

        return $clone;
    }

    /**
     * Create new style that places a grid item in its area along the row, like CSS justify-self: start, center, end or stretch.
     */
    public function withJustifySelf(?string $justifySelf): self
    {
        $clone = clone $this;
        $clone->justifySelf = $justifySelf;

        return $clone;
    }

    /**
     * Create new style that places the items of a grid in their areas along the row, like CSS justify-items.
     */
    public function withJustifyItems(?string $justifyItems): self
    {
        $clone = clone $this;
        $clone->justifyItems = $justifyItems;

        return $clone;
    }

    /**
     * Create new style without a box: the children are laid out in the parent, like CSS display: contents.
     */
    public function withContents(?bool $contents = true): self
    {
        $clone = clone $this;
        $clone->contents = $contents;

        return $clone;
    }

    /**
     * Create new style with a height that follows the width, like CSS aspect-ratio.
     */
    public function withAspectRatio(?float $aspectRatio): self
    {
        $clone = clone $this;
        $clone->aspectRatio = $aspectRatio;

        return $clone;
    }

    /**
     * Create new style that says how an image fills its box, like CSS object-fit.
     */
    public function withObjectFit(?string $objectFit): self
    {
        $clone = clone $this;
        $clone->objectFit = $objectFit;

        return $clone;
    }

    /**
     * Create new style that puts the widget at the left or right of its block container, like CSS float.
     */
    public function withFloat(?string $float): self
    {
        $clone = clone $this;
        $clone->float = $float;

        return $clone;
    }

    /**
     * The layers of the background when there are several, from the top one, each with its layout, and the color they
     * are over, like several background images (null = not set, a single one, see getGradient() and getBackgroundImage()).
     *
     * @return array{list<array{0: Gradient|RasterImage, 1: BackgroundLayout, 2?: ?BlendMode}>, Color, bool}|null The layers, the color under them, and whether it is the background color, which they blend with
     */
    public function getBackgroundLayers(): ?array
    {
        return $this->backgroundLayers;
    }

    /**
     * Create new style with the layers of a list of background images, from the top one, over a color.
     *
     * @param list<array{0: Gradient|RasterImage, 1: BackgroundLayout, 2?: ?BlendMode}> $layers
     */
    public function withBackgroundLayers(array $layers, Color $under, bool $background = true): self
    {
        $clone = clone $this;
        $clone->backgroundLayers = $layers ? [array_values($layers), $under, $background] : null;

        return $clone;
    }

    /**
     * Whether images are turned as their EXIF data says, like CSS image-orientation (null = not set, from-image).
     */
    public function getImageFromImage(): ?bool
    {
        return $this->imageFromImage;
    }

    /**
     * Create new style that turns images as their EXIF data says or draws them as stored, like CSS image-orientation.
     */
    public function withImageFromImage(?bool $fromImage): self
    {
        $clone = clone $this;
        $clone->imageFromImage = $fromImage;

        return $clone;
    }

    /**
     * Whether images are scaled keeping their pixels sharp, like CSS image-rendering: pixelated (null = not set, smooth).
     */
    public function getPixelated(): ?bool
    {
        return $this->pixelated;
    }

    /**
     * Create new style that scales images keeping their pixels sharp or smoothly, like CSS image-rendering.
     */
    public function withPixelated(?bool $pixelated): self
    {
        $clone = clone $this;
        $clone->pixelated = $pixelated;

        return $clone;
    }

    /**
     * The shape the text beside a float wraps around, like CSS shape-outside (null = not set, its box).
     */
    public function getFloatShape(): ?FloatShape
    {
        return $this->floatShape;
    }

    /**
     * Create new style that has the text beside a float wrap around a shape, like CSS shape-outside.
     */
    public function withFloatShape(?FloatShape $shape): self
    {
        $clone = clone $this;
        $clone->floatShape = $shape;

        return $clone;
    }

    /**
     * The opacity from 0 to 1 the pixels of an image shape of a float are part of it past, like CSS shape-image-threshold (null = not set, 0).
     */
    public function getShapeImageThreshold(): ?float
    {
        return $this->shapeImageThreshold;
    }

    /**
     * Create new style with the opacity the pixels of an image shape of a float are part of it past, like CSS shape-image-threshold.
     */
    public function withShapeImageThreshold(?float $threshold): self
    {
        $clone = clone $this;
        $clone->shapeImageThreshold = null !== $threshold ? max(0.0, min(1.0, $threshold)) : null;

        return $clone;
    }

    /**
     * The room kept around the shape of a float, in columns, like CSS shape-margin (null = not set, none).
     */
    public function getShapeMargin(): ?float
    {
        return $this->shapeMargin;
    }

    /**
     * Create new style that keeps room around the shape of a float, in columns, like CSS shape-margin.
     */
    public function withShapeMargin(?float $columns): self
    {
        $clone = clone $this;
        $clone->shapeMargin = null !== $columns ? max(0.0, $columns) : null;

        return $clone;
    }

    /**
     * Create new style that puts the widget below the floats before it, like CSS clear.
     */
    public function withClear(?string $clear): self
    {
        $clone = clone $this;
        $clone->clear = $clear;

        return $clone;
    }

    /**
     * Create new style that names the widget for anchor positioning, like CSS anchor-name.
     */
    public function withAnchorName(?string $anchorName): self
    {
        $clone = clone $this;
        $clone->anchorName = $anchorName;

        return $clone;
    }

    public function getReadingOrder(): ?int
    {
        return $this->readingOrder;
    }

    /**
     * Create new style that places the widget in the reading flow of its parent, like CSS reading-order.
     */
    public function withReadingOrder(?int $readingOrder): self
    {
        $clone = clone $this;
        $clone->readingOrder = $readingOrder;

        return $clone;
    }

    public function getContentHidden(): ?bool
    {
        return $this->contentHidden;
    }

    /**
     * Skips the content of the widget, its children or its text, and keeps its box, like CSS content-visibility:
     * hidden; its size is that of an empty box, unless it has one.
     */
    public function withContentHidden(?bool $contentHidden = true): self
    {
        $clone = clone $this;
        $clone->contentHidden = $contentHidden;

        return $clone;
    }

    public function getBackgroundLayout(): ?BackgroundLayout
    {
        return $this->backgroundLayout;
    }

    /**
     * Sets where the background is painted, and how its gradient is sized, placed and repeated.
     */
    public function withBackgroundLayout(?BackgroundLayout $backgroundLayout): self
    {
        $clone = clone $this;
        $clone->backgroundLayout = $backgroundLayout;

        return $clone;
    }

    /**
     * The image of the background; null when there is none, or when it is set to none.
     */
    public function getBackgroundImage(): ?RasterImage
    {
        return $this->backgroundImage ?: null;
    }

    /**
     * Sets the image of the background, like background-image: url(); false removes the one an earlier style sets,
     * like background-image: none.
     */
    public function withBackgroundImage(RasterImage|false|null $image): self
    {
        $clone = clone $this;
        $clone->backgroundImage = $image;

        return $clone;
    }

    /**
     * What colors the lines of the border; null when nothing does.
     */
    public function getBorderImage(): Gradient|RasterImage|null
    {
        return $this->borderImage ?: null;
    }

    /**
     * Colors the lines of the border, cell by cell, with a gradient or an image, like border-image-source; false
     * removes what an earlier style sets, like border-image: none.
     */
    public function withBorderImage(Gradient|RasterImage|false|null $image): self
    {
        $clone = clone $this;
        $clone->borderImage = $image;

        return $clone;
    }

    /**
     * Whether the widget is as wide as its content (fit-content), or as narrow as it can be (min-content); null when
     * it takes the room it gets.
     *
     * @return 'fit-content'|'min-content'|null
     */
    public function getContentWidth(): ?string
    {
        return $this->contentWidth ?: null;
    }

    /**
     * Makes the widget as wide as its content, like CSS width: fit-content or max-content, or as narrow as it can be
     * without overflowing, like min-content; false makes it take the room it gets again.
     *
     * @param 'fit-content'|'min-content'|false|null $contentWidth
     */
    public function withContentWidth(string|false|null $contentWidth): self
    {
        $clone = clone $this;
        $clone->contentWidth = $contentWidth;

        return $clone;
    }

    public function getTransparent(): ?bool
    {
        return $this->transparent;
    }

    /**
     * Draws nothing of the widget, what is behind it showing, though it keeps the focus and the pointer, like CSS
     * opacity: 0; unlike invisible, it stays in the focus cycle.
     */
    public function withTransparent(?bool $transparent = true): self
    {
        $clone = clone $this;
        $clone->transparent = $transparent;

        return $clone;
    }

    /**
     * @return array{bool, bool}|null Whether the top and the bottom margins are auto, null for neither
     */
    public function getMinWidthPercent(): ?float
    {
        return $this->minWidthPercent;
    }

    /**
     * Sets the least width of a box sized by its content, like a table, as a percentage of its container, like CSS
     * min-width: 100%, which stretches a narrower table to the room.
     */
    public function withMinWidthPercent(?float $percent): self
    {
        $clone = clone $this;
        $clone->minWidthPercent = null !== $percent ? max(0.0, min(100.0, $percent)) : null;

        return $clone;
    }

    public function getAutoMarginRows(): ?array
    {
        return $this->autoMarginRows;
    }

    /**
     * Makes the top and bottom margins take the free rows of a flex column of a set height, like CSS margin-top: auto,
     * which pushes the widget to the bottom.
     *
     * @param array{bool, bool}|null $sides The top and the bottom, null for neither
     */
    public function withAutoMarginRows(?array $sides): self
    {
        $clone = clone $this;
        $clone->autoMarginRows = null === $sides || [false, false] === $sides ? null : [(bool) $sides[0], (bool) $sides[1]];

        return $clone;
    }

    public function getInert(): ?bool
    {
        return $this->inert;
    }

    /**
     * Makes the widget take neither the focus nor the pointer, like CSS interactivity: inert; what is behind it takes
     * the pointer.
     */
    public function withInert(?bool $inert = true): self
    {
        $clone = clone $this;
        $clone->inert = $inert;

        return $clone;
    }

    public function getReadingFlow(): ?string
    {
        return $this->readingFlow;
    }

    /**
     * Create new style that orders the focus through the children of the widget, like CSS reading-flow.
     */
    public function withReadingFlow(?string $readingFlow): self
    {
        $clone = clone $this;
        $clone->readingFlow = $readingFlow;

        return $clone;
    }

    /**
     * @return list<string>|null The names, "all" for every name, [] for none
     */
    public function getAnchorScope(): ?array
    {
        return $this->anchorScope;
    }

    /**
     * Create new style that keeps anchor names inside the widget, like CSS anchor-scope.
     *
     * @param list<string>|null $anchorScope The names, "all" for every name, [] for none
     */
    public function withAnchorScope(?array $anchorScope): self
    {
        $clone = clone $this;
        $clone->anchorScope = $anchorScope;

        return $clone;
    }

    /**
     * Create new style that says where an image is in its box, like CSS object-position.
     *
     * @param array{float, float, 2?: int, 3?: int}|null $objectPosition
     */
    public function withObjectPosition(?array $objectPosition): self
    {
        $clone = clone $this;
        $clone->objectPosition = $objectPosition;

        return $clone;
    }

    /**
     * Create new style with a translucent background as declared, before it is blended: what a ::backdrop lays over the page.
     *
     * @param array{Color, int}|null $translucentBackground
     */
    public function withTranslucentBackground(?array $translucentBackground): self
    {
        $clone = clone $this;
        $clone->translucentBackground = $translucentBackground;

        return $clone;
    }

    /**
     * Create new style with a gradient background, like CSS linear-gradient() and radial-gradient().
     */
    public function withGradient(?Gradient $gradient): self
    {
        $clone = clone $this;
        $clone->gradient = $gradient;

        return $clone;
    }

    /**
     * Create new style with where its scroll container snaps to it, like CSS scroll-snap-align.
     *
     * @param 'start'|'center'|'end'|null $align    Where down, null for none
     * @param bool                        $always   Whether a scroll stops at it rather than going past it, like scroll-snap-stop: always
     * @param 'start'|'center'|'end'|null $sideways Where sideways, null for none
     */
    public function withSnapAlign(?string $align, bool $always = false, ?string $sideways = null): self
    {
        $clone = clone $this;
        $clone->snapAlign = null !== $align || null !== $sideways ? [$align, $always, $sideways] : null;

        return $clone;
    }

    /**
     * Create new style with the rows kept around the element when it is scrolled into view, like CSS scroll-margin.
     *
     * @param array{int, int}|array{int, int, int, int}|null $scrollMargin The rows above and below, then the columns at the left and right
     */
    public function withScrollMargin(?array $scrollMargin): self
    {
        $clone = clone $this;
        $clone->scrollMargin = $scrollMargin;

        return $clone;
    }

    /**
     * Create new style that starts a column before or after the element in a multi-column container, like CSS break-before and break-after.
     *
     * @param array{bool, bool, 2?: bool, 3?: bool}|null $columnBreaks Before and after, then whether they are avoided before and after
     */
    public function withColumnBreaks(?array $columnBreaks): self
    {
        $clone = clone $this;
        $clone->columnBreaks = $columnBreaks;

        return $clone;
    }

    /**
     * Create new style with a drop shadow, like CSS box-shadow.
     */
    public function withShadow(?Shadow $shadow): self
    {
        $clone = clone $this;
        $clone->shadow = $shadow;

        return $clone;
    }

    /**
     * Create new style that keeps the widget in one column of a multi-column parent, like break-inside: avoid.
     */
    public function withAvoidBreak(?bool $avoid = true): self
    {
        $clone = clone $this;
        $clone->avoidBreak = $avoid;

        return $clone;
    }

    /**
     * Create new style that positions the widget, like CSS position with its insets and z-index.
     */
    public function withPosition(?Position $position): self
    {
        $clone = clone $this;
        $clone->position = $position;

        return $clone;
    }

    /**
     * Create new style that sticks the widget some rows below the top of its scroll container, like CSS position: sticky.
     */
    public function withStickyTop(?int $rows): self
    {
        $clone = clone $this;
        $clone->stickyTop = null !== $rows ? max(0, $rows) : null;

        return $clone;
    }

    /**
     * Create new style with the rows from the bottom of its scroll container where the widget sticks while its parent
     * is in view, like CSS position: sticky with bottom.
     */
    public function withStickyBottom(?int $rows): self
    {
        $clone = clone $this;
        $clone->stickyBottom = null !== $rows ? max(0, $rows) : null;

        return $clone;
    }

    public function getStickyBottom(): ?int
    {
        return $this->stickyBottom;
    }

    /**
     * Create new style with the shape and color of the underline, like CSS
     * text-decoration-style and text-decoration-color; they apply when the text is underlined.
     */
    public function withUnderlineStyle(?UnderlineStyle $style, Color|string|int|null $color = null): self
    {
        $clone = clone $this;
        $clone->underlineStyle = $style;
        $clone->underlineColor = null !== $color ? Color::from($color) : null;
        $clone->ansiPrefix = null;

        return $clone;
    }

    /**
     * Create new style with the number of table rows a cell spans, like rowspan: its column stays taken in the rows below.
     */
    public function withRowSpan(?int $rowSpan): self
    {
        $clone = clone $this;
        $clone->rowSpan = null !== $rowSpan ? max(1, min(65_534, $rowSpan)) : null;

        return $clone;
    }

    /**
     * Create new style with bounds on the height as percentages of the parent, like CSS height: 50%.
     *
     * They apply when the parent has a definite height: the root, or a
     * container with a fixed or percentage height; elsewhere they are ignored,
     * like in CSS. The rows count the border and the padding. Rows are added
     * to each, like calc(100% - 2rem).
     */
    public function withRowsPercent(?int $min, ?int $max, int $minOffset = 0, int $maxOffset = 0): self
    {
        $clone = clone $this;
        $clone->minRowsPercent = null !== $min ? max(0, min(100, $min)) : null;
        $clone->maxRowsPercent = null !== $max ? max(0, min(100, $max)) : null;
        $clone->rowsPercentOffsets = [null !== $min ? max(-10_000, min(10_000, $minOffset)) : 0, null !== $max ? max(-10_000, min(10_000, $maxOffset)) : 0];

        return $clone;
    }

    /**
     * Create new style with the basis as a percentage of the row, see withFlexBasis().
     */
    public function withFlexBasisPercent(?int $percent): self
    {
        $clone = clone $this;
        $clone->flexBasisPercent = null !== $percent ? max(0, min(100, $percent)) : null;
        $clone->flexBasis = null;
        $clone->contentBasis = false;

        return $clone;
    }

    /**
     * Create new style whose widget keeps its room in the layout but draws
     * nothing, its children included, like CSS visibility: hidden. Unlike
     * hidden, the room stays; like it, the widget leaves the focus cycle.
     */
    public function withInvisible(?bool $invisible = true): self
    {
        $clone = clone $this;
        $clone->invisible = $invisible;

        return $clone;
    }

    /**
     * Create new style that lays the children out from the last to the
     * first, like CSS row-reverse and column-reverse; the focus order does
     * not change.
     */
    public function withReverseOrder(?bool $reverseOrder): self
    {
        $clone = clone $this;
        $clone->reverseOrder = $reverseOrder;

        return $clone;
    }

    /**
     * Create new style whose row breaks into lines when its children do not
     * fit, like CSS flex-wrap; each line is laid out like a row of its own.
     *
     * @param int|null $rowGap Rows between the lines, null keeps the current value
     */
    public function withWrap(?bool $wrap, ?int $rowGap = null, ?bool $reverse = null): self
    {
        $clone = clone $this;
        $clone->wrap = $wrap;
        $clone->wrapReverse = $reverse ?? $clone->wrapReverse;
        if (null !== $rowGap) {
            $clone->rowGap = max(0, $rowGap);
        }

        return $clone;
    }

    /**
     * Create new style that aligns the widget in its row, over the alignment
     * of the row, like CSS align-self; null follows the row.
     */
    public function withAlignSelf(?VerticalAlign $alignSelf): self
    {
        $clone = clone $this;
        $clone->alignSelf = $alignSelf;

        return $clone;
    }

    /**
     * Create new style that places the widget among its siblings, like CSS order.
     *
     * Siblings are laid out by increasing order, then in their order in the
     * tree; the focus order does not change.
     */
    public function withOrder(?int $order): self
    {
        $clone = clone $this;
        $clone->order = $order;

        return $clone;
    }

    /**
     * Create new style with a minimum width, like CSS min-width.
     *
     * A widget sized by its content does not shrink below it in a row while
     * its siblings can give way, and a narrower block (max-width, percentage)
     * is not narrowed below it; it never exceeds the room it gets.
     */
    public function withMinColumns(?int $minColumns): self
    {
        $clone = clone $this;
        $clone->minColumns = null !== $minColumns ? max(0, $minColumns) : null;

        return $clone;
    }

    /**
     * Create new style whose horizontal margins take the free columns, like CSS "margin: auto".
     */
    public function withAutoMargin(?AutoMargin $autoMargin): self
    {
        $clone = clone $this;
        $clone->autoMargin = $autoMargin;

        return $clone;
    }

    /**
     * Create new style that shares the free columns of a horizontal row
     * between its children; null leaves them to the alignment.
     */
    public function withJustify(?Justify $justify): self
    {
        $clone = clone $this;
        $clone->justify = $justify;

        return $clone;
    }

    /**
     * Create new style with bounds on the number of content rows.
     */
    public function withRows(?int $minRows, ?int $maxRows): self
    {
        $clone = clone $this;
        $clone->minRows = null !== $minRows ? max(0, $minRows) : null;
        $clone->maxRows = null !== $maxRows ? max(0, $maxRows) : null;

        return $clone;
    }

    /**
     * Create new style with a different margin.
     *
     * @param Padding|array<int> $margin Margin specification (see Padding::from())
     */
    public function withMargin(Padding|array $margin): self
    {
        $clone = clone $this;
        $clone->margin = Padding::from($margin);

        return $clone;
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
        // Another background: not the translucent one declared anymore
        $clone->translucentBackground = null;

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
     * Create new style without text decorations, neither underline nor line through or over, like the border and the
     * padding of a box whose text is decorated.
     *
     * @internal
     */
    public function withoutTextDecorations(): self
    {
        if (null === $this->underline && null === $this->strikethrough && null === $this->overline) {
            return $this;
        }
        $clone = clone $this;
        $clone->underline = $clone->strikethrough = $clone->overline = null;

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
     * Create new style with the color of the caret, null for the color of the terminal cursor.
     */
    public function withCaretColor(?Color $caretColor): self
    {
        $clone = clone $this;
        // Set back to the color of the terminal cursor: over a color set before, when merged
        $clone->caretColor = $caretColor ?? false;

        return $clone;
    }

    /**
     * @return array{BlendMode, Color|null}|null The mode the widget and what it contains blend with, and the color behind them (null for the terminal background); null for normal
     */
    public function getBlend(): ?array
    {
        return $this->blend;
    }

    /**
     * Create new style whose colors, and those of what it contains, blend with the color behind it, like mix-blend-mode.
     *
     * @param Color|null $backdrop The color behind the widget, null for the terminal background
     */
    public function withBlend(?BlendMode $mode, ?Color $backdrop = null): self
    {
        $clone = clone $this;
        $clone->blend = null !== $mode && BlendMode::Normal !== $mode ? [$mode, $backdrop] : null;

        return $clone;
    }

    public function getCloneDecoration(): ?bool
    {
        return $this->cloneDecoration;
    }

    /**
     * Create new style that draws the border and the padding of the widget on both sides of where a multi-column
     * parent cuts it, each piece a box of its own, like box-decoration-break: clone; otherwise the box is sliced, its
     * bottom edge in the last piece only.
     */
    public function withCloneDecoration(?bool $clone = true): self
    {
        $style = clone $this;
        $style->cloneDecoration = $clone;

        return $style;
    }

    public function getDocumentHeight(): ?bool
    {
        return $this->documentHeight;
    }

    /**
     * Create new style whose least height is the height of a document its content overflows, like the height: 100% of
     * a <body>, which passes it to the percentages in it.
     */
    public function withDocumentHeight(?bool $documentHeight = true): self
    {
        $clone = clone $this;
        $clone->documentHeight = $documentHeight;

        return $clone;
    }

    /**
     * @internal
     */
    public function getBeforeClear(): ?string
    {
        return $this->beforeClear;
    }

    /**
     * Create new style whose content starts below the floats beside it, its box where it is, like an empty ::before
     * displayed as a block that clears them in CSS: left, right or both.
     *
     * @internal
     */
    public function withBeforeClear(?string $beforeClear): self
    {
        $clone = clone $this;
        $clone->beforeClear = $beforeClear;

        return $clone;
    }

    public function getOwnLine(): ?bool
    {
        return $this->ownLine;
    }

    /**
     * Create new style that gives the widget a line of its own in a row that wraps, like a block among inline boxes.
     */
    public function withOwnLine(?bool $ownLine = true): self
    {
        $clone = clone $this;
        $clone->ownLine = $ownLine;

        return $clone;
    }

    public function getSpaceBefore(): ?bool
    {
        return $this->spaceBefore;
    }

    /**
     * Create new style that leaves a column before the widget in a row it is not the first of on its line, like the
     * space between two words, dropped where the line wraps.
     */
    public function withSpaceBefore(?bool $spaceBefore = true): self
    {
        $clone = clone $this;
        $clone->spaceBefore = $spaceBefore;

        return $clone;
    }

    public function getZIndex(): ?int
    {
        return $this->zIndex;
    }

    /**
     * Create new style that paints the widget, a flex or grid item that is not positioned, over the items it overlaps of
     * a lower z-index, like CSS z-index.
     */
    public function withZIndex(?int $zIndex): self
    {
        $clone = clone $this;
        $clone->zIndex = $zIndex;

        return $clone;
    }

    public function getIsolated(): ?bool
    {
        return $this->isolated;
    }

    /**
     * Create new style that makes the widget a stacking context: the positioned widgets inside it are ordered among
     * themselves, then drawn with it, like isolation: isolate, opacity below 1, a filter or a transform.
     *
     * A positioned widget of a z-index other than 0 is one without it.
     */
    public function withIsolated(?bool $isolated = true): self
    {
        $clone = clone $this;
        $clone->isolated = $isolated;

        return $clone;
    }

    public function getBackgroundBlend(): ?BlendMode
    {
        return $this->backgroundBlend;
    }

    /**
     * Create new style whose background image or gradient blends with its background color, like background-blend-mode.
     */
    public function withBackgroundBlend(?BlendMode $mode): self
    {
        $clone = clone $this;
        $clone->backgroundBlend = BlendMode::Normal !== $mode ? $mode : null;

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
     * @param string|null $font Bundled font name (big, small, slant, standard, mini) or path to a .flf file, or a list of fonts ending with a generic family, like CSS font-family "slant, monospace", drawn in the first registered one, or null to clear
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
        $clone->rowMaxColumns = null;

        return $clone;
    }

    /**
     * Create new style whose width, as an item of a row, only starts it there, like the width of a flex item of
     * flex: 1, which grows past it, or of flex-basis, which replaces it: there, the most it takes is that many
     * columns, like its max-width, or as many as the row gives it for null.
     */
    public function withRowMaxColumns(?int $columns): self
    {
        $clone = clone $this;
        $clone->rowMaxColumns = null !== $columns ? max(1, $columns) : \PHP_INT_MAX;

        return $clone;
    }

    /**
     * The most columns the widget takes as an item of a row: see withRowMaxColumns().
     */
    public function getRowMaxColumns(): ?int
    {
        if (null === $this->rowMaxColumns) {
            return $this->maxColumns;
        }

        return \PHP_INT_MAX === $this->rowMaxColumns ? null : $this->rowMaxColumns;
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
     * @param int|float|null $flex 0 = intrinsic width, above = proportional weight, like 0.5 or 2, null = clear
     */
    public function withFlex(int|float|null $flex): self
    {
        $clone = clone $this;
        $clone->flex = self::factor($flex);

        return $clone;
    }

    /**
     * Create a copy with only visual formatting and content properties.
     *
     * Strips layout properties that the Renderer owns: margin, padding, border,
     * gap, direction, hidden, cursorShape, caretColor, textAlign, maxColumns, align,
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
        if (null === $this->padding && null === $this->border && null === $this->margin
            && null === $this->widthPercent && null === $this->minRows && null === $this->maxRows && null === $this->shrink && null === $this->justify && null === $this->autoMargin && null === $this->minColumns && null === $this->order && null === $this->alignSelf && null === $this->wrap && null === $this->rowGap && null === $this->reverseOrder && null === $this->flexBasis && null === $this->flexBasisPercent && null === $this->minRowsPercent && null === $this->maxRowsPercent && null === $this->gridTemplate && null === $this->gridPlacement && null === $this->scroll && null === $this->stickyTop && null === $this->stickyBottom && null === $this->position && null === $this->zIndex && null === $this->spaceBefore && null === $this->ownLine && null === $this->documentHeight && null === $this->beforeClear && null === $this->columns && null === $this->avoidBreak && null === $this->cloneDecoration && null === $this->shadow && null === $this->spanAll && null === $this->columnBreaks && null === $this->objectPosition && null === $this->anchorName && null === $this->anchorScope && null === $this->clear && null === $this->float && null === $this->floatShape && null === $this->shapeMargin && null === $this->shapeImageThreshold && null === $this->objectFit && null === $this->aspectRatio && null === $this->contents && null === $this->justifyItems && null === $this->justifySelf && null === $this->captionBottom && null === $this->collapse && null === $this->fixedLayout && null === $this->hideEmpty && null === $this->alignContent && null === $this->marginPull && null === $this->marginHairline
            && null === $this->gap && null === $this->direction
            && null === $this->hidden && null === $this->cursorShape && null === $this->caretColor
            && null === $this->textAlign && null === $this->maxColumns
            && null === $this->align && null === $this->verticalAlign
            && null === $this->flex) {
            return $this;
        }

        $clone = clone $this;
        $clone->padding = null;
        $clone->border = null;
        $clone->margin = null;
        $clone->widthPercent = null;
        $clone->widthOffset = 0;
        $clone->widthPercentMax = false;
        $clone->minRows = null;
        $clone->maxRows = null;
        $clone->shrink = null;
        $clone->justify = null;
        $clone->autoMargin = null;
        $clone->minColumns = null;
        $clone->order = null;
        $clone->zIndex = null;
        $clone->spaceBefore = null;
        $clone->ownLine = null;
        $clone->documentHeight = null;
        $clone->beforeClear = null;
        $clone->alignSelf = null;
        $clone->wrap = null;
        $clone->rowGap = null;
        $clone->reverseOrder = null;
        $clone->flexBasis = null;
        $clone->flexBasisPercent = null;
        $clone->contentBasis = false;
        $clone->minRowsPercent = null;
        $clone->maxRowsPercent = null;
        $clone->rowsPercentOffsets = [0, 0];
        $clone->gridTemplate = null;
        $clone->gridPlacement = null;
        $clone->scroll = null;
        $clone->stickyTop = null;
        $clone->stickyBottom = null;
        $clone->position = null;
        $clone->columns = null;
        $clone->avoidBreak = null;
        $clone->cloneDecoration = null;
        $clone->shadow = null;
        $clone->spanAll = null;
        $clone->columnBreaks = null;
        $clone->objectPosition = null;
        $clone->anchorName = null;
        $clone->anchorScope = null;
        $clone->clear = null;
        $clone->float = null;
        $clone->floatShape = null;
        $clone->shapeMargin = null;
        $clone->shapeImageThreshold = null;
        $clone->objectFit = null;
        $clone->aspectRatio = null;
        $clone->contents = null;
        $clone->justifyItems = null;
        $clone->justifySelf = null;
        $clone->captionBottom = null;
        $clone->collapse = null;
        $clone->fixedLayout = null;
        $clone->hideEmpty = null;
        $clone->alignContent = null;
        $clone->marginPull = null;
        $clone->marginHairline = null;
        $clone->gap = null;
        $clone->direction = null;
        $clone->hidden = null;
        $clone->cursorShape = null;
        $clone->caretColor = null;
        $clone->textAlign = null;
        $clone->maxColumns = null;
        $clone->rowMaxColumns = null;
        $clone->align = null;
        $clone->verticalAlign = null;
        $clone->flex = null;

        return $clone;
    }

    /**
     * Keeps the computed CSS resets of inherited text attributes.
     *
     * @internal
     */
    public function withCssCascade(): self
    {
        $clone = clone $this;
        $clone->cssCascade = true;

        return $clone;
    }

    /** @internal */
    public function isCssCascade(): bool
    {
        return $this->cssCascade;
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

        if (true === $this->concealed) {
            $text = preg_replace_callback('/(?:\e\[[0-9;:]*m)(*SKIP)(*F)|\X/u', static fn (array $m): string => str_repeat(' ', max(0, AnsiUtils::graphemeWidth($m[0]))), $text) ?? $text;
        }
        if ($this->cssCascade) {
            if (null !== $this->backgroundColor) {
                $text = AnsiUtils::reapplyBackgroundAfterResets($text, $this->backgroundColor->toBackgroundCode());
            }
        } elseif (null !== $this->restorePattern && str_contains($text, "\x1b[")) {
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
        if (true === $this->overline) {
            $prefix .= "\x1b[53m";
            $suffix = "\x1b[55m".$suffix;
        } elseif (false === $this->overline) {
            $prefix .= "\x1b[55m";
        }
        if (true === $this->underline) {
            // A plain underline first: terminals without styled underlines keep it
            $prefix .= $this->restoreCodes[24] = "\x1b[4m";
            if (null !== $this->underlineStyle && UnderlineStyle::Single !== $this->underlineStyle) {
                $prefix .= $this->restoreCodes[24] = "\x1b[4:".$this->underlineStyle->value.'m';
            }
            if (null !== $this->underlineColor) {
                $prefix .= $this->restoreCodes[59] = $this->underlineColor->toUnderlineCode();
                $suffix = "\x1b[59m".$suffix;
            }
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
