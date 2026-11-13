<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Css;

use Psr\Log\LoggerInterface;
use Symfony\Component\Tui\Compatibility\Support;
use Symfony\Component\Tui\Compatibility\SupportProviderInterface;
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Style\Align;
use Symfony\Component\Tui\Style\AutoMargin;
use Symfony\Component\Tui\Style\BackgroundLayout;
use Symfony\Component\Tui\Style\BackgroundLength;
use Symfony\Component\Tui\Style\BlendMode;
use Symfony\Component\Tui\Style\Border;
use Symfony\Component\Tui\Style\BorderPattern;
use Symfony\Component\Tui\Style\Color;
use Symfony\Component\Tui\Style\ColorScheme;
use Symfony\Component\Tui\Style\Columns;
use Symfony\Component\Tui\Style\CursorShape;
use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\Filter;
use Symfony\Component\Tui\Style\FloatShape;
use Symfony\Component\Tui\Style\Gradient;
use Symfony\Component\Tui\Style\GridPlacement;
use Symfony\Component\Tui\Style\GridTemplate;
use Symfony\Component\Tui\Style\Justify;
use Symfony\Component\Tui\Style\MediaContext;
use Symfony\Component\Tui\Style\Outline;
use Symfony\Component\Tui\Style\Padding;
use Symfony\Component\Tui\Style\PercentLength;
use Symfony\Component\Tui\Style\Position;
use Symfony\Component\Tui\Style\PositionMode;
use Symfony\Component\Tui\Style\RasterImage;
use Symfony\Component\Tui\Style\Scroll;
use Symfony\Component\Tui\Style\Shadow;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\TableRole;
use Symfony\Component\Tui\Style\TextAlign;
use Symfony\Component\Tui\Style\UnderlineStyle;
use Symfony\Component\Tui\Style\VerticalAlign;
use Symfony\Component\Tui\Widget\Figlet\FontRegistry;

/**
 * Maps computed CSS declarations to a Tui {@see Style}.
 *
 * It follows CSS error handling: an unknown property or an invalid value is
 * skipped, and reported to the logger. In strict mode, it throws instead,
 * which is what tests and CI want.
 *
 * @experimental
 */
class DeclarationMapper implements SupportProviderInterface
{
    /** Properties a child takes from its parent when it does not set them */
    /** The parts each pair of stops of a gradient interpolated in another space than sRGB is drawn in */
    private const GRADIENT_STEPS = 8;

    public const INHERITED = ['color', 'font-weight', 'font-style', 'text-align', 'caret-shape', 'caret-animation', 'caret-color', 'caret', 'font-family', 'direction', 'interactivity', 'writing-mode', 'text-orientation', 'font-size'];

    /** The properties of a grid written vertically, laid out as a horizontal one whose rows are its columns */
    private const VERTICAL_GRID_AXES = [
        'grid-template-columns' => 'grid-template-rows', 'grid-template-rows' => 'grid-template-columns',
        'grid-auto-columns' => 'grid-auto-rows', 'grid-auto-rows' => 'grid-auto-columns',
        'column-gap' => 'row-gap', 'row-gap' => 'column-gap',
        'justify-items' => 'align-items', 'align-items' => 'justify-items',
        'justify-content' => 'align-content', 'align-content' => 'justify-content',
    ];
    /** And those of its items */
    private const VERTICAL_GRID_ITEM_AXES = [
        'grid-row' => 'grid-column', 'grid-column' => 'grid-row',
        'grid-row-start' => 'grid-column-start', 'grid-column-start' => 'grid-row-start',
        'grid-row-end' => 'grid-column-end', 'grid-column-end' => 'grid-row-end',
        'justify-self' => 'align-self', 'align-self' => 'justify-self',
    ];

    /** The logical sizes in a vertical writing mode: the inline size is the height */
    private const VERTICAL_SIZES = [
        'inline-size' => 'height', 'block-size' => 'width', 'min-inline-size' => 'min-height', 'max-inline-size' => 'max-height',
        'min-block-size' => 'min-width', 'max-block-size' => 'max-width',
    ];
    private const LOGICAL_SIZES = [
        'inline-size' => 'width', 'block-size' => 'height', 'min-inline-size' => 'min-width', 'max-inline-size' => 'max-width',
        'min-block-size' => 'min-height', 'max-block-size' => 'max-height',
    ];

    /** The notes of the SVG properties that the shapes of an <svg> are not drawn with */
    private const SVG_PRESENTATION = 'The shapes of an <svg> are not drawn with it, from a style sheet or an attribute: see html.elements.svg.';
    private const IGNORED = [
        'user-select' => '',
        'text-decoration-thickness' => 'The terminal draws the underline of its font.',
        'text-underline-offset' => 'The terminal draws the underline of its font.',
        'text-underline-position' => 'The terminal draws the underline of its font.',
        'font-kerning' => 'Every character takes whole cells.',
        'font-feature-settings' => 'The terminal chooses the glyphs of its font.',
        'resize' => 'The size of a textarea follows its rows and its content.',
        'zoom' => 'Terminals have one font size.',
        'rotate' => 'Cells cannot be rotated.',
        'scale' => 'Cells cannot be scaled.',
        'backdrop-filter' => 'What is behind an element is not filtered; see filter.',
        'will-change' => 'A hint for browsers, without effect here.',
        'overflow-clip-margin' => 'The content of a box is drawn in its cells: it cannot go past the edge it is clipped at; outlines are drawn over what is around already.',
        'print-color-adjust' => 'Nothing is printed.',
        '-webkit-backdrop-filter' => 'What is behind an element is not filtered; see filter.',
        '-webkit-overflow-scrolling' => 'Scrolling follows the keys and the wheel: there is no momentum.',
        '-webkit-user-drag' => 'Elements are not dragged and dropped.',
        'color-adjust' => 'The old name of print-color-adjust: nothing is printed.',
        '-ms-overflow-style' => 'Scrollbars are drawn by the scroll containers, see scrollbar-width.',
        '-webkit-box-orient' => 'A -webkit-box is a block, its children one under the other: see -webkit-line-clamp.',
        'box-orient' => 'A -webkit-box is a block, its children one under the other: see -webkit-line-clamp.',
        '-webkit-box-pack' => 'A -webkit-box is a block: see display: flex.',
        '-webkit-box-align' => 'A -webkit-box is a block: see display: flex.',
        '-webkit-box-flex' => 'A -webkit-box is a block: see display: flex.',
        '-webkit-box-direction' => 'A -webkit-box is a block: see display: flex.',
        'font-stretch' => 'Terminals have one font: its width does not change.',
        'font-width' => 'Terminals have one font: its width does not change.',
        'text-size-adjust' => 'Terminals have one font size.',
        '-webkit-text-size-adjust' => 'Terminals have one font size.',
        '-webkit-font-smoothing' => 'The terminal draws the glyphs of its font.',
        '-moz-osx-font-smoothing' => 'The terminal draws the glyphs of its font.',
        '-webkit-tap-highlight-color' => 'Terminals report no touches.',
        'font-variant-alternates' => 'The terminal chooses the glyphs of its font.',
        'font-variant-east-asian' => 'The terminal chooses the glyphs of its font.',
        'font-variant-emoji' => 'The terminal chooses the glyphs of its font.',
        'font-variant-ligatures' => 'The terminal chooses the glyphs of its font.',
        'font-variation-settings' => 'The terminal chooses the glyphs of its font.',
        'font-optical-sizing' => 'The terminal chooses the glyphs of its font.',
        'font-language-override' => 'The terminal chooses the glyphs of its font.',
        'font-palette' => 'The terminal chooses the glyphs of its font.',
        'text-rendering' => 'The terminal chooses the glyphs of its font.',
        'font-synthesis' => 'The terminal draws bold and italic text with its own fonts; small capitals are Unicode characters, see font-variant-caps.',
        'font-synthesis-weight' => 'The terminal draws bold and italic text with its own fonts; small capitals are Unicode characters, see font-variant-caps.',
        'font-synthesis-style' => 'The terminal draws bold and italic text with its own fonts; small capitals are Unicode characters, see font-variant-caps.',
        'font-synthesis-small-caps' => 'The terminal draws bold and italic text with its own fonts; small capitals are Unicode characters, see font-variant-caps.',
        'font-size-adjust' => 'Terminals have one font size.',
        'lighting-color' => self::SVG_PRESENTATION,
        'color-interpolation' => self::SVG_PRESENTATION,
        'color-interpolation-filters' => self::SVG_PRESENTATION,
        'shape-rendering' => self::SVG_PRESENTATION,
        'vector-effect' => self::SVG_PRESENTATION,
        'dominant-baseline' => self::SVG_PRESENTATION,
        'alignment-baseline' => self::SVG_PRESENTATION,
        'baseline-shift' => self::SVG_PRESENTATION,
        'mask-image' => 'Over a background color, like an icon of currentColor: the shape of the image (its alpha) in that color, stretched to the box, its strokes showing where they cover half a cell, or, when none does, each one in the cells it crosses, like a chevron drawn in a few cells; an image that cannot be read draws no block of the color. A box is otherwise a rectangle of cells.',
        'mask-mode' => 'A box is a rectangle of cells.',
        'mask-repeat' => 'A box is a rectangle of cells.',
        'mask-position' => 'A box is a rectangle of cells.',
        'mask-clip' => 'A box is a rectangle of cells.',
        'mask-origin' => 'A box is a rectangle of cells.',
        'mask-size' => 'A box is a rectangle of cells.',
        'mask-composite' => 'A box is a rectangle of cells.',
        'mask-border' => 'A box is a rectangle of cells.',
        'mask-border-source' => 'A box is a rectangle of cells.',
        'mask-border-slice' => 'A box is a rectangle of cells.',
        'mask-border-width' => 'A box is a rectangle of cells.',
        'mask-border-outset' => 'A box is a rectangle of cells.',
        'mask-border-repeat' => 'A box is a rectangle of cells.',
        'transform-origin' => 'Cells cannot be rotated nor scaled: there is no 3D space; see translate.',
        'transform-box' => 'Cells cannot be rotated nor scaled: there is no 3D space; see translate.',
        'transform-style' => 'Cells cannot be rotated nor scaled: there is no 3D space; see translate.',
        'perspective' => 'Cells cannot be rotated nor scaled: there is no 3D space; see translate.',
        'perspective-origin' => 'Cells cannot be rotated nor scaled: there is no 3D space; see translate.',
        'backface-visibility' => 'Cells cannot be rotated nor scaled: there is no 3D space; see translate.',
        'offset' => 'Boxes are not moved along paths; see translate.',
        'offset-path' => 'Boxes are not moved along paths; see translate.',
        'offset-distance' => 'Boxes are not moved along paths; see translate.',
        'offset-rotate' => 'Boxes are not moved along paths; see translate.',
        'offset-anchor' => 'Boxes are not moved along paths; see translate.',
        'offset-position' => 'Boxes are not moved along paths; see translate.',
        '-webkit-text-stroke' => 'A cell has one foreground color: the outline of a glyph cannot be drawn.',
        '-webkit-text-stroke-color' => 'A cell has one foreground color: the outline of a glyph cannot be drawn.',
        '-webkit-text-stroke-width' => 'A cell has one foreground color: the outline of a glyph cannot be drawn.',
        'text-decoration-skip' => 'The terminal draws the underline of its font.',
        'text-decoration-skip-ink' => 'The terminal draws the underline of its font.',
        'text-combine-upright' => 'In a vertical writing mode, every character is upright, one a row: none is combined with others.',
        'text-spacing-trim' => 'A fullwidth punctuation mark takes two whole columns: half of it cannot be trimmed.',
        'text-decoration-inset' => 'A line under or through text is as long as its cells: a fraction of one cannot be left out.',
        'line-fit-edge' => 'A line is a row of cells, its height that of the text.',
        'initial-letter-align' => 'A drop cap is a FIGlet glyph whose top is that of the first line, see initial-letter.',
        'font-smooth' => 'The terminal draws the glyphs.',
        'overlay' => 'Set by the browser to keep an element in the top layer while it animates out: dialogs and popovers leave it at once.',
        'scroll-target-group' => 'The links of a group are not scroll markers: see ::scroll-marker for the markers of a scroll container.',
        'user-modify' => 'Only form controls are edited.',
        'speak' => 'Nothing is spoken: screen readers read the terminal.',
        'text-box' => 'A line of text is a row of cells: there is no space above or below its glyphs to trim.',
        'text-box-trim' => 'A line of text is a row of cells: there is no space above or below its glyphs to trim.',
        'text-box-edge' => 'A line of text is a row of cells: there is no space above or below its glyphs to trim.',
        'baseline-source' => 'The characters of a row share one baseline.',
        'background-attachment' => 'Backgrounds scroll with their box.',
        'overflow-anchor' => 'Scroll containers keep their offset when what is above changes.',
        'touch-action' => 'Terminals report no touches.',
        'dynamic-range-limit' => 'Colors are sent to the terminal as they are.',
        'math-depth' => 'MathML is not drawn.',
        'math-style' => 'MathML is not drawn.',
        'page' => 'Nothing is printed.',
        'view-transition-name' => 'Changes are drawn at once, without view transitions; see transition.',
        'view-transition-class' => 'Changes are drawn at once, without view transitions; see transition.',

        'white-space' => '',
        'content' => '',
    ];

    /** @var array<string, array{\Closure(Style, string, MediaContext): (Style|false), Support}> */
    private array $properties = [];

    /** @var array<string, array<string, string>> The declarations of the @position-try rules, by name */
    private array $positionTries = [];

    /** @var array<string, true> */
    private array $reported = [];

    /** @var (\Closure(string): ?RasterImage)|null */
    private ?\Closure $imageLoader = null;

    /** @var array<string, RasterImage|null> The masks painted in a color, by address and color, see maskIcon() */
    private array $masks = [];

    public function __construct(
        private readonly ?LoggerInterface $logger = null,
        private readonly bool $strict = false,
    ) {
        $this->registerDefaults();
    }

    /**
     * Sets what reads the images of url(), like background-image: url(a.png): it returns the image, or null when it
     * cannot be loaded.
     *
     * @param (\Closure(string): ?RasterImage)|null $loader
     */
    public function setImageLoader(?\Closure $loader): void
    {
        $this->imageLoader = $loader;
    }

    /**
     * The property a vendor prefix stands for, like -moz-user-select for user-select, when the prefixed one is not
     * known itself, like browsers that alias them; the property as is otherwise.
     */
    private function unprefixed(string $property): string
    {
        if (isset($this->properties[$property]) || isset(self::IGNORED[$property]) || !preg_match('/^-(?:webkit|moz|ms|o)-(.+)$/', $property, $m)) {
            return $property;
        }

        return isset($this->properties[$m[1]]) || isset(self::IGNORED[$m[1]]) ? $m[1] : $property;
    }

    /**
     * Registers a property, or replaces the handling of a built-in one.
     *
     * @param \Closure(Style, string, MediaContext): (Style|false) $handler Returns the updated style, or false when the value is invalid
     */
    public function addProperty(string $name, \Closure $handler, ?Support $support = null): static
    {
        $this->properties[strtolower($name)] = [$handler, $support ?? Support::supported()];

        return $this;
    }

    /**
     * Sets the declarations of the @position-try rules, which position-try-fallbacks names.
     *
     * @param array<string, array<string, string>> $tries
     *
     * @internal
     */
    public function setPositionTries(array $tries): void
    {
        $this->positionTries = $tries;
    }

    /**
     * The color part of a border shorthand, like the transparent of border: 1px solid transparent; null without one.
     */
    private static function shorthandBorderColor(string $value): ?string
    {
        $color = null;
        foreach (Value::split($value) as $part) {
            if (null === self::borderWidth($part, new MediaContext()) && null === self::borderPattern(strtolower($part))) {
                $color = $part;
            }
        }

        return $color;
    }

    /**
     * The sides of the border whose color is transparent, the declarations taken in order: 0 is the top, then clockwise.
     *
     * @param array<string, string> $declarations
     *
     * @return array<int, bool>
     */
    private static function transparentBorderSides(array $declarations, MediaContext $media): array
    {
        static $physical = [
            'top' => [0], 'right' => [1], 'bottom' => [2], 'left' => [3],
            'block' => [0, 2], 'inline' => [3, 1],
            'block-start' => [0], 'block-end' => [2], 'inline-start' => [3], 'inline-end' => [1],
        ];
        $transparent = static fn (?string $color): bool => null !== $color && false !== Value::color($color, $media) && 0.0 === Value::alpha($color, $media);
        $sides = [false, false, false, false];
        foreach ($declarations as $property => $value) {
            if ('border' === $property) {
                $sides = array_fill(0, 4, $transparent(self::shorthandBorderColor($value)));
            } elseif ('border-color' === $property) {
                $parts = array_map($transparent, Value::split($value));
                $sides = match (\count($parts)) {
                    1 => array_fill(0, 4, $parts[0]),
                    2 => [$parts[0], $parts[1], $parts[0], $parts[1]],
                    3 => [$parts[0], $parts[1], $parts[2], $parts[1]],
                    4 => $parts,
                    default => $sides,
                };
            } elseif (str_starts_with($property, 'border-') && preg_match('/^border-([a-z-]+?)(-color)?$/', $property, $m) && isset($physical[$m[1]])) {
                $value = isset($m[2]) ? $transparent(trim($value)) : $transparent(self::shorthandBorderColor($value));
                foreach ($physical[$m[1]] as $side) {
                    $sides[$side] = $value;
                }
            }
        }

        return $sides;
    }

    /**
     * The shape of the caret, like caret-shape; null when it is not one.
     */
    private static function caretShape(string $value): ?CursorShape
    {
        return match (strtolower(trim($value))) {
            'auto', 'bar' => CursorShape::Bar,
            'block' => CursorShape::Block,
            'underscore' => CursorShape::Underline,
            default => null,
        };
    }

    /**
     * @param array<string, string> $declarations   Computed values, custom properties already substituted
     * @param bool                  $inVerticalGrid Whether the element is an item of a grid written vertically
     */
    public function map(array $declarations, MediaContext $media, ?Color $backdrop = null, bool $inVerticalGrid = false): Style
    {
        $style = new Style();
        // forced-color-adjust: none keeps the colors of the element and of what inherits it, like a color swatch
        if ($media->forcedColors && 'none' === strtolower(trim($declarations['forced-color-adjust'] ?? ''))) {
            $media = $media->withForcedColors(false);
        }
        $declarations = self::resolveCurrentColor($declarations);
        $rtl = 'rtl' === strtolower(trim($declarations['direction'] ?? ''));
        // A vertical writing mode: the inline axis runs down, the blocks from the right (or from the left)
        $vertical = self::writingMode($declarations['writing-mode'] ?? '');
        if (null !== $vertical && 'horizontal-tb' !== $vertical) {
            $declarations = self::verticalSides($declarations, 'vertical-lr' === $vertical || 'sideways-lr' === $vertical, $rtl);
        } elseif ($rtl) {
            $declarations = self::rightToLeft($declarations);
        }
        $declarations = self::physicalInsets($declarations);
        $declarations = self::gridShorthands($declarations);
        // A grid written vertically is laid out as a horizontal one, its axes swapped, and so are its items placed
        if (null !== $vertical && 'horizontal-tb' !== $vertical && \in_array(strtolower(trim($declarations['display'] ?? '')), ['grid', 'inline-grid'], true)) {
            $declarations = self::verticalGrid($declarations);
        }
        if ($inVerticalGrid) {
            $declarations = self::verticalGridItem($declarations);
        }
        // Logical sizes, in a horizontal writing mode: the inline size is the width (see verticalSides() for the others)
        foreach (self::LOGICAL_SIZES as $logical => $physical) {
            if (isset($declarations[$logical])) {
                $declarations[$physical] ??= $declarations[$logical];
                unset($declarations[$logical]);
            }
        }

        foreach ($declarations as $name => $value) {
            if (str_starts_with($name, '--')) {
                continue;
            }

            if (\in_array(strtolower($value), ['initial', 'unset', 'revert', 'revert-layer', 'inherit'], true)) {
                // "inherit" is resolved by the cascade; whatever is left means "not set", but for the text look, which an
                // initial value sets, over what the widget would otherwise get, like the heading classes
                if ('initial' === strtolower($value)) {
                    $style = match ($name) {
                        'color' => $style->withColor(Color::named('default')),
                        'font-weight' => $style->withBold(false),
                        'font-style' => $style->withItalic(false),
                        'opacity' => $style->withDim(false),
                        'text-decoration', 'text-decoration-line' => $style->withUnderline(false)->withStrikethrough(false),
                        default => $style,
                    };
                }
                continue;
            }

            $name = $this->unprefixed($name);
            if (!isset($this->properties[$name])) {
                if (isset(self::IGNORED[$name])) {
                    continue;
                }
                $this->report($name, \sprintf('The "%s" CSS property is not supported.', $name));
                continue;
            }

            $result = $this->properties[$name][0]($style, $value, $media);
            if (false === $result) {
                $this->report($name.':'.$value, \sprintf('Invalid value "%s" for the "%s" CSS property.', $value, $name));
                continue;
            }
            $style = $result;
        }

        return $this->finalize($style, $declarations, $media, $backdrop, $rtl);
    }

    /** The properties whose invalid values rejects() finds: colors and keywords */
    private const VALIDATED = [
        'color' => true, 'background-color' => true, 'border-color' => true, 'border-top-color' => true, 'border-right-color' => true,
        'border-bottom-color' => true, 'border-left-color' => true, 'outline-color' => true, 'text-decoration-color' => true,
        'caret-color' => true, 'accent-color' => true, 'column-rule-color' => true,
        'white-space' => true, 'position' => true, 'float' => true, 'visibility' => true, 'font-style' => true,
    ];

    /** @var array<string, bool> Whether a value of a property is rejected, see rejects() */
    private array $rejected = [];

    /**
     * Tells whether a declaration is invalid, which a browser drops when it parses it, an earlier one of the property
     * applying instead, like the -moz-pre-wrap !important after a white-space: pre-wrap: a value of a property known
     * by its name, not one it substitutes into, like var(), which is known only once computed.
     *
     * @internal
     */
    public function rejects(string $property, string $value): bool
    {
        // Only the properties whose handler refuses invalid values alone, not values valid in CSS it does not draw, like
        // justify-content: normal or a gap in percents; nor a value valid in its context only: an image, a size of its
        // container, its place among its siblings, a function of the page, an anchor
        if (!isset(self::VALIDATED[$property]) || !isset($this->properties[$property]) || preg_match('/\b(?:var|env|attr|if|url|inherit|initial|unset|revert|revert-layer|sibling-(?:index|count)|anchor(?:-size)?)\b|(?<![a-z-])cq(?:w|h|i|b|min|max)\b|--[\w-]+\(/i', $value)) {
            return false;
        }
        if (\count($this->rejected) > 10_000) {
            $this->rejected = [];
        }

        return $this->rejected[$property."\0".$value] ??= !$this->supports($property, $value);
    }

    /**
     * Tells whether a declaration would have an effect, like @supports (property: value).
     */
    public function supports(string $property, string $value, ?MediaContext $media = null): bool
    {
        $property = strtolower(trim($property));
        $value = trim($value);

        if (str_starts_with($property, '--')) {
            return true;
        }
        $property = $this->unprefixed($property);
        if (!isset($this->properties[$property])) {
            return false;
        }
        if (\in_array(strtolower($value), ['initial', 'unset', 'revert', 'revert-layer', 'inherit'], true)) {
            return true;
        }

        $media ??= new MediaContext();
        $value = preg_replace('/\bcurrentcolor\b/i', 'white', $value);

        // Properties resolved by finalize() accept anything in their handler: check their values here
        return match ($property) {
            'gap', 'row-gap', 'column-gap' => array_all(Value::split($value), static fn (string $part) => 'normal' === $part || null !== Length::toCells($part, $media)),
            'align-items' => null !== self::position($value) || \in_array(strtolower($value), ['normal', 'stretch'], true),
            'justify-content' => null !== self::position($value) || null !== Justify::tryFrom(strtolower($value)),
            'place-items', 'place-content' => array_all(Value::split($value), static fn (string $part) => null !== self::position($part) || \in_array($part, ['stretch', 'normal', 'space-between', 'space-around', 'space-evenly', 'baseline'], true)),
            default => (function () use ($property, $value, $media): bool {
                try {
                    return false !== $this->properties[$property][0](new Style(), $value, $media);
                } catch (\Throwable) {
                    return false;
                }
            })(),
        };
    }

    public function getSupport(): array
    {
        $support = [];
        foreach ($this->properties as $name => [, $propertySupport]) {
            $support['css.properties.'.$name] = $propertySupport;
        }
        foreach (self::IGNORED as $name => $note) {
            // A property registered later (addProperty()) is not ignored anymore
            $support['css.properties.'.$name] ??= Support::ignored($note);
        }
        $support['css.properties.custom-property'] = Support::supported('Cascades and inherits; read with var(); initial is the guaranteed-invalid value, var() taking its fallback, and unset inherits, like CSS.');
        $support['css.types.color.light-dark'] = Support::supported('Uses prefers-color-scheme.');
        $support['css.types.easing-function.cubic-bezier'] = Support::supported('ease, ease-in, ease-out and ease-in-out too; outputs past 0 and 1 take numbers further, colors stay between.');
        $support['css.types.easing-function.linear-function'] = Support::supported('Stops with one or two input percentages, the missing ones spread evenly.');
        $support['css.types.easing-function.steps'] = Support::supported('jump-start, jump-end, jump-none and jump-both, start, end, step-start and step-end.');
        $support['css.types.color.color-mix'] = Support::partial('Two colors, in the space named (oklab by default) with the hue interpolation methods of the polar ones, premultiplied by their alpha; percentages summing to less than 100% make the mix translucent, blended like rgb().');
        $support['css.types.env'] = Support::supported('The safe-area-inset, safe-area-max-inset and keyboard-inset variables are 0px: a terminal has no notch nor virtual keyboard; the others, like titlebar-area-x, take the fallback, without one the declaration is invalid at computed-value time; in every property and custom property.');
        $support['css.types.calc-size'] = Support::partial('In height, min-height and max-height: its basis, auto for a keyword, outside transitions; a transition to or from it computes it, size being the height of the content in the last frame, see interpolate-size.');
        $support['css.types.anchor-size'] = Support::partial('In width, height and their min- and max- of an absolute or fixed element: the width or height of its anchor (the default one or the one named), alone or in a math function linear in it, like calc(anchor-size(width) + 2ch), or the fallback without the anchor; one anchor-size() per value; not in margins nor insets.');
        $support['css.types.if'] = Support::supported('The value of the first branch whose condition passes: else, or media(), supports() and style() tests (on the custom properties of the element) joined by not, and and or; without one passing, the declaration is invalid at computed-value time.');
        $support['css.types.attr'] = Support::supported('In any property, of the element or the inline element: a string without a type (or raw-string), a value of the syntax of type(), a number, or a number with a unit; a fallback when the attribute is missing or not of the type, else the declaration is invalid at computed-value time. In content, a string.');
        $support['css.types.sibling-index'] = Support::supported('The position of the element among the elements of its parent, from 1, wherever a number is; inline elements count among the inline elements of their parent.');
        $support['css.types.sibling-count'] = Support::supported('The number of elements of the parent of the element, itself included.');
        $support['css.types.color.contrast-color'] = Support::supported('White or black, whichever has the higher WCAG contrast ratio with the color.');
        $support['css.types.color.rgb'] = Support::partial('A translucent color is blended over what is behind it: the closest background around the element (blended itself over the terminal background when translucent), its own background for its text, or the terminal background of the color scheme; transparent text takes the color behind it.');
        $support['css.types.color.hsl'] = Support::partial('A translucent color is blended over what is behind it: the closest background around the element (blended itself over the terminal background when translucent), its own background for its text, or the terminal background of the color scheme; transparent text takes the color behind it.');
        $support['css.types.color.hwb'] = $support['css.types.color.hsl'];
        foreach (['lab', 'lch', 'oklab', 'oklch', 'color'] as $function) {
            $support['css.types.color.'.$function] = Support::partial('Out of the sRGB gamut, the chroma is lowered in OKLCH until clipping is not seen, like CSS gamut mapping; color() takes srgb, srgb-linear, display-p3, a98-rgb, prophoto-rgb, rec2020, xyz, xyz-d50 and xyz-d65; translucent, blended like rgb().');
        }
        foreach (['rgb', 'hsl', 'hwb', 'lab', 'lch', 'oklab', 'oklch', 'color'] as $function) {
            $support['css.types.color.'.$function.'.relative_syntax'] = Support::supported('The channels of the origin color, in the space of the function, are keywords (r g b, h s l, l c h, x y z..., and alpha) usable in calc(), min(), max() and clamp().');
        }
        $support['css.types.system-color'] = Support::supported('Fixed colors of the light and dark color schemes, Canvas and CanvasText being the terminal background and text; AccentColor is the accent-color of the element when it sets one, AccentColorText the color that contrasts with it.');
        $support['css.types.color.rgb_hexadecimal_notation'] = Support::partial('#rgb, #rrggbb, and #rgba and #rrggbbaa with an alpha channel, blended like rgb().');
        $support['css.types.color.transparent'] = Support::supported('No color: what is behind shows; transparent text takes the color behind it.');
        $support['css.types.color.named-color'] = Support::supported('All CSS named colors as true colors, except the 16 ANSI names (red, bright-red...) which keep the colors of the terminal theme; also Tailwind shades (emerald-500) and palette indexes (0-255).');
        $support['css.types.color.currentcolor'] = Support::supported('The color of the element; ignored when no color is set.');
        $support['css.types.length'] = Support::partial('Unitless, ch and ex are columns, lh rows, and rch, rex and rlh alike; px are converted with the cell pixel size, the absolute units (pt, pc, in, cm, mm, Q) as their CSS pixels, 96 an inch; em and rem are 16px; vw, vh, vi, vb, vmin and vmax, and their small, large and dynamic forms (svh, dvh...), are shares of the screen, which does not change with toolbars; cqw, cqh, cqi, cqb, cqmin and cqmax are shares of the content box of the nearest container (inline-size or size for the width, size for the height), as laid out in the last frame, or of the screen without one; % for widths, heights, flex-basis, padding, margins and the insets of positioned elements.');
        $support['css.types.calc'] = Support::partial('Over lengths, evaluated in cells after unit conversion; with width and max-width, percentages mix with lengths when the size stays a share plus columns, like calc(100% - 20ch) or calc((100% - 2ch) / 3).');
        $support['css.types.min'] = Support::partial('Over lengths, see calc().');
        $support['css.types.max'] = Support::partial('Over lengths, see calc().');
        $support['css.types.clamp'] = Support::partial('Over lengths, see calc().');
        $support['css.types.round'] = Support::partial('nearest, up, down and to-zero, to a multiple of a step, 1 (a cell) by default; see calc().');
        foreach (['mod', 'rem', 'abs', 'sign', 'pow', 'sqrt', 'hypot', 'exp', 'log'] as $function) {
            $support['css.types.'.$function] = Support::partial('See calc().');
        }
        foreach (['sin', 'cos', 'tan'] as $function) {
            $support['css.types.'.$function] = Support::partial('Of an angle (deg, grad, rad, turn) or a number of radians; see calc().');
        }
        foreach (['asin', 'acos', 'atan', 'atan2'] as $function) {
            $support['css.types.'.$function] = Support::partial('An angle, like a hue in degrees; see calc().');
        }
        $support['css.types.var'] = Support::supported('Custom properties in any property, with a fallback; see css.properties.custom-property.');
        $support['css.types.global_keywords'] = Support::supported('initial, inherit, unset, revert (to the user agent value) and revert-layer (like revert), in every property; see all.');
        $support['css.types.color'] = Support::supported('Named colors, hexadecimal notations and the color functions; see css.types.color.*.');
        $support['css.types.string'] = Support::supported('In content, quotes and the other properties taking text.');
        $support['css.types.integer'] = Support::supported();
        $support['css.types.number'] = Support::supported();
        $support['css.types.dimension'] = Support::partial('Lengths, angles, times and resolutions; see each of them.');
        $support['css.types.percentage'] = Support::partial('Of the size of the containing block in widths, heights, paddings, margins and insets; of colors and of the stops of gradients. See css.types.length.');
        $support['css.types.length-percentage'] = Support::partial('See css.types.length and css.types.percentage.');
        $support['css.types.ratio'] = Support::partial('In aspect-ratio and in the aspect-ratio media feature, cells being twice as tall as wide.');
        $support['css.types.flex'] = Support::supported('fr, in the tracks of a grid.');
        $support['css.types.time'] = Support::supported('s and ms, in transitions and animations.');
        $support['css.types.angle'] = Support::supported('deg, grad, rad and turn: in gradients, hues, hue-rotate() and the trigonometric functions.');
        $support['css.types.angle-percentage'] = Support::supported('Angles and percentages of a turn, in the stops of conic-gradient().');
        $support['css.types.resolution'] = Support::supported('dppx, x, dpi and dpcm, in the resolution media feature.');
        $support['css.types.counter'] = Support::supported('counter(), in content, with a counter style.');
        $support['css.types.counters'] = Support::supported('counters(), in content, with a separator and a counter style.');
        $support['css.types.url'] = Support::partial('In @import, background-image, list-style-image, border-image-source, content and shape-outside, through the stylesheet loader, or data: URLs.');
        $support['css.types.image'] = Support::partial('url() images, gradients and image-set(), in background-image, border-image-source, list-style-image, content and shape-outside; not cross-fade() nor element().');
        $support['css.types.gradient'] = Support::partial('linear-gradient(), radial-gradient(), conic-gradient() and their repeating forms, see background-image; the colors are interpolated in the space they name, like in oklch longer hue (sRGB by default), but for the colors of the terminal palette; translucent colors, transparent too, are interpolated premultiplied and blended over the background color of the box, else over what is behind it, which shows where the gradient is transparent.');
        $support['css.types.image.image-set'] = Support::supported('-webkit-image-set() too: the option of the lowest resolution not below the one of the screen (from the cell pixel size), else the highest, its size divided by it; options of a type() that cannot be decoded are left out; url(), a string or a gradient.');
        $support['css.types.gradient.linear-gradient'] = Support::supported('An angle or to a side or corner, color stops with percentages or lengths; vertical distances count double, cells being twice as tall as wide.');
        $support['css.types.gradient.radial-gradient'] = Support::supported('circle or ellipse, to the closest or farthest side or corner (farthest-corner by default) or of radii in lengths and percentages, at a position, like background-position; rows count double, cells being twice as tall as wide.');
        $support['css.types.gradient.conic-gradient'] = Support::supported('From an angle, around a position, like background-position, the center by default; stops with percentages or angles.');
        $support['css.types.gradient.repeating-linear-gradient'] = Support::supported('The stops again and again past the last one; see linear-gradient().');
        foreach (['radial', 'conic'] as $kind) {
            $support['css.types.gradient.repeating-'.$kind.'-gradient'] = Support::supported('The stops again and again past the last one; see '.$kind.'-gradient().');
        }
        $support['css.types.position'] = Support::supported('Keywords, lengths and percentages, one to four values with offsets from an edge, in background-position.');
        $support['css.types.easing-function'] = Support::supported('linear, ease, ease-in, ease-out, ease-in-out, cubic-bezier(), steps() and linear(); see css.types.easing-function.*.');
        $support['css.types.overflow'] = Support::partial('See overflow.');
        $support['css.types.line-style'] = Support::supported('See border-style.');
        $support['css.types.transform-function'] = Support::partial('translate(), translateX() and translateY(); see transform.');
        $support['css.types.filter-function'] = Support::partial('See filter.');
        $support['css.types.anchor'] = Support::partial('The whole inset of an absolute or fixed element: a side of its anchor (top, right, bottom, left, center, start or end), the default one or the one named; no percentage side nor fallback, not in calc(); see position-anchor.');
        $support['css.types.calc-keyword'] = Support::supported('e, pi, infinity, -infinity and NaN: NaN is 0, infinities are clamped to the largest size.');
        $support['css.types.basic-shape'] = Support::partial('circle(), ellipse(), inset() and polygon() in shape-outside; see clip-path.');
        $support['css.types.blend-mode'] = Support::supported('normal, multiply, screen, overlay, darken, lighten, color-dodge, color-burn, hard-light, soft-light, difference, exclusion, hue, saturation, color and luminosity, with the formulas of the Compositing specification, on the colors of the cells; plus-lighter and plus-darker in mix-blend-mode.');
        $support['css.types.ray'] = Support::ignored('Boxes are not moved along paths; see offset-path.');
        $support['css.types.text-edge'] = Support::ignored('A line of text is a row of cells; see text-box-edge.');
        $support['css.types.calc-constant'] = Support::supported('e, pi, infinity, -infinity and NaN: NaN is 0, infinities are clamped to the largest size.');

        return $support;
    }

    private function registerDefaults(): void
    {
        $this->addProperty('color', static fn (Style $s, string $v, MediaContext $m) => self::withColor($s, $v, $m, 'color'));
        $this->addProperty('-webkit-text-fill-color', static fn (Style $s, string $v, MediaContext $m) => false !== Value::color($v, $m) ? $s : false, Support::supported('The color of the glyphs, over color: currentcolor, borders and outlines keep color; inherited; transparent text takes the color behind it.'));
        // clip and clip-path: a box is a rectangle of cells; clipped to nothing, it is not drawn (see CssStyleSheet)
        $this->addProperty('clip', static fn (Style $s, string $v) => 'auto' === strtolower(trim($v)) || preg_match('/^rect\(.*\)$/i', trim($v)) ? $s : false, Support::partial('A rect() without width or height leaves nothing to see, like the visually hidden pattern (.sr-only): the element is not drawn, its text staying in the names of what holds it; other rects are ignored, a box being a rectangle of cells.'));
        $this->addProperty('clip-path', static fn (Style $s, string $v) => preg_match('/^(?:none|[a-z-]+\(.*\)(?:\s+[a-z-]+)?|[a-z-]+-box|url\(.*\))$/i', trim($v)) ? $s : false, Support::partial('inset() of half the box or more, or a rect() without width or height, like the rect(0 0 0 0) of the .sr-only of GitHub, leaves nothing to see, like the visually hidden pattern: the element is not drawn; other shapes are ignored, a box being a rectangle of cells. On the shapes and groups of an <svg>, from its presentation attributes, the rules that match them and their style attribute, url() of a <clipPath>: what they draw is clipped to the union of its shapes (rect, circle, ellipse, polygon, path... and the copies of <use>, not text), in their user space or, with clipPathUnits="objectBoundingBox", the box of a shape; see html.elements.svg.'));
        $this->addProperty('-webkit-text-decoration', fn (Style $s, string $v, MediaContext $m) => false !== ($handler = $this->properties['text-decoration'][0] ?? null) ? $handler($s, $v, $m) : false, Support::partial('Like text-decoration.'));
        $this->addProperty('background-color', static fn (Style $s, string $v, MediaContext $m) => self::withColor($s, $v, $m, 'background'));
        $this->addProperty('background', function (Style $s, string $v, MediaContext $m) {
            $found = false;
            $parts = Value::split($v);
            // What it does not give back to its initial value, like CSS: no color nor image, like background: 0 0 over a
            // color and a gradient
            $s = $s->withBackground(null)->withGradient(null)->withBackgroundImage(false);
            foreach ($parts as $part) {
                [$part, $resolution] = self::imageSet($part, $m) ?? [$part, 1.0];
                // url(): the image of the first layer, drawn when it can be loaded and decoded
                if (null !== $url = self::url($part)) {
                    $s = $s->withBackgroundImage($m->forcedColors ? false : ($this->image($url, $resolution) ?? false))->withGradient(null);
                    $found = true;
                    continue;
                }
                // A number among other values is a position or a size, a palette color only alone
                if (\count($parts) > 1 && preg_match('/^\d+$/', $part)) {
                    $found = true;
                    continue;
                }
                // none, and the keywords and lengths of the position, the size, the repeat, the attachment and the boxes
                if (preg_match('~^(?:none|repeat(?:-[xy])?|no-repeat|space|round|left|right|top|bottom|center|cover|contain|auto|scroll|fixed|local|(?:border|padding|content)-box|text|/|-?\d*\.?\d+(?:%|[a-z]+)?)$~i', $part) && !(1 === \count($parts) && preg_match('/^\d+$/', $part))) {
                    $found = true;
                    continue;
                }
                if (null !== $gradient = self::gradient($part, $m)) {
                    $s = $s->withGradient($m->forcedColors ? null : $gradient)->withBackgroundImage(false);
                    $found = true;
                } elseif (false !== Value::color($part, $m)) {
                    if (false === $colored = self::withColor($s, $part, $m, 'background')) {
                        return false;
                    }
                    $s = $colored;
                    $found = true;
                }
            }

            return $found ? $s : false;
        }, Support::partial('The color and an image, a url() or a gradient, with its position / size, repeat and boxes (the origin and the clip, or the origin then the clip), of each layer, the first one over the others, see background-image; not the attachment.'));
        $this->addProperty('background-image', function (Style $s, string $v, MediaContext $m) {
            $first = Selector::splitTopLevel($v)[0] ?? '';
            if ('none' === strtolower(trim($first))) {
                return $s->withGradient(null)->withBackgroundImage(false);
            }
            [$first, $resolution] = self::imageSet($first, $m) ?? [$first, 1.0];
            if (null !== $url = self::url($first)) {
                return $s->withBackgroundImage($m->forcedColors ? false : ($this->image($url, $resolution) ?? false))->withGradient(null);
            }
            $gradient = self::gradient(trim($first), $m);

            return null === $gradient ? false : $s->withGradient($m->forcedColors ? null : $gradient)->withBackgroundImage(false);
        }, Support::partial('url() images (PNG, JPEG, GIF, WebP... with the GD extension, and SVG, its currentColor black, strokes thinner than half a cell fading out, like the close button of Bootstrap), from a data: URL or the stylesheet loader, two pixels per cell: half blocks in the spaces, the average of the two under the characters; linear-gradient() (an angle or to a side or corner), radial-gradient() (a circle or an ellipse, of a size, at a position), conic-gradient() (from an angle, around a position) and their repeating forms, color stops with percentages, lengths, or angles around a conic one. Every image of a list, up to 8, the first one over the others, each with its own position, size, repeat and boxes (a list shorter than them repeating), their translucent colors blended over the layers under them, over the background color (with background-clip: text, the first one alone); forced colors draw no image, and the first color of a gradient.'));

        // border-image: the lines of the border in the colors of a gradient or an image; they stay lines of cells
        $this->addProperty('border-image-source', function (Style $s, string $v, MediaContext $m) {
            $image = $this->borderImageSource(trim($v), $m);

            return null === $image ? false : $s->withBorderImage($image);
        }, Support::partial('A gradient or a url() image, see background-image: the lines of the border take its colors, cell by cell, the average of the two pixels of a cell of an image; none removes it; forced colors keep the border color.'));
        $this->addProperty('border-image', function (Style $s, string $v, MediaContext $m) {
            if ('none' === strtolower(trim($v))) {
                return $s->withBorderImage(false);
            }
            foreach (Value::split($v) as $part) {
                if (null !== $image = $this->borderImageSource($part, $m)) {
                    return $s->withBorderImage($image);
                }
            }

            return false;
        }, Support::partial('Its source, see border-image-source; the slice, width, outset and repeat are accepted: a border is a line of cells.'));
        $this->addProperty('border-image-slice', static fn (Style $s, string $v) => preg_match('/^(?:(?:\d*\.?\d+%?|fill)\s*){1,5}$/i', trim($v)) ? $s : false, Support::partial('Accepted: the whole source colors the border, a line of cells.'));
        foreach (['border-image-width', 'border-image-outset'] as $name) {
            $this->addProperty($name, static fn (Style $s, string $v) => preg_match('/^(?:(?:auto|-?\d*\.?\d+(?:[a-z]+|%)?)\s*){1,4}$/i', trim($v)) ? $s : false, Support::partial('Accepted: the border keeps its width, a line of cells, see border-width.'));
        }
        $this->addProperty('border-image-repeat', static fn (Style $s, string $v) => preg_match('/^(?:(?:stretch|repeat|round|space)\s*){1,2}$/i', trim($v)) ? $s : false, Support::partial('Accepted: the source stretches over the border.'));

        // The longhands of the background layout: valid values are kept, the layout is built in finalize()
        $layout = static fn (string $property) => static fn (Style $s, string $v, MediaContext $m) => null !== self::backgroundLayoutPart($property, $v, $m) ? $s : false;
        $this->addProperty('background-clip', $layout('background-clip'), Support::partial('border-box, padding-box and content-box: the cells the background color, image and gradient are painted in; text paints them as the color of the glyphs, as with a transparent text color, the cells keeping what is behind them.'));
        $this->addProperty('background-origin', $layout('background-origin'), Support::supported('border-box, padding-box (the initial value) and content-box: the area the image or gradient is sized and placed in.'));
        $this->addProperty('background-size', $layout('background-size'), Support::partial('Lengths and percentages of the positioning area, one or two; auto is the size of an image (8 pixels per column, two per row), keeping its proportions when the other one is set, or the area for a gradient, which has none of its own; cover and contain scale an image to the area, keeping its proportions, and make a gradient the size of the area.'));
        $this->addProperty('background-position', $layout('background-position'), Support::supported('Keywords, lengths and percentages, one to four values with offsets from an edge, like right 2ch bottom 1; one per layer, see background-image.'));
        $this->addProperty('background-position-x', $layout('background-position-x'), Support::supported('See background-position.'));
        $this->addProperty('background-position-y', $layout('background-position-y'), Support::supported('See background-position.'));
        $this->addProperty('background-repeat', $layout('background-repeat'), Support::supported('repeat, no-repeat, space and round, by axis, repeat-x and repeat-y: the image or gradient tiles its painting area from its position; space spreads whole tiles across the positioning area.'));
        $this->addProperty('background-repeat-x', $layout('background-repeat-x'), Support::supported('See background-repeat.'));
        $this->addProperty('background-repeat-y', $layout('background-repeat-y'), Support::supported('See background-repeat.'));

        foreach (['padding' => Support::supported('Lengths, the top and bottom ones a row from three quarters of one, like padding: .5rem 1rem none above and below, and percentages of the width of the containing block for every side, like CSS (an item of a row takes them of the row), calc() mixing them and min(), max() and clamp() over them, like max(1ch, 5%); on inline elements, the left and right padding, in their colors, percentages being 0.'), 'margin' => Support::partial('Around the border, painted like the parent; percentages are of the width of the containing block, for every side, like padding. A negative top or bottom margin pulls a block over what is before or after it in block and flex column layouts, its cells over theirs but for its spaces on no background or the one it inherits, like its margin, collapsing with the others like CSS; in a row, a negative left or right margin pulls an item over the one before it, never before the row, or the first child of an item with nothing on its left pulls the item, like a link of a pagination; a negative margin of less than a cell, like -1px, is a cell on a side with a border, which joins the border before it, ┐ and ┌ making ┬, like the buttons of a group; a negative bottom margin of the last children of a box with a bottom border and no bottom padding draws their last row over that border, a line hidden in the color of the background opening it, like the active tab of the tabs of Bootstrap; negative left and right margins are 0 elsewhere. With no border nor padding between them, the negative margin of a first or last block collapses into that of its parent, pulling the parent; inside a border or padding, it is 0. On inline elements, the left and right margins. Vertical margins collapse in block layout like CSS, between siblings and between a parent and its first or last child when no border or padding separates them; not in flex columns. Horizontal auto margins take the free columns of a row, or center a narrower block; vertical ones take the free rows of a flex column of a set height, like margin-top: auto pushing a button to the bottom of a card, nothing growing in it, and are 0 elsewhere.')] as $box => $support) {
            $this->addProperty($box, static function (Style $s, string $v, MediaContext $m) use ($box) {
                $parts = Value::split($v);
                if (!$parts || \count($parts) > 4) {
                    return false;
                }

                return self::withBoxSides($s, $box, [0, 1, 2, 3], match (\count($parts)) {
                    1 => [$parts[0], $parts[0], $parts[0], $parts[0]],
                    2 => [$parts[0], $parts[1], $parts[0], $parts[1]],
                    3 => [$parts[0], $parts[1], $parts[2], $parts[1]],
                    4 => $parts,
                }, $m);
            }, $support);
            foreach (['top' => 0, 'right' => 1, 'bottom' => 2, 'left' => 3] as $side => $index) {
                $this->addProperty($box.'-'.$side, static fn (Style $s, string $v, MediaContext $m) => self::withBoxSides($s, $box, [$index], Value::split($v), $m), $support);
            }
            $this->addProperty($box.'-inline', static fn (Style $s, string $v, MediaContext $m) => self::withBoxSides($s, $box, [3, 1], Value::split($v), $m), $support);
            // Logical sides: inline start and end are left and right, right to left the other way (see rightToLeft())
            foreach (['inline-start' => 3, 'inline-end' => 1, 'block-start' => 0, 'block-end' => 2] as $side => $index) {
                $this->addProperty($box.'-'.$side, static fn (Style $s, string $v, MediaContext $m) => self::withBoxSides($s, $box, [$index], Value::split($v), $m), $support);
            }
            $this->addProperty($box.'-block', static fn (Style $s, string $v, MediaContext $m) => self::withBoxSides($s, $box, [0, 2], Value::split($v), $m), $support);
        }

        $this->addProperty('border', static function (Style $s, string $v, MediaContext $m) {
            if ('none' === strtolower(trim($v)) || '0' === trim($v)) {
                return $s->withBorder(new Border(0, 0, 0, 0));
            }
            $width = 1;
            $pattern = $s->getBorder()?->pattern;
            $color = $s->getBorder()?->color;
            foreach (Value::split($v) as $part) {
                if (null !== $cells = self::borderWidth($part, $m)) {
                    $width = $cells;
                } elseif (null !== $p = self::borderPattern($part)) {
                    $width = BorderPattern::NONE === $p ? 0 : $width;
                    $pattern = BorderPattern::NONE === $p ? null : $p;
                } elseif (false !== $c = Value::color($part, $m)) {
                    $color = $c;
                } else {
                    return false;
                }
            }

            return $s->withBorder(new Border($width, $width, $width, $width, $pattern, $color));
        }, Support::partial('Any width above 0 draws a one-cell border, thick ones with heavy lines; dashed and dotted have their own lines, groove, ridge, inset and outset are solid. The lines of a box right under another join those of the other where they go on from them, like the items of a list group, └ over │ making ├. An empty ::before, ::after or element drawn by its borders, one side of a color and one beside it transparent, like the caret of a dropdown of Bootstrap, is a triangle pointing away from that side, ▾ ▴ ▸ ◂, in its color.'));
        $this->addProperty('border-width', static function (Style $s, string $v, MediaContext $m) {
            $widths = array_map(static fn (string $p) => self::borderWidth($p, $m), Value::split($v));
            if (!$widths || \in_array(null, $widths, true) || \count($widths) > 4) {
                return false;
            }
            $border = Border::from($widths);

            return $s->withBorder(($s->getBorder() ?? new Border(0, 0, 0, 0))->withWidths($border->top, $border->right, $border->bottom, $border->left));
        }, Support::partial('Any width above 0 is one cell; thick draws heavy lines.'));
        $sides = [
            'top' => ['top'], 'right' => ['right'], 'bottom' => ['bottom'], 'left' => ['left'],
            'block' => ['top', 'bottom'], 'inline' => ['left', 'right'],
            'block-start' => ['top'], 'block-end' => ['bottom'], 'inline-start' => ['left'], 'inline-end' => ['right'],
        ];
        foreach ($sides as $name => $physical) {
            $this->addProperty('border-'.$name, static fn (Style $s, string $v, MediaContext $m) => self::withBorderSides($s, $physical, $v, $m), Support::partial(match ($name) {
                'top', 'right', 'bottom', 'left' => 'The width, style and color of the side; any width above 0 is one cell.',
                default => 'The width, style and color of the sides; any width above 0 is one cell; logical sides follow direction, in a top-to-bottom writing mode.',
            }));
            $logical = \in_array($name, ['top', 'right', 'bottom', 'left'], true) ? '' : '; logical sides follow direction, in a top-to-bottom writing mode';
            $this->addProperty('border-'.$name.'-color', static function (Style $s, string $v, MediaContext $m) use ($physical) {
                $color = Value::color(trim($v), $m);
                if (false === $color) {
                    return false;
                }

                return $s->withBorder(($s->getBorder() ?? new Border(0, 0, 0, 0))->withSideColors(array_fill_keys(array_map(self::sideIndex(...), $physical), $color)));
            }, Support::supported('' !== $logical ? ucfirst(substr($logical, 2)).'.' : ''));
            $this->addProperty('border-'.$name.'-width', static fn (Style $s, string $v, MediaContext $m) => null === ($width = self::borderWidth(strtolower(trim($v)), $m)) ? false : self::withSideWidths($s, $physical, $width), Support::partial('Any width above 0 is one cell'.$logical.'.'));
            $this->addProperty('border-'.$name.'-style', static function (Style $s, string $v) use ($physical) {
                $pattern = self::borderPattern(trim($v));
                if (null === $pattern) {
                    return false;
                }
                if (BorderPattern::NONE === $pattern) {
                    return self::withSideWidths($s, $physical, 0);
                }
                $current = $s->getBorder();
                $styled = self::withSideWidths($s, $physical, 1);
                // A border of no width yet takes the style as a whole
                if (null === $current || 0 === $current->top + $current->right + $current->bottom + $current->left) {
                    return $styled->withBorder($styled->getBorder()?->withPattern($pattern));
                }

                return $styled->withBorder($styled->getBorder()?->withSidePatterns(array_fill_keys(array_map(self::sideIndex(...), $physical), $pattern)));
            }, Support::supported('none removes the side; the corners join the styles of their sides when box drawing has a glyph for them (light and heavy, single and double), else take the style of the top or bottom side'.$logical.'.'));
        }
        $this->addProperty('border-style', static function (Style $s, string $v) {
            $patterns = array_map(self::borderPattern(...), Value::split($v));
            if (!$patterns || \count($patterns) > 4 || \in_array(null, $patterns, true)) {
                return false;
            }
            // Top, right, bottom and left, like the other box sides
            $patterns = match (\count($patterns)) {
                1 => array_fill(0, 4, $patterns[0]),
                2 => [$patterns[0], $patterns[1], $patterns[0], $patterns[1]],
                3 => [$patterns[0], $patterns[1], $patterns[2], $patterns[1]],
                default => $patterns,
            };
            if ([BorderPattern::NONE] === array_unique($patterns)) {
                return $s->withBorder(new Border(0, 0, 0, 0));
            }
            $border = $s->getBorder();
            $border = null === $border || 0 === $border->top + $border->right + $border->bottom + $border->left ? new Border(1, 1, 1, 1, null, $border?->color, $border?->sideColors) : $border;
            $widths = [$border->top, $border->right, $border->bottom, $border->left];
            foreach ($patterns as $side => $pattern) {
                if (BorderPattern::NONE === $pattern) {
                    $widths[$side] = 0;
                }
            }
            $drawn = array_values(array_filter($patterns, static fn (string $pattern) => BorderPattern::NONE !== $pattern));
            $border = $border->withWidths(...$widths)->withPattern($drawn[0]);

            return $s->withBorder(1 === \count(array_unique($drawn)) ? $border : $border->withSidePatterns(array_map(static fn (string $pattern) => BorderPattern::NONE === $pattern ? null : $pattern, $patterns)));
        }, Support::supported('One to four styles, by side, like the other box sides; Tui patterns (rounded, tall, wide...) are accepted too.'));
        $this->addProperty('border-color', static function (Style $s, string $v, MediaContext $m) {
            $colors = array_map(static fn (string $part) => Value::color($part, $m), Value::split($v));
            if (!$colors || \count($colors) > 4 || \in_array(false, $colors, true)) {
                return false;
            }
            if (1 === \count($colors)) {
                return $s->withBorderColor($colors[0]);
            }
            $sides = match (\count($colors)) {
                2 => [$colors[0], $colors[1], $colors[0], $colors[1]],
                3 => [$colors[0], $colors[1], $colors[2], $colors[1]],
                default => $colors,
            };

            return $s->withBorder(($s->getBorder() ?? new Border(0, 0, 0, 0))->withSideColors($sides));
        }, Support::partial('One to four colors, by side; the corners take the color of the top or bottom side.'));
        // Corners depend on the final border style: finalize() handles them
        $this->addProperty('border-radius', static fn (Style $s, string $v, MediaContext $m) => null === self::radii($v, $m) ? false : $s, Support::partial('One to four radii, by corner; any radius above 0 rounds that corner of a solid border (╭), the elliptic radii after a slash are not needed; see corner-shape.'));
        foreach (['top-left', 'top-right', 'bottom-right', 'bottom-left', 'start-start', 'start-end', 'end-end', 'end-start'] as $corner) {
            $this->addProperty('border-'.$corner.'-radius', static fn (Style $s, string $v, MediaContext $m) => \in_array(\count(Value::split($v)), [1, 2], true) && [] === array_filter(Value::split($v), static fn (string $r) => null === self::radius($r, $m)) ? $s : false, Support::partial('See border-radius.'));
        }
        $shapes = static fn (string $v, int $most): bool => \count($parts = self::cornerShapeParts($v)) >= 1 && \count($parts) <= $most && !\in_array(false, $parts, true);
        $this->addProperty('corner-shape', static fn (Style $s, string $v) => $shapes($v, 4) ? $s : false, Support::partial('One to four shapes, by corner, of the corners with a radius above 0: round, squircle and superellipse() above 0 draw ╭, square and superellipse(infinity) the corner of the border, bevel, notch, scoop and superellipse() of 0 or below draw a diagonal (╱), the cells being too coarse for the others.'));
        foreach (['top-left', 'top-right', 'bottom-right', 'bottom-left', 'start-start', 'start-end', 'end-end', 'end-start'] as $corner) {
            $this->addProperty('corner-'.$corner.'-shape', static fn (Style $s, string $v) => $shapes($v, 1) ? $s : false, Support::partial('See corner-shape.'));
        }
        foreach (['top', 'right', 'bottom', 'left', 'block-start', 'block-end', 'inline-start', 'inline-end'] as $side) {
            $this->addProperty('corner-'.$side.'-shape', static fn (Style $s, string $v) => $shapes($v, 2) ? $s : false, Support::partial('The two corners of a side, see corner-shape.'));
        }

        // bolder and lighter are resolved against the weight of the parent by the cascade
        $this->addProperty('font-weight', static fn (Style $s, string $v) => match (true) {
            \in_array($v, ['bold', 'bolder'], true) || (is_numeric($v) && $v >= 600 && $v <= 1000) => $s->withBold(),
            'lighter' === $v || (is_numeric($v) && $v >= 1 && $v <= 300) => $s->withBold(false)->withDim(),
            'normal' === $v || (is_numeric($v) && $v >= 1 && $v <= 1000) => $s->withBold(false),
            default => false,
        }, Support::partial('600 and above are bold, 300 and below dim (SGR 2); bolder and lighter are relative to the weight of the parent.'));
        $this->addProperty('font-style', static fn (Style $s, string $v) => match (Value::split($v)[0] ?? '') {
            'italic', 'oblique' => $s->withItalic(),
            'normal' => $s->withItalic(false),
            default => false,
        });
        $this->addProperty('font', static function (Style $s, string $v) {
            $parts = Value::split(strtolower($v));
            if (!$parts) {
                return false;
            }
            $bold = $italic = $dim = false;
            foreach ($parts as $part) {
                if ('italic' === $part || 'oblique' === $part) {
                    $italic = true;
                } elseif ('bold' === $part || 'bolder' === $part || (is_numeric($part) && $part >= 600 && $part <= 1000)) {
                    $bold = true;
                } elseif ('lighter' === $part || (is_numeric($part) && $part >= 1 && $part <= 300)) {
                    $dim = true;
                }
            }

            // A shorthand resets the longhands it does not set
            $s = $s->withBold($bold)->withItalic($italic);

            return $dim ? $s->withDim() : $s;
        }, Support::partial('The style, the weight, small-caps, the size, the line height after a slash and the families, see font-size and font-family; the longhands it does not set reset; the system fonts, like menu, set neither a size nor a family.'));
        $decorationStyle = static fn (string $value): UnderlineStyle|false => match ($value) {
            'solid' => UnderlineStyle::Single,
            'double' => UnderlineStyle::Double,
            'wavy' => UnderlineStyle::Curly,
            'dotted' => UnderlineStyle::Dotted,
            'dashed' => UnderlineStyle::Dashed,
            default => false,
        };
        $decoration = static function (Style $s, string $v, MediaContext $m, bool $shorthand = false) use ($decorationStyle) {
            $parts = Value::split($v);
            $lower = array_map('strtolower', $parts);
            if (\in_array('none', $lower, true)) {
                return $s->withUnderline(false)->withStrikethrough(false)->withOverline(false);
            }
            $underline = \in_array('underline', $lower, true);
            $strike = \in_array('line-through', $lower, true);
            $over = \in_array('overline', $lower, true);
            // The lines it names; an overline alone leaves the others as they are
            $lines = static fn (Style $s): Style => ($underline || $strike ? $s->withUnderline($underline)->withStrikethrough($strike) : $s)->withOverline($underline || $strike || $over ? $over : $s->getOverline());
            if (!$shorthand) {
                return $lines($s);
            }

            // The shorthand: lines, a style and a color, in any order
            $style = $s->getUnderlineStyle();
            $color = $s->getUnderlineColor();
            foreach ($parts as $i => $part) {
                if (\in_array($lower[$i], ['underline', 'line-through', 'overline', 'blink'], true)) {
                    continue;
                }
                if (false !== $shape = $decorationStyle($lower[$i])) {
                    $style = $shape;
                } elseif (\in_array($lower[$i], ['auto', 'from-font'], true) || preg_match('/^\d*\.?\d+%$/', $part) || (!is_numeric($part) && null !== Length::toCells($part, $m))) {
                    // The thickness: the terminal draws the line of its font, see text-decoration-thickness
                    continue;
                } elseif (false !== $parsed = Value::color($part, $m)) {
                    // transparent: no color of its own
                    $color = $parsed;
                } else {
                    return false;
                }
            }
            $s = $s->withUnderlineStyle($style, $color);

            return $lines($s);
        };
        $this->addProperty('text-decoration', static fn (Style $s, string $v, MediaContext $m) => $decoration($s, $v, $m, true), Support::partial('underline, line-through, overline (SGR 53, drawn by kitty, WezTerm, VTE and others) and none, with the style and color of the underline; the thickness is accepted, the terminal drawing the line of its font.'));
        $this->addProperty('text-decoration-line', $decoration, Support::partial('underline, line-through, overline and none.'));
        $this->addProperty('text-decoration-style', static fn (Style $s, string $v) => false !== ($shape = $decorationStyle(strtolower(trim($v)))) ? $s->withUnderlineStyle($shape, $s->getUnderlineColor()) : false, Support::supported('solid, double, wavy (curly), dotted and dashed underlines, in the terminals that draw them; the others draw a plain underline.'));
        $this->addProperty('text-decoration-color', static fn (Style $s, string $v, MediaContext $m) => false !== ($color = Value::color($v, $m)) ? $s->withUnderlineStyle($s->getUnderlineStyle(), $color) : false, Support::supported('The color of the underline (SGR 58), in the terminals that draw it.'));
        $this->addProperty('opacity', static fn (Style $s, string $v) => is_numeric(rtrim($v, '%')) ? $s->withDim((float) rtrim($v, '%') < (str_ends_with($v, '%') ? 100 : 1))->withTransparent((float) rtrim($v, '%') <= 0) : false, Support::partial('Below 1, the colors of the element are blended over what is behind it, and its text is dim, the text of its children too; 0 draws nothing of it, what is behind showing, though it keeps its room, the focus and the pointer, like a checkbox hidden behind its label.'));
        $this->addProperty('box-shadow', static fn (Style $s, string $v, MediaContext $m) => 'none' === strtolower(trim($v)) || null !== self::shadow($v, $m, null) ? $s : false, Support::partial('A shadow spread around the box, not moved nor blurred, like a focus ring, is a line hugging the box from outside, over the blank cells around it (not the text nor the lines), thin up to 2px, else thick, the widest of them, unless an outline is drawn; an inset one of a few pixels is the same line along the edge cells of the box, inside it, over the blank ones, like the frame of the notes of PHP.net, or around a box of less than three rows, like a field with the ring-inset of Tailwind; the first other shadow that is not inset: its offsets, blur and spread rounded to whole cells, from three quarters of one, so that a shadow of a pixel or two, a hairline in browsers, is none (its offsets 0 to 4, to the right and below), take room between the border and the margin, in its color blended over what is behind; the first inset one shades the edges inside the border it is offset from (top and left, or bottom and right when negative), the text staying; one not moved nor blurred with a spread of a hundred cells or more, like the inset 0 0 0 9999px of the stripes of a table of Bootstrap, is a background over the background color, blended with it, under the border too. Outside, the spread grows the shadow on the right and below (not past the top and left of the box, where it has no room), and a blur adds a softer cell around it, drawn with half blocks in its color; inside, blur and spread are ignored.'));
        // Applied in finalize(), over the color behind the element
        $this->addProperty('mix-blend-mode', static fn (Style $s, string $v) => null !== BlendMode::fromKeyword($v, plus: true) ? $s : false, Support::partial('The colors of the element and of what it contains, cell by cell, the text and the backgrounds, blended with the closest background around it (or the terminal background of the color scheme), see the blend modes; an ancestor without a background that is a group of its own (isolation: isolate, opacity below 1, a filter, a transform or a blend mode of its own) stops it, its content then drawn as it is. The cells without a background of their own keep the one behind.'));
        $this->addProperty('isolation', static fn (Style $s, string $v) => \in_array(strtolower(trim($v)), ['auto', 'isolate'], true) ? $s : false, Support::supported('isolate makes the element a group of its own: the mix-blend-mode of what it contains blends with its background, or with nothing when it has none, not with what is behind it.'));
        $this->addProperty('background-blend-mode', static function (Style $s, string $v) {
            // One mode per background layer: the first here, for a single one; several layers take theirs, see backgroundLayers()
            $mode = BlendMode::fromKeyword(Value::split(explode(',', $v)[0])[0] ?? '');
            foreach (explode(',', $v) as $part) {
                if (null === BlendMode::fromKeyword($part)) {
                    return false;
                }
            }

            return null !== $mode ? $s->withBackgroundBlend($mode) : false;
        }, Support::partial('The background image or gradient blended with the background color, cell by cell, see the blend modes; a mode per layer, the list repeated past its end: each layer blended with the layers under it and the background color.'));
        $this->addProperty('filter', static function (Style $s, string $v) {
            $v = strtolower(trim($v));
            if ('none' === $v) {
                return $s->withReverse(false)->withFilter(false);
            }
            // A full invert() alone swaps the colors, which also inverts the terminal ones; -tui-invert, that of the user agent
            if ('-tui-invert' === $v || preg_match('/^invert\(\s*(1(?:\.0+)?|100(?:\.0+)?%)?\s*\)$/', $v)) {
                return $s->withReverse()->withFilter(false);
            }

            // blur() cannot be drawn, drop-shadow() is the shadow of the box, see finalize()
            [$colors, $shadows] = self::filterParts($v);
            if (null === $colors) {
                return false;
            }
            if ('' === $colors) {
                return $s->withReverse(false)->withFilter(false);
            }
            if (preg_match('/^invert\(\s*(1(?:\.0+)?|100(?:\.0+)?%)?\s*\)$/', $colors)) {
                return $s->withReverse()->withFilter(false);
            }

            return null !== ($filter = self::filter($colors)) ? $s->withFilter($filter) : false;
        }, Support::partial('grayscale(), sepia(), saturate(), hue-rotate(), brightness(), contrast(), invert() and opacity(), one after the other, on the colors of the element and of what it contains, the text of the terminal color taken as that of the color scheme and the terminal background kept; invert() alone swaps the foreground and background colors. drop-shadow() is the shadow of the box when box-shadow sets none, see box-shadow; blur() is accepted, a cell cannot be blurred; url() is not supported. On the shapes and groups of an <svg>, from its presentation attributes, the rules that match them and their style attribute: these functions, blur() and drop-shadow() too, on their pixels, or url() of a <filter>, see html.elements.svg.'));
        $this->addProperty('font-family', static function (Style $s, string $v) {
            // The families before the first generic one, which is the font of the terminal
            $families = [];
            foreach (Selector::splitTopLevel($v) as $family) {
                $family = trim($family, " \t\n\"'");
                if ('' === $family) {
                    return false;
                }
                if (\in_array(strtolower($family), FontRegistry::GENERIC_FAMILIES, true)) {
                    break;
                }
                $families[] = $family;
            }
            if (!$families) {
                return $s->withFont(null);
            }

            // Like browsers, a font that is not there is passed over: the first registered one is drawn (see FontRegistry::first())
            return $s->withFont(1 === \count($families) && \in_array($families[0], FontRegistry::BUNDLED_FONTS, true) ? $families[0] : implode(', ', [...$families, 'monospace']));
        }, Support::partial('The first FIGlet font of the list that is registered (big, small, slant, standard and mini are bundled), like font-family: slant, sans-serif; the others, like web fonts, are passed over, and generic families use the terminal font.'));

        $this->addProperty('display', static fn (Style $s, string $v) => match ($v) {
            'none' => $s->withHidden(),
            'contents' => $s->withHidden(false)->withTableRole(null)->withContents(),
            'block', 'flex', 'inline-flex', 'inline', 'inline-block', 'grid', 'inline-grid', 'flow-root', 'ruby', 'ruby-base', 'ruby-text', 'ruby-base-container', 'ruby-text-container' => $s->withHidden(false)->withTableRole(null)->withContents(false),
            // A caption is a block of its table, drawn above or below its rows: its side says it is one
            'table-caption' => $s->withHidden(false)->withTableRole(null)->withContents(false)->withCaptionBottom($s->getCaptionBottom() ?? false),
            'table', 'inline-table' => $s->withHidden(false)->withTableRole(TableRole::Table),
            'table-row-group', 'table-header-group', 'table-footer-group' => $s->withHidden(false)->withTableRole(TableRole::RowGroup),
            'table-row' => $s->withHidden(false)->withTableRole(TableRole::Row)->withDirection(Direction::Horizontal),
            'table-cell' => $s->withHidden(false)->withTableRole(TableRole::Cell),
            'table-column', 'table-column-group' => $s->withHidden(true)->withTableRole(TableRole::Column),
            default => false,
        }, Support::partial('none hides; contents has no box, its children laid out in its parent; flex lays children out in a row unless flex-direction says otherwise, its items sized by their content unless they grow. Inline children of a block that some rule makes a flex container, in any media, become flex items, those of a text block like <p> or <li> too, its text runs items of their own. inline-block, inline-flex and inline-grid with a border are boxed in the line of the text around them, like a button, as wide as their content, and with a width, at that width, like the bars of the placeholders of Bootstrap; a block element displayed inline-block, inline-flex or inline-grid by the rules of every media and state, like <div class="d-inline-block">, is in the line too, as wide as its content, and stays a block when some media or state makes it one; one displayed inline by the rules of every media and state, like the <li> of a breadcrumb, is text of the line, unless it holds a block or a form control. table, table-row-group (and the header and footer groups), table-row and table-cell size the columns across the rows, table-column and table-column-group give them a least width; a caption, or display: table-caption, is drawn above its table, or below it by caption-side, the table at least as wide as its longest word. A block of rows, like table { display: block; overflow-x: auto } to scroll a wide table, is drawn as their table, like the anonymous table of CSS.'));
        $this->addProperty('interactivity', static fn (Style $s, string $v) => match (strtolower(trim($v))) {
            'inert' => $s->withInert(),
            'auto' => $s->withInert(false),
            default => false,
        }, Support::supported('inert: like the inert attribute, the element and what it holds, which inherits it, take neither the focus nor the pointer, what is behind them taking the pointer; auto, the default, takes them back.'));
        $this->addProperty('content-visibility', static fn (Style $s, string $v) => match (strtolower(trim($v))) {
            'hidden' => $s->withContentHidden(),
            'visible', 'auto' => $s->withContentHidden(false),
            default => false,
        }, Support::partial('hidden draws the box without its children or its text, which take neither the focus nor the pointer, sized by contain-intrinsic-size; auto is like visible: what is not on the screen costs nothing already.'));
        $intrinsic = static fn (Style $s, string $v, MediaContext $m) => array_reduce(Value::split(trim($v)), static fn (bool $valid, string $part) => $valid && ('auto' === strtolower($part) || 'none' === strtolower($part) || null !== Length::toCells($part, $m)), '' !== trim($v)) ? $s : false;
        $this->addProperty('contain-intrinsic-size', $intrinsic, Support::partial('The width and the height of the content of an element with content-visibility: hidden or contain: size (its content drawn in that size), and no width or height; auto is the size given, nothing being remembered.'));
        $this->addProperty('contain', static fn (Style $s, string $v) => preg_match('/^(?:none|strict|content|(?:(?:size|inline-size|layout|style|paint)\s*)+)$/i', trim($v)) ? $s : false, Support::partial('size and strict size the element by contain-intrinsic-size, its content drawn in that size, cut at it; inline-size its width alone; without a size there, nothing changes, as the content would go over what follows; layout, style, paint and content are hints for browsers.'));
        foreach (['contain-intrinsic-width', 'contain-intrinsic-height', 'contain-intrinsic-inline-size', 'contain-intrinsic-block-size'] as $property) {
            $this->addProperty($property, $intrinsic, Support::partial('See contain-intrinsic-size.'));
        }
        $this->addProperty('visibility', static fn (Style $s, string $v) => match ($v) {
            'hidden' => $s->withInvisible(),
            'collapse' => $s->withHidden(),
            'visible' => $s->withInvisible(false),
            default => false,
        }, Support::partial('hidden keeps the room and draws nothing, collapse frees it; a visible descendant of a hidden element stays hidden, and neither can take the focus.'));
        $this->addProperty('flex-direction', static fn (Style $s, string $v) => match ($v) {
            'row', 'row-reverse' => $s->withDirection(Direction::Horizontal)->withReverseOrder('row-reverse' === $v),
            'column', 'column-reverse' => $s->withDirection(Direction::Vertical)->withReverseOrder('column-reverse' === $v),
            default => false,
        }, Support::supported('The reverse directions lay the children out from the last; the focus order follows the document, like in CSS.'));
        $this->addProperty('flex-flow', static function (Style $s, string $v) {
            foreach (Value::split($v) as $part) {
                if (\in_array($part, ['row', 'row-reverse'], true)) {
                    return $s->withDirection(Direction::Horizontal)->withReverseOrder('row-reverse' === $part);
                }
                if (\in_array($part, ['column', 'column-reverse'], true)) {
                    return $s->withDirection(Direction::Vertical)->withReverseOrder('column-reverse' === $part);
                }
                if (!\in_array($part, ['nowrap', 'wrap', 'wrap-reverse'], true)) {
                    return false;
                }
            }

            return $s;
        }, Support::partial('The direction and the wrapping, see flex-direction and flex-wrap.'));
        $this->addProperty('flex-wrap', static fn (Style $s, string $v) => \in_array(strtolower(trim($v)), ['nowrap', 'wrap', 'wrap-reverse'], true) ? $s : false, Support::partial('In rows, wrap breaks the children that do not fit onto the next lines, measured by their content; wrap-reverse puts the lines from the bottom up; row-gap separates the lines.'));
        $flex = static function (Style $s, string $v) {
            $first = Value::split($v)[0] ?? '';

            return match (true) {
                'none' === $first => $s->withFlex(0),
                'auto' === $first => $s->withFlex(1),
                is_numeric($first) && (float) $first >= 0 => $s->withFlex((float) $first),
                default => false,
            };
        };
        // A basis: null for the content, an int of columns, or a percentage as a string
        $basis = static fn (string $v, MediaContext $m): int|string|false|null => match (true) {
            \in_array(strtolower($v), ['auto', 'content', 'max-content', 'fit-content'], true) => null,
            1 === preg_match('/^(\d+(?:\.\d+)?)%$/', $v, $p) => (float) $p[1] <= 100 ? (string) (int) round((float) $p[1]) : false,
            default => (null !== $cells = Length::toCells($v, $m)) && $cells >= 0 ? $cells : false,
        };
        $withBasis = static fn (Style $s, int|string|null $cells): Style => match (true) {
            \is_string($cells) => $s->withFlexBasisPercent((int) $cells),
            null === $cells => $s->withContentBasis(),
            default => $s->withFlexBasis($cells),
        };
        $this->addProperty('flex', static function (Style $s, string $v, MediaContext $m) use ($flex, $basis, $withBasis) {
            $parts = Value::split($v);
            $keyword = strtolower($parts[0] ?? '');
            if ('none' === $keyword || 'auto' === $keyword || 'initial' === $keyword) {
                // none: 0 0 auto; auto: 1 1 auto; initial: 0 1 auto
                return 1 === \count($parts) ? $s->withFlex('auto' === $keyword ? 1 : 0)->withShrink('none' === $keyword ? 0 : 1)->withContentBasis() : false;
            }

            // <grow> [<shrink>] [<basis>], or <basis> alone; a number is a factor, anything else the basis
            $numbers = array_values(array_filter($parts, 'is_numeric'));
            $rest = array_values(array_filter($parts, static fn (string $part) => !is_numeric($part)));
            // A third number is the basis: a unitless 0, like in "flex: 1 1 0"
            if (3 === \count($numbers) && !$rest) {
                $rest = [array_pop($numbers)];
            }
            if (\count($numbers) > 2 || \count($rest) > 1) {
                return false;
            }
            $grow = $numbers ? $numbers[0] : '1';
            $style = $flex($s, $grow);
            if (false === $style) {
                return false;
            }
            $style = $style->withShrink(isset($numbers[1]) ? (float) $numbers[1] : 1);
            // With the grow factor alone, the basis is 0: the share of the free columns is the whole width
            $cells = $rest ? $basis($rest[0], $m) : 0;

            return false === $cells ? false : $withBasis($style, $cells);
        }, Support::supported('The grow and shrink factors, fractions too, and the basis.'));
        $this->addProperty('flex-basis', static function (Style $s, string $v, MediaContext $m) use ($basis, $withBasis) {
            $cells = $basis($v, $m);

            return false === $cells ? false : $withBasis($s, $cells);
        }, Support::partial('auto, content, lengths in columns and percentages of the row; a basis of 0 on an item that does not grow keeps its content width.'));
        $this->addProperty('flex-shrink', static fn (Style $s, string $v) => is_numeric($v) && (float) $v >= 0 ? $s->withShrink((float) $v) : false, Support::supported('Fractions too, weighing how much of the overflow each item takes with its width; 0 keeps the intrinsic width while others can give way.'));
        $this->addProperty('flex-grow', $flex, Support::supported('Fractions too; factors summing to less than 1 share only that part of the free columns, like CSS; in a flex column of a set height, a least height, like min-height: 100vh, or stretched, the items that grow share its free rows, like a <main> of flex: 1 pushing a footer to the bottom.'));
        // Gap and alignment depend on the direction: finalize() handles them
        foreach (['gap', 'row-gap', 'column-gap', 'align-items', 'justify-content', 'place-items', 'place-content'] as $name) {
            $this->addProperty($name, static fn (Style $s) => $s, Support::partial(match ($name) {
                'gap' => 'One gap along the layout direction; grid-gap, its old name, too.',
                'row-gap', 'column-gap' => 'Used when it matches the layout direction; grid-row-gap and grid-column-gap, their old names, too.',
                'place-items' => 'Sets align-items: start, center, end and stretch, and justify-items, its second value or its only one.',
                'align-items' => 'start, center and end; in rows, stretch (the default) pads the shorter items to the height of the row, that of the container when it has a set height, like a single-line flex container; in flex columns, the items not stretched are as wide as their content, placed across; baseline is like start (rows have one height), last baseline like end.',
                'place-content' => 'Sets align-content from its first value and justify-content from its second one (or both from its only one): start, center, end, stretch and space-*.',
                'justify-content' => 'start, center and end, each line of a wrapping row in the room it leaves; space-between, space-around and space-evenly in rows, and in flex columns of a set height; stretch is like start, as in flexbox.',
            }));
        }
        $this->addProperty('align-self', static fn (Style $s, string $v) => match (strtolower(trim($v))) {
            'auto' => $s->withAlignSelf(null),
            // Of an anchored element, applied where it is placed (see finalize())
            'anchor-center' => $s,
            'normal', 'stretch' => $s->withAlignSelf(VerticalAlign::Stretch),
            default => match (self::position($v)) {
                'start' => $s->withAlignSelf(VerticalAlign::Top),
                'center' => $s->withAlignSelf(VerticalAlign::Center),
                'end' => $s->withAlignSelf(VerticalAlign::Bottom),
                null => false,
            },
        }, Support::partial('start, center, end and stretch: in rows, the item within the height of the row; in flex columns, across: an item not stretched is as wide as its content; baseline is like start. anchor-center centers an element placed next to its anchor down it, see justify-self.'));
        $overflow = static fn (Style $s, string $v) => array_all(Value::split(strtolower($v)), static fn (string $part) => \in_array($part, ['visible', 'hidden', 'clip', 'scroll', 'auto', 'overlay'], true)) ? $s : false;
        foreach (['overflow', 'overflow-x', 'overflow-y', 'overflow-inline', 'overflow-block'] as $name) {
            $this->addProperty($name, $overflow, Support::partial(match ($name) {
                'overflow-x', 'overflow-inline' => 'auto and scroll scroll the content wider than the container sideways, with a scrollbar on its last row: text that does not wrap, like <pre> and white-space: nowrap, rows of items, and tables as wide as their longest words; the others clip, a child of a width set wider than the box laid out at it and cut, like a list of slides of a carousel. With overflow-y alone, what is too wide is clipped.',
                'overflow' => 'auto and scroll make a container of bounded height (height, max-height, or the rows it fills) a scroll viewport, with a scrollbar, and scroll the content wider than it sideways, see overflow-x; the others clip.',
                default => 'Vertically, auto and scroll make a container of bounded height (height, max-height, or the rows it fills) a scroll viewport, with a scrollbar; the others clip.',
            }));
        }
        $this->addProperty('position', static fn (Style $s, string $v) => \in_array(strtolower(trim($v)), ['static', 'relative', 'sticky', 'absolute', 'fixed'], true) ? $s : false, Support::partial('relative stays in the flow and contains the absolute elements inside it, its offsets moving it within its parent, its room kept empty (block elements, not inline ones); absolute and fixed leave the flow and are drawn over the rest at their insets from the closest positioned ancestor or the screen, or where they would be in the flow without insets, like a dropdown menu under its button, their blank cells without a background of their own showing what is under them, like a floating label over its field, an absolute ::before or ::after too (not the lists of controls, dialogs nor popovers, on the background of the page), sized by their content or both insets, inline elements too, like a badge in a paragraph, the text around them flowing on; sticky with top or bottom keeps the element in view in its scroll container, below its top or above its bottom, while its parent is, or its table for a cell, a row or a row group, like a header.'));
        $inset = static fn (Style $s, string $v, MediaContext $m) => 'auto' === strtolower(trim($v)) || null !== Length::toCells($v, $m) || null !== self::insetPercent($v, $m) || null !== self::anchorEdge($v) ? $s : false;
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $this->addProperty($side, $inset, Support::partial('In cells or percents of the containing block of an absolute or fixed element (top: 100% puts it below), also in calc(), min(), max() and clamp(), like left: calc(50% - 10ch); top and bottom also place a sticky one.'));
        }
        foreach (['inset-inline-start', 'inset-inline-end', 'inset-block-start', 'inset-block-end'] as $name) {
            $this->addProperty($name, $inset, Support::partial('Like left, right, top and bottom; right to left, the inline start is the right.'));
        }
        foreach (['inset-inline', 'inset-block'] as $name) {
            $this->addProperty($name, static fn (Style $s, string $v, MediaContext $m) => ($parts = Value::split($v)) && \count($parts) <= 2 && array_all($parts, static fn (string $p) => 'auto' === strtolower($p) || null !== Length::toCells($p, $m) || null !== self::insetPercent($p, $m) || null !== self::anchorEdge($p)) ? $s : false, Support::partial('The start and end insets, see inset-inline-start.'));
        }
        $this->addProperty('inset', static fn (Style $s, string $v, MediaContext $m) => ($parts = Value::split($v)) && \count($parts) <= 4 && array_all($parts, static fn (string $p) => 'auto' === strtolower($p) || null !== Length::toCells($p, $m) || null !== self::insetPercent($p, $m) || null !== self::anchorEdge($p)) ? $s : false, Support::partial('One to four insets, like top, right, bottom and left, anchor() included.'));
        $this->addProperty('z-index', static fn (Style $s, string $v) => 'auto' === strtolower(trim($v)) ? $s->withZIndex(0) : (null !== ($zIndex = self::integer($v)) ? $s->withZIndex($zIndex) : false), Support::partial('Orders the absolute and fixed elements drawn over the flow, a negative one drawn under the flow instead, the text of the page over its background, and not taking the pointer; the relative elements of a container moved by their offsets over each other, the higher one over the others, and those of a positive z-index drawn with the absolute and fixed ones, by z-index, their lines joined with those under them, like the active link of a pagination; the other elements in the flow are under the absolute and fixed ones of z-index 0 and more; a positioned element of a z-index other than auto is a stacking context, like one of opacity below 1, a filter, a blend mode, a transform or isolation: isolate: the positioned elements inside it are ordered among themselves and drawn with it, a negative one over its background and under its text, a z-index of 100 in one of 1 under a sibling of 2; a stacking context in the flow is drawn with the flow, whatever its z-index; the pointer follows the same order; the items of a grid that overlap are painted by it too, positioned or not.'));
        $keyword = static fn (array $values) => static fn (Style $s, string $v) => array_all(Value::split(strtolower($v)), static fn (string $part) => \in_array($part, $values, true)) && '' !== trim($v) ? $s : false;
        $clamp = static fn (Style $s, string $v) => 'none' === strtolower(trim($v)) || preg_match('/^\d{1,5}$/', trim($v)) ? $s : false;
        $this->addProperty('line-clamp', $clamp, Support::partial('On text blocks: at most that many lines, the last one ending with an ellipsis.'));
        $this->addProperty('-webkit-line-clamp', $clamp, Support::partial('Like line-clamp; display: -webkit-box and -webkit-box-orient are not needed.'));
        $this->addProperty('forced-color-adjust', $keyword(['auto', 'none', 'preserve-parent-color']), Support::partial('Forced colors draw no colors: none keeps the colors and gradients of the element, inherited by its descendants; preserve-parent-color is like auto.'));
        $this->addProperty('hyphenate-limit-chars', static fn (Style $s, string $v) => 'auto' === strtolower(trim($v)) || null !== CssStyleSheet::hyphenLimits($v) ? $s : false, Support::supported('With hyphens: auto, the fewest characters of a word broken at a soft hyphen, before the break and after it (the before one when missing); auto is no limit; inherited.'));
        $this->addProperty('line-break', $keyword(['auto', 'loose', 'normal', 'strict', 'anywhere']), Support::partial('anywhere breaks lines between any two characters, like word-break: break-all; loose, normal and strict are like auto: lines break at spaces and soft hyphens, without the rules of the languages that differ.'));
        $this->addProperty('word-break', $keyword(['normal', 'break-all', 'keep-all', 'break-word', 'auto-phrase']), Support::partial('break-all breaks lines between any two characters; keep-all keeps the letters of Chinese, Japanese and Korean words together, lines breaking at spaces and punctuation, a word longer than the line still broken; auto-phrase is like normal.'));
        $overflowWrap = Support::partial('Words longer than the line are always broken, like anywhere and break-word: a terminal line cannot overflow.');
        $this->addProperty('overflow-wrap', $keyword(['normal', 'anywhere', 'break-word']), $overflowWrap);
        $this->addProperty('word-wrap', $keyword(['normal', 'anywhere', 'break-word']), $overflowWrap);
        $this->addProperty('hyphens', $keyword(['none', 'manual', 'auto']), Support::partial('Soft hyphens (&shy;) are where words break, showing the hyphenate-character there; none ignores them; auto also breaks the words without soft hyphens where the patterns of their language (lang) do: English, French, German and Spanish are bundled, other languages registered with HyphenatorRegistry::register(); a word split across inline elements is broken in each of them apart.'));
        $this->addProperty('text-wrap', $keyword(['wrap', 'nowrap', 'balance', 'pretty', 'stable']), Support::partial('nowrap keeps a text block on one line; balance makes its lines as even as possible; pretty keeps the last line of a paragraph from holding a single word, when a narrower width does it without adding a line; stable is like wrap.'));
        $this->addProperty('text-wrap-mode', $keyword(['wrap', 'nowrap']), Support::supported('See text-wrap.'));
        $this->addProperty('text-wrap-style', $keyword(['auto', 'balance', 'pretty', 'stable']), Support::partial('See text-wrap.'));
        $this->addProperty('tab-size', static fn (Style $s, string $v, MediaContext $m) => null !== Length::tabSize($v, $m) ? $s : false, Support::supported('In preserved whitespace, the columns between tab stops: a number of spaces, or a length rounded to columns.'));
        $count = static fn (string $v) => 'auto' === $v || preg_match('/^\d{1,3}$/', $v);
        $columnWidth = static fn (string $v, MediaContext $m) => 'auto' === $v || null !== Length::toCells($v, $m);
        $this->addProperty('columns', static fn (Style $s, string $v, MediaContext $m) => ($parts = Value::split(strtolower($v))) && \count($parts) <= 2 && array_all($parts, static fn (string $p) => $count($p) || $columnWidth($p, $m)) ? $s : false, Support::partial('A count and a least width, see column-count and column-width.'));
        $this->addProperty('column-count', static fn (Style $s, string $v) => $count(strtolower(trim($v))) ? $s : false, Support::partial('The content of a container flows into that many balanced columns, filled one after the other, the last one shorter: blocks are cut between columns between their lines, as orphans and widows allow, and between the blocks they hold; images, form controls, flex rows and grids are not cut, and neither are the edges of a box from its first and last lines.'));
        $this->addProperty('column-width', static fn (Style $s, string $v, MediaContext $m) => $columnWidth(strtolower(trim($v)), $m) ? $s : false, Support::partial('As many columns as fit at that least width, at most column-count.'));
        $ruleStyle = static fn (string $v) => \in_array($v, ['none', 'hidden', 'solid', 'dashed', 'dotted', 'double', 'groove', 'ridge', 'inset', 'outset'], true);
        $this->addProperty('column-rule', static fn (Style $s, string $v, MediaContext $m) => array_all(Value::split(strtolower($v)), static fn (string $p) => $ruleStyle($p) || null !== self::borderWidth($p, $m) || false !== Value::color($p, $m)) ? $s : false, Support::partial('A line between the columns, in the middle of the gap, in the style (solid, dashed, dotted, double) and color; the width is one cell.'));
        $this->addProperty('column-rule-style', static fn (Style $s, string $v) => $ruleStyle(strtolower(trim($v))) ? $s : false, Support::partial('See column-rule.'));
        $this->addProperty('column-rule-color', static fn (Style $s, string $v, MediaContext $m) => false !== Value::color(trim($v), $m) ? $s : false, Support::supported());
        $this->addProperty('column-rule-width', static fn (Style $s, string $v, MediaContext $m) => null !== self::borderWidth(strtolower(trim($v)), $m) ? $s : false, Support::partial('Any width above 0 is one cell.'));
        $breakInside = static fn (Style $s, string $v) => match (strtolower(trim($v))) {
            'avoid', 'avoid-column', 'avoid-page' => $s->withAvoidBreak(),
            'auto' => $s->withAvoidBreak(false),
            default => false,
        };
        $this->addProperty('break-inside', $breakInside, Support::partial('avoid and avoid-column keep the element in one column of a multi-column ancestor, unless it is taller than a column; avoid-page is like avoid.'));
        $this->addProperty('page-break-inside', $breakInside, Support::partial('Like break-inside.'));
        // The paint of the shapes of an <svg>, which reads them from the cascade, see CssStyleSheet::svgDeclarations()
        $svgNote = static fn (string $note) => Support::supported($note.' On the shapes of an <svg>, from its presentation attributes, the rules that match them, without states, in the media of the terminal, drawn again as it changes, and their style attribute, inherited, var() taking the custom properties of what holds them; see html.elements.svg.');
        $paint = static fn (Style $s, string $v) => '' !== trim($v) ? $s : false;
        $fraction = static fn (Style $s, string $v) => preg_match('/^[+-]?(?:\d+\.?\d*|\.\d+)(?:e[+-]?\d+)?%?$/i', trim($v)) ? $s : false;
        $this->addProperty('fill', $paint, $svgNote('A color, currentColor, none, or a gradient or a pattern by url().'));
        $this->addProperty('stroke', $paint, $svgNote('A color, currentColor, none, or a pattern by url(), or a gradient drawn in its average color.'));
        $this->addProperty('fill-opacity', $fraction, $svgNote('A number or a percentage.'));
        $this->addProperty('stroke-opacity', $fraction, $svgNote('A number or a percentage.'));
        $this->addProperty('stroke-width', static fn (Style $s, string $v) => preg_match('/^(?:\d+\.?\d*|\.\d+)(?:px|%)?$/i', trim($v)) ? $s : false, $svgNote('In user units, px or a percentage.'));
        $this->addProperty('stop-color', static fn (Style $s, string $v, MediaContext $m) => false !== Value::color(trim($v), $m) || 'currentcolor' === strtolower(trim($v)) ? $s : false, $svgNote('The color of a <stop> of a gradient.'));
        $this->addProperty('stop-opacity', $fraction, $svgNote('The opacity of a <stop> of a gradient, a number or a percentage.'));
        // The geometry of the shapes of an <svg>, over their attributes
        $coordinate = static fn (Style $s, string $v) => preg_match('/^[+-]?(?:\d+\.?\d*|\.\d+)(?:e[+-]?\d+)?(?:%|px|em|rem|pt|pc|in|cm|mm)?$/i', trim($v)) ? $s : false;
        $this->addProperty('cx', $coordinate, $svgNote('The center of a <circle> or an <ellipse>, in user units, a unit or a percentage of the viewBox.'));
        $this->addProperty('cy', $coordinate, $svgNote('The center of a <circle> or an <ellipse>, in user units, a unit or a percentage of the viewBox.'));
        $this->addProperty('r', $coordinate, $svgNote('The radius of a <circle>.'));
        $this->addProperty('rx', static fn (Style $s, string $v) => 'auto' === strtolower(trim($v)) ? $s : $coordinate($s, $v), $svgNote('The radius of an <ellipse>, or of the corners of a <rect>.'));
        $this->addProperty('ry', static fn (Style $s, string $v) => 'auto' === strtolower(trim($v)) ? $s : $coordinate($s, $v), $svgNote('The radius of an <ellipse>, or of the corners of a <rect>.'));
        $this->addProperty('x', $coordinate, $svgNote('The corner of a <rect>, or where a <text> starts.'));
        $this->addProperty('y', $coordinate, $svgNote('The corner of a <rect>, or the baseline of a <text>.'));
        $this->addProperty('d', static fn (Style $s, string $v) => preg_match('/^(?:none|path\(\s*(["\'])[^"\']*\1\s*\))$/i', trim($v)) ? $s : false, $svgNote('The outline of a <path>: none or path() of its commands.'))
        ;
        $this->addProperty('text-anchor', static fn (Style $s, string $v) => \in_array(strtolower(trim($v)), ['start', 'middle', 'end'], true) ? $s : false, $svgNote('start, middle or end: where the text of a <text> is at its x.'));
        $svgLength = '(?:\\d+\\.?\\d*|\\.\\d+)(?:e[+-]?\\d+)?(?:%|px|em|rem|pt|pc|in|cm|mm)?';
        $this->addProperty('stroke-dasharray', static fn (Style $s, string $v) => 'none' === strtolower(trim($v)) || preg_match('/^'.$svgLength.'(?:(?:\\s*,\\s*|\\s+)'.$svgLength.')*$/i', trim($v)) ? $s : false, $svgNote('none, or lengths in user units, px or percentages, the dashes and the gaps between them along the stroke, repeated; an odd list is taken twice.'));
        $this->addProperty('stroke-dashoffset', static fn (Style $s, string $v) => preg_match('/^[+-]?'.$svgLength.'$/i', trim($v)) ? $s : false, $svgNote('Where the dashes start along the stroke, in user units, px or a percentage.'));
        $this->addProperty('stroke-linecap', static fn (Style $s, string $v) => \in_array(strtolower(trim($v)), ['butt', 'round', 'square'], true) ? $s : false, $svgNote('butt, round or square: the ends of open lines and dashes.'));
        $this->addProperty('stroke-linejoin', static fn (Style $s, string $v) => \in_array(strtolower(trim($v)), ['miter', 'miter-clip', 'round', 'bevel', 'arcs'], true) ? $s : false, $svgNote('miter, round or bevel: the corners of a stroke at least two pixels wide; miter-clip and arcs are drawn like miter.'));
        $this->addProperty('stroke-miterlimit', static fn (Style $s, string $v) => is_numeric(trim($v)) && (float) trim($v) >= 1 ? $s : false, $svgNote('A number of 1 or more: a miter longer than that many widths of the stroke is bevelled.'));
        $marker = static fn (Style $s, string $v) => 'none' === strtolower(trim($v)) || preg_match('/^url\\(\\s*([\'"]?)#[^\'")\\s]+\\1\\s*\\)$/i', trim($v)) ? $s : false;
        $markerNote = 'none, or the url() of a <marker> of the <svg>: its content is drawn at %s of a line, polyline, polygon or path (the ends of its commands), at its refX and refY, turned by its orient (auto, auto-start-reverse or an angle), markerWidth and markerHeight in widths of the stroke (or user units with markerUnits), its viewBox fit into them; not clipped to them.';
        $this->addProperty('marker-start', $marker, $svgNote(\sprintf($markerNote, 'the first vertex')));
        $this->addProperty('marker-mid', $marker, $svgNote(\sprintf($markerNote, 'every vertex but the first and the last')));
        $this->addProperty('marker-end', $marker, $svgNote(\sprintf($markerNote, 'the last vertex')));
        $this->addProperty('marker', $marker, $svgNote('The three markers, see marker-start, marker-mid and marker-end; the longhands an element sets win over it.'));
        $this->addProperty('paint-order', static fn (Style $s, string $v) => 'normal' === strtolower(trim($v)) || preg_match('/^(?:fill|stroke|markers)(?:\\s+(?:fill|stroke|markers)){0,2}$/i', trim($v)) ? $s : false, $svgNote('normal, or the order the fill, the stroke and the markers of a shape are drawn in, those left out after the others; text is drawn with its fill only.'));
        // Elsewhere than on an <svg>, like the url() of an icon of VitePress masking a box: nothing, a box being cells
        $this->addProperty('mask', static fn (Style $s, string $v) => '' !== trim($v) ? $s : false, Support::partial('On the shapes and groups of an <svg>, from its presentation attributes, the rules that match them and their style attribute: url() of a <mask>, whose content fades what they draw by its luminance (or its alpha, see mask-type), inside its region (x, y, width and height, -10% and 120% of the box of a shape by default, maskUnits), in their user space or in fractions of the box of a shape (maskContentUnits); a group takes the view box as region. Elsewhere, a box is a rectangle of cells.'));
        $this->addProperty('flood-color', static fn (Style $s, string $v, MediaContext $m) => false !== Value::color(trim($v), $m) || 'currentcolor' === strtolower(trim($v)) ? $s : false, $svgNote('The color of an feFlood or an feDropShadow of a <filter>.'));
        $this->addProperty('flood-opacity', $fraction, $svgNote('The opacity of an feFlood or an feDropShadow of a <filter>, a number or a percentage.'));
        $this->addProperty('mask-type', static fn (Style $s, string $v) => \in_array(strtolower(trim($v)), ['luminance', 'alpha'], true) ? $s : false, $svgNote('luminance or alpha: what of the content of a <mask> fades what it masks.'));
        $this->addProperty('clip-rule', static fn (Style $s, string $v) => \in_array(strtolower(trim($v)), ['nonzero', 'evenodd'], true) ? $s : false, $svgNote('nonzero or evenodd: the inside of the shapes of a <clipPath>.'));
        $this->addProperty('fill-rule', static fn (Style $s, string $v) => \in_array(strtolower(trim($v)), ['nonzero', 'evenodd'], true) ? $s : false, $svgNote('nonzero or evenodd.'));
        // The marks of text-emphasis, which the text blocks draw on rows of their own, like ruby annotations
        $emphasisStyle = static fn (string $v) => 'none' === strtolower(trim($v)) || (bool) preg_match('/^(["\']).*\1$/s', trim($v))
            || ([] !== ($words = Value::split(strtolower($v))) && \count($words) <= 2 && array_all($words, static fn (string $w) => \in_array($w, ['filled', 'open', 'dot', 'circle', 'double-circle', 'triangle', 'sesame'], true)) && \count(array_intersect($words, ['filled', 'open'])) <= 1);
        $emphasisNote = ' The marks are on a row above the line (under: below it), over each character but the spaces and the punctuation, like a ruby annotation; a line that cannot have rows above it, like a button, has none.';
        $this->addProperty('text-emphasis-style', static fn (Style $s, string $v) => $emphasisStyle($v) ? $s : false, Support::supported('none, filled or open dot (•◦), circle (●○, the default shape), double-circle (◉◎), triangle (▲△) and sesame (﹅﹆), or a string, its first character.'.$emphasisNote));
        $this->addProperty('text-emphasis-color', static fn (Style $s, string $v, MediaContext $m) => false !== Value::color(trim($v), $m) || 'currentcolor' === strtolower(trim($v)) ? $s : false, Support::supported('The color of the marks, that of the text by default.'));
        $this->addProperty('text-emphasis-position', static fn (Style $s, string $v) => array_all(Value::split(strtolower($v)), static fn (string $w) => \in_array($w, ['over', 'under', 'left', 'right', 'auto'], true)) ? $s : false, Support::partial('over or under the line; left and right, for vertical writing modes, are ignored.'));
        $this->addProperty('text-emphasis', static fn (Style $s, string $v) => '' !== trim($v) ? $s : false, Support::supported('The style and the color, see text-emphasis-style and text-emphasis-color.'));
        // text-shadow: the text again in the color of the shadow, under it, moved by whole cells, showing where it leaves blank
        $this->addProperty('text-shadow', static fn (Style $s, string $v) => '' !== trim($v) ? $s : false, Support::partial('Up to 4 shadows, the first one over the others, each moved by whole cells (up to 2 each way), in its color or the text dimmed, under the text and showing only around it, not through the spaces between its words; the blur is ignored and a shadow under the text, without an offset, is not drawn.'));
        $this->addProperty('column-fill', static fn (Style $s, string $v) => \in_array(strtolower(trim($v)), ['auto', 'balance', 'balance-all'], true) ? $s : false, Support::supported('balance (and balance-all) shares the content evenly between the columns; auto fills them one after the other up to the height of the container, taller when the content does not fit, balanced without a height or max-height, like browsers.'));
        $this->addProperty('column-span', static fn (Style $s, string $v) => match (strtolower(trim($v))) {
            'all' => $s->withSpanAll(),
            'none' => $s->withSpanAll(false),
            default => false,
        }, Support::supported('all: a child of the multi-column container spans its columns, the content before and after it in columns of their own.'));
        $any = static fn (Style $s, string $v) => '' !== trim($v) ? $s : false;
        $this->addProperty('animation', $any, Support::partial('Every animation of the list, up to 16, a later one winning on the properties both animate: name, duration, timing (see the easing functions), delay, iteration count, direction, fill mode and play state; drawn at about 30 frames a second, smooth ones in 32 steps.'));
        $this->addProperty('transition', $any, Support::partial('Each property with its duration, timing and delay, or all: colors and numbers go from the old value to the new one, also in lists and functions of the same shape (1ch 2ch, translate(), a shadow and its color, a gradient), lengths of different units through calc(); other values change at once, or halfway with allow-discrete, see transition-behavior.'));
        foreach (['transition-property', 'transition-duration', 'transition-delay', 'transition-timing-function'] as $name) {
            $this->addProperty($name, $any, Support::partial('See transition.'));
        }
        $this->addProperty('transition-behavior', static fn (Style $s, string $v) => \in_array(strtolower(trim($v)), ['normal', 'allow-discrete'], true) ? $s : false, Support::supported('allow-discrete: the values that cannot be interpolated change halfway, visibility staying visible while one end is; normal: they change at once.'));
        foreach (['animation-name', 'animation-duration', 'animation-delay', 'animation-iteration-count', 'animation-direction', 'animation-fill-mode', 'animation-timing-function', 'animation-play-state'] as $name) {
            $this->addProperty($name, $any, Support::partial('A list, one value per animation-name, repeated when shorter; see animation.'));
        }
        $this->addProperty('animation-timeline', $any, Support::partial('auto, none, scroll() (nearest, root or self; block, inline, y or x), view() (block, inline, y or x) or the name of a scroll-timeline or view-timeline: the animation goes with where its scroll container is scrolled, as it was laid out in the last frame, its duration and delay not used; view() and view timelines from where the element enters the scroll container to where it leaves it.'));
        $this->addProperty('animation-composition', static fn (Style $s, string $v) => array_all(Selector::splitTopLevel(strtolower($v)), static fn (string $part) => \in_array(trim($part), ['replace', 'add', 'accumulate'], true)) ? $s : false, Support::partial('A list, one value per animation-name: add and accumulate add the keyframes set to the element\'s value, numbers of the same unit and colors channel by channel, the keyframes missing being its value; other values are replaced.'));
        foreach (['animation-range', 'animation-range-start', 'animation-range-end'] as $name) {
            $this->addProperty($name, $any, Support::partial('normal, cover, contain, entry and exit, with percentages, on view timelines; on scroll timelines normal and percentages.'));
        }
        foreach (['scroll-timeline', 'scroll-timeline-name', 'scroll-timeline-axis', 'view-timeline', 'view-timeline-name', 'view-timeline-axis'] as $name) {
            $this->addProperty($name, $any, Support::partial('A named timeline of the scroll container, or of the element in its scroll container, which animation-timeline names in the element and its descendants; block, inline, y or x.'));
        }
        $this->addProperty('view-timeline-inset', $any, Support::partial('Lengths moving the edges of the scroll container in, for the view timeline; auto is 0.'));
        $this->addProperty('timeline-scope', $any, Support::partial('The names of timelines defined inside the element that its descendants see, like a progress bar beside the scroll container it follows.'));
        $this->addProperty('border-collapse', static fn (Style $s, string $v) => match (strtolower(trim($v))) {
            'collapse' => $s->withCollapse(),
            'separate' => $s->withCollapse(false),
            default => false,
        }, Support::partial('collapse draws a table and its cells as one grid of lines, with junctions, the table as wide as its columns, or as its width, the columns sharing the room left as they are wide; a line is drawn where the table, a row group, a row or a cell beside it has a border on that side, like the border-bottom of cells drawing the lines under rows only, in the style and color of the table, or of the first border; a table whose rows are displayed as blocks, like those of EasyAdmin on a narrow screen, lays them out like blocks.'));
        $this->addProperty('caption-side', static fn (Style $s, string $v) => match (strtolower(trim($v))) {
            'top', 'block-start' => $s->withCaptionBottom(false),
            'bottom', 'block-end' => $s->withCaptionBottom(),
            default => false,
        }, Support::supported('The caption above or below its table, collapsed or separate.'));
        $this->addProperty('border-spacing', static fn (Style $s, string $v, MediaContext $m) => array_all(Value::split($v), static fn (string $p) => null !== Length::toCells($p, $m)) && '' !== trim($v) ? $s : false, Support::partial('The first length sets how far apart the cells of a separate table are across, 0 making their borders touch, like the files of GitHub; without it they are a column apart, see the gap of tr in the user agent stylesheet. The second is accepted, the rows touching.'));
        $this->addProperty('empty-cells', static fn (Style $s, string $v) => match (strtolower(trim($v))) {
            'hide' => $s->withHideEmpty(),
            'show' => $s->withHideEmpty(false),
            default => false,
        }, Support::supported('hide: in a separate table, a cell without content draws no border nor background, keeping its room; inherited, so set on the table; collapsed tables draw every cell, like CSS.'));
        $this->addProperty('table-layout', static fn (Style $s, string $v) => match (strtolower(trim($v))) {
            'fixed' => $s->withFixedLayout(),
            'auto' => $s->withFixedLayout(false),
            default => false,
        }, Support::partial('fixed, on a table with a width (or a max-width, which width is here): the columns take the widths of the <col> elements, then of the cells of the first row, the others sharing what is left evenly; the rows after the first are not measured. Without a width, like auto.'));
        $this->addProperty('transform', static fn (Style $s, string $v, MediaContext $m) => 'none' === strtolower(trim($v)) || null !== self::transformFunctions($v, $m) ? $s : false, Support::partial('translate(), translateX(), translateY() and the x and y of translate3d() move the element like relative offsets, its room kept: lengths, and percentages of the element itself, like translate(-50%, -50%) to center it, mixed with lengths in calc() too; scale(), rotate(), skew(), matrix() and perspective() are accepted and change nothing, cells being neither scaled nor turned, like the scale(1.05) of a card.'));
        $this->addProperty('translate', static fn (Style $s, string $v, MediaContext $m) => 'none' === strtolower(trim($v)) || null !== self::translation('translate('.str_replace(' ', ', ', trim($v)).')', $m) ? $s : false, Support::partial('Lengths and percentages of the element itself, like transform: translate().'));
        $justify = self::justifyKeyword(...);
        $this->addProperty('justify-self', static fn (Style $s, string $v) => 'anchor-center' === strtolower(trim($v)) ? $s : (null !== ($j = $justify($v)) ? $s->withJustifySelf($j) : false), Support::partial('A grid item in its area along the row: start, center and end at its content width, stretch (the default) the whole area; ignored outside grids. anchor-center centers an element placed next to its anchor across it, like align-self down it.'));
        $this->addProperty('justify-items', static fn (Style $s, string $v) => null !== ($j = $justify($v)) ? $s->withJustifyItems($j) : false, Support::partial('The justify-self of the items of a grid.'));
        $this->addProperty('place-self', static function (Style $s, string $v) use ($justify) {
            $parts = Value::split($v);
            $align = match (self::position($parts[0] ?? '') ?? strtolower($parts[0] ?? '')) {
                'start' => VerticalAlign::Top,
                'center' => VerticalAlign::Center,
                'end' => VerticalAlign::Bottom,
                'stretch', 'normal', 'auto' => VerticalAlign::Stretch,
                default => null,
            };
            $j = $justify($parts[1] ?? $parts[0] ?? '');
            if (null === $align || null === $j || \count($parts) > 2) {
                return false;
            }

            return $s->withAlignSelf($align)->withJustifySelf($j);
        }, Support::partial('align-self, then justify-self (or both from one value).'));
        $this->addProperty('grid-template', static fn (Style $s, string $v) => null !== self::gridTemplateShorthand($v) ? $s : false, Support::partial('none, rows / columns, and areas: each row a string of area names, its height after it, then / and the columns; line names are kept.'));
        $this->addProperty('grid', static fn (Style $s, string $v) => null !== self::gridShorthand($v) ? $s : false, Support::supported('Like grid-template, or auto-flow [dense] [auto rows] / columns, or rows / auto-flow [dense] [auto columns], column after column.'));
        $this->addProperty('grid-auto-rows', static fn (Style $s, string $v, MediaContext $m) => 'none' !== strtolower(trim($v)) && null !== self::gridRows($v, $m) ? $s : false, Support::partial('The least height of the rows past grid-template-rows, its values taken in turn when it has several, like 1fr 3ch: lengths, percentages and minmax(); auto and fr are their content.'));
        $this->addProperty('grid-auto-flow', static fn (Style $s, string $v) => array_all(Value::split(strtolower($v)), static fn (string $p) => \in_array($p, ['row', 'column', 'dense'], true)) ? $s : false, Support::supported('row places the items row after row, column column after column in the explicit rows (one without), adding columns; dense fills the free cells left before an item.'));
        $this->addProperty('grid-auto-columns', static fn (Style $s, string $v, MediaContext $m) => null !== self::gridAutoColumns($v, $m) ? $s : false, Support::partial('The width of the columns past the explicit ones, its values taken in turn when it has several, like 10ch 20ch: lengths, percentages and minmax(); auto and fr are their content.'));
        // Applied by finalize(), with place-content
        $this->addProperty('align-content', static fn (Style $s, string $v) => null !== self::contentDistribution($v) ? $s : false, Support::partial('The lines of a wrapped row and the rows of a grid of a set height share its free rows: start, center, end, space-between, space-around, space-evenly and stretch (normal: stretch for lines, start for grid rows, whose auto rows stretch takes as even shares).'));
        $this->addProperty('all', static fn (Style $s, string $v) => \in_array(strtolower(trim($v)), ['initial', 'inherit', 'unset', 'revert', 'revert-layer'], true) ? $s : false, Support::supported('initial, inherit, unset, revert (to the user agent value) and revert-layer (like revert) for every property but the custom ones, direction and unicode-bidi.'));
        $this->addProperty('box-sizing', static fn (Style $s, string $v) => \in_array(strtolower(trim($v)), ['border-box', 'content-box'], true) ? $s : false, Support::partial('border-box, the widths and heights set including the padding and the border, is the initial value, like the common reset; content-box adds them to the widths and heights set.'));
        $this->addProperty('aspect-ratio', static fn (Style $s, string $v) => match (true) {
            'auto' === strtolower(trim($v)) => $s->withAspectRatio(null),
            null !== $ratio = self::ratio($v) => $s->withAspectRatio($ratio),
            default => false,
        }, Support::partial('A ratio (16 / 9) or a number: a box without a height is as tall as its width says, cells being twice as tall as wide, the percentage heights of its children of that height; an image keeps it too; auto is its content.'));
        $this->addProperty('object-fit', static fn (Style $s, string $v) => \in_array($fit = strtolower(trim($v)), ['fill', 'contain', 'cover', 'none', 'scale-down'], true) ? $s->withObjectFit($fit) : false, Support::partial('How an image fills its width and height: fill stretches it, contain fits it inside, cover fills it cut, none keeps its size cut, scale-down is the smaller of none and contain; placed by object-position.'));
        $this->addProperty('object-position', static fn (Style $s, string $v, MediaContext $m) => null !== ($position = self::objectPosition($v, $m)) ? $s->withObjectPosition($position) : false, Support::supported('Where an image is in its box when object-fit leaves room or cuts it: keywords, percentages and lengths, one or two, and offsets from an edge, like right 2ch bottom 1.'));
        $this->addProperty('float', static fn (Style $s, string $v, MediaContext $m) => match (strtolower(trim($v))) {
            'left', 'inline-start' => $s->withFloat('left'),
            'right', 'inline-end' => $s->withFloat('right'),
            'none' => $s->withFloat(null),
            default => false,
        }, Support::partial('In a block container: the element at its left or right, at its width or its content\'s, the text of the blocks beside it flows around it, under it once it ends, aligned at its start, the paragraphs in blocks in them too (not those in flex rows, grids or scroll containers, laid out narrower beside it while they start beside it, like other boxes).'));
        $this->addProperty('image-orientation', static fn (Style $s, string $v) => match (strtolower(trim($v))) {
            'from-image' => $s->withImageFromImage(true),
            'none' => $s->withImageFromImage(false),
            default => preg_match('/^(?:-?\d*\.?\d+(?:deg|grad|rad|turn)?(?:\s+flip)?|flip)$/i', trim($v)) ? $s->withImageFromImage(false) : false,
        }, Support::partial('from-image, the default, turns and flips a JPEG as the orientation of its EXIF data says, a photo taken sideways standing up; none draws it as stored; the deprecated angles and flip are taken as none.'));
        $this->addProperty('image-rendering', static fn (Style $s, string $v) => match (strtolower(trim($v))) {
            'pixelated', 'crisp-edges', '-moz-crisp-edges', '-webkit-optimize-contrast', 'optimizespeed' => $s->withPixelated(true),
            'auto', 'smooth', 'high-quality', 'optimizequality' => $s->withPixelated(false),
            default => false,
        }, Support::supported('pixelated and crisp-edges scale images to their cells keeping the nearest pixel, like pixel art, sharp; auto and smooth average the pixels each half cell covers; in <img>, background-image and list-style-image.'));
        $this->addProperty('shape-outside', fn (Style $s, string $v, MediaContext $m) => false !== ($shape = $this->shapeOutside($v, $m)) ? $s->withFloatShape($shape) : false, Support::partial('On a float, the text beside it wraps around its shape, row by row, the float itself drawn whole under the text that comes over it: circle(), ellipse(), inset() with round corners and polygon(), in the margin box or in the box named, like circle() border-box, the box alone too (its corners square); a url() image its pixels more opaque than shape-image-threshold, at its size from the top left of the box (one that cannot be read, or a gradient, gives the box); floats beside each other stay side by side by their boxes.'));
        $this->addProperty('shape-image-threshold', static fn (Style $s, string $v) => null !== ($n = Length::number(trim($v))) && is_finite($n) ? $s->withShapeImageThreshold($n) : (preg_match('/^(-?\d*\.?\d+)%$/', trim($v), $m) ? $s->withShapeImageThreshold((float) $m[1] / 100) : false), Support::supported('The opacity, from 0 to 1 or a percentage, past which the pixels of a shape-outside image are part of the shape.'));
        $this->addProperty('shape-margin', static fn (Style $s, string $v, MediaContext $m) => null !== ($columns = preg_match('/^-?(?:\d+\.?\d*|\.\d+)%$/', trim($v)) ? null : Length::toColumns($v, $m)) && $columns >= 0 ? $s->withShapeMargin($columns) : false, Support::partial('The room kept around the shape of a float, a length like 1em, not a percentage; within its margin box, which the shape is clipped to.'));
        $this->addProperty('clear', static fn (Style $s, string $v) => match (strtolower(trim($v))) {
            'left', 'inline-start' => $s->withClear('left'),
            'right', 'inline-end' => $s->withClear('right'),
            'both' => $s->withClear('both'),
            'none' => $s->withClear(null),
            default => false,
        }, Support::supported('The element goes below the floats before it on that side.'));
        $dashed = static fn (string $v): bool => 1 === preg_match('/^--[a-z_][\w-]*$/i', trim($v));
        $this->addProperty('anchor-name', static fn (Style $s, string $v) => match (true) {
            'none' === strtolower(trim($v)) => $s->withAnchorName(null),
            $dashed($first = trim(Selector::splitTopLevel($v)[0] ?? '')) => $s->withAnchorName($first),
            default => false,
        }, Support::partial('A dashed name absolute and fixed elements are placed next to, see position-anchor; the first name of a list.'));
        $this->addProperty('anchor-scope', static fn (Style $s, string $v) => match (true) {
            \in_array(strtolower(trim($v)), ['none', 'all'], true) => $s->withAnchorScope('none' === strtolower(trim($v)) ? [] : ['all']),
            [] !== ($names = array_map('trim', explode(',', $v))) && [] === array_filter($names, static fn (string $n) => !$dashed($n)) => $s->withAnchorScope($names),
            default => false,
        }, Support::supported('all or a list of dashed names: an absolute or fixed element outside the element does not see those names on it nor inside it, like the repeated cards of a list each anchoring their own tooltip.'));
        $this->addProperty('reading-flow', static fn (Style $s, string $v) => \in_array($v = strtolower(trim($v)), ['normal', 'flex-visual', 'flex-flow', 'grid-rows', 'grid-columns', 'grid-order', 'source-order'], true) ? $s->withReadingFlow('normal' === $v ? null : $v) : false, Support::supported('The order Tab and Shift+Tab follow through the children of the element, and what is inside them: flex-visual and grid-rows by rows of the last frame, then from the left, grid-columns by columns, flex-flow and grid-order by order then as written, source-order as written; positive tabindex values come first still.'));
        $this->addProperty('reading-order', static fn (Style $s, string $v) => null !== ($order = self::integer($v)) ? $s->withReadingOrder($order) : false, Support::supported('In a reading-flow container, the children of a smaller one come first.'));
        $this->addProperty('position-visibility', static fn (Style $s, string $v) => null !== self::positionVisibility($v) ? $s : false, Support::supported('always; anchors-visible, the initial value: an anchored element is not drawn while its anchor is not, like scrolled out of view or hidden; anchors-valid: nor when it has no anchor; no-overflow: nor when it does not fit on the screen, its fallbacks tried.'));
        $this->addProperty('position-anchor', static fn (Style $s, string $v) => 'auto' === strtolower(trim($v)) || $dashed($v) ? $s : false, Support::partial('The anchor-name an absolute or fixed element is placed next to, by position-area or anchor() in its insets.'));
        $this->addProperty('position-area', static fn (Style $s, string $v) => 'none' === strtolower(trim($v)) || null !== self::positionArea($v) ? $s : false, Support::partial('Next to the anchor: top, center or bottom, then left, center, right, span-left, span-right or span-all (one keyword is centered the other way), and the logical keywords (x-start, block-end, span-inline-start, start end, self-...), from the left and the top; the element is at its content width.'));
        $tries = static fn (Style $s, string $v) => 'none' === strtolower(trim($v)) || array_all(Selector::splitTopLevel(strtolower($v)), static fn (string $try) => null !== self::positionTry($try)) ? $s : false;
        $this->addProperty('position-try-fallbacks', $tries, Support::partial('flip-block and flip-inline: the other side of the anchor when the element would leave the screen; then the @position-try rules named and the position-area values listed, in order, the first that fits on the screen, their insets or position-area replacing the insets of the element (their insets, position-area, position-anchor, width and height; not their margins).'));
        $this->addProperty('position-try', static fn (Style $s, string $v) => $tries($s, self::positionTryOrder($v)[1]), Support::partial('An order, see position-try-order, then the fallbacks, see position-try-fallbacks.'));
        $this->addProperty('position-try-order', static fn (Style $s, string $v) => '' === self::positionTryOrder($v)[1] ? $s : false, Support::supported('most-width and most-height, and their logical names most-inline-size and most-block-size: the position-try-fallbacks are tried by the room they leave next to the anchor, its width or its height, the most first, the element\'s own place still before them.'));
        $overscroll = static fn (Style $s, string $v) => \in_array(strtolower(trim($v)), ['auto', 'contain', 'none'], true) ? $s : false;
        $this->addProperty('overscroll-behavior', static fn (Style $s, string $v) => \count($parts = Value::split(strtolower($v))) <= 2 && array_all($parts, static fn (string $p) => \in_array($p, ['auto', 'contain', 'none'], true)) ? $s : false, Support::supported('contain and none stop the wheel and the keys at a scroll container that cannot scroll further that way, instead of scrolling the ones around it; there is no overscroll effect to remove. One value, or the horizontal one then the vertical one.'));
        foreach (['overscroll-behavior-y', 'overscroll-behavior-block'] as $name) {
            $this->addProperty($name, $overscroll, Support::supported('contain and none stop the wheel and the page keys at the scroll container, instead of scrolling the ones around it.'));
        }
        foreach (['overscroll-behavior-x', 'overscroll-behavior-inline'] as $name) {
            $this->addProperty($name, $overscroll, Support::supported('contain and none stop the wheel and the arrow keys at the container scrolled sideways, instead of scrolling the ones around it.'));
        }
        $decorationBreak = static fn (Style $s, string $v) => match (strtolower(trim($v))) {
            'slice' => $s->withCloneDecoration(false),
            'clone' => $s->withCloneDecoration(),
            default => false,
        };
        $this->addProperty('box-decoration-break', $decorationBreak, Support::partial('On inline elements: slice, the default, draws the left padding and border on the first line of an element that wraps and the right ones on its last line; clone draws them on each of its lines. On blocks cut across the columns of a multi-column container: slice draws the top edge in the first piece and the bottom one in the last, clone draws both edges around each piece.'));
        $this->addProperty('-webkit-box-decoration-break', $decorationBreak, Support::partial('Like box-decoration-break.'));
        foreach (['orphans', 'widows'] as $property) {
            $this->addProperty($property, static fn (Style $s, string $v) => preg_match('/^\s*[1-9]\d*\s*$/', $v) ? $s : false, Support::supported('orphans' === $property ? 'The fewest lines of a text block left at the bottom of a column of a multi-column container when it is cut, 2 by default: otherwise the block starts the next column.' : 'The fewest lines of a text block at the top of the next column of a multi-column container when it is cut, 2 by default: otherwise lines go with them.'));
        }
        $this->addProperty('scroll-snap-type', static fn (Style $s, string $v) => null !== self::snapType($v) ? $s : false, Support::supported('A scroll container snaps down (y, block), sideways (x, inline) or both ways to its children with a scroll-snap-align once scrolled, by the wheel, the keys or scrollTo(): mandatory always, to the snap position nearest the one asked in the way it went, proximity when one is within a third of its size.'));
        $this->addProperty('scroll-snap-align', static fn (Style $s, string $v) => \count($parts = Value::split(strtolower($v))) <= 2 && array_all($parts, static fn (string $p) => \in_array($p, ['none', 'start', 'center', 'end'], true)) ? $s : false, Support::supported('The block value, then the inline one: start, center or end of the child at the same place of the scroll container, its scroll-padding and the scroll-margin of the child kept clear.'));
        $this->addProperty('scroll-snap-stop', static fn (Style $s, string $v) => \in_array(strtolower(trim($v)), ['normal', 'always'], true) ? $s : false, Support::supported('always: a scroll stops at the child rather than going past it.'));
        $this->addProperty('scroll-behavior', static fn (Style $s, string $v) => \in_array(strtolower(trim($v)), ['auto', 'smooth'], true) ? $s : false, Support::supported('smooth: the content of a scroll container glides to where it is scrolled, down or sideways, by the wheel, the keys, a target or the focus, in about 150 ms; at once with prefers-reduced-motion.'));
        $this->addProperty('scrollbar-gutter', static fn (Style $s, string $v) => \in_array(strtolower(trim($v)), ['auto', 'stable', 'stable both-edges'], true) ? $s : false, Support::partial('stable keeps the column of the scrollbar of a scroll container even when its content fits, so that nothing moves when it overflows; both-edges keeps no column on the left.'));
        foreach (['break-before', 'break-after', 'page-break-before', 'page-break-after'] as $property) {
            $this->addProperty($property, static fn (Style $s, string $v) => \in_array(strtolower(trim($v)), ['auto', 'avoid', 'avoid-page', 'avoid-column', 'page', 'column', 'always', 'left', 'right', 'recto', 'verso', 'all'], true) ? $s : false, Support::partial('column (and page, always, left, right) starts a column before or after the element in a multi-column container, within its column count; avoid keeps it in the column of its neighbour.'));
        }
        $scrollRoom = static fn (Style $s, string $v, MediaContext $m) => array_all(Value::split($v), static fn (string $p) => 'auto' === strtolower($p) || null !== Length::toCells($p, $m, true)) && \count(Value::split($v)) <= 4 ? $s : false;
        foreach (['', '-top', '-bottom', '-left', '-right', '-block', '-block-start', '-block-end', '-inline', '-inline-start', '-inline-end'] as $suffix) {
            $this->addProperty('scroll-padding'.$suffix, $scrollRoom, Support::supported('The rows and columns of a scroll container kept clear at its sides when a target (:target) or the focus is scrolled into view, like under a sticky header.'));
            $this->addProperty('scroll-margin'.$suffix, $scrollRoom, Support::supported('The rows and columns kept around an element when it is scrolled into view, as a target or as it takes the focus.'));
        }
        $this->addProperty('scrollbar-width', static fn (Style $s, string $v) => \in_array(strtolower(trim($v)), ['auto', 'thin', 'none'], true) ? $s : false, Support::supported('none hides the scrollbar, the content still scrolls; thin draws it with light lines.'));
        $this->addProperty('scrollbar-color', static fn (Style $s, string $v, MediaContext $m) => 'auto' === strtolower(trim($v)) || (2 === \count($parts = Value::split($v)) && array_all($parts, static fn (string $part) => Value::color($part, $m) instanceof Color)) ? $s : false, Support::supported('The thumb and the track colors.'));
        $outlineNote = Support::partial('On an element with a border, and no positive outline-offset, the border is drawn in the outline color, like a focus ring; otherwise a line is drawn around the box, over what is next to it, outline-offset cells further out (or in, when negative), a cell from three quarters of one, so that the 2px of a focus ring are none, cut by the screen: solid, dashed, dotted, double, thick ones heavy.');
        foreach (['outline', 'outline-color', 'outline-style', 'outline-width', 'outline-offset'] as $name) {
            $this->addProperty($name, static fn (Style $s) => $s, $outlineNote);
        }
        $this->addProperty('grid-template-columns', static fn (Style $s, string $v, MediaContext $m) => 'none' === strtolower(trim($v)) || self::isSubgrid($v) || null !== self::gridTemplate($v, $m) ? $s : false, Support::partial('Lengths, percentages of the width of the grid, also in calc(), min(), max() and clamp(), like minmax(min(20ch, 100%), 1fr), fr, auto, minmax() and repeat(), auto-fill and auto-fit, which drops the tracks no item is in, the others sharing their room; [line names] between the tracks, inside repeat() too, like repeat(auto-fill, [cell] 8ch), once per repetition; subgrid, the columns of the parent grid it spans, their widths and gap, which the content of its items, those of subgrids in it too, sizes, like rows of a table each a subgrid (not its line names, nor the margins, borders and padding of the subgrid at the edges of its tracks).'));
        $lines = static fn (Style $s, string $v) => null !== self::gridLines($v) ? $s : false;
        $line = static fn (Style $s, string $v) => preg_match('/^(?:auto|-?[1-9]\d*|span\s+[1-9]\d*)$/i', trim($v)) ? $s : false;
        $placementNote = Support::partial('Line numbers (negative ones count from the end), span N, auto and line names: name-start or name for a start, name-end or name for an end, the edges of a named area too; a name alone is both; name N (or N name) is the Nth line of the name, counted from the end when negative; span name (or span N name) reaches the Nth line of the name from the other line, auto without one.');
        $this->addProperty('grid-column', $lines, $placementNote);
        $this->addProperty('grid-row', $lines, $placementNote);
        foreach (['grid-column-start', 'grid-column-end', 'grid-row-start', 'grid-row-end'] as $name) {
            $this->addProperty($name, $line, $placementNote);
        }
        $this->addProperty('grid-area', static fn (Style $s, string $v) => preg_match('/^-?[a-z_][\w-]*$/i', trim($v)) || null !== self::gridArea($v) ? $s : false, Support::partial('An area name of grid-template-areas, or row-start / column-start / row-end / column-end lines.'));
        $this->addProperty('grid-template-areas', static fn (Style $s, string $v) => 'none' === strtolower(trim($v)) || null !== self::gridAreas($v) ? $s : false, Support::supported('Rectangular areas, . for empty cells.'));
        $this->addProperty('grid-template-rows', static fn (Style $s, string $v, MediaContext $m) => 'none' === strtolower(trim($v)) || self::isSubgrid($v) || null !== self::gridRows($v, $m) ? $s : false, Support::partial('Lengths and minmax() give the least height of a row, auto and fr its content; in a grid of a set height, the fr rows share its free rows as their fractions say; rows grow with their content, they are never cut; subgrid, the rows of the parent grid it spans and their gap, as tall as the content of its items and of the other items of those rows needs, like cards side by side whose titles, texts and links line up, its borders and padding above its first row and below its last (not its line names, nor its margins).'));
        $this->addProperty('order', static fn (Style $s, string $v) => null !== ($order = self::integer($v)) ? $s->withOrder($order) : false, Support::supported('Lays the widget out among its siblings, lowest first; the focus order follows the document, like in CSS.'));
        $this->addProperty('min-width', static function (Style $s, string $v, MediaContext $m) {
            if (self::isAnchorSize($v)) {
                return $s;
            }
            // max-content and fit-content: an item keeps the width of its content already, while its siblings give way
            if (\in_array(strtolower(trim($v)), ['auto', '0', 'min-content', 'max-content', 'fit-content'], true)) {
                return $s->withMinColumns(null)->withMinWidthPercent(null);
            }
            // A percentage of the container: the least width of a box sized by its content, like a table
            if (preg_match('/^(\d+(?:\.\d+)?)%$/', trim($v), $percent)) {
                return $s->withMinColumns(null)->withMinWidthPercent((float) $percent[1]);
            }
            $cells = Length::toCells($v, $m);

            return null !== $cells && $cells >= 0 ? $s->withMinColumns($cells)->withMinWidthPercent(null) : false;
        }, Support::partial('In columns: items sized by their content keep it while their siblings can give way, and narrower blocks are not narrowed below it; growing items ignore it, and nothing exceeds the room it gets. A percentage is the least width of a box sized by its content, like min-width: 100% stretching a table to the room; max-content and fit-content are like auto, an item keeping the width of its content while its siblings give way.'));
        $maxWidth = static function (Style $s, string $v, MediaContext $m) {
            if (self::isAnchorSize($v)) {
                // Sizes of the anchor, applied where an absolute or fixed element is drawn (see finalize())
                return $s;
            }
            if ('none' === $v || 'auto' === $v) {
                return $s->withMaxColumns(null)->withWidthPercent(null)->withContentWidth(false);
            }
            // The intrinsic sizes: as wide as the content (a line cannot overflow: max-content is fit-content), as
            // narrow as it can be, or the room it gets
            $keyword = strtolower(trim($v));
            if (\in_array($keyword, ['fit-content', 'max-content', '-moz-fit-content', '-webkit-fit-content', '-webkit-max-content', '-moz-max-content'], true)) {
                return $s->withMaxColumns(null)->withWidthPercent(null)->withContentWidth('fit-content');
            }
            if (\in_array($keyword, ['min-content', '-webkit-min-content', '-moz-min-content'], true)) {
                return $s->withMaxColumns(null)->withWidthPercent(null)->withContentWidth('min-content');
            }
            // fit-content(20ch): as wide as the content, up to that length
            if (preg_match('/^fit-content\((.+)\)$/', $keyword, $fit)) {
                return null !== ($cells = Length::toCells($fit[1], $m)) && $cells >= 0 ? $s->withWidthPercent(null)->withContentWidth('fit-content')->withMaxColumns(max(1, $cells)) : false;
            }
            if (\in_array($keyword, ['stretch', '-webkit-fill-available', '-moz-available'], true)) {
                return $s->withMaxColumns(null)->withWidthPercent(null)->withContentWidth(false);
            }
            // min(), max() and clamp() of a percentage and lengths, like min(100%, 60ch): the percentage within them
            if (null !== $bounded = self::boundedPercent($v, $m)) {
                [$linear, $min, $max] = $bounded;

                return $s->withWidthPercent((int) round($linear[0]), (int) round($linear[1]))->withMaxColumns($max)->withMinColumns($min)->withContentWidth(false);
            }
            // A percentage, or a size mixing it with lengths like calc(100% - 20ch)
            if (null !== $linear = Length::linear($v, $m)) {
                if (0.0 === (float) $linear[0] && $linear[1] >= 0) {
                    // None of it, like the width: 0% of GitHub: its length alone, like width: 0
                    return $s->withMaxColumns(max(1, (int) round($linear[1])));
                }

                return $linear[0] > 0 ? $s->withWidthPercent((int) round($linear[0]), (int) round($linear[1])) : false;
            }
            $cells = Length::toCells($v, $m);

            return null === $cells ? false : $s->withMaxColumns(max(1, $cells));
        };
        $this->addProperty('max-width', static function (Style $s, string $v, MediaContext $m) use ($maxWidth) {
            // All of the container or more, like the max-width: 100% of the images of a page: no bound, a box never
            // being wider than its container; its width stays
            if (null !== ($linear = Length::linear($v, $m)) && $linear[0] >= 100 && $linear[1] >= 0) {
                return $s;
            }
            // Less: a bound, a box sized by its content, like a bubble of max-width: 75% in a row, staying narrower
            $bounded = $maxWidth($s, $v, $m);

            return $bounded instanceof Style && null !== $linear ? $bounded->withWidthPercentMax() : $bounded;
        }, Support::supported('Lengths and percentages of the container, and calc() mixing them, like calc(100% - 20ch); 100% or more bounds nothing, a box never being wider than its container; less bounds a box sized by its content, like an item of a row, as wide as its content up to it.'));
        $this->addProperty('width', $maxWidth, Support::partial('A percentage takes that share of a row before flexible siblings, a growing item growing from it, its basis, like the width: 1% of the field of an input group of Bootstrap; inside a box sized by its content, a percentage is auto, like CSS; an absolute or fixed element takes it of its containing block; a flex item of a row that grows or has a basis takes the share of the row past its width, like the w-0 flex-1 of Tailwind; elsewhere width acts as max-width: widgets shrink to the room they get. fit-content and max-content make the box as wide as its content, fit-content(20ch) up to that length, min-content as narrow as it can be without overflowing, stretch (and -webkit-fill-available) take the room, like auto. On a <rect> of an <svg>, its width in user units, a unit or a percentage of the viewBox.'));
        $rows = static function (Style $s, string $v, MediaContext $m, bool $min, bool $max) {
            if (self::isAnchorSize($v)) {
                return $s;
            }
            // calc-size(): its basis, the size it is computed from; transitions compute it, see CssStyleSheet
            if (preg_match('/^calc-size\(\s*([^,()]+(?:\([^()]*\))?)\s*(?:,.*)?\)$/is', trim($v), $basis)) {
                $v = \in_array($basis = strtolower(trim($basis[1])), ['auto', 'any', 'fit-content', 'min-content', 'max-content', 'stretch'], true) ? 'auto' : $basis;
            }
            // The intrinsic heights are the height of the content, like auto, a block being as tall as its content
            if (\in_array(strtolower(trim($v)), ['fit-content', 'max-content', 'min-content'], true)) {
                $v = 'auto';
            }
            if ('auto' === $v || 'none' === $v) {
                return $s->withRows($min ? null : $s->getMinRows(), $max ? null : $s->getMaxRows())
                    ->withRowsPercent($min ? null : $s->getMinRowsPercent(), $max ? null : $s->getMaxRowsPercent(), $min ? 0 : $s->getRowsPercentOffsets()[0], $max ? 0 : $s->getRowsPercentOffsets()[1]);
            }
            if (preg_match('/^(\d+(?:\.\d+)?)%$/', trim($v), $p)) {
                $percent = (int) round((float) $p[1]);

                return $percent > 100 ? false : $s->withRowsPercent($min ? $percent : $s->getMinRowsPercent(), $max ? $percent : $s->getMaxRowsPercent(), $min ? 0 : $s->getRowsPercentOffsets()[0], $max ? 0 : $s->getRowsPercentOffsets()[1]);
            }
            // A percentage and rows, like calc(100% - 2rem): the rows added to the share of the parent
            if (null !== ($linear = Length::linear($v, $m, true)) && $linear[0] > 0 && $linear[0] <= 100) {
                $percent = (int) round($linear[0]);
                $offset = (int) round($linear[1]);
                [$minOffset, $maxOffset] = $s->getRowsPercentOffsets();

                return $s->withRowsPercent($min ? $percent : $s->getMinRowsPercent(), $max ? $percent : $s->getMaxRowsPercent(), $min ? $offset : $minOffset, $max ? $offset : $maxOffset);
            }
            // A least height alone, like the min-height: 1.5rem of a check of Bootstrap, from three quarters of a row
            $cells = $min && !$max && !is_numeric(trim($v)) ? Length::toWholeCells($v, $m, true) : Length::toCells($v, $m, true);
            if (null === $cells) {
                return false;
            }

            return $s->withRows($min ? max(0, $cells) : $s->getMinRows(), $max ? max(0, $cells) : $s->getMaxRows());
        };
        $this->addProperty('height', static fn (Style $s, string $v, MediaContext $m) => $rows($s, $v, $m, true, true), Support::partial('fit-content, max-content and min-content are the height of the content, like auto. In rows: the content is padded or clipped to it, a border-box height keeping a row for it when CSS leaves room for a line inside its border, like a field of 30px with a border of 1px; a percentage and a length, like calc(100% - 2rem), the length added to the share; a percentage of a parent of definite height (the root, or a fixed or percentage height) counts the border and padding, elsewhere it is ignored. On a <rect> of an <svg>, its height in user units, a unit or a percentage of the viewBox.'));
        $this->addProperty('min-height', static fn (Style $s, string $v, MediaContext $m) => $rows($s, $v, $m, true, false), Support::partial('In rows, or a percentage like height, with a length too, like calc(50% + 1rem); a length is a row from three quarters of one, like padding, so that the min-height: 1.5rem of a line of a check of Bootstrap is one row.'));
        $this->addProperty('max-height', static fn (Style $s, string $v, MediaContext $m) => $rows($s, $v, $m, false, true), Support::partial('In rows, the rest is clipped, or a percentage like height.'));
        // Logical sizes are their physical ones in a horizontal writing mode (see map())
        foreach (self::LOGICAL_SIZES as $logical => $physical) {
            $this->properties[$logical] = [$this->properties[$physical][0], Support::partial(\sprintf('Like %s in a horizontal writing mode, like %s in a vertical one.', $physical, self::VERTICAL_SIZES[$logical]))];
        }
        $this->addProperty('font-size', static fn (Style $s, string $v) => null !== ($scale = self::fontScale($v)) ? $s->withFontScale($scale > 1 ? $scale : null) : false, Support::partial('Where the terminal draws text larger with the text sizing protocol of kitty (OSC 66), which the application tells with MediaContext::withTextSizing(): the text of a text block 2, 3 or 4 times as large, a size rounded to a whole number of times 16px (2em, 32px, xx-large, 200%), at most 4, each of its lines as many rows tall, the room of its line divided by it; the links of its text are followed by a click on the cells they take, and an element drawn over it hides it. Elsewhere, terminals have one font size.'));
        $this->addProperty('writing-mode', static fn (Style $s, string $v) => null !== ($mode = self::writingMode($v)) ? $s->withWritingMode('horizontal-tb' === $mode ? null : $mode) : false, Support::partial('vertical-rl and vertical-lr turn the text of a text block: its lines are columns, from the right (or from the left), one character a row, as many rows as its height or the room; its logical sides and sizes are physical ones, the inline start at the top; sideways-rl and sideways-lr like vertical ones, the characters upright, cells not turning; the blocks of a block written vertically are its columns, from the right (or from the left), each as wide as its own, the block as wide as them unless its width is set; the row of a flex container runs down and its column from the right, like the axes of the text; the columns of a grid run down and its rows from the right (or from the left): grid-template-columns, grid-auto-columns, grid-column and column-gap are along the height, a length of a column in rows as many as its cells (8ch is 8 rows, 8 characters), and the rows, their areas, gaps, flow and alignments are turned likewise, the grid as wide as its rows unless its width is set; the text of an item in a column of a set length, or in an fr one of a grid of a set height, has lines as long as that column, in an auto one as long as the height of the grid or the screen; a percentage column is sized by its content, auto-fill and auto-fit columns repeat once (rows fill a set width, else repeat once), and direction: rtl does not turn the columns of a grid upward; its links are followed by a click where they are drawn, down their column.'));
        $this->addProperty('text-orientation', static fn (Style $s, string $v) => \in_array(strtolower(trim($v)), ['mixed', 'upright', 'sideways', 'sideways-right', 'use-glyph-orientation'], true) ? $s : false, Support::partial('Every character is upright in a vertical writing mode, one a row: a cell does not turn.'));
        $this->addProperty('direction', static fn (Style $s, string $v) => \in_array(strtolower(trim($v)), ['ltr', 'rtl'], true) ? $s : false, Support::partial('rtl: logical sides (inline start and end) are the right and left ones, rows and grids run from the right, text and blocks start on the right; the lines of text are drawn in visual order by the Unicode Bidirectional Algorithm (UAX #9), with ext-intl or symfony/polyfill-intl-char, otherwise as stored, the terminal reordering them or not, and as stored in the terminals that reorder them themselves (Terminal.app, Konsole, mlterm); the text of inputs and textareas, each of its lines in the direction of its first strong character, the cursor with the character it is on.'));
        $this->addProperty('unicode-bidi', static fn (Style $s, string $v) => \in_array(strtolower(trim($v)), ['normal', 'embed', 'isolate', 'bidi-override', 'isolate-override', 'plaintext'], true) ? $s : false, Support::partial('embed, isolate, bidi-override, isolate-override and plaintext set an inline element apart for the bidirectional algorithm, in its direction, like the controls of Unicode around it; plaintext on a text block takes its direction from its first strong character; with ext-intl or symfony/polyfill-intl-char.'));
        $this->addProperty('text-align', static fn (Style $s, string $v) => match ($v) {
            'left', 'start', 'match-parent', '-webkit-left', '-moz-left', '-webkit-match-parent' => $s->withTextAlign(TextAlign::Left),
            'justify' => $s->withTextAlign(TextAlign::Justify),
            'center', '-webkit-center', '-moz-center' => $s->withTextAlign(TextAlign::Center),
            'right', 'end', '-webkit-right', '-moz-right' => $s->withTextAlign(TextAlign::Right),
            default => false,
        }, Support::partial('Each line of a text block is aligned, justify stretching the spaces of all but the last line of a paragraph; other widgets move their content as one block; match-parent takes the alignment of the parent, its start and end on the sides of its direction.'));
        $this->addProperty('caret-shape', static fn (Style $s, string $v) => null !== ($shape = self::caretShape($v)) ? $s->withCursorShape($shape) : false);
        // Whether the caret blinks, applied with its shape (see finalize())
        $this->addProperty('caret-animation', static fn (Style $s, string $v) => \in_array(strtolower(trim($v)), ['auto', 'manual'], true) ? $s : false, Support::supported('manual: the caret of the terminal does not blink; auto, the default, lets it blink.'));
        $this->addProperty('caret-color', static fn (Style $s, string $v, MediaContext $m) => 'auto' === strtolower(trim($v)) ? $s->withCaretColor(null) : (false !== ($color = Value::color($v, $m)) ? $s->withCaretColor($color) : false), Support::partial('The terminal cursor takes the color while a field has the focus (OSC 12), given back to the terminal (OSC 112) otherwise and on exit; auto, the default, and transparent keep the color of the terminal cursor; the 16 ANSI colors are sent in their xterm shades.'));
        $this->addProperty('caret', static function (Style $s, string $v, MediaContext $m) {
            foreach (Value::split(strtolower(trim($v))) as $part) {
                if (null !== $shape = 'auto' === $part ? null : self::caretShape($part)) {
                    $s = $s->withCursorShape($shape);
                } elseif ('auto' !== $part && 'manual' !== $part) {
                    if (false === $color = Value::color($part, $m)) {
                        return false;
                    }
                    $s = $s->withCaretColor($color);
                }
            }

            return $s;
        }, Support::supported('Its color, shape and animation, see caret-color, caret-shape and caret-animation.'));
    }

    /**
     * @param array<string, string> $declarations
     */
    private function finalize(Style $style, array $declarations, MediaContext $media, ?Color $backdrop = null, bool $rtl = false): Style
    {
        // A negative margin of less than a cell on a side with a border, like the margin-left: -1px of the buttons of a
        // group or the links of a pagination: a cell, the border over the one of the box before it, which it joins
        if (null !== ($hairline = $style->getMarginHairline()) && null !== $border = $style->getBorder()) {
            $pull = $style->getMarginPull();
            $sides = [$pull->top ?? 0, $pull->right ?? 0, $pull->bottom ?? 0, $pull->left ?? 0];
            foreach ([$border->top, $border->right, $border->bottom, $border->left] as $side => $width) {
                if ($hairline[$side] && $width > 0) {
                    $sides[$side] = max($sides[$side], 1);
                }
            }
            $style = $style->withMarginPull(new Padding(...$sides));
        }
        // A border image with translucent colors, over what is behind the box, like a background
        if (!$media->forcedColors && ($borderImage = $style->getBorderImage()) instanceof Gradient && $borderImage->isTranslucent()) {
            $style = $style->withBorderImage($borderImage->withBackdrop($backdrop ?? Color::hex(ColorScheme::Light === $media->colorScheme ? '#ffffff' : '#000000')));
        }
        // Several background images, the first one over the others: each with its layout, over the background color,
        // else what is behind the box
        // A list of several, though some cannot be drawn, like a missing image over a gradient: those that can
        if (!$media->forcedColors && [] !== $layers = $this->backgroundLayers($declarations, $media)) {
            $style = $style->withBackgroundLayers($layers, $style->getBackground() ?? $backdrop ?? Color::hex(ColorScheme::Light === $media->colorScheme ? '#ffffff' : '#000000'), null !== $style->getBackground());
        }
        // A gradient is drawn over the background color: without one, its first color is the background, which is
        // also what is left without colors; not under several layers, where none of them is
        if (null !== ($gradient = $style->getGradient()) && null === $style->getBackground() && null === $style->getBackgroundLayers()) {
            // A translucent one over what is behind the box, which shows where it is transparent
            if ($gradient->isTranslucent() && !$media->forcedColors) {
                $gradient = $gradient->withBackdrop($backdrop ?? Color::hex(ColorScheme::Light === $media->colorScheme ? '#ffffff' : '#000000'));
                $style = $style->withGradient($gradient);
            }
            $style = $style->withBackground($gradient->at(0.0));
        }
        if (null !== $layout = self::backgroundLayout($declarations, $media)) {
            $style = $style->withBackgroundLayout($layout);
        }
        // A transparent border, like the border-color: transparent of a button that keeps the room of the border of
        // the others: spaces, in the background
        // Some sides only, like the border-bottom: 2px solid transparent of the links of the .nav-underline of
        // Bootstrap, the active one given a color
        if (null !== ($border = $style->getBorder()) && [] !== $sides = array_keys(array_filter(self::transparentBorderSides($declarations, $media)))) {
            $blank = new BorderPattern([[' ', ' ', ' '], [' ', ' ', ' '], [' ', ' ', ' ']]);
            $border = 4 === \count($sides) ? $border->withPattern($blank)->withColor(null) : $border->withSidePatterns(array_fill_keys($sides, $blank));
            // All around an inline block, like the counter of a button of GitHub, a pill given a border in high contrast
            // only: no rows above and below, which the line would not hold, a pixel or two in browsers; not a box out of
            // the lines, absolute or floating, a block, like the toggler of the menu of Kubernetes
            if (4 === \count($sides) && preg_match('/^\s*inline-block\s*$/i', $declarations['display'] ?? '')
                && !preg_match('/^\s*(?:absolute|fixed)\b/i', $declarations['position'] ?? '') && !preg_match('/^\s*(?:left|right|inline-start|inline-end)\b/i', $declarations['float'] ?? '')) {
                $border = $border->withWidths(0, $border->right, 0, $border->left);
            }
            $style = $style->withBorder($border);
        }
        // caret-animation: manual, or manual in the caret shorthand, whichever comes last: a caret that does not blink
        $caretOrder = array_flip(array_keys($declarations));
        $manual = isset($declarations['caret-animation']) && (!isset($declarations['caret']) || $caretOrder['caret-animation'] > $caretOrder['caret'])
            ? 'manual' === strtolower(trim($declarations['caret-animation']))
            : \in_array('manual', Value::split(strtolower($declarations['caret'] ?? '')), true);
        if ($manual) {
            $style = $style->withCursorShape(($style->getCursorShape() ?? CursorShape::Block)->steady());
        }
        // An outline on a border, with no offset out, repaints it, like a focus ring; else it is drawn around the box
        // A side whose width comes last as 0 has none, whatever style comes after it, like the border-width: 0 then
        // border-style: solid of the preflight of Tailwind: the style alone gives the medium width only without one
        if (null !== ($border = $style->getBorder()) && [] !== $zero = self::zeroBorderSides($declarations, $media)) {
            $widths = [$border->top, $border->right, $border->bottom, $border->left];
            foreach ($zero as $side) {
                $widths[$side] = 0;
            }
            $style = $style->withBorder($border->withWidths(...$widths));
        }
        if (null !== $outline = self::outline($declarations, $media)) {
            $border = $style->getBorder();
            if (null !== $border && $border->top + $border->right + $border->bottom + $border->left > 0 && $outline->offset <= 0) {
                if (null !== $outline->color) {
                    $style = $style->withBorderColor($outline->color);
                }
            } else {
                $style = $style->withOutline($outline);
            }
        }

        if (isset($declarations['box-shadow']) && !$media->forcedColors) {
            // An inset shadow over the whole box, like the stripes of a table of Bootstrap: a background over its own
            if (false !== $fill = self::insetFill($declarations['box-shadow'], $media, $style->getBackground() ?? $backdrop)) {
                $style = null !== $fill ? $style->withBackground($fill) : $style;
            }
            $style = $style->withShadow(self::shadow($declarations['box-shadow'], $media, $backdrop))
                ->withInsetShadow(self::shadow($declarations['box-shadow'], $media, $backdrop, true));
            // A shadow spread around the box, not moved nor blurred, like a focus ring: a line hugging it, unless an
            // outline is drawn
            if (null === $style->getOutline() && null !== $ring = self::ring($declarations['box-shadow'], $media, $backdrop)) {
                $style = $style->withOutline($ring);
            }
        }
        // filter: drop-shadow(), the shadow of the box of cells when box-shadow sets none
        if (null === $style->getShadow() && !$media->forcedColors && isset($declarations['filter']) && '' !== $drop = self::filterParts(strtolower($declarations['filter']))[1]) {
            $style = $style->withShadow(self::shadow($drop, $media, $backdrop));
        }

        // vertical-align places the content of a table cell in the height of its row
        if (TableRole::Cell === $style->getTableRole() && isset($declarations['vertical-align'])) {
            $style = $style->withVerticalAlign(match (strtolower(trim($declarations['vertical-align']))) {
                'middle' => VerticalAlign::Center,
                'bottom' => VerticalAlign::Bottom,
                default => VerticalAlign::Top,
            });
        }

        // Translucent colors are blended over what is behind: the background around the element, then its own for its text
        if (!$media->forcedColors) {
            $background = null;
            foreach (isset($declarations['background-color']) ? [$declarations['background-color']] : Value::split($declarations['background'] ?? '') as $part) {
                if (false !== Value::color($part, $media)) {
                    $background = $part;
                    break;
                }
            }
            if (null !== $background && null !== $backdrop && Value::alpha($background, $media) < 1.0) {
                $style = $style->withBackground(Value::color($background, $media, $backdrop) ?: null);
            }
            // As declared too, for what lays it over the page
            if (null !== $background && ($alpha = Value::alpha($background, $media)) > 0.0 && $alpha < 1.0 && ($color = Value::opaqueColor($background, $media)) instanceof Color) {
                $style = $style->withTranslucentBackground([$color, max(1, min(99, (int) round($alpha * 100)))]);
            }
            $behind = $style->getBackground() ?? $backdrop;
            if (null !== $behind && isset($declarations['color']) && Value::alpha($declarations['color'], $media) < 1.0) {
                $color = Value::color($declarations['color'], $media, $behind);
                $style = $style->withColor($color instanceof Color ? $color : $behind);
            }

            // opacity fades the element's own colors into what is behind it
            $opacity = trim($declarations['opacity'] ?? '1');
            $opacity = is_numeric(rtrim($opacity, '%')) ? max(0.0, min(1.0, (float) rtrim($opacity, '%') / (str_ends_with($opacity, '%') ? 100 : 1))) : 1.0;
            if ($opacity < 1.0) {
                $under = $backdrop ?? Color::hex(ColorScheme::Light === $media->colorScheme ? '#ffffff' : '#000000');
                // The 16 ANSI colors keep the terminal theme's shade: only dim shows their opacity
                if (null !== ($background = $style->getBackground()) && !$background->isThemed()) {
                    $style = $style->withBackground($under->mix($background, (int) round($opacity * 100)));
                }
                if (null !== ($color = $style->getColor()) && !$color->isThemed()) {
                    $style = $style->withColor(($style->getBackground() ?? $under)->mix($color, (int) round($opacity * 100)));
                }
            }

            // mix-blend-mode: the colors of the element and of what it contains blend with what is behind it, see Renderer
            if (null !== ($mode = BlendMode::fromKeyword($declarations['mix-blend-mode'] ?? 'normal', plus: true)) && BlendMode::Normal !== $mode) {
                $style = $style->withBlend($mode, $backdrop);
            }
        }

        // Without colors, borders are drawn in the default color like text
        if ($media->forcedColors && null !== ($border = $style->getBorder()) && (null !== $border->color || [null, null, null, null] !== $border->sideColors)) {
            $style = $style->withBorderColor(null);
        }

        // Thick solid sides draw heavy lines
        if (null !== $border = $style->getBorder()) {
            $thick = [];
            foreach ($declarations as $name => $value) {
                if (str_starts_with($name, 'border') && !str_contains($name, 'color') && !str_contains($name, 'radius') && \in_array('thick', $parts = Value::split(strtolower($value)), true)) {
                    $thick += array_fill_keys(self::thickSides($name, $parts), true);
                }
            }
            $style = $style->withBorder(self::solidSidesAs($border, array_keys($thick), BorderPattern::HEAVY));
        }

        // The corners with a radius: rounded, or shaped by corner-shape
        if (null !== ($border = $style->getBorder()) && null !== $corners = self::cornerShapes($declarations, $media)) {
            $style = $style->withBorder(['round', 'round', 'round', 'round'] === $corners ? self::solidSidesAs($border, [0, 1, 2, 3], BorderPattern::ROUNDED) : $border->withCornerShapes($corners));
        }

        $display = $declarations['display'] ?? null;
        if (null === $style->getDirection() && ('flex' === $display || 'inline-flex' === $display) && !isset($declarations['flex-direction']) && !isset($declarations['flex-flow'])) {
            $style = $style->withDirection(Direction::Horizontal);
        }
        // A flex container written vertically: its row runs down, its column across, from the right for vertical-rl
        $writing = self::writingMode($declarations['writing-mode'] ?? '');
        if (null !== $writing && 'horizontal-tb' !== $writing && \in_array(strtolower(trim((string) $display)), ['flex', 'inline-flex'], true)) {
            $fromRight = !\in_array($writing, ['vertical-lr', 'sideways-lr'], true);
            $style = Direction::Horizontal === $style->getDirection()
                ? $style->withDirection(Direction::Vertical)
                : $style->withDirection(Direction::Horizontal)->withReverseOrder($fromRight xor ($style->getReverseOrder() ?? false));
            if ($fromRight && null === $style->getAlign()) {
                $style = $style->withAlign(Align::Right);
            }
            if (!isset($declarations['width']) && null === $style->getContentWidth()) {
                $style = $style->withContentWidth('fit-content');
            }
        }
        // A block written vertically lays its blocks out as columns, from the right for vertical-rl, each turned itself
        if (null === $style->getDirection() && null !== $writing && 'horizontal-tb' !== $writing && \in_array(strtolower(trim((string) $display)), ['', 'block', 'flow-root', 'list-item'], true)) {
            $fromRight = !\in_array($writing, ['vertical-lr', 'sideways-lr'], true);
            $style = $style->withDirection(Direction::Horizontal)->withReverseOrder($fromRight);
            if ($fromRight && null === $style->getAlign()) {
                $style = $style->withAlign(Align::Right);
            }
            // Its width is its block size: as wide as its columns, unless set
            if (!isset($declarations['width']) && null === $style->getContentWidth()) {
                $style = $style->withContentWidth('fit-content');
            }
        }
        $horizontal = Direction::Horizontal === $style->getDirection();

        // An item of a grid: where it goes, from grid-area, then grid-row and grid-column, then their longhands
        $mode = strtolower(trim($declarations['position'] ?? ''));
        if ('sticky' === $mode && null !== $top = Length::toCells($declarations['top'] ?? 'auto', $media, vertical: true)) {
            $style = $style->withStickyTop($top);
        }
        if ('sticky' === $mode && null !== $bottom = Length::toCells($declarations['bottom'] ?? 'auto', $media, vertical: true)) {
            $style = $style->withStickyBottom($bottom);
        }
        // Sticky with no inset, which nothing sticks: in place, like a relative one, the containing block of the absolute
        // elements in it, like CSS
        if ('sticky' === $mode && !array_any(['top', 'right', 'bottom', 'left', 'inset', 'inset-block', 'inset-inline', 'inset-block-start', 'inset-block-end', 'inset-inline-start', 'inset-inline-end'], static fn (string $property) => isset($declarations[$property]))) {
            $mode = 'relative';
        }
        if (null !== $positionMode = PositionMode::tryFrom($mode)) {
            $insets = Value::split($declarations['inset'] ?? '');
            $insets = match (\count($insets)) {
                1 => [$insets[0], $insets[0], $insets[0], $insets[0]],
                2 => [$insets[0], $insets[1], $insets[0], $insets[1]],
                3 => [$insets[0], $insets[1], $insets[2], $insets[1]],
                4 => $insets,
                default => ['auto', 'auto', 'auto', 'auto'],
            };
            $cells = [];
            $percents = [];
            foreach (['top', 'right', 'bottom', 'left'] as $index => $side) {
                $value = trim($declarations[$side] ?? $insets[$index]);
                if (null !== $percent = self::insetPercent($value, $media)) {
                    $percents[$side] = $percent;
                    $cells[] = null;
                } else {
                    // A cell from three quarters of one: the top: .5rem of a badge is at the top
                    $cells[] = Length::toWholeCells($value, $media, 0 === $index % 2);
                }
            }
            $zIndex = self::integer($declarations['z-index'] ?? '') ?? 0;
            // Anchor positioning: anchor() in the insets, and position-area next to position-anchor
            $edges = [];
            foreach (['top', 'right', 'bottom', 'left'] as $index => $side) {
                if (null !== $edge = self::anchorEdge($declarations[$side] ?? $insets[$index])) {
                    $edges[$side] = $edge;
                }
            }
            $anchorName = trim($declarations['position-anchor'] ?? '');
            [$tryOrder, $shorthandFallbacks] = self::positionTryOrder($declarations['position-try'] ?? '');
            $tryOrder = self::positionTryOrder($declarations['position-try-order'] ?? '')[0] ?? $tryOrder ?? 'normal';
            $fallbacks = strtolower(trim($declarations['position-try-fallbacks'] ?? $shorthandFallbacks));
            $flips = [];
            $alternatives = [];
            foreach ('' === $fallbacks || 'none' === $fallbacks ? [] : Selector::splitTopLevel($fallbacks) as $fallback) {
                if (null === $try = self::positionTry($fallback)) {
                    continue;
                }
                [$name, $tactics, $area] = $try;
                if (null === $name && null === $area) {
                    array_push($flips, ...$tactics);
                    continue;
                }
                $tryDeclarations = null !== $name ? self::physicalInsets($this->positionTries[$name] ?? []) : ['position-area' => (string) $area];
                if (null !== $name && !isset($this->positionTries[$name])) {
                    continue;
                }
                // The insets of a try replace those of the element, and so does its position-area, which the insets would move
                $base = array_any(['inset', 'top', 'right', 'bottom', 'left', 'position-area'], static fn (string $side) => isset($tryDeclarations[$side]))
                    ? array_diff_key($declarations, array_flip(['inset', 'top', 'right', 'bottom', 'left']))
                    : $declarations;
                $merged = $tryDeclarations + $base;
                $tryEdges = [];
                foreach (['top', 'right', 'bottom', 'left'] as $side) {
                    if (null !== $edge = self::anchorEdge($merged[$side] ?? '')) {
                        $tryEdges[$side] = $edge;
                    }
                }
                $tryAnchor = trim($merged['position-anchor'] ?? '');
                // Its width and its height, in cells or after an anchor: a length is a size of no anchor
                $trySizes = [];
                foreach (['width', 'height'] as $property) {
                    $value = $tryDeclarations[$property] ?? null;
                    if (null === $value || 'auto' === strtolower(trim($value))) {
                        continue;
                    }
                    $size = self::anchorSize($value, 'height' === $property, $media) ?? (null !== ($length = Length::toCells($value, $media, 'height' === $property)) ? [null, $property, 0.0, max(1, $length), max(1, $length)] : null);
                    if (null !== $size) {
                        $trySizes[$property] = $size;
                    }
                }
                $alternatives[] = new Position($positionMode, anchor: str_starts_with($tryAnchor, '--') ? $tryAnchor : null, area: self::positionArea($merged['position-area'] ?? ''), anchorEdges: $tryEdges, anchorSizes: $trySizes);
            }
            $flips = array_values(array_unique($flips));
            $anchorSizes = [];
            foreach (['width', 'min-width', 'max-width', 'height', 'min-height', 'max-height'] as $property) {
                if (isset($declarations[$property]) && null !== $size = self::anchorSize($declarations[$property], str_contains($property, 'height'), $media)) {
                    $anchorSizes[$property] = $size;
                }
            }
            $visibility = self::positionVisibility($declarations['position-visibility'] ?? '') ?? ['anchors-visible'];
            $style = $style->withPosition(new Position($positionMode, $cells[0], $cells[1], $cells[2], $cells[3], $zIndex, $percents, str_starts_with($anchorName, '--') ? $anchorName : null, self::positionArea($declarations['position-area'] ?? ''), $edges, $flips, anchorSizes: $anchorSizes, tries: $alternatives, visibility: $visibility, tryOrder: $tryOrder, anchorCenter: ['anchor-center' === strtolower(trim($declarations['justify-self'] ?? '')), 'anchor-center' === strtolower(trim($declarations['align-self'] ?? ''))]));
        }

        // transform: translate() moves the element after layout: offsets, over those it has
        $translation = isset($declarations['translate']) && 'none' !== strtolower(trim($declarations['translate']))
            ? self::translation('translate('.str_replace(' ', ', ', trim($declarations['translate'])).')', $media)
            : (isset($declarations['transform']) ? self::translation($declarations['transform'], $media) : null);
        if (null !== $translation && [0, 0, 0.0, 0.0] !== $translation) {
            [$x, $y, $px, $py] = $translation;
            $position = $style->getPosition() ?? new Position(PositionMode::Relative);
            $style = $style->withPosition(PositionMode::Relative === $position->mode
                ? new Position(PositionMode::Relative, null !== $position->top || null === $position->bottom ? ($position->top ?? 0) + $y : null, $position->right, null !== $position->bottom && null === $position->top ? $position->bottom - $y : null, null !== $position->left || null === $position->right ? ($position->left ?? 0) + $x : null, $position->zIndex, $position->percents)
                // Absolute or fixed: the insets that place it move; without one on an axis, it gets one only when it moves along it
                : new Position(
                    $position->mode,
                    null !== $position->top ? $position->top + $y : (null !== $position->bottom || 0 === $y ? null : $y),
                    null === $position->left && null !== $position->right ? $position->right - $x : $position->right,
                    null === $position->top && null !== $position->bottom ? $position->bottom - $y : $position->bottom,
                    null !== $position->left ? $position->left + $x : (null !== $position->right || 0 === $x ? null : $x),
                    $position->zIndex,
                    $position->percents,
                ));
            // Percentages of the element itself
            if (0.0 !== $px || 0.0 !== $py) {
                $style = $style->withPosition(($style->getPosition() ?? new Position(PositionMode::Relative))->withTranslate([$px, $py]));
            }
        }

        // A stacking context: a positioned element of a z-index other than auto, a group of its own, or one that moves
        if ((('sticky' === $mode || null !== $positionMode) && null !== self::integer($declarations['z-index'] ?? ''))
            || CssStyleSheet::isGroup($declarations) || !\in_array(strtolower(trim($declarations['translate'] ?? 'none')), ['none', ''], true)) {
            $style = $style->withIsolated();
        }

        // A size stays what it is, within its minimum and maximum, whatever the order they come in, like CSS
        // A percentage of none, like 0%, is its length alone
        $cells = static fn (?string $value, bool $vertical): ?int => null === $value ? null : (null !== ($linear = Length::linear($value, $media)) ? (0.0 === (float) $linear[0] && $linear[1] >= 0 ? (int) round($linear[1]) : null) : Length::toCells($value, $media, $vertical));
        if (null !== $width = $cells($declarations['width'] ?? null, false)) {
            $max = $cells($declarations['max-width'] ?? null, false);
            $min = $cells($declarations['min-width'] ?? null, false);
            $style = $style->withMaxColumns(max(1, max($min ?? 0, min($max ?? \PHP_INT_MAX, $width))));
            // A flex item that grows or has a basis, like the w-0 flex-1 of Tailwind: in a row, its max-width bounds it
            if (isset($declarations['flex']) || isset($declarations['flex-grow']) || isset($declarations['flex-basis'])) {
                $style = $style->withRowMaxColumns(null !== $max ? max($min ?? 0, $max) : null);
                // One that does not grow, its basis its width, like flex: none, the slides of a carousel: that width
                if (!isset($declarations['flex-basis']) && preg_match('/^\s*(?:none|initial)\s*$/i', $declarations['flex'] ?? '') && null === $style->getFlexBasis()) {
                    $style = $style->withFlexBasis(max(1, max($min ?? 0, min($max ?? \PHP_INT_MAX, $width))));
                }
            }
        }
        if (null !== $height = $cells($declarations['height'] ?? null, true)) {
            $max = $cells($declarations['max-height'] ?? null, true);
            $min = $cells($declarations['min-height'] ?? null, true);
            $rows = max(0, max($min ?? 0, min($max ?? \PHP_INT_MAX, $height)));
            $style = $style->withRows($rows, $rows);
        }
        // Clipping or scrolling sideways, like the .overflow-hidden around a file name of GitHub: no automatic minimum, like
        // CSS, it shrinks past its content in a row before the items beside it, the icon before the name
        $sideways = strtolower(trim($declarations['overflow-x'] ?? $declarations['overflow-inline'] ?? (Value::split(strtolower($declarations['overflow'] ?? ''))[0] ?? '')));
        if (null === $style->getMinColumns() && \in_array($sideways, ['hidden', 'clip', 'auto', 'scroll', 'overlay'], true)) {
            $style = $style->withMinColumns(0);
        }
        // Masked by an image, like an icon drawn by mask-image: url(chevron.svg) over a background color: its shape is
        // not drawn, its background neither, not a block of color where the icon is
        foreach (['mask-image', '-webkit-mask-image', 'mask', '-webkit-mask'] as $property) {
            if (isset($declarations[$property]) && str_contains(strtolower($declarations[$property]), 'url(')) {
                // Over a color, like the chevron of a menu of VitePress over currentColor: the shape of the image in that
                // color, stretched to the box
                $icon = null;
                if (($tint = $style->getBackground()) instanceof Color && !$media->forcedColors) {
                    foreach (Value::split($declarations[$property]) as $part) {
                        if (null !== $url = self::url($part)) {
                            $icon = $this->maskIcon($url, $tint);
                            break;
                        }
                    }
                }
                $style = $style->withBackground(null)->withGradient(null);
                if (null !== $icon) {
                    $style = $style->withBackgroundImage($icon)->withBackgroundLayout(new BackgroundLayout(size: [new BackgroundLength(100.0), new BackgroundLength(100.0)], repeat: [BackgroundLayout::NO_REPEAT, BackgroundLayout::NO_REPEAT]));
                }
                break;
            }
        }
        // The height of a table cell is a least one, like browsers: its content grows it, like the second line of the
        // <td style="height: 10px"> of the header of Hacker News, whatever max-height says
        if (TableRole::Cell === $style->getTableRole() && null !== $style->getMaxRows()) {
            $style = $style->withRows($style->getMinRows(), null);
        }

        // A table without a width is as wide as its columns, like CSS, its border around them, not across the page
        if (TableRole::Table === $style->getTableRole() && null === $style->getContentWidth() && !isset($declarations['width']) && !isset($declarations['inline-size'])) {
            $style = $style->withContentWidth('fit-content');
        }

        // flex-grow alone keeps the basis of auto: a growing item starts from its content
        if (isset($declarations['flex-grow']) && !isset($declarations['flex-basis']) && !isset($declarations['flex'])) {
            $style = $style->withContentBasis();
        }

        // A flex item's width is its flex basis when flex-basis is auto, like CSS: a row gives it that width
        if (isset($declarations['width']) && !isset($declarations['flex-basis']) && !isset($declarations['flex']) && null === Length::linear($declarations['width'], $media)
            && null !== ($cells = Length::toCells($declarations['width'], $media)) && $cells > 0) {
            $style = $style->withFlexBasis($cells);
        }

        if (null !== $columns = self::columns($declarations, $media)) {
            $style = $style->withColumns($columns);
        }

        if (null !== $scroll = self::scroll($declarations, $media)) {
            $style = $style->withScroll($scroll);
        }
        // A forced break before or after the element, in a multi-column container
        // page-break-before and page-break-after are the legacy names of break-before and break-after: the last wins
        $breakOrder = array_flip(array_keys($declarations));
        $breakValue = static fn (string $property): string => strtolower(trim(isset($declarations['page-'.$property]) && ($breakOrder['page-'.$property] > ($breakOrder[$property] ?? -1)) ? $declarations['page-'.$property] : $declarations[$property] ?? ''));
        $break = static fn (string $property): bool => \in_array($breakValue($property), ['column', 'page', 'always', 'left', 'right', 'recto', 'verso'], true);
        // avoid keeps the element in the column of the one before it, or of the one after it
        $avoid = static fn (string $property): bool => \in_array($breakValue($property), ['avoid', 'avoid-column', 'avoid-page'], true);
        if ($break('break-before') || $break('break-after') || $avoid('break-before') || $avoid('break-after')) {
            $style = $style->withColumnBreaks([$break('break-before'), $break('break-after'), $avoid('break-before'), $avoid('break-after')]);
        }
        if (null !== $scrollMargin = self::scrollRoom($declarations, 'scroll-margin', $media)) {
            $style = $style->withScrollMargin($scrollMargin);
        }
        // scroll-snap-align: the block value, then the inline one, the same when there is one
        $snapParts = Value::split(strtolower($declarations['scroll-snap-align'] ?? ''));
        $snapAlign = \in_array($snapParts[0] ?? '', ['start', 'center', 'end'], true) ? $snapParts[0] : null;
        $snapSideways = \in_array($snapParts[1] ?? $snapParts[0] ?? '', ['start', 'center', 'end'], true) ? $snapParts[1] ?? $snapParts[0] : null;
        if (null !== $snapAlign || null !== $snapSideways) {
            $style = $style->withSnapAlign($snapAlign, 'always' === strtolower(trim($declarations['scroll-snap-stop'] ?? '')), $snapSideways);
        }

        if (null !== $placement = self::gridPlacement($declarations)) {
            $style = $style->withGridPlacement($placement);
        }

        // A grid: its columns, and both gaps
        if (\in_array($display, ['grid', 'inline-grid'], true)) {
            // subgrid: the columns of the parent grid it spans, which the layout gives it
            $subgrid = isset($declarations['grid-template-columns']) && self::isSubgrid($declarations['grid-template-columns']);
            // subgrid rows: the rows of the parent grid it spans, which the layout gives it
            $subgridRows = isset($declarations['grid-template-rows']) && self::isSubgrid($declarations['grid-template-rows']);
            if ($subgridRows) {
                $declarations['grid-template-rows'] = 'none';
            }
            $template = isset($declarations['grid-template-columns']) && 'none' !== strtolower(trim($declarations['grid-template-columns'])) && !$subgrid ? self::gridTemplate($declarations['grid-template-columns'], $media) : new GridTemplate([]);
            if (null !== $template) {
                $template = new GridTemplate(
                    $template->tracks,
                    $template->autoRepeat,
                    self::gridRows($declarations['grid-template-rows'] ?? 'none', $media) ?? [],
                    self::gridAreas($declarations['grid-template-areas'] ?? 'none') ?? [],
                    ($autoRows = self::gridRows($declarations['grid-auto-rows'] ?? 'auto', $media) ?: [null])[0],
                    \in_array('dense', Value::split(strtolower($declarations['grid-auto-flow'] ?? '')), true),
                    $template->columnLines,
                    'none' !== strtolower(trim($declarations['grid-template-rows'] ?? 'none')) ? self::gridTemplate(str_ireplace(['auto-fill', 'auto-fit'], '1', $declarations['grid-template-rows']), $media)->columnLines ?? [] : [],
                    \in_array('column', Value::split(strtolower($declarations['grid-auto-flow'] ?? '')), true),
                    ($autoColumns = isset($declarations['grid-auto-columns']) ? self::gridAutoColumns($declarations['grid-auto-columns'], $media) ?? [] : [])[0] ?? null,
                    self::gridRowFractions($declarations['grid-template-rows'] ?? 'none', $media),
                    \count($autoRows) > 1 ? $autoRows : [],
                    \count($autoColumns) > 1 ? $autoColumns : [],
                    $template->repeatAt,
                    $template->repeatLines,
                    $template->autoFit,
                    subgrid: $subgrid,
                    subgridRows: $subgridRows,
                );
            }
            if (null === $template) {
                $this->report('grid-template-columns:'.$declarations['grid-template-columns'], \sprintf('Invalid value "%s" for the "grid-template-columns" CSS property.', $declarations['grid-template-columns']));
                $template = new GridTemplate([]);
            }
            $style = $style->withGridTemplate($template);
            if (!isset($declarations['justify-items']) && isset($declarations['place-items']) && null !== $j = self::justifyKeyword(($parts = Value::split($declarations['place-items']))[1] ?? $parts[0] ?? '')) {
                $style = $style->withJustifyItems($j);
            }
            $parts = isset($declarations['gap']) ? Value::split($declarations['gap']) : [];
            $columnGap = $declarations['column-gap'] ?? $parts[1] ?? $parts[0] ?? null;
            $rowGap = $declarations['row-gap'] ?? $parts[0] ?? null;
            if (null !== $columnGap && null !== $cells = 'normal' === $columnGap ? 0 : Length::toCells($columnGap, $media)) {
                $style = $style->withGap(max(0, $cells));
            }
            if (null !== $rowGap && null !== $cells = 'normal' === $rowGap ? 0 : Length::toRows($rowGap, $media)) {
                $style = $style->withWrap(null, max(0, $cells));
            }
        }

        $gap = null !== $style->getGridTemplate() ? null : ($declarations[$horizontal ? 'column-gap' : 'row-gap'] ?? null);
        if (null === $gap && isset($declarations['gap']) && null === $style->getGridTemplate()) {
            $parts = Value::split($declarations['gap']);
            $gap = $horizontal ? ($parts[1] ?? $parts[0] ?? null) : ($parts[0] ?? null);
        }
        // A wrapping row: its lines are separated by the row gap
        $wrapValue = $declarations['flex-wrap'] ?? (isset($declarations['flex-flow']) ? (preg_match('/\b(wrap|wrap-reverse|nowrap)\b/i', $declarations['flex-flow'], $m) ? $m[1] : null) : null);
        if ($horizontal && null !== $wrapValue) {
            $wraps = 'nowrap' !== strtolower($wrapValue);
            $rowGap = $declarations['row-gap'] ?? (isset($declarations['gap']) ? (Value::split($declarations['gap'])[0] ?? null) : null);
            $rowCells = null === $rowGap || 'normal' === $rowGap ? 0 : Length::toRows($rowGap, $media);
            $style = $style->withWrap($wraps, $wraps ? max(0, $rowCells ?? 0) : null, 'wrap-reverse' === strtolower($wrapValue));
        }

        if (null !== $gap) {
            // Rows from three quarters of one, like padding: the gap: .25rem of a column is none
            $cells = 'normal' === $gap ? 0 : ($horizontal ? Length::toCells($gap, $media) : Length::toRows($gap, $media));
            if (null === $cells) {
                $this->report('gap:'.$gap, \sprintf('Invalid value "%s" for the "gap" CSS property.', $gap));
            } else {
                $style = $style->withGap(max(0, $cells));
            }
        }

        // The place-* shorthands, when the longhands are not set
        $alignment = [
            'justify-content' => $declarations['justify-content'] ?? (isset($declarations['place-content']) ? (($parts = Value::split($declarations['place-content']))[1] ?? $parts[0] ?? null) : null),
            'align-items' => $declarations['align-items'] ?? (isset($declarations['place-items']) ? (Value::split($declarations['place-items'])[0] ?? null) : null),
        ];

        // align-content, or the first value of place-content
        $alignContent = $declarations['align-content'] ?? (isset($declarations['place-content']) ? (Value::split($declarations['place-content'])[0] ?? null) : null);
        if (null !== $alignContent && null !== $distribution = self::contentDistribution($alignContent)) {
            $style = $style->withAlignContent('normal' === $distribution ? null : $distribution);
        }

        // Flex rows stretch their items by default, like align-items: normal
        if ($horizontal && \in_array(strtolower(trim($alignment['align-items'] ?? 'normal')), ['normal', 'stretch'], true)) {
            $style = $style->withVerticalAlign(VerticalAlign::Stretch);
        }

        // Along the main axis: justify-content; across it: align-items
        foreach (['justify-content' => $horizontal, 'align-items' => !$horizontal] as $property => $alongColumns) {
            if (null === $alignment[$property]) {
                continue;
            }
            // space-between, space-around and space-evenly: in rows, and in flex columns of a set height
            if ('justify-content' === $property && null !== $justify = Justify::tryFrom(strtolower(trim($alignment[$property])))) {
                $style = $style->withJustify($justify);
                continue;
            }
            $position = self::position($alignment[$property]);
            if (null === $position) {
                continue;
            }
            $style = $alongColumns
                ? $style->withAlign(match ($position) {
                    'start' => Align::Left, 'center' => Align::Center, 'end' => Align::Right,
                })
                : $style->withVerticalAlign(match ($position) {
                    'start' => VerticalAlign::Top, 'center' => VerticalAlign::Center, 'end' => VerticalAlign::Bottom,
                });
        }

        // A grid written vertically is laid out with its rows as columns, mirrored for vertical-rl: its first row on the right
        $verticalGrid = null !== $style->getGridTemplate() && null !== $writing && 'horizontal-tb' !== $writing;
        if ($verticalGrid) {
            $style = $style->withReverseOrder(!\in_array($writing, ['vertical-lr', 'sideways-lr'], true));
            // Its width is its block size: as wide as its rows, unless set
            if (!isset($declarations['width']) && null === $style->getContentWidth()) {
                $style = $style->withContentWidth('fit-content');
            }
        }

        // Right to left, a row runs from the right: its items in reverse, start on the right; blocks start on the right too
        if ($rtl) {
            // A grid is mirrored: its first column on the right; written vertically, its direction is that of its columns
            // running down, not mirrored
            if (null !== $style->getGridTemplate()) {
                $style = $verticalGrid ? $style : $style->withReverseOrder(true);
            } elseif (Direction::Horizontal === $style->getDirection()) {
                $style = $style->withReverseOrder(!($style->getReverseOrder() ?? false))->withAlign(match ($style->getAlign()) {
                    null, Align::Left => Align::Right,
                    Align::Right => Align::Left,
                    default => $style->getAlign(),
                });
            }
            // A block narrower than its parent starts on the right, like margin-inline-start: auto; not an item of a row
            // (whose flex-grow the style sheet sets), which its row places, like the box of a check of Bootstrap
            if (null === $style->getAutoMargin() && (null !== $style->getMaxColumns() || null !== $style->getWidthPercent()) && !isset($declarations['flex-grow']) && !isset($declarations['flex'])) {
                $style = $style->withAutoMargin(AutoMargin::Left);
            }
        }

        // box-sizing: the widths and heights set include the padding and the border (border-box, the default here),
        // or are those of the content (content-box). Widgets take a width with them and rows without them
        $padding = $style->getPadding();
        $border = $style->getBorder();
        $vertical = ($padding?->getVertical() ?? 0) + ($border->top ?? 0) + ($border->bottom ?? 0);
        $contentBox = 'content-box' === strtolower(trim($declarations['box-sizing'] ?? ''));
        if (!$contentBox && $vertical > 0 && (null !== $style->getMinRows() || null !== $style->getMaxRows())) {
            // A row for the content when CSS leaves room for a line in it, a border row being the pixel it is there,
            // like the field of 30px of EasyAdmin, its two borders taking its two rows
            $line = static function (?string $height) use ($media, $border, $vertical): int {
                // Rows set as rows, a bare number, are those rows
                $columns = null !== $height && !str_contains($height, '%') && !is_numeric(trim($height)) ? Length::toColumns($height, $media) : null;
                $borders = ($border->top ?? 0) + ($border->bottom ?? 0);

                return null !== $columns && $columns * 8 - $borders - ($vertical - $borders) * 16 >= 16 ? 1 : 0;
            };
            $height = $declarations['height'] ?? $declarations['block-size'] ?? null;
            $style = $style->withRows(
                null !== $style->getMinRows() ? max($line($declarations['min-height'] ?? $declarations['min-block-size'] ?? $height), $style->getMinRows() - $vertical) : null,
                null !== $style->getMaxRows() ? max($line($declarations['max-height'] ?? $declarations['max-block-size'] ?? $height), $style->getMaxRows() - $vertical) : null,
            );
        }
        if ($contentBox) {
            $horizontal = ($padding?->getHorizontal() ?? 0) + ($border->left ?? 0) + ($border->right ?? 0);
            if ($horizontal > 0) {
                if (null !== $max = $style->getMaxColumns()) {
                    $style = $style->withMaxColumns($max + $horizontal);
                }
                if (null !== $min = $style->getMinColumns()) {
                    $style = $style->withMinColumns($min + $horizontal);
                }
                if (null !== $percent = $style->getWidthPercent()) {
                    $style = $style->withWidthPercent($percent, $style->getWidthOffset() + $horizontal);
                }
            }
        }

        // contain-intrinsic-size: the size of the content of an element whose content is hidden, without its own, or
        // of one contained in size, like contain: size, its content drawn in it, cut at it
        $contain = preg_split('/\s+/', strtolower(trim($declarations['contain'] ?? '')), -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        $sized = $style->getContentHidden() || array_intersect(['size', 'strict'], $contain) ? [true, true] : [\in_array('inline-size', $contain, true), false];
        if ($sized[0] || $sized[1]) {
            [$width, $height] = self::intrinsicSize($declarations);
            $width = $sized[0] ? $width : null;
            $height = $sized[1] ? $height : null;
            $padding = $style->getPadding();
            $border = $style->getBorder();
            if (null !== $height && !isset($declarations['height']) && null !== $cells = Length::toCells($height, $media, true)) {
                // Rows are those of the content already, columns count the padding and the border
                $style = $style->withRows(max(0, $cells), max(0, $cells));
            }
            if (null !== $width && !isset($declarations['width']) && null !== $cells = Length::toCells($width, $media)) {
                $columns = max(0, $cells) + ($padding->left ?? 0) + ($padding->right ?? 0) + ($border->left ?? 0) + ($border->right ?? 0);
                $style = $style->withMinColumns($columns)->withMaxColumns($columns);
            }
        }

        // -webkit-text-fill-color: the glyphs only, the border and the outline keep the color, like currentcolor
        $fill = $declarations['-webkit-text-fill-color'] ?? null;
        if (null !== $fill && !\in_array(strtolower(trim($fill)), ['initial', 'unset', 'revert', 'revert-layer', 'inherit'], true) && false !== $filled = self::withColor($style, $fill, $media, 'color')) {
            if (null !== $color = $style->getColor()) {
                if (null !== ($border = $style->getBorder()) && null === $border->color) {
                    $filled = $filled->withBorder($border->withColor($color));
                }
                if (($outline = $style->getOutline()) instanceof Outline && null === $outline->color) {
                    $filled = $filled->withOutline(new Outline($outline->pattern, $color, $outline->offset));
                }
            }
            $style = $filled;
        }

        return $style;
    }

    /**
     * Parses linear-gradient() and radial-gradient(): the direction, then color stops with their positions.
     */
    private static function gradient(string $value, MediaContext $media): ?Gradient
    {
        if (!preg_match('/^(repeating-)?(linear|radial|conic)-gradient\((.*)\)$/is', trim($value), $m)) {
            return null;
        }
        $repeating = '' !== $m[1];
        $kind = strtolower($m[2]);
        $arguments = array_map('trim', Selector::splitTopLevel($m[3]));
        // The color space the colors are interpolated in, like "in oklch" before or after the direction: sRGB by default
        $method = null;
        if (preg_match('/^(.*?)\s*\b(in\s+[a-z0-9-]+(?:\s+[a-z]+\s+hue)?)\s*(.*)$/is', $arguments[0] ?? '', $in) && !str_contains($in[1].$in[3], '(')) {
            if (null === $method = Value::interpolationMethod($in[2])) {
                return null;
            }
            $arguments[0] = trim($in[1].' '.$in[3]);
            if ('' === $arguments[0]) {
                array_shift($arguments);
            }
        }
        $angle = 180.0;
        $from = 0.0;
        $first = strtolower($arguments[0] ?? '');
        if ('linear' === $kind && null !== $a = self::angle($first)) {
            $angle = $a;
            array_shift($arguments);
        } elseif ('linear' === $kind && str_starts_with($first, 'to ')) {
            $sides = preg_split('/\s+/', substr($first, 3)) ?: [];
            $x = (\in_array('right', $sides, true) ? 1 : 0) - (\in_array('left', $sides, true) ? 1 : 0);
            $y = (\in_array('bottom', $sides, true) ? 1 : 0) - (\in_array('top', $sides, true) ? 1 : 0);
            if (0 === $x && 0 === $y) {
                return null;
            }
            $angle = rad2deg(atan2($x, -$y));
            array_shift($arguments);
        } elseif ('linear' !== $kind && false === Value::color(Value::split($first)[0] ?? '', $media)) {
            // The shape and the size of a radial one, where a conic one starts, then where they are centered
            $tokens = Value::split($first);
            $at = array_search('at', $tokens, true);
            if (false !== $at) {
                if (null === $center = self::backgroundPosition(\array_slice($tokens, $at + 1), $media)) {
                    return null;
                }
                $tokens = \array_slice($tokens, 0, $at);
            }
            if ('conic' === $kind) {
                if ($tokens && (2 !== \count($tokens) || 'from' !== $tokens[0] || null === $from = self::angle($tokens[1]))) {
                    return null;
                }
            } else {
                $lengths = [];
                foreach ($tokens as $token) {
                    if ('circle' === $token || 'ellipse' === $token) {
                        $circle = 'circle' === $token;
                    } elseif (\in_array($token, ['closest-side', 'closest-corner', 'farthest-side', 'farthest-corner'], true)) {
                        $extent = $token;
                    } elseif (null !== $length = self::backgroundLength($token, $media, 1 === \count($lengths))) {
                        $lengths[] = $length;
                    } else {
                        return null;
                    }
                }
                if ($lengths) {
                    // One length is the radius of a circle, two the radii of an ellipse
                    if (\count($lengths) > 2 || isset($extent)) {
                        return null;
                    }
                    $circle ??= 1 === \count($lengths);
                    $extent = [$lengths[0], $circle ? null : ($lengths[1] ?? null)];
                    if (!$circle && null === $extent[1]) {
                        return null;
                    }
                }
            }
            array_shift($arguments);
        }
        if (\count($arguments) < 1 || \count($arguments) > 32) {
            return null;
        }

        // Each stop: a color, then none, one or two positions: shares of the line, angles around a conic one, or
        // lengths along a linear or radial one
        $raw = [];
        $cells = false;
        foreach ($arguments as $argument) {
            $parts = Value::split($argument);
            $name = array_shift($parts) ?? '';
            // Its alpha kept apart, the color blended over what is behind the box when painted
            $color = Value::opaqueColor($name, $media);
            $alpha = 'transparent' === strtolower(trim($name)) ? 0.0 : Value::alpha($name, $media);
            if (0.0 === $alpha && false !== Value::color($name, $media)) {
                $color ??= Color::hex('#000000');
            }
            if (!$color instanceof Color || \count($parts) > 2) {
                return null;
            }
            $positions = [];
            foreach ($parts as $part) {
                if ('0' === $part) {
                    $positions[] = [0.0, 0.0];
                } elseif (preg_match('/^(-?\d*\.?\d+)%$/', $part, $p)) {
                    $positions[] = [(float) $p[1] / 100, 0.0];
                } elseif ('conic' === $kind && null !== $degrees = self::angle($part)) {
                    $positions[] = [$degrees / 360, 0.0];
                } elseif ('conic' !== $kind && null !== $length = Length::toCells($part, $media)) {
                    $positions[] = [0.0, (float) $length];
                    $cells = true;
                } elseif ('conic' !== $kind && null !== $linear = Length::linear($part, $media)) {
                    // A share and a length, like the calc(100% - 2rem) of the fade under the menu of MDN
                    $positions[] = [(float) $linear[0] / 100, (float) $linear[1]];
                    $cells = true;
                } else {
                    return null;
                }
            }
            foreach ($positions ?: [[null, 0.0]] as [$share, $offset]) {
                $raw[] = $alpha < 1.0 ? [$color, $share, $offset, max(0.0, $alpha)] : [$color, $share, $offset];
            }
        }

        // In another space than sRGB, which the painting blends in: stops between the stops, spread evenly between them
        if (null !== $method && 'srgb' !== $method[0]) {
            // A transparent stop has the color of its neighbor, like premultiplied colors: only the alpha goes to it
            foreach ($raw as $i => $stop) {
                if (0.0 === ($stop[3] ?? 1.0)) {
                    $neighbor = null;
                    for ($j = $i + 1; $j < \count($raw) && null === $neighbor; ++$j) {
                        $neighbor = ($raw[$j][3] ?? 1.0) > 0.0 ? $raw[$j][0] : null;
                    }
                    for ($j = $i - 1; $j >= 0 && null === $neighbor; --$j) {
                        $neighbor = ($raw[$j][3] ?? 1.0) > 0.0 ? $raw[$j][0] : null;
                    }
                    $raw[$i][0] = $neighbor ?? $stop[0];
                }
            }
            $expanded = [];
            foreach ($raw as $i => $stop) {
                $next = $raw[$i + 1] ?? null;
                $expanded[] = $stop;
                if (null !== $next && !$stop[0]->isThemed() && !$next[0]->isThemed() && $stop[0]->toHex() !== $next[0]->toHex()) {
                    for ($step = 1; $step < self::GRADIENT_STEPS; ++$step) {
                        $alpha = ($stop[3] ?? 1.0) + (($next[3] ?? 1.0) - ($stop[3] ?? 1.0)) * $step / self::GRADIENT_STEPS;
                        $expanded[] = [Value::interpolate($stop[0], $next[0], $step / self::GRADIENT_STEPS, ...$method), null, 0.0, $alpha];
                    }
                }
            }
            $raw = $expanded;
        }

        return new Gradient($kind, $angle, Gradient::resolve($raw, 0.0), $repeating, $from, $cells ? $raw : null, $circle ?? false, $extent ?? 'farthest-corner', $center ?? null);
    }

    /**
     * The image image-set() chooses, as url() or the gradient it lists, and its resolution: of the lowest resolution
     * that is not below the one of the screen, else the highest; options of a type() that cannot be decoded are left
     * out. Null when it is not an image-set(), or none can be chosen.
     *
     * @return array{string, float}|null
     */
    private static function imageSet(string $value, MediaContext $media): ?array
    {
        if (!preg_match('/^(?:-webkit-)?image-set\((.*)\)$/is', trim($value), $m)) {
            return null;
        }
        $types = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/bmp', ...(\function_exists('imagecreatefromavif') ? ['image/avif'] : [])];
        $options = [];
        foreach (Selector::splitTopLevel($m[1]) as $option) {
            $parts = Value::split(trim($option));
            $image = array_shift($parts) ?? '';
            // A string is a url()
            if (preg_match('/^(["\'])(.*)\1$/s', $image, $string)) {
                $image = 'url("'.$string[2].'")';
            }
            $resolution = 1.0;
            foreach ($parts as $part) {
                if (preg_match('/^type\(\s*(["\'])(.*?)\1\s*\)$/is', $part, $type)) {
                    if (!\in_array(strtolower($type[2]), $types, true)) {
                        continue 2;
                    }
                } elseif (null === $resolution = MediaQuery::resolution($part)) {
                    continue 2;
                }
            }
            $options[] = [$resolution, $image];
        }
        if (!$options) {
            return null;
        }
        usort($options, static fn (array $a, array $b) => $a[0] <=> $b[0]);
        $dppx = $media->dppx();
        foreach ($options as [$resolution, $image]) {
            if ($resolution >= $dppx - 1e-9) {
                return [$image, $resolution];
            }
        }

        return [$options[\count($options) - 1][1], $options[\count($options) - 1][0]];
    }

    /**
     * The source of border-image: a gradient, a url() or image-set() image (none when it cannot be loaded), or false
     * for none; null when it is not one.
     */
    private function borderImageSource(string $value, MediaContext $media): Gradient|RasterImage|false|null
    {
        if ('none' === strtolower($value)) {
            return false;
        }
        [$value, $resolution] = self::imageSet($value, $media) ?? [$value, 1.0];
        if (null !== $url = self::url($value)) {
            return $media->forcedColors ? false : ($this->image($url, $resolution) ?? false);
        }
        if (null !== $gradient = self::gradient($value, $media)) {
            return $media->forcedColors ? false : $gradient;
        }

        return null;
    }

    /**
     * A width of min(), max() or clamp() over one percentage (with lengths added, like calc(100% - 2ch)) and lengths:
     * the percentage, and the least and the most columns the lengths make; null when it is not one.
     *
     * @return array{array{float, float}, ?int, ?int}|null
     */
    private static function boundedPercent(string $value, MediaContext $media): ?array
    {
        // calc() of that function alone is that function, like the max-w-[calc(min(700px,100%))] of the docs of React
        while (preg_match('/^calc\(\s*((?:min|max|clamp)\(.*\))\s*\)$/is', trim($value), $inner) && Value::closingParenthesis($inner[1], strpos($inner[1], '(')) === \strlen($inner[1]) - 1) {
            $value = $inner[1];
        }
        if (!preg_match('/^(min|max|clamp)\((.*)\)$/is', trim($value), $m) || !str_contains($m[2], '%')) {
            return null;
        }
        $function = strtolower($m[1]);
        $arguments = array_map('trim', Selector::splitTopLevel($m[2]));
        if ('clamp' === $function && 3 !== \count($arguments)) {
            return null;
        }
        $percent = null;
        $lengths = [];
        foreach ($arguments as $i => $argument) {
            $argument = self::sumArgument($argument);
            if (str_contains($argument, '%')) {
                // One percentage, the preferred value of clamp()
                if (null !== $percent || ('clamp' === $function && 1 !== $i) || null === $percent = Length::linear($argument, $media)) {
                    return null;
                }
                continue;
            }
            if (null === $cells = Length::toCells($argument, $media)) {
                return null;
            }
            $lengths[$i] = max(0, $cells);
        }
        if (null === $percent || !$lengths) {
            return null;
        }

        return match ($function) {
            'min' => [$percent, null, min($lengths)],
            'max' => [$percent, max($lengths), null],
            default => [$percent, $lengths[0], $lengths[2]],
        };
    }

    /**
     * An argument of a math function, a sum without calc() around it like the 100% - 4ch of min(60ch, 100% - 4ch), as
     * a calc() of it.
     */
    private static function sumArgument(string $argument): string
    {
        $argument = trim($argument);

        return \count(array_filter(Selector::splitTopLevel($argument, ' '), static fn (string $part) => '' !== trim($part))) > 1 ? 'calc('.$argument.')' : $argument;
    }

    /**
     * Converts an inset that is a percentage of the containing block, like 50% or calc(50% - 2ch); null when it is not one.
     */
    private static function insetPercent(string $value, MediaContext $media): int|PercentLength|null
    {
        $value = trim($value);
        if (preg_match('/^(-?\d{1,4}(?:\.\d+)?)%$/', $value, $m)) {
            return (int) round((float) $m[1]);
        }
        if (!str_contains($value, '%')) {
            return null;
        }

        return null !== ($linear = Length::linear($value, $media)) ? new PercentLength(true, [$linear]) : self::percentLength($value, $media);
    }

    /**
     * Converts min(), max() or clamp() over percentages and lengths, like max(1ch, 5%), each argument linear in the
     * room or one of them; null when it is not one.
     */
    private static function percentLength(string $value, MediaContext $media, int $depth = 0): ?PercentLength
    {
        // calc() around one of them is the same
        $value = preg_match('/^calc\(\s*((?:min|max|clamp)\(.*\))\s*\)$/is', trim($value), $m) && 1 === \count(Selector::splitTopLevel($m[1], ' ')) ? $m[1] : trim($value);
        if ($depth > 8 || !preg_match('/^(min|max|clamp)\((.*)\)$/is', $value, $m)) {
            return null;
        }
        $terms = [];
        foreach (Selector::splitTopLevel($m[2]) as $argument) {
            $argument = self::sumArgument($argument);
            if (null !== $term = Length::linear($argument, $media) ?? self::percentLength($argument, $media, $depth + 1)) {
                $terms[] = $term;
            } elseif (null !== $cells = Length::toCells($argument, $media)) {
                $terms[] = [0.0, (float) $cells];
            } else {
                return null;
            }
        }
        $function = strtolower($m[1]);
        if (!$terms || ('clamp' === $function && 3 !== \count($terms))) {
            return null;
        }

        return 'clamp' === $function ? new PercentLength(true, [$terms[0], new PercentLength(false, [$terms[1], $terms[2]])]) : new PercentLength('max' === $function, $terms);
    }

    /**
     * The address of url(), with or without quotes; null when it is not one.
     */
    private static function url(string $value): ?string
    {
        if (!preg_match('/^url\(\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'()]*))\s*\)$/is', trim($value), $m)) {
            return null;
        }

        return ($m[1] ?? '').($m[2] ?? '').($m[3] ?? '');
    }

    /**
     * The image of an address, drawable; null when there is none.
     */
    /**
     * An image used as a mask, in a color: its shape, its alpha, painted in that color; null when it cannot be read.
     */
    private function maskIcon(string $url, Color $tint): ?RasterImage
    {
        ['r' => $red, 'g' => $green, 'b' => $blue] = $tint->toRgb();
        $key = $url.'|'.$red.','.$green.','.$blue;
        if (\array_key_exists($key, $this->masks)) {
            return $this->masks[$key];
        }
        // Each mask once, a few of them: icons, not images
        if (\count($this->masks) >= 64 || null === ($image = $this->image($url)) || null === ($size = $image->peekSize()) || $size[0] * $size[1] > 256 * 256
            || false === ($source = @imagecreatefromstring($image->data))) {
            return $this->masks[$key] = null;
        }
        [$width, $height] = [imagesx($source), imagesy($source)];
        $painted = imagecreatetruecolor($width, $height);
        imagealphablending($painted, false);
        imagesavealpha($painted, true);
        for ($y = 0; $y < $height; ++$y) {
            for ($x = 0; $x < $width; ++$x) {
                $alpha = imageistruecolor($source) ? (imagecolorat($source, $x, $y) >> 24) & 0x7F : imagecolorsforindex($source, imagecolorat($source, $x, $y))['alpha'];
                imagesetpixel($painted, $x, $y, imagecolorallocatealpha($painted, $red, $green, $blue, $alpha) ?: 0);
            }
        }
        ob_start();
        imagepng($painted);

        // An icon of strokes, like a chevron: drawn at the size of a few cells, its lines kept
        return $this->masks[$key] = new RasterImage((string) ob_get_clean())->withResolution($image->getResolution())->withStrokesKept();
    }

    private function image(string $url, float $resolution = 1.0): ?RasterImage
    {
        $image = '' !== $url && null !== $this->imageLoader ? ($this->imageLoader)($url) : null;
        // Its header read: decoded when it is first drawn
        if (null === $image?->peekSize()) {
            return null;
        }

        return 1.0 !== $resolution ? $image->withResolution($resolution) : $image;
    }

    /**
     * An angle in degrees, null when it is not one.
     */
    private static function angle(string $value): ?float
    {
        if (!preg_match('/^(-?\d*\.?\d+)(deg|turn|rad|grad)$/i', trim($value), $a)) {
            return null;
        }

        return match (strtolower($a[2])) {
            'turn' => (float) $a[1] * 360,
            'rad' => rad2deg((float) $a[1]),
            'grad' => (float) $a[1] * 0.9,
            default => (float) $a[1],
        };
    }

    /**
     * Replaces grid-template and grid by their longhands, where they are: a longhand after them still wins; an invalid
     * one is left, to be reported.
     *
     * @param array<string, string> $declarations
     *
     * @return array<string, string>
     */
    private static function gridShorthands(array $declarations): array
    {
        if (!isset($declarations['grid-template']) && !isset($declarations['grid'])) {
            return $declarations;
        }
        $expanded = [];
        foreach ($declarations as $property => $value) {
            $longhands = match ($property) {
                'grid-template' => self::gridTemplateShorthand($value),
                'grid' => self::gridShorthand($value),
                default => null,
            };
            foreach ($longhands ?? [$property => $value] as $longhand => $longhandValue) {
                unset($expanded[$longhand]);
                $expanded[$longhand] = $longhandValue;
            }
        }

        return $expanded;
    }

    /**
     * The longhands of grid-template: none, "rows / columns", or area strings each followed by its row height, then
     * "/ columns".
     *
     * @return array<string, string>|null
     */
    private static function gridTemplateShorthand(string $value): ?array
    {
        $value = trim($value);
        if ('none' === strtolower($value)) {
            return ['grid-template-rows' => 'none', 'grid-template-columns' => 'none', 'grid-template-areas' => 'none'];
        }
        $sides = Selector::splitTopLevel(str_replace('/', ',', $value));
        if (\count($sides) > 2 || \in_array('', array_map('trim', $sides), true)) {
            return null;
        }
        [$rows, $columns] = [trim($sides[0]), trim($sides[1] ?? 'none')];

        if (!preg_match('/["\']/', $rows)) {
            return 1 === \count($sides) ? null : ['grid-template-rows' => $rows, 'grid-template-columns' => $columns, 'grid-template-areas' => 'none'];
        }

        // Area strings, each with the height of its row after it (auto without one), line names around
        if (!preg_match_all('/"[^"]*"|\'[^\']*\'|\[[^\]]*\]|[^\s"\'\[(]+(?:\((?:[^()]|\([^()]*\))*\))?/', $rows, $tokens)) {
            return null;
        }
        $areas = [];
        $heights = [];
        $pending = false;
        foreach ($tokens[0] as $token) {
            if ('"' === $token[0] || "'" === $token[0]) {
                if ($pending) {
                    $heights[] = 'auto';
                }
                $areas[] = '"'.substr($token, 1, -1).'"';
                $pending = true;
            } elseif ('[' === $token[0]) {
                $heights[] = $token;
            } elseif ($pending) {
                $heights[] = $token;
                $pending = false;
            } else {
                // A height before any string
                return null;
            }
        }
        if ($pending) {
            $heights[] = 'auto';
        }

        return ['grid-template-rows' => implode(' ', $heights), 'grid-template-columns' => $columns, 'grid-template-areas' => implode(' ', $areas)];
    }

    /**
     * The longhands of grid: a grid-template, or "auto-flow [dense] [auto rows] / columns".
     *
     * @return array<string, string>|null
     */
    private static function gridShorthand(string $value): ?array
    {
        $sides = Selector::splitTopLevel(str_replace('/', ',', trim($value)));
        if (2 === \count($sides) && preg_match('/^auto-flow(\s+dense)?(?:\s+(.+))?$/i', trim($sides[0]), $m)) {
            return [
                'grid-template-rows' => 'none',
                'grid-template-columns' => trim($sides[1]),
                'grid-template-areas' => 'none',
                'grid-auto-flow' => '' !== ($m[1] ?? '') ? 'row dense' : 'row',
                'grid-auto-rows' => trim($m[2] ?? '') ?: 'auto',
                'grid-auto-columns' => 'auto',
            ];
        }
        // "rows / auto-flow [dense] [auto columns]": column after column
        if (2 === \count($sides) && preg_match('/^auto-flow(\s+dense)?(?:\s+(.+))?$/i', trim($sides[1]), $m)) {
            return [
                'grid-template-rows' => trim($sides[0]),
                'grid-template-columns' => 'none',
                'grid-template-areas' => 'none',
                'grid-auto-flow' => '' !== ($m[1] ?? '') ? 'column dense' : 'column',
                'grid-auto-columns' => trim($m[2] ?? '') ?: 'auto',
                'grid-auto-rows' => 'auto',
            ];
        }
        if (str_contains(strtolower($value), 'auto-flow')) {
            return null;
        }
        $template = self::gridTemplateShorthand($value);

        return null === $template ? null : $template + ['grid-auto-flow' => 'row', 'grid-auto-rows' => 'auto', 'grid-auto-columns' => 'auto'];
    }

    /**
     * Whether a value of grid-template-columns is subgrid, with line names or not, like subgrid [a] [b].
     */
    private static function isSubgrid(string $value): bool
    {
        return (bool) preg_match('/^subgrid(?:\s*\[[^\]]*\])*$/i', trim($value));
    }

    /**
     * Parses grid-template-columns: lengths, percentages, fr, auto, minmax() and repeat(), auto-fill and auto-fit included.
     */
    private static function gridTemplate(string $value, MediaContext $media): ?GridTemplate
    {
        // Line names, [a b], between the tracks: the line before the next track
        $names = [];
        $value = (string) preg_replace_callback('/\[([^\]]*)\]/', static function (array $m) use (&$names): string {
            $names[] = preg_split('/\s+/', strtolower(trim($m[1])), -1, \PREG_SPLIT_NO_EMPTY) ?: [];

            return " \x01".(\count($names) - 1).' ';
        }, $value);
        $valid = static fn (string $name): bool => 1 === preg_match('/^-?[a-z_][\w-]*$/', $name) && !\in_array($name, ['span', 'auto'], true);
        $tracks = [];
        $lines = [];
        $autoRepeat = $repeatAt = null;
        $repeatLines = [];
        foreach (Value::split(trim($value)) as $part) {
            if (preg_match('/^\x01(\d+)$/', $part, $m)) {
                // After a repeat that fills the room, as if it had one track: GridTemplate::lines() moves them
                foreach ($names[(int) $m[1]] as $name) {
                    if ($valid($name) && \count($lines) < 100) {
                        $lines[$name][] = \count($tracks) + 1 + (null !== $autoRepeat ? 1 : 0);
                    }
                }
                continue;
            }
            if (preg_match('/^repeat\(\s*(auto-fill|auto-fit|\d+)\s*,\s*(.+)\)$/is', $part, $m)) {
                // The tracks repeated, and the names of the lines between them, by the number of tracks before them
                $repeated = [];
                $inner = [];
                foreach (Value::split($m[2]) as $token) {
                    if (preg_match('/^\x01(\d+)$/', $token, $n)) {
                        foreach ($names[(int) $n[1]] as $name) {
                            if ($valid($name) && \count($inner) < 100) {
                                $inner[$name][] = \count($repeated);
                            }
                        }
                        continue;
                    }
                    if (null === $track = self::gridTrack($token, $media)) {
                        return null;
                    }
                    $repeated[] = $track;
                }
                if (!$repeated) {
                    return null;
                }
                if (ctype_digit($m[1])) {
                    for ($i = 0; $i < min(1000, (int) $m[1]); ++$i) {
                        foreach ($inner as $name => $offsets) {
                            foreach ($offsets as $offset) {
                                $lines[$name][] = \count($tracks) + 1 + $offset + (null !== $autoRepeat ? 1 : 0);
                            }
                        }
                        array_push($tracks, ...$repeated);
                    }
                    continue;
                }
                // auto-fill and auto-fit fill the room with one repeated track; empty tracks are kept, like auto-fill
                if (null !== $autoRepeat || 1 !== \count($repeated)) {
                    return null;
                }
                $autoRepeat = $repeated[0];
                $autoFit = 'auto-fit' === strtolower($m[1]);
                $repeatAt = \count($tracks);
                $repeatLines = $inner;
                continue;
            }
            if (null === $track = self::gridTrack($part, $media)) {
                return null;
            }
            $tracks[] = $track;
        }
        $lines = array_map(static fn (array $numbers) => array_values(array_unique($numbers)), $lines);

        return new GridTemplate($tracks, $autoRepeat, columnLines: $lines, repeatAt: $repeatAt, repeatLines: $repeatLines, autoFit: $autoFit ?? false);
    }

    /**
     * Parses lines of grid-column or grid-row: "start / end", where each is a line, a line name, the Nth line of a name
     * ("name N"), span N or auto; a name alone is both lines, like the edges of an area.
     *
     * @return array{?int, ?int, int, ?string, ?string}|null The start line, the end line, the span, the start and end names
     */
    private static function gridLines(string $value): ?array
    {
        $sides = array_map('trim', explode('/', strtolower(trim($value))));
        if (\count($sides) > 2 || \in_array('', $sides, true)) {
            return null;
        }
        $parsed = [];
        foreach ($sides as $side) {
            $line = match (true) {
                'auto' === $side => [null, null, null],
                1 === preg_match('/^-?[1-9]\d*$/', $side) => [max(-1000, min(1000, (int) $side)), null, null],
                1 === preg_match('/^span\s+([1-9]\d*)$/', $side, $m) => [null, min(1000, (int) $m[1]), null],
                1 === preg_match('/^-?[a-z_][\w-]*$/', $side) && 'span' !== $side => [null, null, $side],
                // To the Nth line of a name, like span col or span 2 col: LayoutEngine counts them from the other line
                1 === preg_match('/^span\s+(?:([1-9]\d*)\s+)?(-?[a-z_][\w-]*)$/', $side, $m) && !\in_array($m[2], ['span', 'auto'], true) => [null, null, 'span '.$m[2].' '.min(1000, (int) ($m[1] ?: 1))],
                // The Nth line of a name, like a 2 or 2 a: GridTemplate::line() counts them
                1 === preg_match('/^(?:(-?[1-9]\d*)\s+(-?[a-z_][\w-]*)|(-?[a-z_][\w-]*)\s+(-?[1-9]\d*))$/', $side, $m) && !\in_array($m[2] ?: $m[3], ['span', 'auto'], true) => [null, null, ($m[2] ?: $m[3]).' '.max(-1000, min(1000, (int) ($m[1] ?: $m[4])))],
                default => null,
            };
            if (null === $line) {
                return null;
            }
            $parsed[] = $line;
        }
        [$start, $startSpan, $startName] = $parsed[0];
        // A name alone is both lines; the Nth line of a name alone, the start only
        [$end, $endSpan, $endName] = $parsed[1] ?? [null, null, 1 === \count($parsed) && !str_contains($startName ?? ' ', ' ') ? $startName : null];

        return [$start, $end, max(1, min(1000, $startSpan ?? $endSpan ?? 1)), $startName, $endName];
    }

    /**
     * Parses grid-area lines: row-start / column-start / row-end / column-end.
     *
     * @return array{array{?int, ?int, int, ?string, ?string}, array{?int, ?int, int, ?string, ?string}}|null The rows and the columns
     */
    private static function gridArea(string $value): ?array
    {
        $parts = array_map('trim', explode('/', trim($value)));
        if (\count($parts) < 2 || \count($parts) > 4) {
            return null;
        }
        $rows = self::gridLines($parts[0].(isset($parts[2]) ? ' / '.$parts[2] : ''));
        $columns = self::gridLines($parts[1].(isset($parts[3]) ? ' / '.$parts[3] : ''));

        return null === $rows || null === $columns ? null : [$rows, $columns];
    }

    /**
     * @param array<string, string> $declarations
     */
    private static function gridPlacement(array $declarations): ?GridPlacement
    {
        $keys = ['grid-area', 'grid-row', 'grid-column', 'grid-row-start', 'grid-row-end', 'grid-column-start', 'grid-column-end'];
        if (!array_intersect_key($declarations, array_flip($keys))) {
            return null;
        }
        // In the order of the cascade: a shorthand sets its longhands declared before it, like the md:row-auto of
        // Tailwind after its row-start-2, and those after it win over it
        $longhands = [
            'grid-area' => ['grid-row', 'grid-column', 'grid-row-start', 'grid-row-end', 'grid-column-start', 'grid-column-end'],
            'grid-row' => ['grid-row-start', 'grid-row-end'],
            'grid-column' => ['grid-column-start', 'grid-column-end'],
        ];
        $placed = [];
        foreach ($declarations as $property => $value) {
            if (\in_array($property, $keys, true)) {
                foreach ($longhands[$property] ?? [] as $longhand) {
                    unset($placed[$longhand]);
                }
                $placed[$property] = $value;
            }
        }
        $declarations = $placed;

        $area = $declarations['grid-area'] ?? null;
        $placement = null !== $area && preg_match('/^-?[a-z_][\w-]*$/i', trim($area)) && 'auto' !== strtolower(trim($area)) ? new GridPlacement(trim($area)) : new GridPlacement();
        if (null !== $area && str_contains($area, '/') && null !== $lines = self::gridArea($area)) {
            $placement = $placement->withRows(...$lines[0])->withColumns(...$lines[1]);
        }
        foreach (['row' => 'withRows', 'column' => 'withColumns'] as $axis => $with) {
            $lines = isset($declarations['grid-'.$axis]) ? self::gridLines($declarations['grid-'.$axis]) : null;
            $start = self::gridLines($declarations['grid-'.$axis.'-start'] ?? '');
            $end = self::gridLines($declarations['grid-'.$axis.'-end'] ?? '');
            if (null !== $lines || null !== $start || null !== $end) {
                // The longhands win over the shorthand
                $lines ??= [null, null, 1, null, null];
                $placement = $placement->{$with}(
                    null !== $start ? $start[0] : $lines[0],
                    null !== $end ? $end[0] : $lines[1],
                    null !== $start && 1 !== $start[2] ? $start[2] : (null !== $end && 1 !== $end[2] ? $end[2] : $lines[2]),
                    null !== $start ? $start[3] : $lines[3],
                    // A name alone in grid-*-start is that line only
                    null !== $end ? $end[3] : (null !== $start ? null : $lines[4]),
                );
            }
        }

        return $placement;
    }

    /**
     * Parses grid-template-areas: one string per row, a name per cell, . for an empty one; each name a rectangle.
     *
     * @return array<string, array{int, int, int, int}>|null
     */
    private static function gridAreas(string $value): ?array
    {
        if ('none' === strtolower(trim($value))) {
            return [];
        }
        if (!preg_match_all('/"([^"]*)"|\'([^\']*)\'/', $value, $m, \PREG_SET_ORDER) || '' !== trim((string) preg_replace('/"[^"]*"|\'[^\']*\'/', '', $value))) {
            return null;
        }
        $cells = [];
        $width = null;
        foreach ($m as $index => $match) {
            $text = '' !== ($match[1] ?? '') ? $match[1] : ($match[2] ?? '');
            $names = preg_split('/\s+/', trim($text)) ?: [];
            if (null !== $width && \count($names) !== $width) {
                return null;
            }
            $width = \count($names);
            foreach ($names as $column => $name) {
                if (!preg_match('/^\.+$/', $name)) {
                    $cells[$name][] = [$index, $column];
                }
            }
        }

        $areas = [];
        foreach ($cells as $name => $positions) {
            $rows = array_column($positions, 0);
            $columns = array_column($positions, 1);
            $area = [min($rows), min($columns), max($rows), max($columns)];
            // A name must fill a rectangle
            if (\count($positions) !== ($area[2] - $area[0] + 1) * ($area[3] - $area[1] + 1)) {
                return null;
            }
            $areas[$name] = $area;
        }

        return $areas;
    }

    /**
     * The fraction of the free height of the grid each row of grid-template-rows takes: its fr, 0 for the others.
     *
     * @return list<float>
     */
    private static function gridRowFractions(string $value, MediaContext $media): array
    {
        if ('none' === strtolower(trim($value)) || null === $template = self::gridTemplate(str_ireplace(['auto-fill', 'auto-fit'], '1', $value), $media)) {
            return [];
        }

        return array_map(static fn (array $track) => 'fr' === $track[0] ? $track[1] : 0.0, $template->tracks);
    }

    /**
     * Parses grid-template-rows into the least height of each row, null for its content.
     *
     * @return list<int|null>|null
     */
    private static function gridRows(string $value, MediaContext $media): ?array
    {
        if ('none' === strtolower(trim($value))) {
            return [];
        }
        $template = self::gridTemplate(str_ireplace(['auto-fill', 'auto-fit'], '1', $value), $media);
        if (null === $template) {
            return null;
        }

        return array_map(static fn (array $track) => 'cells' === $track[0] ? (int) $track[1] : (\is_int($track[2]) && $track[2] > 0 ? $track[2] : null), $template->tracks);
    }

    /**
     * The sizes of grid-auto-columns, one per value; null when one is invalid.
     *
     * @return list<array{'cells'|'percent'|'fr'|'auto'|'length', float, int|PercentLength}>|null
     */
    private static function gridAutoColumns(string $value, MediaContext $media): ?array
    {
        $tracks = [];
        foreach (Value::split($value) as $part) {
            if (null === $track = self::gridTrack($part, $media)) {
                return null;
            }
            $tracks[] = $track;
        }

        return $tracks ?: null;
    }

    /**
     * @return array{'cells'|'percent'|'fr'|'auto'|'length', float, int|PercentLength}|null
     */
    private static function gridTrack(string $track, MediaContext $media): ?array
    {
        $track = strtolower(trim($track));
        if (preg_match('/^minmax\((.*)\)$/', $track, $m)) {
            $arguments = Selector::splitTopLevel($m[1]);
            $min = 2 === \count($arguments) ? self::gridTrack($arguments[0], $media) : null;
            $max = 2 === \count($arguments) ? self::gridTrack($arguments[1], $media) : null;
            if (null === $min || null === $max || 'fr' === $min[0]) {
                return null;
            }
            // The minimum, in cells or of the width of the grid: a length, or the content for auto, min-content and max-content
            $minimum = match ($min[0]) {
                'cells' => (int) $min[1],
                'percent' => new PercentLength(true, [[$min[1], 0.0]]),
                'length' => $min[2],
                default => 0,
            };
            if (\is_int($minimum) && 'cells' === $max[0]) {
                return ['cells', max($max[1], $minimum), 0];
            }
            // A size: the largest of it and the minimum, like CSS
            $size = match ($max[0]) {
                'cells' => [0.0, $max[1]],
                'percent' => [$max[1], 0.0],
                'length' => $max[2],
                default => null,
            };

            return null !== $size ? ['length', 0.0, new PercentLength(true, [$size, \is_int($minimum) ? [0.0, (float) $minimum] : $minimum])] : [$max[0], $max[1], $minimum];
        }

        return match (true) {
            // Sized by their content; auto stretches over the room left, the others do not: a size of 1 tells them
            'auto' === $track => ['auto', 0.0, 0],
            \in_array($track, ['min-content', 'max-content', 'fit-content'], true) => ['auto', 1.0, 0],
            1 === preg_match('/^(\d+(?:\.\d+)?)fr$/', $track, $m) => ['fr', (float) $m[1], 0],
            1 === preg_match('/^(\d+(?:\.\d+)?)%$/', $track, $m) => ['percent', (float) $m[1], 0],
            // Of the width of the grid, like calc(100% - 2ch) or min(20ch, 50%)
            str_contains($track, '%') => null !== ($size = Length::linear($track, $media) ?? self::percentLength($track, $media)) ? ['length', 0.0, \is_array($size) ? new PercentLength(true, [$size]) : $size] : null,
            default => null !== ($cells = Length::toCells($track, $media)) && $cells >= 0 ? ['cells', (float) $cells, 0] : null,
        };
    }

    /**
     * The first shadow of a box-shadow list that is not inset, or the first inset one, its color blended over what is behind.
     */
    /**
     * The ring of a box-shadow: its widest shadow spread around the box, not moved nor blurred, like the focus ring of
     * a page, as a line hugging the box from outside, thin up to 2px, else thick; null without one.
     */
    private static function ring(string $value, MediaContext $media, ?Color $backdrop): ?Outline
    {
        $ring = null;
        $widest = 0.0;
        $inset = false;
        foreach (Selector::splitTopLevel($value) as $layer) {
            if (null === $spread = self::ringSpread($layer, $media, true)) {
                continue;
            }
            $layerInset = \in_array('inset', array_map(strtolower(...), Value::split($layer)), true);
            $color = null;
            foreach (Value::split($layer) as $part) {
                if (null === Length::toColumns($part, $media) && false !== $found = Value::color($part, $media, $backdrop)) {
                    $color = $found;
                }
            }
            if ($spread > $widest && null !== $color) {
                [$widest, $ring, $inset] = [$spread, $color, $layerInset];
            }
        }
        if (null === $ring) {
            return null;
        }

        // Spread inside, like the ring-inset of Tailwind or the frame of the notes of PHP.net: along the edge cells of
        // the box, not over what is around it; a column is 8 CSS pixels wide
        return $inset ? new Outline(BorderPattern::RING_INSET, $ring, -1) : new Outline($widest * 8 <= 2.0 ? BorderPattern::RING : BorderPattern::RING_THICK, $ring);
    }

    /**
     * The spread, in columns, of a shadow spread around the box, not moved nor blurred; null for another.
     *
     * @param bool $insetToo Whether one spread inside the box by a few pixels counts too, like the ring-1 ring-inset of
     *                       Tailwind around its fields: at the edge of the box all the same, a cell being 8 pixels wide
     */
    private static function ringSpread(string $layer, MediaContext $media, bool $insetToo = false): ?float
    {
        $lengths = [];
        $inset = false;
        foreach (Value::split($layer) as $part) {
            if ('inset' === strtolower($part)) {
                $inset = true;
            } elseif (null !== $columns = Length::toColumns($part, $media)) {
                $lengths[] = $columns;
            }
        }
        if ($inset && (!$insetToo || 4 !== \count($lengths) || $lengths[3] * 8 > 4)) {
            return null;
        }

        return 4 === \count($lengths) && 0.0 === $lengths[0] && 0.0 === $lengths[1] && 0.0 === $lengths[2] && $lengths[3] > 0 ? $lengths[3] : null;
    }

    /**
     * The color of the last inset shadow spread over the whole box, not moved nor blurred, like the inset 0 0 0 9999px
     * of the stripes of a table of Bootstrap: over the background, blended with it; null when it is transparent, false
     * without one.
     */
    private static function insetFill(string $value, MediaContext $media, ?Color $background): Color|false|null
    {
        $fill = false;
        // The first layer is drawn on top
        foreach (array_reverse(Selector::splitTopLevel($value)) as $layer) {
            $lengths = [];
            $color = null;
            $inset = false;
            foreach (Value::split($layer) as $part) {
                if ('inset' === strtolower($part)) {
                    $inset = true;
                } elseif (null !== $cells = Length::toCells($part, $media)) {
                    $lengths[] = [$part, $cells];
                } elseif (false !== $c = Value::color($part, $media, $fill ?: $background)) {
                    $color = $c;
                } else {
                    return false;
                }
            }
            if ($inset && self::fillsTheBox($lengths)) {
                $fill = $color ?? (false === $fill ? null : $fill);
            }
        }

        return $fill;
    }

    /**
     * Whether the lengths of an inset shadow spread it over any box: no offset nor blur, a spread of a hundred cells.
     *
     * @param list<array{string, float|int}> $lengths
     */
    private static function fillsTheBox(array $lengths): bool
    {
        return 4 === \count($lengths) && 0 == $lengths[0][1] && 0 == $lengths[1][1] && 0 == $lengths[2][1] && $lengths[3][1] >= 100;
    }

    private static function shadow(string $value, MediaContext $media, ?Color $backdrop, bool $inset = false): ?Shadow
    {
        $wanted = $inset;
        foreach (Selector::splitTopLevel($value) as $layer) {
            $lengths = [];
            $color = null;
            $inset = false;
            foreach (Value::split($layer) as $part) {
                if ('inset' === strtolower($part)) {
                    $inset = true;
                } elseif (null !== $cells = Length::toCells($part, $media)) {
                    $lengths[] = [$part, $cells];
                } elseif (false !== $c = Value::color($part, $media, $backdrop)) {
                    $color = $c;
                } else {
                    return null;
                }
            }
            if (\count($lengths) < 2 || \count($lengths) > 4) {
                return null;
            }
            // An inset one over the whole box is a background, see insetFill()
            if ($inset !== $wanted || ($inset && self::fillsTheBox($lengths))) {
                continue;
            }
            // A ring, see ring(): drawn around the box, not as the shadow
            if (null !== self::ringSpread($layer, $media, true)) {
                continue;
            }

            // Rows are twice as tall as columns: the vertical offset is in rows
            $y = Length::toCells($lengths[1][0], $media, vertical: true) ?? $lengths[1][1];
            [$x, $blur, $spread] = [$lengths[0][1], $lengths[2][1] ?? 0, $lengths[3][1] ?? 0];
            // Outside, whole cells, from three quarters of one: a shadow of a pixel or two, like the 0 1px 2px of the
            // fields of EasyAdmin, a hairline in browsers, takes no room
            if (!$inset) {
                $x = Length::toWholeCells($lengths[0][0], $media, false) ?? $x;
                $y = Length::toWholeCells($lengths[1][0], $media, true) ?? $y;
                $blur = isset($lengths[2]) ? Length::toWholeCells($lengths[2][0], $media, false) ?? $blur : 0;
                $spread = isset($lengths[3]) ? Length::toWholeCells($lengths[3][0], $media, false) ?? $spread : 0;
            }

            // The blur in columns, as long as anything; the spread in columns, sideways and down alike
            return new Shadow($x, $y, $color ?? Value::color('rgba(0, 0, 0, .5)', $media, $backdrop) ?: null, $inset, max(0, $blur), $spread);
        }

        return $wanted ? null : new Shadow(0, 0);
    }

    /**
     * Multi-column layout from columns, column-count and column-width, with the gap and the rule between them.
     *
     * @param array<string, string> $declarations
     */
    private static function columns(array $declarations, MediaContext $media): ?Columns
    {
        $count = null;
        $width = null;
        foreach (Value::split(strtolower($declarations['columns'] ?? '')) as $part) {
            if (preg_match('/^\d{1,3}$/', $part)) {
                $count = (int) $part;
            } elseif ('auto' !== $part) {
                $width = Length::toCells($part, $media);
            }
        }
        if (isset($declarations['column-count']) && preg_match('/^\d{1,3}$/', trim($declarations['column-count']))) {
            $count = (int) trim($declarations['column-count']);
        }
        if (isset($declarations['column-width']) && 'auto' !== strtolower(trim($declarations['column-width']))) {
            $width = Length::toCells($declarations['column-width'], $media);
        }
        if ((null === $count || $count < 2) && null === $width) {
            return null;
        }

        // The gap is 1em by default, two columns
        $gapValue = strtolower(trim($declarations['column-gap'] ?? (isset($declarations['gap']) ? (Value::split($declarations['gap'])[1] ?? Value::split($declarations['gap'])[0] ?? 'normal') : 'normal')));
        $gap = 'normal' === $gapValue ? 2 : (Length::toCells($gapValue, $media) ?? 2);

        $rule = null;
        $color = null;
        $ruleStyle = strtolower(trim($declarations['column-rule-style'] ?? 'none'));
        $ruleWidth = strtolower(trim($declarations['column-rule-width'] ?? ''));
        foreach (Value::split(strtolower($declarations['column-rule'] ?? '')) as $part) {
            if (\in_array($part, ['none', 'hidden', 'solid', 'dashed', 'dotted', 'double', 'groove', 'ridge', 'inset', 'outset'], true)) {
                $ruleStyle = $part;
            } elseif (null !== self::borderWidth($part, $media)) {
                $ruleWidth = isset($declarations['column-rule-width']) ? $ruleWidth : $part;
            } elseif (($c = Value::color($part, $media)) instanceof Color) {
                $color = $c;
            }
        }
        if (isset($declarations['column-rule-color']) && ($c = Value::color($declarations['column-rule-color'], $media)) instanceof Color) {
            $color = $c;
        }
        if ('' === $ruleWidth || 0 !== (self::borderWidth($ruleWidth, $media) ?? 1)) {
            $rule = match ($ruleStyle) {
                'none', 'hidden' => null,
                'dashed' => '╎',
                'dotted' => '┊',
                'double' => '║',
                default => '│',
            };
        }

        return new Columns($count, $width, $gap, $rule, $media->forcedColors ? null : $color, 'auto' !== strtolower(trim($declarations['column-fill'] ?? '')));
    }

    /**
     * The columns and rows a transform translates by, and its percentages of the element itself, null when it is not
     * only translations.
     *
     * @return array{int, int, float, float}|null
     */
    private static function translation(string $value, MediaContext $media): ?array
    {
        $m = self::transformFunctions($value, $media);
        $m = null !== $m ? array_values(array_filter($m, static fn (array $function) => \in_array(strtolower($function[1]), ['translate', 'translatex', 'translatey'], true))) : [];
        if (!$m) {
            return null;
        }
        $x = 0;
        $y = 0;
        $px = 0.0;
        $py = 0.0;
        $percent = static fn (string $value): ?float => preg_match('/^(-?\d*\.?\d+)%$/', $value, $p) ? (float) $p[1] : null;
        foreach ($m as [, $function, $arguments]) {
            // Split at its own commas, not those of a function in it, like the min() of the sidebar width of mdBook
            $values = array_map('trim', Selector::splitTopLevel($arguments));
            $function = strtolower($function);
            if (('translate' === $function && \count($values) > 2) || ('translate' !== $function && 1 !== \count($values))) {
                return null;
            }
            $dx = 'translatey' === $function ? '0' : $values[0];
            $dy = 'translatex' === $function ? '0' : ('translatey' === $function ? $values[0] : ($values[1] ?? '0'));
            // A percentage is of the element itself: resolved once its size is known; mixed with lengths too, like the
            // translateX(calc(var(--i) * 100%)) of the glider of the tabs of the exception page of Symfony
            if (null !== $p = $percent($dx)) {
                $px += $p;
                $cx = 0;
            } elseif (null === $cx = Length::toCells($dx, $media)) {
                if (null !== $linear = Length::linear($dx, $media)) {
                    $px += $linear[0];
                    $cx = (int) round($linear[1]);
                }
            }
            if (null !== $p = $percent($dy)) {
                $py += $p;
                $cy = 0;
            } elseif (null === $cy = Length::toCells($dy, $media, vertical: true)) {
                if (null !== $linear = Length::linear($dy, $media, true)) {
                    $py += $linear[0];
                    $cy = (int) round($linear[1]);
                }
            }
            if (null === $cx || null === $cy) {
                return null;
            }
            $x += $cx;
            $y += $cy;
        }

        return [max(-1000, min(1000, $x)), max(-1000, min(1000, $y)), $px, $py];
    }

    /**
     * The functions of a transform, each one the whole call, its name and its arguments, translate3d() as the
     * translate() of its x and y; null when it is not a transform. Those that scale or turn are kept, cells being
     * neither scaled nor turned: translation() leaves them out.
     *
     * @return list<array{string, string, string}>|null
     */
    private static function transformFunctions(string $value, MediaContext $media): ?array
    {
        $value = trim($value);
        if (!preg_match_all('/\s*(translate|translateX|translateY|translateZ|translate3d|scale|scaleX|scaleY|scaleZ|scale3d|rotate|rotateX|rotateY|rotateZ|rotate3d|skew|skewX|skewY|matrix|matrix3d|perspective)\(\s*((?:[^()]|\((?:[^()]|\([^()]*\))*\))*)\)/iA', $value, $m, \PREG_SET_ORDER) || implode('', array_column($m, 0)) !== $value) {
            return null;
        }
        foreach ($m as $i => [$call, $function, $arguments]) {
            if ('translate3d' === strtolower($function)) {
                $values = array_map('trim', Selector::splitTopLevel($arguments));
                if (3 !== \count($values) || null === Length::toCells($values[2], $media)) {
                    return null;
                }
                $m[$i] = [$call, 'translate', $values[0].', '.$values[1]];
            }
        }

        return $m;
    }

    /**
     * A ratio like 16 / 9 or 1.5, null when it is not one.
     */
    private static function ratio(string $value): ?float
    {
        $value = preg_replace('/^auto\s+|\s+auto$/i', '', trim($value)) ?? '';
        if (!preg_match('/^(\d+(?:\.\d+)?)(?:\s*\/\s*(\d+(?:\.\d+)?))?$/', $value, $m) || (float) $m[1] <= 0 || (isset($m[2]) && (float) $m[2] <= 0)) {
            return null;
        }

        return max(0.01, min(100.0, (float) $m[1] / (float) ($m[2] ?? 1)));
    }

    /**
     * A fallback of position-try-fallbacks: a @position-try name, flip tactics, or a position-area; null when invalid.
     *
     * @return array{?string, list<'flip-block'|'flip-inline'>, ?string}|null The name, the tactics, and the area
     */
    private static function positionTry(string $fallback): ?array
    {
        $fallback = trim($fallback);
        if (null !== self::positionArea($fallback)) {
            return [null, [], $fallback];
        }
        $name = null;
        $tactics = [];
        foreach (Value::split($fallback) as $part) {
            if (str_starts_with($part, '--') && null === $name && preg_match('/^--[a-z_][\w-]*$/i', $part)) {
                $name = $part;
            } elseif (\in_array($part, ['flip-block', 'flip-inline'], true)) {
                $tactics[] = $part;
            } else {
                return null;
            }
        }

        return null === $name && !$tactics ? null : [$name, $tactics, null];
    }

    /**
     * Whether a size uses anchor-size(), alone or in a math function, like calc(anchor-size(width) + 2ch).
     */
    private static function isAnchorSize(string $value): bool
    {
        return 1 === preg_match('/(?<![\w-])anchor-size\(/i', $value);
    }

    /**
     * A size after an anchor: anchor-size([name] [dimension], [fallback]), alone or in a math function linear in it,
     * like calc(anchor-size(width) / 2 + 1ch); null when it is not one.
     *
     * @return array{?string, 'width'|'height', float, int, ?int}|null The anchor name, its dimension, a factor and an offset in cells, and the size without the anchor
     */
    private static function anchorSize(string $value, bool $vertical, MediaContext $media): ?array
    {
        $value = trim($value);
        if (1 !== preg_match_all('/(?<![\w-])anchor-size\(/i', $value, $all, \PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $start = $all[0][0][1];
        $end = Value::closingParenthesis($value, $start + 11);
        $arguments = Selector::splitTopLevel(substr($value, $start + 12, $end - $start - 12));
        $parts = Value::split(strtolower(trim($arguments[0] ?? '')));
        $name = isset($parts[0]) && str_starts_with($parts[0], '--') ? array_shift($parts) : null;
        $dimension = match ($parts[0] ?? null) {
            null => $vertical ? 'height' : 'width',
            'width', 'inline', 'self-inline' => 'width',
            'height', 'block', 'self-block' => 'height',
            default => null,
        };
        if (null === $dimension || \count($parts) > 1) {
            return null;
        }
        // In cells, for an anchor of no size and of 1000 cells: the size is linear in it
        $with = static fn (string $cells): ?int => Length::toCells(substr($value, 0, $start).$cells.substr($value, $end + 1), $media, $vertical);
        $offset = $with('0');
        $large = $with('1000');
        if (null === $offset || null === $large) {
            return null;
        }
        $fallback = trim(implode(', ', \array_slice($arguments, 1)));
        $fallback = '' === $fallback ? null : $with(0 === $start && $end === \strlen($value) - 1 ? $fallback : '('.$fallback.')');

        return [$name, $dimension, ($large - $offset) / 1000, $offset, $fallback];
    }

    /**
     * The order a position-try value starts with, its logical names physical, and the rest of it.
     *
     * @return array{'normal'|'most-width'|'most-height'|null, string}
     */
    private static function positionTryOrder(string $value): array
    {
        if (!preg_match('/^\s*(normal|most-width|most-height|most-inline-size|most-block-size)(?![\w-])\s*(.*)$/is', $value, $m)) {
            return [null, trim($value)];
        }
        $order = match (strtolower($m[1])) {
            'most-width', 'most-inline-size' => 'most-width',
            'most-height', 'most-block-size' => 'most-height',
            default => 'normal',
        };

        return [$order, trim($m[2])];
    }

    /**
     * The anchor and edge of an anchor() inset, like anchor(--menu bottom), null when it is not one.
     *
     * @return array{?string, 'top'|'right'|'bottom'|'left'|'center'}|null
     */
    private static function anchorEdge(string $value): ?array
    {
        if (!preg_match('/^anchor\(\s*(--[a-z_][\w-]*\s+)?(top|right|bottom|left|center|start|end)\s*\)$/i', trim($value), $m)) {
            return null;
        }
        $edge = match (strtolower($m[2])) {
            'start', 'left' => 'left',
            'end', 'right' => 'right',
            'top' => 'top',
            'bottom' => 'bottom',
            default => 'center',
        };

        return ['' !== trim($m[1]) ? trim($m[1]) : null, $edge];
    }

    /**
     * The conditions of position-visibility, [] for always; null when invalid.
     *
     * @return list<'anchors-valid'|'anchors-visible'|'no-overflow'>|null
     */
    private static function positionVisibility(string $value): ?array
    {
        $parts = Value::split(strtolower(trim($value)));
        if (['always'] === $parts) {
            return [];
        }

        return $parts && \count($parts) === \count(array_unique($parts)) && !array_diff($parts, ['anchors-valid', 'anchors-visible', 'no-overflow']) ? $parts : null;
    }

    /**
     * A position-area: where next to the anchor along the rows, then along the columns.
     *
     * @return array{string, string}|null
     */
    private static function positionArea(string $value): ?array
    {
        $parts = Value::split(strtolower(trim($value)));
        // The logical keywords, in a horizontal writing mode from the left: x and inline are the row, y and block the
        // column; start and end alone are of the block axis first, then of the inline one, both when alone
        $logical = static function (string $part, ?string $axis) {
            $span = str_starts_with($part, 'span-') ? 'span-' : '';
            $name = preg_replace('/^(?:span-)?(?:self-)?/', '', $part);
            [$side, $axis] = match ($name) {
                'x-start', 'inline-start' => ['start', 'inline'],
                'x-end', 'inline-end' => ['end', 'inline'],
                'y-start', 'block-start' => ['start', 'block'],
                'y-end', 'block-end' => ['end', 'block'],
                'start', 'end' => [$name, $axis],
                default => [null, null],
            };
            if (null === $side || null === $axis) {
                return $part;
            }

            return $span.('inline' === $axis ? ('start' === $side ? 'left' : 'right') : ('start' === $side ? 'top' : 'bottom'));
        };
        if (1 === \count($parts) && \in_array(preg_replace('/^(?:span-)?(?:self-)?/', '', $parts[0]), ['start', 'end'], true)) {
            $parts = [$logical($parts[0], 'block'), $logical($parts[0], 'inline')];
        } else {
            $parts = array_map(static fn (string $part, int $i) => $logical($part, 2 === \count($parts) ? (0 === $i ? 'block' : 'inline') : null), $parts, array_keys($parts));
        }
        $block = ['top', 'center', 'bottom', 'span-top', 'span-bottom', 'span-all'];
        $inline = ['left', 'center', 'right', 'span-left', 'span-right', 'span-all'];
        if (1 === \count($parts)) {
            return match (true) {
                \in_array($parts[0], ['top', 'bottom', 'span-top', 'span-bottom'], true) => [$parts[0], 'center'],
                \in_array($parts[0], ['left', 'right', 'span-left', 'span-right'], true) => ['center', $parts[0]],
                'center' === $parts[0] => ['center', 'center'],
                default => null,
            };
        }
        if (2 !== \count($parts)) {
            return null;
        }
        // Either order, like CSS: the block keyword is the one that is only a block keyword
        if (\in_array($parts[0], ['left', 'right', 'span-left', 'span-right'], true) || \in_array($parts[1], ['top', 'bottom', 'span-top', 'span-bottom'], true)) {
            $parts = [$parts[1], $parts[0]];
        }

        return \in_array($parts[0], $block, true) && \in_array($parts[1], $inline, true) ? [$parts[0], $parts[1]] : null;
    }

    /**
     * Where something is in a box, like object-position: fractions of the room left, from the left and the top.
     *
     * @return array{float, float}|null
     */
    /**
     * Reads a shape-outside: a basic shape, a box, or both; null for none and for an image, which gives the box.
     */
    private function shapeOutside(string $value, MediaContext $media): FloatShape|false|null
    {
        $value = trim($value);
        // An image: its pixels; with forced colors, or one that cannot be read, a gradient too, the box
        [$source, $resolution] = self::imageSet($value, $media) ?? [$value, 1.0];
        if (null !== $url = self::url($source)) {
            $image = $media->forcedColors ? null : $this->image($url, $resolution);
            $cell = $media->getCellSize();

            return null !== $image ? new FloatShape('image', 'margin-box', rowRatio: $cell->height / $cell->width, image: $image) : null;
        }
        $value = strtolower($value);
        if ('none' === $value || preg_match('/^(?:url|image|image-set|(?:repeating-)?(?:linear|radial|conic)-gradient)\(.*\)$/s', $value)) {
            return null;
        }
        $box = null;
        $shape = null;
        foreach (Value::split($value) as $part) {
            if (\in_array($part, FloatShape::BOXES, true) && null === $box) {
                $box = $part;
            } elseif (null === $shape && preg_match('/^(circle|ellipse|inset|polygon)\((.*)\)$/s', $part, $m)) {
                $shape = [$m[1], trim($m[2])];
            } else {
                return false;
            }
        }
        $cell = $media->getCellSize();
        $ratio = $cell->height / $cell->width;
        if (null === $shape) {
            return null === $box ? false : new FloatShape('box', $box, rowRatio: $ratio);
        }
        $box ??= 'margin-box';
        // A length in columns, or a percentage of the box; null when invalid, like a negative radius
        $length = static function (string $part, bool $negative = false) use ($media): float|string|null {
            if (preg_match('/^(-?(?:\d+\.?\d*|\.\d+))%$/', $part, $m)) {
                // Within a hundred times the box either way, a number too long to be finite included
                $percent = max(-10000.0, min(10000.0, (float) $m[1]));

                return $negative || $percent >= 0 ? $percent.'%' : null;
            }
            $columns = Length::toColumns($part, $media);

            return null !== $columns && ($negative || $columns >= 0) ? $columns : null;
        };
        [$name, $arguments] = $shape;

        if ('circle' === $name || 'ellipse' === $name) {
            $parts = Value::split($arguments);
            $at = array_search('at', $parts, true);
            $radii = false === $at ? $parts : \array_slice($parts, 0, $at);
            $position = null;
            if (false !== $at) {
                $position = self::objectPosition(implode(' ', \array_slice($parts, $at + 1)), $media);
                if (null === $position) {
                    return false;
                }
            }
            if (\count($radii) > ('circle' === $name ? 1 : 2) || ('ellipse' === $name && 1 === \count($radii))) {
                return false;
            }
            $values = [];
            foreach ($radii as $radius) {
                $values[] = \in_array($radius, ['closest-side', 'farthest-side'], true) ? $radius : ($length($radius) ?? false);
                if (false === end($values)) {
                    return false;
                }
            }

            return new FloatShape($name, $box, $values, $position, rowRatio: $ratio);
        }

        if ('inset' === $name) {
            $round = preg_split('/\s+round\s+/', $arguments);
            if (\count($round) > 2) {
                return false;
            }
            $sides = Value::split($round[0]);
            if (!$sides || \count($sides) > 4) {
                return false;
            }
            $insets = [];
            foreach ($sides as $side) {
                if (null === $insets[] = $length($side, true)) {
                    return false;
                }
            }
            // Like margin: top, right, bottom, left, the missing ones from the opposite side
            $insets = [$insets[0], $insets[1] ?? $insets[0], $insets[2] ?? $insets[0], $insets[3] ?? $insets[1] ?? $insets[0]];
            $radii = [];
            if (isset($round[1])) {
                $axes = explode('/', $round[1]);
                if (\count($axes) > 2) {
                    return false;
                }
                $corners = [];
                foreach ($axes as $axis) {
                    $values = Value::split(trim($axis));
                    if (!$values || \count($values) > 4) {
                        return false;
                    }
                    $lengths = [];
                    foreach ($values as $radius) {
                        if (null === $lengths[] = $length($radius)) {
                            return false;
                        }
                    }
                    $corners[] = [$lengths[0], $lengths[1] ?? $lengths[0], $lengths[2] ?? $lengths[0], $lengths[3] ?? $lengths[1] ?? $lengths[0]];
                }
                $corners[1] ??= $corners[0];
                for ($i = 0; $i < 4; ++$i) {
                    array_push($radii, $corners[0][$i], $corners[1][$i]);
                }
            }

            return new FloatShape('inset', $box, $insets, radii: $radii, rowRatio: $ratio);
        }

        $points = Selector::splitTopLevel($arguments);
        if ($points && \in_array(trim($points[0]), ['nonzero', 'evenodd'], true)) {
            array_shift($points);
        }
        $values = [];
        foreach ($points as $point) {
            $xy = Value::split(trim($point));
            if (2 !== \count($xy) || null === ($x = $length($xy[0], true)) || null === ($y = $length($xy[1], true))) {
                return false;
            }
            array_push($values, $x, $y);
        }

        return \count($values) >= 6 ? new FloatShape('polygon', $box, $values, rowRatio: $ratio) : false;
    }

    private static function objectPosition(string $value, MediaContext $media): ?array
    {
        $parts = Value::split(strtolower(trim($value)));
        if (!$parts || \count($parts) > 4) {
            return null;
        }
        $vertical = static fn (string $part) => \in_array($part, ['top', 'bottom'], true);
        $horizontal = static fn (string $part) => \in_array($part, ['left', 'right'], true);
        // Each component: its axis when a keyword says it (x, y, or null), the fraction of the room left, cells from it
        $components = [];
        foreach ($parts as $part) {
            if (\in_array($part, ['left', 'right', 'top', 'bottom', 'center'], true)) {
                $components[] = [$horizontal($part) ? 'x' : ($vertical($part) ? 'y' : null), \in_array($part, ['right', 'bottom'], true) ? 1.0 : ('center' === $part ? 0.5 : 0.0), 0, $part];
                continue;
            }
            $fraction = preg_match('/^(-?\d*\.?\d+)%$/', $part, $m) ? max(-10.0, min(10.0, (float) $m[1] / 100)) : null;
            // A length, in cells on the axis it ends up on
            $cells = null === $fraction ? Length::toCells($part, $media) : null;
            if (null === $fraction && null === $cells) {
                return null;
            }
            $cells = null !== $cells ? $part : null;
            $last = array_key_last($components);
            // An offset after an edge keyword, in three or four values: from that edge, inwards
            if (\count($parts) > 2 && null !== $last && \in_array($components[$last][3], ['left', 'right', 'top', 'bottom'], true) && 0 === $components[$last][2] && 4 === \count($components[$last])) {
                // From the right or the bottom, inwards: negated, a negative length outwards
                $fromEnd = \in_array($components[$last][3], ['right', 'bottom'], true);
                $components[$last][2] = null === $cells ? 0 : ($fromEnd ? (str_starts_with($cells, '-') ? substr($cells, 1) : '-'.$cells) : $cells);
                $components[$last][1] += null !== $fraction ? (\in_array($components[$last][3], ['right', 'bottom'], true) ? -$fraction : $fraction) : 0.0;
                $components[$last][] = true;
                continue;
            }
            $components[] = [null, $fraction ?? 0.0, $cells ?? 0, ''];
        }
        if (\count($components) > 2) {
            return null;
        }
        // The keywords on their axis, the others where they are: the first one horizontal, a lone one centered the other way
        $x = $y = null;
        foreach ($components as $component) {
            if ('x' === $component[0]) {
                $x = null === $x ? $component : null;
            } elseif ('y' === $component[0]) {
                $y = null === $y ? $component : null;
            }
            if (('x' === $component[0] && null === $x) || ('y' === $component[0] && null === $y)) {
                return null;
            }
        }
        foreach ($components as $component) {
            if (null === $component[0]) {
                if (null === $x) {
                    $x = $component;
                } elseif (null === $y) {
                    $y = $component;
                } else {
                    return null;
                }
            }
        }
        $x ??= [null, 0.5, 0, 'center'];
        $y ??= [null, 0.5, 0, 'center'];

        $cellsOf = static function (int|string $length, bool $vertical) use ($media): int {
            if (\is_int($length)) {
                return $length;
            }
            $negative = str_starts_with($length, '-');
            $cells = Length::toCells(ltrim($length, '-'), $media, $vertical) ?? 0;

            return max(-1000, min(1000, $negative ? -$cells : $cells));
        };
        $offsetX = $cellsOf($x[2], false);
        $offsetY = $cellsOf($y[2], true);

        return 0 === $offsetX && 0 === $offsetY ? [$x[1], $y[1]] : [$x[1], $y[1], $offsetX, $offsetY];
    }

    private static function justifyKeyword(string $value): ?string
    {
        return match (strtolower(trim($value))) {
            'start', 'self-start', 'flex-start', 'left' => 'start',
            'center' => 'center',
            'end', 'self-end', 'flex-end', 'right' => 'end',
            'stretch', 'normal', 'auto', 'legacy' => 'stretch',
            default => null,
        };
    }

    /**
     * An integer, or a math function over numbers rounded to the nearest one, like calc(sibling-count() - sibling-index()).
     */
    private static function integer(string $value): ?int
    {
        $value = trim($value);
        if (preg_match('/^[+-]?\d{1,9}$/', $value)) {
            return (int) $value;
        }

        return Length::isMath($value) && null !== ($number = Length::number($value)) ? (int) round($number) : null;
    }

    /**
     * A scroll viewport from the vertical overflow: auto or scroll; the longhands win over the shorthand.
     *
     * @param array<string, string> $declarations
     */
    private static function scroll(array $declarations, MediaContext $media): ?Scroll
    {
        $parts = Value::split(strtolower($declarations['overflow'] ?? ''));
        $overflow = strtolower(trim($declarations['overflow-y'] ?? $declarations['overflow-block'] ?? ($parts[1] ?? $parts[0] ?? '')));
        $sideways = strtolower(trim($declarations['overflow-x'] ?? $declarations['overflow-inline'] ?? ($parts[0] ?? '')));
        $scrolling = ['auto', 'scroll', 'overlay'];
        $horizontal = \in_array($sideways, $scrolling, true);
        // Like CSS, visible becomes auto when the other axis scrolls
        $vertical = \in_array($overflow, $scrolling, true) || ($horizontal && \in_array($overflow, ['', 'visible'], true));
        if (!$vertical && !$horizontal) {
            return null;
        }
        $colors = Value::split($declarations['scrollbar-color'] ?? '');
        $thumb = 2 === \count($colors) ? Value::color($colors[0], $media) : null;
        $track = 2 === \count($colors) ? Value::color($colors[1], $media) : null;

        [$paddingTop, $paddingBottom, $paddingLeft, $paddingRight] = self::scrollRoom($declarations, 'scroll-padding', $media) ?? [0, 0, 0, 0];

        return new Scroll(
            'scroll' === $overflow,
            'none' !== strtolower(trim($declarations['scrollbar-width'] ?? '')),
            $thumb instanceof Color && !$media->forcedColors ? $thumb : null,
            $track instanceof Color && !$media->forcedColors ? $track : null,
            $paddingTop,
            $paddingBottom,
            str_starts_with(strtolower(trim($declarations['scrollbar-gutter'] ?? '')), 'stable'),
            // Motion the user asked to reduce: at once
            'smooth' === strtolower(trim($declarations['scroll-behavior'] ?? '')) && !$media->reducedMotion,
            'thin' === strtolower(trim($declarations['scrollbar-width'] ?? '')),
            \in_array(self::overscroll($declarations), ['contain', 'none'], true),
            self::snapType($declarations['scroll-snap-type'] ?? 'none') ?: null,
            $vertical,
            $horizontal,
            'scroll' === $sideways,
            $paddingLeft,
            $paddingRight,
            \in_array(self::overscroll($declarations, true), ['contain', 'none'], true),
            self::snapType($declarations['scroll-snap-type'] ?? 'none', true) ?: null,
        );
    }

    /**
     * The vertical strictness of a scroll-snap-type, or the horizontal one: mandatory or proximity, '' when it does not
     * snap that way, null when invalid.
     */
    private static function snapType(string $value, bool $sideways = false): ?string
    {
        $parts = Value::split(strtolower(trim($value)));
        if (['none'] === $parts) {
            return '';
        }
        if (!$parts || \count($parts) > 2 || !\in_array($parts[0], ['x', 'y', 'block', 'inline', 'both'], true) || !\in_array($parts[1] ?? 'proximity', ['mandatory', 'proximity'], true)) {
            return null;
        }

        return \in_array($parts[0], $sideways ? ['x', 'inline', 'both'] : ['y', 'block', 'both'], true) ? $parts[1] ?? 'proximity' : '';
    }

    /**
     * The vertical overscroll-behavior: the longhands, else the second value of the shorthand, else its first one; the
     * horizontal one: the longhands, else the first value.
     *
     * @param array<string, string> $declarations
     */
    private static function overscroll(array $declarations, bool $sideways = false): string
    {
        $parts = Value::split(strtolower($declarations['overscroll-behavior'] ?? ''));

        return strtolower(trim($sideways
            ? $declarations['overscroll-behavior-x'] ?? $declarations['overscroll-behavior-inline'] ?? ($parts[0] ?? 'auto')
            : $declarations['overscroll-behavior-y'] ?? $declarations['overscroll-behavior-block'] ?? ($parts[1] ?? $parts[0] ?? 'auto')));
    }

    /**
     * The rows above and below, then the columns at the left and right, of scroll-padding or scroll-margin: the
     * shorthand, then the block and inline ones, then the side longhands; null when none is set.
     *
     * @param array<string, string> $declarations
     *
     * @return array{int, int, int, int}|null
     */
    private static function scrollRoom(array $declarations, string $property, MediaContext $media): ?array
    {
        $cells = static fn (string $value, bool $vertical): int => 'auto' === strtolower(trim($value)) ? 0 : max(0, min(100, Length::toCells($value, $media, $vertical) ?? 0));
        $room = null;
        if (isset($declarations[$property])) {
            $parts = Value::split($declarations[$property]);
            $room = [$cells($parts[0] ?? '0', true), $cells($parts[2] ?? $parts[0] ?? '0', true), $cells($parts[3] ?? $parts[1] ?? $parts[0] ?? '0', false), $cells($parts[1] ?? $parts[0] ?? '0', false)];
        }
        foreach (['-block' => [0, 1, true], '-inline' => [2, 3, false]] as $suffix => [$start, $end, $vertical]) {
            if (isset($declarations[$property.$suffix])) {
                $parts = Value::split($declarations[$property.$suffix]);
                $room ??= [0, 0, 0, 0];
                $room[$start] = $cells($parts[0] ?? '0', $vertical);
                $room[$end] = $cells($parts[1] ?? $parts[0] ?? '0', $vertical);
            }
        }
        foreach ([0 => ['-top', '-block-start'], 1 => ['-bottom', '-block-end'], 2 => ['-left', '-inline-start'], 3 => ['-right', '-inline-end']] as $side => $suffixes) {
            foreach ($suffixes as $suffix) {
                if (isset($declarations[$property.$suffix])) {
                    $room ??= [0, 0, 0, 0];
                    $room[$side] = $cells($declarations[$property.$suffix], $side < 2);
                }
            }
        }

        return $room;
    }

    /**
     * Returns a visible outline, from the outline shorthand and its longhands; null without a style, or with none.
     *
     * @param array<string, string> $declarations
     */
    private static function outline(array $declarations, MediaContext $media): ?Outline
    {
        $styles = ['none', 'hidden', 'solid', 'double', 'dotted', 'dashed', 'groove', 'ridge', 'inset', 'outset', 'auto'];
        $parts = Value::split(strtolower($declarations['outline'] ?? ''));
        $style = strtolower(trim($declarations['outline-style'] ?? (array_values(array_intersect($parts, $styles))[0] ?? '')));
        if ('' === $style || 'none' === $style || 'hidden' === $style) {
            return null;
        }

        $width = $declarations['outline-width'] ?? (array_values(array_filter($parts, static fn (string $part) => !\in_array($part, $styles, true) && null !== self::borderWidth($part, $media)))[0] ?? 'medium');
        if (0 === self::borderWidth(strtolower(trim($width)), $media)) {
            return null;
        }
        $pattern = self::borderPattern('auto' === $style ? 'solid' : $style) ?? BorderPattern::NORMAL;
        if (BorderPattern::NORMAL === $pattern && 'thick' === strtolower(trim($width))) {
            $pattern = BorderPattern::HEAVY;
        }

        $color = null;
        foreach (isset($declarations['outline-color']) ? [$declarations['outline-color']] : $parts as $part) {
            // Transparent, like the outline-none of a page that draws its focus otherwise: none to see
            if ('transparent' === $part || (false !== Value::color($part, $media) && 0.0 === Value::alpha($part, $media))) {
                return null;
            }
            if (($found = Value::color($part, $media)) instanceof Color) {
                $color = $found;
                break;
            }
        }

        // A cell from three quarters of one: the 2px of a focus ring is none
        return new Outline($pattern, $color, Length::toWholeCells($declarations['outline-offset'] ?? '0', $media, false) ?? 0);
    }

    private static function withColor(Style $style, string $value, MediaContext $media, string $property): Style|false
    {
        $color = Value::color($value, $media);
        if (false === $color) {
            return false;
        }

        // Without colors, state must be carried by the other cues: drop them all
        if ($media->forcedColors) {
            return $style;
        }

        return 'color' === $property ? $style->withColor($color) : $style->withBackground($color);
    }

    /**
     * Reads a list of filter functions, like grayscale(50%) brightness(1.2).
     */
    /**
     * The filter functions that change colors, and the arguments of the drop-shadow() ones, as box-shadow layers;
     * blur() is left out. Null when a function is not a filter one.
     *
     * @return array{?string, string}
     */
    private static function filterParts(string $value): array
    {
        $colors = [];
        $shadows = [];
        foreach (Value::split(trim($value)) as $part) {
            if (preg_match('/^(drop-shadow|blur)\(\s*(.*?)\s*\)$/s', $part, $m)) {
                if ('drop-shadow' === $m[1]) {
                    $shadows[] = $m[2];
                }
                continue;
            }
            if (!preg_match('/^[a-z-]+\(.*\)$/s', $part)) {
                return [null, ''];
            }
            $colors[] = $part;
        }

        return [implode(' ', $colors), implode(', ', $shadows)];
    }

    private static function filter(string $value): ?Filter
    {
        $functions = [];
        foreach (Value::split($value) as $part) {
            if (!preg_match('/^([a-z-]+)\(\s*(.*?)\s*\)$/s', $part, $m) || !\in_array($m[1], Filter::FUNCTIONS, true)) {
                return null;
            }
            if ('hue-rotate' === $m[1]) {
                if (false === $amount = '' === $m[2] ? 0.0 : ColorFunction::angle($m[2])) {
                    return null;
                }
            } elseif ('' === $m[2]) {
                $amount = 1.0;
            } elseif (preg_match('/^(\d*\.?\d+)(%?)$/', $m[2], $n)) {
                $amount = '%' === $n[2] ? (float) $n[1] / 100 : (float) $n[1];
            } else {
                return null;
            }
            $functions[] = [$m[1], $amount];
        }

        return $functions ? new Filter($functions) : null;
    }

    /**
     * Sets sides of the padding or the margin.
     *
     * @param 'padding'|'margin' $box
     * @param list<int>          $sides  Indexes in top, right, bottom, left order
     * @param list<string>       $values One per side; a single value applies to all of them
     */
    private static function withBoxSides(Style $style, string $box, array $sides, array $values, MediaContext $media): Style|false
    {
        $current = ('margin' === $box ? $style->getMargin() : $style->getPadding()) ?? new Padding(0, 0, 0, 0);
        $cells = [$current->top, $current->right, $current->bottom, $current->left];
        // Negative margins, as the rows and columns they pull the box by
        $pullBox = $style->getMarginPull();
        $pull = [$pullBox->top ?? 0, $pullBox->right ?? 0, $pullBox->bottom ?? 0, $pullBox->left ?? 0];
        // Negative margins of less than a cell, like the -1px that overlaps the borders of neighbors
        $hairline = $style->getMarginHairline() ?? [false, false, false, false];
        $percents = $current->percents ?? [];
        $auto = $style->getAutoMargin();
        // Indexes in top, right, bottom, left order: the horizontal sides take free columns, the vertical ones the free
        // rows of a flex column of a set height
        $autoRows = $style->getAutoMarginRows() ?? [false, false];
        $autoSides = [0 => $autoRows[0], 1 => $auto?->hasRight() ?? false, 2 => $autoRows[1], 3 => $auto?->hasLeft() ?? false];
        foreach ($sides as $i => $side) {
            $value = strtolower($values[$i] ?? $values[0] ?? '');
            $isAuto = 'margin' === $box && 'auto' === $value;
            unset($percents[$side]);
            // A percentage of the width of the containing block, for every side: resolved when it is laid out
            if (!$isAuto && str_contains($value, '%')) {
                // Or not linear in the width, like max(1ch, 5%): evaluated when it is laid out
                if (null === $linear = Length::linear($value, $media) ?? self::percentLength($value, $media)) {
                    return false;
                }
                $percents[$side] = $linear;
                $cells[$side] = 0;
            } else {
                // Rows from three quarters of one, see Length::toRows()
                $length = $isAuto ? 0 : (0 === $side % 2 ? Length::toRows($value, $media) : Length::toCells($value, $media));
                if (null === $length) {
                    return false;
                }
                $cells[$side] = max(0, $length);
                if ('margin' === $box) {
                    $pull[$side] = max(0, -$length);
                    $hairline[$side] = !$isAuto && 0 === $length && (Length::toColumns($value, $media) ?? 0.0) < 0;
                }
            }
            if (isset($autoSides[$side])) {
                $autoSides[$side] = $isAuto;
            }
        }

        if ('padding' === $box) {
            return $style->withPadding(new Padding(...$cells, percents: $percents ?: null));
        }

        return $style->withMargin(new Padding(...$cells, percents: $percents ?: null))->withAutoMargin(AutoMargin::fromSides($autoSides[3], $autoSides[1]))->withAutoMarginRows([$autoSides[0], $autoSides[2]])
            ->withMarginPull([0, 0, 0, 0] === $pull ? null : new Padding(...$pull))->withMarginHairline($hairline);
    }

    /**
     * @param list<'top'|'right'|'bottom'|'left'> $physical
     */
    private static function withBorderSides(Style $style, array $physical, string $value, MediaContext $media): Style|false
    {
        $current = $style->getBorder() ?? new Border(0, 0, 0, 0);
        $sides = ['top' => $current->top, 'right' => $current->right, 'bottom' => $current->bottom, 'left' => $current->left];
        $width = 1;
        $pattern = $current->pattern;
        $color = null;
        foreach (Value::split($value) as $part) {
            if (null !== $cells = self::borderWidth($part, $media)) {
                $width = $cells;
            } elseif (null !== $p = self::borderPattern($part)) {
                if (BorderPattern::NONE === $p) {
                    $width = 0;
                } else {
                    $pattern = $p;
                }
            } elseif (false !== $c = Value::color($part, $media)) {
                $color = $c;
            } else {
                return false;
            }
        }
        foreach ($physical as $side) {
            $sides[$side] = $width;
        }

        // Like CSS, the side shorthand resets the color of its sides: to the text color without one; the style is that of its sides
        $border = $current->withWidths($sides['top'], $sides['right'], $sides['bottom'], $sides['left'])->withSideColors(array_fill_keys(array_map(self::sideIndex(...), $physical), $color ?? $current->color));
        // A border of no width yet takes the style as a whole
        $border = 0 === $current->top + $current->right + $current->bottom + $current->left ? $border->withPattern($pattern) : $border->withSidePatterns(array_fill_keys(array_map(self::sideIndex(...), $physical), $pattern));

        return $style->withBorder($border);
    }

    /**
     * The sides a thick width of a border property is for: 0 is the top, then clockwise.
     *
     * @param list<string> $parts The values of the property
     *
     * @return list<int>
     */
    private static function thickSides(string $name, array $parts): array
    {
        $side = preg_replace('/^border-?|-(?:width|style)$/', '', $name);
        if ('' === $side) {
            // border-width by side: top, right, bottom and left, like the other box sides
            if ('border-width' === $name && \count($parts) > 1) {
                $parts = match (\count($parts)) {
                    2 => [$parts[0], $parts[1], $parts[0], $parts[1]],
                    3 => [$parts[0], $parts[1], $parts[2], $parts[1]],
                    default => $parts,
                };

                return array_keys(array_filter($parts, static fn (string $part) => 'thick' === $part));
            }

            return [0, 1, 2, 3];
        }

        return match ($side) {
            'top', 'block-start' => [0],
            'right', 'inline-end' => [1],
            'bottom', 'block-end' => [2],
            'left', 'inline-start' => [3],
            'block' => [0, 2],
            'inline' => [1, 3],
            default => [],
        };
    }

    /**
     * Draws the solid sides of a border with another pattern, like heavy lines for thick sides, or rounded corners.
     */
    /**
     * The radii of border-radius by corner, top-left first then clockwise, before the slash of the elliptic ones;
     * null when invalid.
     *
     * @return array{int, int, int, int}|null
     */
    private static function radii(string $value, MediaContext $media): ?array
    {
        $halves = explode('/', $value);
        if (\count($halves) > 2) {
            return null;
        }
        $radii = [];
        foreach ($halves as $i => $half) {
            $parts = Value::split($half);
            if (!$parts || \count($parts) > 4) {
                return null;
            }
            $cells = [];
            foreach ($parts as $part) {
                if (null === $cell = self::radius($part, $media)) {
                    return null;
                }
                $cells[] = $cell;
            }
            if (0 === $i) {
                $radii = [$cells[0], $cells[1] ?? $cells[0], $cells[2] ?? $cells[0], $cells[3] ?? $cells[1] ?? $cells[0]];
            }
        }

        return $radii;
    }

    /**
     * A radius in cells, a percentage being 1 above 0; null when invalid.
     */
    private static function radius(string $value, MediaContext $media): ?int
    {
        if (preg_match('/^(\d*\.?\d+)%$/', trim($value), $m)) {
            return (float) $m[1] > 0 ? 1 : 0;
        }

        return Length::toCells($value, $media);
    }

    /**
     * The shapes of a corner-shape value, false for an invalid one.
     *
     * @return list<'round'|'bevel'|'square'|false>
     */
    private static function cornerShapeParts(string $value): array
    {
        return array_map(static function (string $part): string|false {
            $part = strtolower($part);
            if (preg_match('/^superellipse\(\s*(-?infinity|-?\d*\.?\d+(?:e[+-]?\d+)?)\s*\)$/', $part, $m)) {
                $k = str_ends_with($m[1], 'infinity') ? (str_starts_with($m[1], '-') ? -\INF : \INF) : (float) $m[1];

                return match (true) {
                    \INF === $k => 'square',
                    $k > 0 => 'round',
                    default => 'bevel',
                };
            }

            return match ($part) {
                'round', 'squircle' => 'round',
                'square' => 'square',
                'bevel', 'notch', 'scoop' => 'bevel',
                default => false,
            };
        }, Value::split($value));
    }

    /**
     * The shapes of the corners with a radius above 0, top-left first then clockwise, from border-radius and
     * corner-shape and their longhands, which win over the shorthands; null when no corner has a radius.
     *
     * @param array<string, string> $declarations
     *
     * @return array{?string, ?string, ?string, ?string}|null
     */
    private static function cornerShapes(array $declarations, MediaContext $media): ?array
    {
        $radii = self::radii($declarations['border-radius'] ?? '0', $media) ?? [0, 0, 0, 0];
        $shapes = ['round', 'round', 'round', 'round'];
        if (isset($declarations['corner-shape']) && !\in_array(false, $parts = self::cornerShapeParts($declarations['corner-shape']), true) && \count($parts) <= 4) {
            $shapes = [$parts[0], $parts[1] ?? $parts[0], $parts[2] ?? $parts[0], $parts[3] ?? $parts[1] ?? $parts[0]];
        }
        // The corners of each side, then each corner
        foreach (['top' => [0, 1], 'right' => [1, 2], 'bottom' => [3, 2], 'left' => [0, 3], 'block-start' => [0, 1], 'block-end' => [3, 2], 'inline-start' => [0, 3], 'inline-end' => [1, 2]] as $side => [$first, $second]) {
            if (isset($declarations['corner-'.$side.'-shape']) && !\in_array(false, $parts = self::cornerShapeParts($declarations['corner-'.$side.'-shape']), true) && \in_array(\count($parts), [1, 2], true)) {
                [$shapes[$first], $shapes[$second]] = [$parts[0], $parts[1] ?? $parts[0]];
            }
        }
        foreach (['top-left' => 0, 'top-right' => 1, 'bottom-right' => 2, 'bottom-left' => 3, 'start-start' => 0, 'start-end' => 1, 'end-end' => 2, 'end-start' => 3] as $corner => $index) {
            if (isset($declarations['border-'.$corner.'-radius']) && null !== $radius = self::radius(Value::split($declarations['border-'.$corner.'-radius'])[0] ?? '', $media)) {
                $radii[$index] = $radius;
            }
            if (isset($declarations['corner-'.$corner.'-shape']) && false !== $shape = self::cornerShapeParts($declarations['corner-'.$corner.'-shape'])[0] ?? false) {
                $shapes[$index] = $shape;
            }
        }
        $corners = [];
        foreach ($radii as $index => $radius) {
            $corners[] = $radius > 0 && 'square' !== $shapes[$index] ? $shapes[$index] : null;
        }

        return [null, null, null, null] === $corners ? null : $corners;
    }

    private static function solidSidesAs(Border $border, array $sides, string $pattern): Border
    {
        $normal = BorderPattern::normal()->getChars();
        if (!$sides) {
            return $border;
        }
        // The whole border, when its sides have no patterns of their own
        if (4 === \count($sides) && [null, null, null, null] === $border->sidePatterns) {
            return $border->pattern->getChars() === $normal ? $border->withPattern($pattern) : $border;
        }

        $patterns = [];
        foreach ($sides as $side) {
            if ($border->getSidePattern($side)->getChars() === $normal) {
                $patterns[$side] = $pattern;
            }
        }

        return $patterns ? $border->withSidePatterns($patterns) : $border;
    }

    private static function withSideWidths(Style $style, array $physical, int $width): Style
    {
        $current = $style->getBorder() ?? new Border(0, 0, 0, 0);
        $sides = ['top' => $current->top, 'right' => $current->right, 'bottom' => $current->bottom, 'left' => $current->left];
        foreach ($physical as $side) {
            $sides[$side] = $width;
        }

        return $style->withBorder($current->withWidths($sides['top'], $sides['right'], $sides['bottom'], $sides['left']));
    }

    /**
     * @param 'top'|'right'|'bottom'|'left' $side
     */
    private static function sideIndex(string $side): int
    {
        return match ($side) {
            'top' => 0,
            'right' => 1,
            'bottom' => 2,
            'left' => 3,
        };
    }

    /**
     * Turns logical insets into physical ones, which a physical one wins over.
     *
     * @param array<string, string> $declarations
     *
     * @return array<string, string>
     */
    private static function physicalInsets(array $declarations): array
    {
        foreach (['inset-inline' => ['left', 'right'], 'inset-block' => ['top', 'bottom']] as $shorthand => [$start, $end]) {
            if (isset($declarations[$shorthand])) {
                $parts = Value::split($declarations[$shorthand]);
                $declarations[$start] ??= $parts[0] ?? 'auto';
                $declarations[$end] ??= $parts[1] ?? $parts[0] ?? 'auto';
                unset($declarations[$shorthand]);
            }
        }
        foreach (['inset-inline-start' => 'left', 'inset-inline-end' => 'right', 'inset-block-start' => 'top', 'inset-block-end' => 'bottom'] as $logical => $side) {
            if (isset($declarations[$logical])) {
                $declarations[$side] ??= $declarations[$logical];
                unset($declarations[$logical]);
            }
        }

        return $declarations;
    }

    /**
     * How many times as large as the text of a page (16px) a font-size is, rounded, from 1 to 4; 1 for the values
     * relative to the parent, like smaller; null when invalid.
     */
    private static function fontScale(string $value): ?int
    {
        $value = strtolower(trim($value));
        $pixels = match ($value) {
            'xx-small', 'x-small', 'small', 'medium', 'large', 'smaller', 'larger', 'math' => 16.0,
            'x-large' => 24.0,
            'xx-large' => 32.0,
            'xxx-large' => 48.0,
            default => null,
        };
        if (null === $pixels) {
            if (!preg_match('/^(\d*\.?\d+)(px|em|rem|%|pt|pc|in|cm|mm|ex|ch|lh|rlh|vw|vh|vmin|vmax)?$/', $value, $m)) {
                // A math function, like clamp(): the size of the text
                return preg_match('/^[a-z-]+\(.*\)$/', $value) ? 1 : null;
            }
            $pixels = (float) $m[1] * match ($m[2] ?? '') {
                '', 'px' => 1.0,
                'em', 'rem', 'lh', 'rlh' => 16.0,
                '%' => 0.16,
                'pt' => 4 / 3,
                'pc' => 16.0,
                'in' => 96.0,
                'cm' => 96 / 2.54,
                'mm' => 96 / 25.4,
                'ex', 'ch' => 8.0,
                default => 0.0,
            };
            // A number alone is a length only when it is 0
            if ('' === ($m[2] ?? '') && 0.0 !== (float) $m[1]) {
                return null;
            }
        }

        return max(1, min(4, (int) round($pixels / 16)));
    }

    /**
     * The writing mode of a value, the SVG 1.1 ones (tb-rl, lr...) as their CSS ones; null when invalid.
     *
     * @internal
     */
    public static function writingMode(string $value): ?string
    {
        return match (strtolower(trim($value))) {
            'horizontal-tb', 'lr', 'lr-tb', 'rl', 'rl-tb' => 'horizontal-tb',
            'vertical-rl', 'tb', 'tb-rl' => 'vertical-rl',
            'vertical-lr' => 'vertical-lr',
            'sideways-rl' => 'sideways-rl',
            'sideways-lr' => 'sideways-lr',
            default => null,
        };
    }

    /**
     * The declarations of a grid written vertically as those of the horizontal grid it is laid out as: its columns run
     * down, they are the rows of that grid, and its rows are its columns, from the right for vertical-rl (it is
     * mirrored, see finalize()); its gaps, alignments and flow are swapped likewise, its areas transposed.
     *
     * @param array<string, string> $declarations
     *
     * @return array<string, string>
     */
    private static function verticalGrid(array $declarations): array
    {
        $swapped = [];
        foreach ($declarations as $property => $value) {
            $swapped[self::VERTICAL_GRID_AXES[$property] ?? $property] = match ($property) {
                // The row value first, then the column one: the other way around
                'gap', 'place-items', 'place-content' => implode(' ', array_reverse(Value::split($value))),
                'grid-template-areas' => self::transposedAreas($value),
                'grid-auto-flow' => match (true) {
                    (bool) preg_match('/\bcolumn\b/i', $value) => (string) preg_replace('/\bcolumn\b/i', 'row', $value),
                    (bool) preg_match('/\brow\b/i', $value) => (string) preg_replace('/\brow\b/i', 'column', $value),
                    default => 'column '.$value,
                },
                default => $value,
            };
        }
        // Its items fill its columns first, which are the rows of the horizontal grid
        $swapped['grid-auto-flow'] ??= 'column';
        // Its rows repeat once to fill a block size that is not set, like CSS does for an indefinite size
        if (!isset($declarations['width']) && isset($swapped['grid-template-columns'])) {
            $swapped['grid-template-columns'] = str_ireplace(['auto-fill', 'auto-fit'], '1', $swapped['grid-template-columns']);
        }

        return $swapped;
    }

    /**
     * The placement of an item of a grid written vertically in the horizontal grid it is laid out as: its row is a
     * column of that grid, its column a row.
     *
     * @param array<string, string> $declarations
     *
     * @return array<string, string>
     */
    private static function verticalGridItem(array $declarations): array
    {
        $swapped = [];
        foreach ($declarations as $property => $value) {
            $swapped[self::VERTICAL_GRID_ITEM_AXES[$property] ?? $property] = match ($property) {
                'place-self' => implode(' ', array_reverse(Value::split($value))),
                'grid-area' => self::transposedGridArea($value),
                default => $value,
            };
        }

        return $swapped;
    }

    /**
     * grid-area with its rows and columns swapped: row-start / column-start / row-end / column-end, the missing lines
     * taken from the given ones like CSS does (a name for a name, auto otherwise), becomes column-start / row-start /
     * column-end / row-end.
     */
    private static function transposedGridArea(string $value): string
    {
        if (!str_contains($value, '/')) {
            return $value;
        }
        $lines = array_map('trim', explode('/', $value));
        $named = static fn (string $line): string => preg_match('/^-?[a-z_][\w-]*$/i', $line) && !\in_array(strtolower($line), ['auto', 'span'], true) ? $line : 'auto';
        $lines[2] ??= $named($lines[0]);
        $lines[3] ??= $named($lines[1]);

        return implode(' / ', [$lines[1], $lines[0], $lines[3], $lines[2]]);
    }

    /**
     * grid-template-areas with its rows as columns: the first column of names becomes the first string; as given when
     * its strings do not have as many names each, which is invalid either way.
     */
    private static function transposedAreas(string $value): string
    {
        if (!preg_match_all('/"([^"]*)"|\'([^\']*)\'/', $value, $m, \PREG_SET_ORDER)) {
            return $value;
        }
        $rows = array_map(static fn (array $match): array => preg_split('/\s+/', trim('' !== $match[1] ? $match[1] : $match[2] ?? '')) ?: [], $m);
        if (1 !== \count(array_unique(array_map('count', $rows)))) {
            return $value;
        }
        $columns = [];
        foreach (array_keys($rows[0]) as $column) {
            $columns[] = '"'.implode(' ', array_column($rows, $column)).'"';
        }

        return implode(' ', $columns);
    }

    /**
     * The logical sides and sizes of a vertical writing mode as physical ones: the inline start and end are the top
     * and the bottom (the other way around right to left), the block start and end the right and the left (the left
     * and the right for vertical-lr); the inline size is the height, the block size the width.
     *
     * @param array<string, string> $declarations
     *
     * @return array<string, string>
     */
    private static function verticalSides(array $declarations, bool $leftToRight, bool $rtl): array
    {
        $sides = [
            'inline' => $rtl ? ['bottom', 'top'] : ['top', 'bottom'],
            'block' => $leftToRight ? ['left', 'right'] : ['right', 'left'],
        ];
        $physical = [];
        foreach ($declarations as $property => $value) {
            if (preg_match('/^(margin|padding|inset|border|scroll-margin|scroll-padding)-(inline|block)(?:-(start|end))?(-width|-style|-color)?$/', $property, $m)) {
                $prefix = 'inset' === $m[1] ? '' : $m[1].'-';
                $suffix = $m[4] ?? '';
                [$start, $end] = $sides[$m[2]];
                if ('' !== ($m[3] ?? '')) {
                    $physical[$prefix.('start' === $m[3] ? $start : $end).$suffix] = $value;
                    continue;
                }
                // The shorthand of both sides: one value for them both, else the start then the end
                $parts = 'border' === $m[1] && '' === $suffix ? [$value, $value] : Value::split($value) + [1 => $value];
                $physical[$prefix.$start.$suffix] = $parts[0];
                $physical[$prefix.$end.$suffix] = $parts[1] ?? $parts[0];
                continue;
            }
            $physical[self::VERTICAL_SIZES[$property] ?? $property] = $value;
        }

        return $physical;
    }

    /**
     * Turns the logical sides of right-to-left declarations into physical ones: the inline start is the right side,
     * text starts on the right.
     *
     * @param array<string, string> $declarations
     *
     * @return array<string, string>
     */
    private static function rightToLeft(array $declarations): array
    {
        $physical = [];
        foreach ($declarations as $property => $value) {
            if (str_contains($property, '-inline-start')) {
                $property = str_replace('-inline-start', '-inline-end', $property);
            } elseif (str_contains($property, '-inline-end')) {
                $property = str_replace('-inline-end', '-inline-start', $property);
            } elseif (\in_array($property, ['margin-inline', 'padding-inline', 'inset-inline', 'scroll-padding-inline', 'scroll-margin-inline'], true) && 2 === \count($parts = Value::split($value))) {
                $value = $parts[1].' '.$parts[0];
            }
            $physical[$property] = $value;
        }
        $physical['text-align'] = match (strtolower(trim($physical['text-align'] ?? 'start'))) {
            'start' => 'right',
            'end' => 'left',
            default => $physical['text-align'],
        };

        return $physical;
    }

    /**
     * The background layout of the declarations: the longhands, over the first layer of the shorthand when they come
     * after it; null when none is set.
     *
     * @param array<string, string> $declarations
     */
    private static function backgroundLayout(array $declarations, MediaContext $media, int $index = 0): ?BackgroundLayout
    {
        $order = array_flip(array_keys($declarations));
        $shorthand = isset($declarations['background']) ? self::backgroundShorthand($declarations['background'], $media, $index) : [];
        $part = static function (string $property, string $component) use ($declarations, $order, $shorthand, $media, $index) {
            if (isset($declarations[$property]) && (!isset($order['background']) || $order[$property] > $order['background'])) {
                return self::backgroundLayoutPart($property, $declarations[$property], $media, $index);
            }

            return $shorthand[$component] ?? null;
        };

        $clip = $part('background-clip', 'clip');
        $origin = $part('background-origin', 'origin');
        $size = $part('background-size', 'size');
        $fit = $size[2] ?? null;
        $size = null !== $size ? [$size[0], $size[1]] : null;
        $position = $part('background-position', 'position');
        $repeat = $part('background-repeat', 'repeat');
        if (null !== $x = $part('background-position-x', '')) {
            $position = [$x, ($position ?? [1 => new BackgroundLength()])[1]];
        }
        if (null !== $y = $part('background-position-y', '')) {
            $position = [($position ?? [new BackgroundLength()])[0], $y];
        }
        if (null !== $x = $part('background-repeat-x', '')) {
            $repeat = [$x, ($repeat ?? [1 => BackgroundLayout::REPEAT])[1]];
        }
        if (null !== $y = $part('background-repeat-y', '')) {
            $repeat = [($repeat ?? [BackgroundLayout::REPEAT])[0], $y];
        }
        if (null === $clip && null === $origin && null === $size && null === $position && null === $repeat) {
            return null;
        }

        return new BackgroundLayout(
            $clip ?? BackgroundLayout::BORDER_BOX,
            $origin ?? BackgroundLayout::PADDING_BOX,
            $size ?? [null, null],
            $position ?? [new BackgroundLength(), new BackgroundLength()],
            $repeat ?? [BackgroundLayout::REPEAT, BackgroundLayout::REPEAT],
            $fit,
        );
    }

    /**
     * The images of the layers of the background, from the top one, each with its layout: those that can be drawn of
     * background-image, or of the background shorthand when it comes after it.
     *
     * @param array<string, string> $declarations
     *
     * @return list<array{Gradient|RasterImage, BackgroundLayout, ?BlendMode}>
     */
    private function backgroundLayers(array $declarations, MediaContext $media): array
    {
        $order = array_flip(array_keys($declarations));
        $longhand = isset($declarations['background-image']) && (!isset($order['background']) || $order['background-image'] > $order['background']);
        $value = $longhand ? $declarations['background-image'] : $declarations['background'] ?? '';
        $layers = Selector::splitTopLevel($value);
        if (\count($layers) < 2) {
            return [];
        }
        // A mode per layer, the list repeated past its end like the layout; normal is none
        $modes = array_map(static fn (string $mode): ?BlendMode => BlendMode::fromKeyword(trim($mode)), explode(',', $declarations['background-blend-mode'] ?? 'normal'));
        $painted = [];
        foreach (\array_slice($layers, 0, 8) as $index => $layer) {
            $source = null;
            foreach ($longhand ? [trim($layer)] : Value::split($layer) as $part) {
                if (preg_match('/^(?:url|image-set|-webkit-image-set|(?:repeating-)?(?:linear|radial|conic)-gradient)\(/i', $part)) {
                    $source = $part;
                    break;
                }
            }
            if (null === $source) {
                continue;
            }
            [$source, $resolution] = self::imageSet($source, $media) ?? [$source, 1.0];
            $paint = null !== ($url = self::url($source)) ? $this->image($url, $resolution) : self::gradient($source, $media);
            if (null !== $paint) {
                $mode = $modes[$index % \count($modes)] ?? null;
                $painted[] = [$paint, self::backgroundLayout($declarations, $media, $index) ?? new BackgroundLayout(), BlendMode::Normal === $mode ? null : $mode];
            }
        }

        return $painted;
    }

    /**
     * The layout parts of the first layer of the background shorthand, by component: position / size, repeat, and
     * the boxes, the first one being the origin and the clip, a second one the clip.
     *
     * @return array{clip?: string, origin?: string, size?: array{?BackgroundLength, ?BackgroundLength}, position?: array{BackgroundLength, BackgroundLength}, repeat?: array{string, string}}
     */
    private static function backgroundShorthand(string $value, MediaContext $media, int $index = 0): array
    {
        $layer = Selector::splitTopLevel($value)[$index] ?? '';
        // A slash at the top level, between the position and the size, is a token of its own
        $tokens = Value::split(preg_replace_callback('/\([^()]*(?:\([^()]*\)[^()]*)*\)|\//', static fn (array $m) => '/' === $m[0] ? ' / ' : $m[0], $layer) ?? $layer);
        $parts = [];
        $positionTokens = [];
        $sizeTokens = null;
        $boxes = [];
        $repeats = [];
        foreach ($tokens as $token) {
            $lower = strtolower($token);
            if ('/' === $token) {
                $sizeTokens = [];
            } elseif (\in_array($lower, ['border-box', 'padding-box', 'content-box', 'text'], true)) {
                $boxes[] = $lower;
            } elseif (\in_array($lower, ['repeat', 'no-repeat', 'space', 'round', 'repeat-x', 'repeat-y'], true)) {
                $repeats[] = $lower;
            } elseif (null !== $sizeTokens && (\in_array($lower, ['auto', 'cover', 'contain'], true) || null !== self::backgroundLength($token, $media, false))) {
                $sizeTokens[] = $token;
            } elseif (null === $sizeTokens && (\in_array($lower, ['left', 'center', 'right', 'top', 'bottom'], true) || null !== self::backgroundLength($token, $media, false))) {
                $positionTokens[] = $token;
            }
        }
        if ($positionTokens && null !== $position = self::backgroundLayoutPart('background-position', implode(' ', $positionTokens), $media)) {
            $parts['position'] = $position;
        }
        if ($sizeTokens && null !== $size = self::backgroundLayoutPart('background-size', implode(' ', $sizeTokens), $media)) {
            $parts['size'] = $size;
        }
        if ($repeats && null !== $repeat = self::backgroundLayoutPart('background-repeat', implode(' ', $repeats), $media)) {
            $parts['repeat'] = $repeat;
        }
        if ($boxes) {
            if ('text' !== $boxes[0]) {
                $parts['origin'] = $boxes[0];
            }
            $parts['clip'] = $boxes[1] ?? $boxes[0];
        }

        return $parts;
    }

    /**
     * The value of a longhand of the background layout, of its first layer; null when it is not valid.
     *
     * @return string|array{?BackgroundLength, ?BackgroundLength}|array{BackgroundLength, BackgroundLength}|array{string, string}|BackgroundLength|null
     */
    private static function backgroundLayoutPart(string $property, string $value, MediaContext $media, int $index = 0): string|array|BackgroundLength|null
    {
        // A list shorter than the layers repeats, like CSS
        $layers = Selector::splitTopLevel($value);
        $layer = trim($layers[$index % max(1, \count($layers))] ?? '');
        $tokens = Value::split($layer);
        $lower = array_map(strtolower(...), $tokens);

        switch ($property) {
            case 'background-clip':
                return \in_array($lower[0] ?? '', ['border-box', 'padding-box', 'content-box', 'text'], true) && 1 === \count($lower) ? $lower[0] : null;

            case 'background-origin':
                return \in_array($lower[0] ?? '', ['border-box', 'padding-box', 'content-box'], true) && 1 === \count($lower) ? $lower[0] : null;

            case 'background-size':
                if (['cover'] === $lower || ['contain'] === $lower) {
                    return [null, null, $lower[0]];
                }
                if (!$tokens || \count($tokens) > 2) {
                    return null;
                }
                $size = [];
                foreach ($tokens as $i => $token) {
                    if ('auto' === strtolower($token)) {
                        $size[] = null;
                    } elseif (null === ($length = self::backgroundLength($token, $media, 1 === $i)) || $length->percent < 0 || $length->cells < 0) {
                        return null;
                    } else {
                        $size[] = $length;
                    }
                }

                return [$size[0], $size[1] ?? null];

            case 'background-repeat':
                return match (true) {
                    ['repeat-x'] === $lower => [BackgroundLayout::REPEAT, BackgroundLayout::NO_REPEAT],
                    ['repeat-y'] === $lower => [BackgroundLayout::NO_REPEAT, BackgroundLayout::REPEAT],
                    1 === \count($lower) && self::isRepeat($lower[0]) => [$lower[0], $lower[0]],
                    2 === \count($lower) && self::isRepeat($lower[0]) && self::isRepeat($lower[1]) => [$lower[0], $lower[1]],
                    default => null,
                };

            case 'background-repeat-x':
            case 'background-repeat-y':
                return 1 === \count($lower) && self::isRepeat($lower[0]) ? $lower[0] : null;

            case 'background-position-x':
            case 'background-position-y':
                return self::backgroundAxis($tokens, 'background-position-y' === $property, $media);

            case 'background-position':
                return self::backgroundPosition($tokens, $media);
        }

        return null;
    }

    private static function isRepeat(string $keyword): bool
    {
        return \in_array($keyword, [BackgroundLayout::REPEAT, BackgroundLayout::NO_REPEAT, BackgroundLayout::SPACE, BackgroundLayout::ROUND], true);
    }

    /**
     * A length or a percentage of a background, null when it is neither.
     */
    private static function backgroundLength(string $token, MediaContext $media, bool $vertical): ?BackgroundLength
    {
        if (preg_match('/^(-?\d+(?:\.\d+)?|-?\.\d+)%$/', trim($token), $m)) {
            return new BackgroundLength((float) $m[1]);
        }
        $cells = Length::toCells($token, $media, $vertical);

        return null === $cells ? null : new BackgroundLength(0.0, $cells);
    }

    /**
     * One axis of background-position-x or -y: a keyword, a length, or an edge and an offset from it.
     *
     * @param list<string> $tokens
     */
    private static function backgroundAxis(array $tokens, bool $vertical, MediaContext $media): ?BackgroundLength
    {
        [$start, $end] = $vertical ? ['top', 'bottom'] : ['left', 'right'];
        $keyword = strtolower($tokens[0] ?? '');
        if (1 === \count($tokens)) {
            return match ($keyword) {
                $start => new BackgroundLength(0.0),
                'center' => new BackgroundLength(50.0),
                $end => new BackgroundLength(100.0),
                default => self::backgroundLength($tokens[0], $media, $vertical),
            };
        }
        if (2 === \count($tokens) && \in_array($keyword, [$start, $end], true) && null !== $offset = self::backgroundLength($tokens[1], $media, $vertical)) {
            return new BackgroundLength($offset->percent, $offset->cells, $keyword === $end);
        }

        return null;
    }

    /**
     * background-position: one to four values, keywords in any order, or edges followed by offsets.
     *
     * @param list<string> $tokens
     *
     * @return array{BackgroundLength, BackgroundLength}|null
     */
    private static function backgroundPosition(array $tokens, MediaContext $media): ?array
    {
        $lower = array_map(strtolower(...), $tokens);
        $count = \count($tokens);
        if (0 === $count || $count > 4) {
            return null;
        }
        $vertical = static fn (string $keyword) => 'top' === $keyword || 'bottom' === $keyword;
        $horizontal = static fn (string $keyword) => 'left' === $keyword || 'right' === $keyword;

        if (1 === $count) {
            // One value: the other axis is centered; top and bottom are vertical
            return $vertical($lower[0])
                ? [new BackgroundLength(50.0), self::backgroundAxis($tokens, true, $media) ?? throw new \LogicException('A keyword is valid.')]
                : (null !== ($x = self::backgroundAxis($tokens, false, $media)) ? [$x, new BackgroundLength(50.0)] : null);
        }
        if (2 === $count) {
            // Two keywords in any order, else horizontal then vertical
            if ($vertical($lower[0]) || $horizontal($lower[1])) {
                if (!\in_array($lower[0], ['top', 'bottom', 'center'], true) || !\in_array($lower[1], ['left', 'right', 'center'], true)) {
                    return null;
                }
                [$tokens, $lower] = [[$tokens[1], $tokens[0]], [$lower[1], $lower[0]]];
            }
            $x = $vertical($lower[0]) ? null : self::backgroundAxis([$tokens[0]], false, $media);
            $y = $horizontal($lower[1]) ? null : self::backgroundAxis([$tokens[1]], true, $media);

            return null !== $x && null !== $y ? [$x, $y] : null;
        }

        // Three or four values: edges, each followed by an offset or not; center stands alone
        $axes = [];
        for ($i = 0; $i < $count; ++$i) {
            $keyword = $lower[$i];
            if (!\in_array($keyword, ['left', 'right', 'top', 'bottom', 'center'], true)) {
                return null;
            }
            $offset = isset($tokens[$i + 1]) && !\in_array($lower[$i + 1], ['left', 'right', 'top', 'bottom', 'center'], true) ? $tokens[++$i] : null;
            if ('center' === $keyword && null !== $offset) {
                return null;
            }
            $axis = 'center' === $keyword ? null : ($vertical($keyword) ? 1 : 0);
            $axes[] = [$axis, null === $offset ? [$tokens[$i]] : [$keyword, $offset]];
        }
        if (2 !== \count($axes)) {
            return null;
        }
        // A center takes the axis the other edge leaves
        $first = $axes[0][0] ?? (1 === $axes[1][0] ? 0 : 1);
        $second = $axes[1][0] ?? (1 - $first);
        if ($first === $second) {
            return null;
        }
        $lengths = [];
        foreach ([[$first, $axes[0][1]], [$second, $axes[1][1]]] as [$axis, $parts]) {
            $lengths[$axis] = self::backgroundAxis(['center'] === array_map(strtolower(...), $parts) ? ['center'] : $parts, 1 === $axis, $media);
            if (null === $lengths[$axis]) {
                return null;
            }
        }

        return [$lengths[0], $lengths[1]];
    }

    /**
     * The width and the height of contain-intrinsic-size and its longhands, null for none; auto, the last size
     * remembered, is the size given, nothing being remembered.
     *
     * @param array<string, string> $declarations
     *
     * @return array{?string, ?string}
     */
    private static function intrinsicSize(array $declarations): array
    {
        $sizes = [null, null];
        if (isset($declarations['contain-intrinsic-size'])) {
            $parts = array_values(array_filter(Value::split(trim($declarations['contain-intrinsic-size'])), static fn (string $part) => 'auto' !== strtolower($part)));
            $sizes = [$parts[0] ?? null, $parts[1] ?? $parts[0] ?? null];
        }
        foreach ([0 => ['contain-intrinsic-width', 'contain-intrinsic-inline-size'], 1 => ['contain-intrinsic-height', 'contain-intrinsic-block-size']] as $axis => $properties) {
            foreach ($properties as $property) {
                if (isset($declarations[$property])) {
                    $parts = array_values(array_filter(Value::split(trim($declarations[$property])), static fn (string $part) => 'auto' !== strtolower($part)));
                    $sizes[$axis] = $parts[0] ?? null;
                }
            }
        }

        return array_map(static fn (?string $size) => null === $size || 'none' === strtolower($size) ? null : $size, $sizes);
    }

    /**
     * Replaces currentcolor by the color of the element, or drops the declarations using it when there is none.
     *
     * @param array<string, string> $declarations
     *
     * @return array<string, string>
     */
    private static function resolveCurrentColor(array $declarations): array
    {
        $color = $declarations['color'] ?? null;
        if (null !== $color && preg_match('/\bcurrentcolor\b/i', $color)) {
            // color: currentcolor is the inherited color, which the cascade already provides when not set
            unset($declarations['color']);
            $color = null;
        }

        foreach ($declarations as $property => $value) {
            if (!str_starts_with($property, '--') && preg_match('/\bcurrentcolor\b/i', $value)) {
                if (null === $color) {
                    unset($declarations[$property]);
                } else {
                    $declarations[$property] = preg_replace('/\bcurrentcolor\b/i', $color, $value);
                }
            }
        }

        return $declarations;
    }

    /**
     * Reads an align-content value: a position, a distribution, stretch or normal.
     *
     * @return 'start'|'center'|'end'|'space-between'|'space-around'|'space-evenly'|'stretch'|'normal'|null
     */
    private static function contentDistribution(string $value): ?string
    {
        $value = strtolower(trim($value));

        return \in_array($value, ['space-between', 'space-around', 'space-evenly', 'stretch', 'normal'], true) ? $value : self::position($value);
    }

    /**
     * @return 'start'|'center'|'end'|null
     */
    private static function position(string $value): ?string
    {
        // safe and unsafe only matter when an item overflows; rows of one height make the baselines their first rows
        return match (preg_replace('/^(?:safe|unsafe)\s+/', '', strtolower(trim($value)))) {
            'start', 'flex-start', 'left', 'top', 'self-start', 'baseline', 'first baseline' => 'start',
            'center' => 'center',
            'end', 'flex-end', 'right', 'bottom', 'self-end', 'last baseline' => 'end',
            default => null,
        };
    }

    /**
     * The sides, from 0 for the top clockwise, whose width the last declaration that sets it makes 0.
     *
     * @param array<string, string> $declarations
     *
     * @return list<int>
     */
    private static function zeroBorderSides(array $declarations, MediaContext $media): array
    {
        $sides = [
            'top' => [0], 'right' => [1], 'bottom' => [2], 'left' => [3],
            'block' => [0, 2], 'inline' => [3, 1], 'block-start' => [0], 'block-end' => [2], 'inline-start' => [3], 'inline-end' => [1],
        ];
        $widths = [null, null, null, null];
        foreach ($declarations as $property => $value) {
            $value = strtolower(trim($value));
            if ('border' === $property || preg_match('/^border-(top|right|bottom|left|block|inline|block-start|block-end|inline-start|inline-end)$/', $property, $m)) {
                // A shorthand sets the width, medium without one
                $width = 1;
                foreach (Value::split($value) as $part) {
                    if (null !== $found = self::borderWidth($part, $media)) {
                        $width = $found;
                    }
                }
                foreach ('border' === $property ? [0, 1, 2, 3] : $sides[$m[1]] as $side) {
                    $widths[$side] = $width;
                }
            } elseif ('border-width' === $property) {
                $values = array_map(static fn (string $part) => self::borderWidth($part, $media), Value::split($value));
                if ($values && \count($values) <= 4 && !\in_array(null, $values, true)) {
                    $widths = match (\count($values)) {
                        1 => array_fill(0, 4, $values[0]),
                        2 => [$values[0], $values[1], $values[0], $values[1]],
                        3 => [$values[0], $values[1], $values[2], $values[1]],
                        default => $values,
                    };
                }
            } elseif (preg_match('/^border-(top|right|bottom|left|block|inline|block-start|block-end|inline-start|inline-end)-width$/', $property, $m) && null !== $width = self::borderWidth($value, $media)) {
                foreach ($sides[$m[1]] as $side) {
                    $widths[$side] = $width;
                }
            }
        }

        return array_keys(array_filter($widths, static fn (?int $width) => 0 === $width));
    }

    private static function borderWidth(string $value, MediaContext $media): ?int
    {
        return match ($value) {
            'thin', 'medium', 'thick' => 1,
            default => null === ($cells = Length::toCells($value, $media)) ? null : min(1, max(0, $cells)),
        };
    }

    private static function borderPattern(string $value): ?string
    {
        $value = strtolower($value);

        return match ($value) {
            'none', 'hidden' => BorderPattern::NONE,
            'solid', 'groove', 'ridge', 'inset', 'outset' => BorderPattern::NORMAL,
            'dashed' => BorderPattern::DASHED,
            'dotted' => BorderPattern::DOTTED,
            'double' => BorderPattern::DOUBLE,
            BorderPattern::HEAVY, BorderPattern::ROUNDED, BorderPattern::TALL, BorderPattern::WIDE, BorderPattern::TALL_MEDIUM, BorderPattern::WIDE_MEDIUM, BorderPattern::TALL_LARGE, BorderPattern::WIDE_LARGE => $value,
            default => null,
        };
    }

    private function report(string $key, string $message): void
    {
        if ($this->strict) {
            throw new InvalidArgumentException($message);
        }

        if (!isset($this->reported[$key])) {
            $this->reported[$key] = true;
            $this->logger?->warning($message);
        }
    }
}
