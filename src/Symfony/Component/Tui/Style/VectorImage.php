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

use Symfony\Component\Tui\Css\Value;

/**
 * The shapes of an inline <svg>, drawn into an image of any size.
 *
 * The basic shapes (rect, circle, ellipse, line, polyline, polygon) and paths (every command, arcs and curves
 * flattened into lines) are drawn, in groups, nested <svg> elements and the copies of <use>, with their transforms, in their fill and
 * stroke colors, the linear and radial gradients of their fills, opacities and fill rule, the dashes, caps and joins of strokes, markers, the paint order, clip paths, patterns, masks and filters; currentColor is the color
 * of the element; text in the bitmap font of GD, scaled to its size.
 *
 * @internal
 *
 * @experimental
 */
final class VectorImage
{
    /** The pixels drawn per pixel of the image, then averaged, for smooth edges */
    private const SUPERSAMPLE = 2;
    /** The largest side drawn, in pixels before supersampling: a cell shows two of them */
    private const MAX_SIDE = 320;
    /** The most work drawing an image, in edges crossed by rows and pixels of strokes: the shapes past it are left out */
    private const MAX_WORK = 2_000_000;
    /** The most shapes and points kept, like a cap on the work of a page */
    private const MAX_SHAPES = 2_000;
    private const MAX_POINTS = 20_000;
    /** The largest size and coordinate kept, in CSS pixels or user units */
    private const MAX_LENGTH = 100_000.0;
    /** The tags whose content is not drawn */
    private const SKIPPED = ['defs', 'title', 'desc', 'metadata', 'style', 'script', 'symbol', 'clippath', 'mask', 'marker', 'pattern', 'lineargradient', 'radialgradient', 'filter', 'foreignobject', 'switch', 'image'];

    /**
     * @var list<array{subpaths: list<array{points: list<array{float, float}>, closed: bool}>, fill: array{int, int, int, float}|array{'gradient', 'linear'|'radial', list<array{float, int, int, int, float}>, list<float>}|array{'pattern', self|null, array{float, float, float, float}, array{float, float, float, float, float, float}, array{int, int, int, float}|'current'|null}|'current'|null, fillOpacity: float, stroke: array{int, int, int, float}|array{'pattern', self|null, array{float, float, float, float}, array{float, float, float, float, float, float}, array{int, int, int, float}|'current'|null}|'current'|null, strokeOpacity: float, strokeWidth: float, dashes: list<float>|null, dashOffset: float, cap: 'butt'|'round'|'square', join: 'miter'|'round'|'bevel', miterLimit: float, evenOdd: bool, clips: list<int>, masks: list<int>, filters: list<int>}|array{text: string, label: string, middle: bool, at: array{float, float}, size: float, anchor: string, fill: array{int, int, int, float}|array{'gradient', 'linear'|'radial', list<array{float, int, int, int, float}>, list<float>}|array{'pattern', self|null, array{float, float, float, float}, array{float, float, float, float, float, float}, array{int, int, int, float}|'current'|null}|'current'|null, fillOpacity: float, clips: list<int>, masks: list<int>, filters: list<int>}>
     */
    private array $shapes = [];
    private int $points = 0;
    /** The most elements walked, the copies of <use> counted, which could otherwise multiply each other */
    private const MAX_WALKS = 20_000;
    /** @var array<string, \Dom\Element> The elements of the <svg> by id, the first of each, which <use> names */
    private array $ids = [];
    private int $walks = 0;
    /** @var list<\Dom\Element> The <use> elements being drawn, so that one drawing itself is not drawn again */
    private array $using = [];
    /** Whether the content of a <marker> is being added: its own shapes take no markers */
    private bool $marking = false;
    /** @var list<int> The filters of the elements being walked, each an index of: a shape is filtered by each, the innermost first */
    private array $filters = [];
    /** @var list<array{list<array<string, mixed>>, list<array{float, float}>|null}> The primitives of each filter, and the corners of its region in the user space of the image, null for all of it */
    private array $filterDefs = [];
    /** @var list<int> The masks of the elements being walked, each an index of: a shape is faded by all of them */
    private array $masks = [];
    /** @var list<array{self, bool}> The content of each mask, as an image of the same view box, and whether its luminance counts (else its alpha) */
    private array $maskImages = [];
    /** The most canvases of masks kept while an image is drawn */
    private const MAX_MASK_CANVASES = 16;
    /** @var list<\Dom\Element> The <mask> elements whose content is being read */
    private array $maskElements = [];
    /** @var list<int> The clips of the elements being walked, each an index of: a shape is drawn inside all of them */
    private array $clips = [];
    /** @var list<list<array{list<array{points: list<array{float, float}>, closed: bool}>, bool}>> The shapes of each clip, in the user space of the image, each with its clip-rule (evenodd or not) */
    private array $clipPaths = [];
    /** @var list<\Dom\Element> The <pattern> elements whose content is being read, around this image */
    private array $patterns = [];
    /** How many patterns were read, those of the patterns in them too */
    private int $patternCount = 0;
    /** The most patterns read for an image */
    private const MAX_PATTERNS = 64;
    /** @var array<string, int> The clips by their <clipPath> and user space */
    private array $clipKeys = [];
    /** @var list<\Dom\Element> The <clipPath> elements being read, so that one is not read inside itself */
    private array $clipping = [];
    /** The most clips of an image */
    private const MAX_CLIPS = 256;
    /** @var \SplObjectStorage<\Dom\Element, array<string, string>> The properties the content of each <marker> inherits */
    private \SplObjectStorage $markerProperties;
    /** The most vertices of a shape that take markers */
    private const MAX_MARKERS = 2_000;
    /** @var (\Closure(\Dom\Element, list<string>): array<string, string>)|null The cascade of the page, see fromElement() */
    private ?\Closure $cascade = null;

    /**
     * @param array{float, float, float, float} $viewBox The area of the user space drawn: x, y, width and height
     * @param float                             $width   The width in CSS pixels
     * @param float                             $height  The height in CSS pixels
     * @param 'meet'|'slice'|'none'             $fit     How the view box fits the size, like preserveAspectRatio
     * @param array{float, float}               $align   Where it is placed in the room left, from 0 to 1 on each axis
     */
    private function __construct(
        public readonly array $viewBox,
        public readonly float $width,
        public readonly float $height,
        private readonly string $fit = 'meet',
        private readonly array $align = [0.5, 0.5],
    ) {
    }

    /**
     * Reads the shapes of an <svg> element; null when it draws nothing.
     *
     * The cascade of the page gives the values of the properties asked for of an element, its presentation attributes
     * and its style attribute included, like CssStyleSheet::svgDeclarations(); without it, those two only.
     *
     * @param (\Closure(\Dom\Element, list<string>): array<string, string>)|null $cascade
     */
    public static function fromElement(\Dom\Element $svg, ?\Closure $cascade = null): ?self
    {
        SvgNames::normalize($svg);
        $box = self::numbers($svg->getAttribute('viewBox') ?? '');
        $box = 4 === \count($box) && $box[2] > 0 && $box[3] > 0 ? $box : null;
        $width = self::size($svg->getAttribute('width'));
        $height = self::size($svg->getAttribute('height'));
        // The size from the other side and the proportions of the view box, else the view box, else 300 by 150, like browsers
        if (null !== $box) {
            $width ??= null !== $height ? $height * $box[2] / $box[3] : $box[2];
            $height ??= $width * $box[3] / $box[2];
        }
        $width ??= 300.0;
        $height ??= 150.0;
        if (!($width > 0) || !($height > 0)) {
            return null;
        }
        // From the proportions of a view box too: from a hundredth of a pixel to the largest size
        $width = max(0.01, min(self::MAX_LENGTH, $width));
        $height = max(0.01, min(self::MAX_LENGTH, $height));

        [$fit, $align] = self::aspectRatio($svg->getAttribute('preserveAspectRatio') ?? '');
        $image = new self($box ?? [0.0, 0.0, $width, $height], $width, $height, $fit, $align);
        $image->cascade = $cascade;
        $image->markerProperties = new \SplObjectStorage();
        foreach ($svg->getElementsByTagName('*') as $element) {
            if (null !== ($id = $element->getAttribute('id')) && !isset($image->ids[$id])) {
                $image->ids[$id] = $element;
            }
        }
        $image->walk($svg, self::IDENTITY, self::INITIAL, true);
        $image->ids = [];
        $image->clipKeys = [];
        $image->cascade = null;
        $image->markerProperties = new \SplObjectStorage();

        return $image->shapes ? $image : null;
    }

    /**
     * Reads the shapes of an SVG document, like the source of an <img> or a url() of a background; null when it is not
     * one, or draws nothing.
     */
    public static function fromString(string $data): ?self
    {
        if (!preg_match('/^\s*(?:<\?xml[^>]*>\s*)?(?:<!--.*?-->\s*|<!DOCTYPE[^>]*>\s*)*<svg\b/is', $data)) {
            return null;
        }
        try {
            $document = \Dom\XMLDocument::createFromString($data, \LIBXML_NONET | \LIBXML_NOERROR | \LIBXML_NOWARNING);
        } catch (\Throwable) {
            return null;
        }
        if (null === ($root = $document->documentElement) || 'svg' !== strtolower($root->localName)) {
            return null;
        }

        return self::fromElement($root);
    }

    /**
     * Draws the shapes into a PNG image of that size in pixels, currentColor being that color.
     *
     * @param array{int, int, int} $currentColor
     * @param list<int>            $skip         The texts not drawn, by their index, see labels()
     * @param float                $minStroke    The least width of a line, in pixels of the image: one that would fade out
     *                                           where its pixels are shown as cells, like the lines of a diagram
     */
    public function toPng(int $width, int $height, array $currentColor, array $skip = [], float $minStroke = 0.0): ?string
    {
        if (!\function_exists('imagecreatetruecolor') || $width <= 0 || $height <= 0) {
            return null;
        }
        $scale = min(1.0, self::MAX_SIDE / max($width, $height));
        $width = max(1, (int) round($width * $scale));
        $height = max(1, (int) round($height * $scale));
        $work = 0;
        if (null === $canvas = $this->canvas($width * self::SUPERSAMPLE, $height * self::SUPERSAMPLE, $currentColor, $work, array_flip($skip), $minStroke * $scale * self::SUPERSAMPLE)) {
            return null;
        }

        // Averaged down, which smooths the edges
        if (false === $image = imagecreatetruecolor($width, $height)) {
            return null;
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagecopyresampled($image, $canvas, 0, 0, 0, 0, $width, $height, $width * self::SUPERSAMPLE, $height * self::SUPERSAMPLE);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean() ?: null;
    }

    /**
     * The texts that can be drawn as characters over the image instead of in its pixels, like the labels of a diagram:
     * those neither clipped, masked nor filtered, of a paint seen enough. Each with its index, see toPng(), the middle
     * of its first line and its size as fractions of the width and the height of the image, its anchor, and its color,
     * null for currentColor.
     *
     * @return list<array{index: int, text: string, x: float, y: float, size: float, anchor: string, color: array{int, int, int}|null}>
     */
    public function labels(): array
    {
        [$x, $y, $boxWidth, $boxHeight] = $this->viewBox;
        // The view box into the image, like canvas()
        $sx = $this->width / $boxWidth;
        $sy = $this->height / $boxHeight;
        if ('none' !== $this->fit) {
            $sx = $sy = 'slice' === $this->fit ? max($sx, $sy) : min($sx, $sy);
        }
        $tx = ($this->width - $boxWidth * $sx) * $this->align[0] - $x * $sx;
        $ty = ($this->height - $boxHeight * $sy) * $this->align[1] - $y * $sy;
        $labels = [];
        // The boxes of the shapes, found once
        $bounds = [];
        foreach ($this->shapes as $index => $shape) {
            if (!isset($shape['text']) || $shape['clips'] || $shape['masks'] || $shape['filters']) {
                continue;
            }
            $paint = $shape['fill'];
            $paint = match (\is_array($paint) ? $paint[0] : null) {
                'gradient' => self::averageColor($paint),
                'pattern' => \is_array($paint[4]) ? $paint[4] : null,
                default => $paint,
            };
            if (null === $paint || ('current' !== $paint && $paint[3] * $shape['fillOpacity'] < 0.5) || ('current' === $paint && $shape['fillOpacity'] < 0.5)) {
                continue;
            }
            // Not one a later shape fills over, like a face hiding it: drawn as characters, it would be over that shape
            $width = mb_strwidth($shape['label']) * 0.6 * $shape['size'];
            $left = $shape['at'][0] - match ($shape['anchor']) {
                'middle' => $width / 2,
                'end' => $width,
                default => 0.0,
            };
            $top = $shape['at'][1] - $shape['size'] * ($shape['middle'] ? 0.5 : 0.8);
            foreach (\array_slice($this->shapes, $index + 1, null, true) as $later => $over) {
                if (isset($over['text']) || null === $over['fill']) {
                    continue;
                }
                $box = $bounds[$later] ??= self::bounds($over['subpaths']) ?? [0.0, 0.0, 0.0, 0.0];
                if ($box[0] < $left + $width && $box[2] > $left && $box[1] < $top + $shape['size'] && $box[3] > $top) {
                    continue 2;
                }
            }
            $size = $shape['size'] * sqrt(abs($sx * $sy));
            $labels[] = [
                'index' => $index,
                'text' => $shape['label'],
                'x' => ($shape['at'][0] * $sx + $tx) / $this->width,
                // The middle of the text: above its baseline by about a third of its size
                'y' => ($shape['at'][1] * $sy + $ty - ($shape['middle'] ? 0.0 : 0.35 * $size)) / $this->height,
                'size' => $size / $this->height,
                'anchor' => $shape['anchor'],
                'color' => 'current' === $paint ? null : [$paint[0], $paint[1], $paint[2]],
            ];
        }

        return $labels;
    }

    /**
     * Draws the shapes on a canvas of that size in pixels, the view box fit into it.
     *
     * @param array{int, int, int} $currentColor
     * @param array<int, int>      $skip         The texts not drawn, by their index as keys
     * @param float                $minStroke    The least width of a line, in pixels of the canvas
     */
    private function canvas(int $canvasWidth, int $canvasHeight, array $currentColor, int &$work, array $skip = [], float $minStroke = 0.0): ?\GdImage
    {
        if (false === $canvas = imagecreatetruecolor($canvasWidth, $canvasHeight)) {
            return null;
        }
        imagealphablending($canvas, false);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagealphablending($canvas, true);

        // The view box into the canvas, like preserveAspectRatio
        [$x, $y, $boxWidth, $boxHeight] = $this->viewBox;
        $sx = $canvasWidth / $boxWidth;
        $sy = $canvasHeight / $boxHeight;
        if ('none' !== $this->fit) {
            $sx = $sy = 'slice' === $this->fit ? max($sx, $sy) : min($sx, $sy);
        }
        $tx = ($canvasWidth - $boxWidth * $sx) * $this->align[0] - $x * $sx;
        $ty = ($canvasHeight - $boxHeight * $sy) * $this->align[1] - $y * $sy;
        // Kept near the canvas: GD takes integers, and what is far past an edge is not drawn
        $map = static fn (array $point): array => [
            max(-4.0 * $canvasWidth, min(5.0 * $canvasWidth, $point[0] * $sx + $tx)),
            max(-4.0 * $canvasHeight, min(5.0 * $canvasHeight, $point[1] * $sy + $ty)),
        ];
        $strokeScale = sqrt(abs($sx * $sy));

        // Clipped shapes are drawn on a layer, then copied where all their clips are
        $layer = null;
        // The tiles of the patterns, kept while the canvas is drawn: GD fills with them without holding them
        $tiles = [];
        // The runs of each clip, and the canvas of each mask, made once
        $spans = [];
        $faders = [];
        foreach ($this->shapes as $index => $shape) {
            if (isset($skip[$index])) {
                continue;
            }
            $target = $canvas;
            if ($shape['clips'] || $shape['masks'] || $shape['filters']) {
                if (null === $layer ??= self::transparent($canvasWidth, $canvasHeight)) {
                    break;
                }
                $target = $layer;
            }
            $drawn = true;
            if (isset($shape['text'])) {
                $color = match (\is_array($shape['fill']) ? $shape['fill'][0] : null) {
                    'gradient' => self::allocate($canvas, self::averageColor($shape['fill']), $shape['fillOpacity'], $currentColor),
                    'pattern' => self::allocate($canvas, $shape['fill'][4], $shape['fillOpacity'], $currentColor),
                    default => self::allocate($canvas, $shape['fill'], $shape['fillOpacity'], $currentColor),
                };
                $drawn = null === $color || self::drawText($target, $shape['text'], $map($shape['at']), $shape['size'] * $strokeScale, $shape['anchor'], $color, $work, $shape['middle']);
            } else {
                $subpaths = array_map(static fn (array $subpath) => ['points' => array_map($map, $subpath['points']), 'closed' => $subpath['closed']], $shape['subpaths']);
                $color = match (\is_array($shape['fill']) ? $shape['fill'][0] : null) {
                    'gradient' => self::gradientColors($target, $shape['fill'], $shape['fillOpacity'], $sx, $sy, $tx, $ty),
                    'pattern' => self::tile($target, $shape['fill'], $shape['fillOpacity'], [$sx, $sy, $tx, $ty], $canvasWidth, $canvasHeight, $currentColor, $work, $tiles),
                    default => self::allocate($target, $shape['fill'], $shape['fillOpacity'], $currentColor),
                };
                if (null !== $color) {
                    $drawn = self::fill($target, $subpaths, $shape['evenOdd'], $color, $canvasHeight, $work);
                }
                $stroke = \is_array($shape['stroke']) && 'pattern' === $shape['stroke'][0]
                    ? self::tile($target, $shape['stroke'], $shape['strokeOpacity'], [$sx, $sy, $tx, $ty], $canvasWidth, $canvasHeight, $currentColor, $work, $tiles)
                    : self::allocate($target, $shape['stroke'], $shape['strokeOpacity'], $currentColor);
                if ($drawn && null !== ($color = $stroke) && $shape['strokeWidth'] > 0) {
                    if (null !== $shape['dashes']) {
                        $subpaths = self::dash($subpaths, array_map(static fn (float $dash) => $dash * $strokeScale, $shape['dashes']), $shape['dashOffset'] * $strokeScale);
                    }
                    // No wider than the canvas: what is past it is not drawn anyway
                    $drawn = self::stroke($target, $subpaths, min(max(1.0, $minStroke, $shape['strokeWidth'] * $strokeScale), 2.0 * max($canvasWidth, $canvasHeight)), $color, $canvasWidth, $canvasHeight, $work, $shape['cap'], $shape['join'], $shape['miterLimit']);
                }
            }
            if ($drawn && null !== $layer && ($shape['clips'] || $shape['masks'] || $shape['filters'])) {
                foreach ($shape['clips'] as $clip) {
                    $spans[$clip] ??= $this->clipSpans($clip, $map, $canvasHeight, $work);
                }
                // The box of what the shape drew: its points, as far as its stroke goes, or the canvas for text
                $box = [0, 0, $canvasWidth - 1, $canvasHeight - 1];
                if (!isset($shape['text']) && null !== $bounds = self::bounds(array_map(static fn (array $subpath) => ['points' => array_map($map, $subpath['points']), 'closed' => false], $shape['subpaths']))) {
                    $reach = (int) min(2.0 * max($canvasWidth, $canvasHeight), ceil(max(1.0, $shape['strokeWidth'] * $strokeScale) * max(1.0, $shape['miterLimit']) / 2) + 2);
                    $box = [max(0, (int) floor($bounds[0]) - $reach), max(0, (int) floor($bounds[1]) - $reach), min($canvasWidth - 1, (int) ceil($bounds[2]) + $reach), min($canvasHeight - 1, (int) ceil($bounds[3]) + $reach)];
                }
                // Filtered, the innermost filter first, each over its region, which the box grows to
                foreach (array_reverse($shape['filters']) as $filter) {
                    if (!$drawn = $this->applyFilter($layer, $filter, $map, $box, [$sx, $sy], $canvasWidth, $canvasHeight, $work)) {
                        break;
                    }
                }
                // Faded by its masks, then copied where its clips are
                foreach ($drawn ? $shape['masks'] : [] as $mask) {
                    if (!\array_key_exists($mask, $faders)) {
                        // A canvas of its own, counted like a tile, and a few of them kept: the oldest one drawn again if needed
                        $work += 1 + intdiv($canvasWidth * $canvasHeight, 16);
                        if ($work > self::MAX_WORK) {
                            $drawn = false;
                            break;
                        }
                        if (\count($faders) >= self::MAX_MASK_CANVASES) {
                            unset($faders[array_key_first($faders)]);
                        }
                        [$content] = $this->maskImages[$mask];
                        $faders[$mask] = $content->canvas($canvasWidth, $canvasHeight, $currentColor, $work);
                    }
                    if (!$drawn = self::fade($layer, $faders[$mask], $this->maskImages[$mask][1], $box, $work)) {
                        break;
                    }
                }
                $drawn = $drawn && self::composite($canvas, $layer, array_map(static fn (int $clip) => $spans[$clip], $shape['clips']), $box, $work);
            }
            if (!$drawn || $work > self::MAX_WORK) {
                break;
            }
        }

        return $canvas;
    }

    private static function transparent(int $width, int $height): ?\GdImage
    {
        if (false === $image = imagecreatetruecolor($width, $height)) {
            return null;
        }
        imagealphablending($image, false);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagealphablending($image, true);

        return $image;
    }

    /**
     * The pixels inside a clip, by row, its runs merged: the union of its shapes.
     *
     * @param \Closure(array{float, float}): array{float, float} $map
     *
     * @return array<int, list<array{int, int}>>
     */
    private function clipSpans(int $clip, \Closure $map, int $height, int &$work): array
    {
        $rows = [];
        foreach ($this->clipPaths[$clip] as [$subpaths, $evenOdd]) {
            $subpaths = array_map(static fn (array $subpath) => ['points' => array_map($map, $subpath['points']), 'closed' => $subpath['closed']], $subpaths);
            if (null === $spans = self::spans($subpaths, $evenOdd, $height, $work)) {
                break;
            }
            foreach ($spans as $row => $runs) {
                $rows[$row] = [...$rows[$row] ?? [], ...$runs];
            }
        }
        foreach ($rows as $row => $runs) {
            $rows[$row] = self::merge($runs);
        }

        return $rows;
    }

    /**
     * Runs of pixels sorted and joined where they touch or overlap.
     *
     * @param list<array{int, int}> $runs
     *
     * @return list<array{int, int}>
     */
    private static function merge(array $runs): array
    {
        usort($runs, static fn (array $a, array $b) => $a[0] <=> $b[0]);
        $merged = [];
        foreach ($runs as [$from, $to]) {
            $last = \count($merged) - 1;
            if ($last >= 0 && $from <= $merged[$last][1] + 1) {
                $merged[$last][1] = max($merged[$last][1], $to);
            } else {
                $merged[] = [$from, $to];
            }
        }

        return $merged;
    }

    /**
     * Fades the pixels of the layer in a box by those of a mask drawn on a canvas of the same size: by their luminance
     * times their alpha, or by their alpha alone; pixel by pixel, counted as work before.
     *
     * @param array{int, int, int, int} $box
     */
    private static function fade(\GdImage $layer, ?\GdImage $mask, bool $luminance, array $box, int &$work): bool
    {
        [$left, $top, $right, $bottom] = $box;
        if ($right < $left || $bottom < $top) {
            return true;
        }
        $work += ($right - $left + 1) * ($bottom - $top + 1);
        if ($work > self::MAX_WORK) {
            return false;
        }
        imagealphablending($layer, false);
        for ($y = $top; $y <= $bottom; ++$y) {
            for ($x = $left; $x <= $right; ++$x) {
                $pixel = imagecolorat($layer, $x, $y);
                $alpha = $pixel >> 24 & 0x7F;
                if (127 === $alpha) {
                    continue;
                }
                $value = null !== $mask ? imagecolorat($mask, $x, $y) : 0x7F000000;
                $coverage = (127 - ($value >> 24 & 0x7F)) / 127;
                if ($luminance) {
                    $coverage *= (0.2125 * ($value >> 16 & 0xFF) + 0.7154 * ($value >> 8 & 0xFF) + 0.0721 * ($value & 0xFF)) / 255;
                }
                $faded = 127 - (int) round((127 - $alpha) * $coverage);
                imagesetpixel($layer, $x, $y, ($pixel & 0xFFFFFF) | ($faded << 24));
            }
        }
        imagealphablending($layer, true);

        return true;
    }

    /**
     * Clears the layer outside every mask in a box, copies that box onto the canvas, then clears it.
     *
     * @param list<array<int, list<array{int, int}>>> $masks
     * @param array{int, int, int, int}               $box   Left, top, right and bottom, in the canvas
     */
    private static function composite(\GdImage $canvas, \GdImage $layer, array $masks, array $box, int &$work): bool
    {
        [$left, $top, $right, $bottom] = $box;
        if ($right < $left || $bottom < $top) {
            return true;
        }
        // Copying and clearing a pixel is less work than drawing it, but not none
        $work += (int) (($right - $left + 1) * ($bottom - $top + 1) / 16);
        if ($work > self::MAX_WORK) {
            return false;
        }
        imagealphablending($layer, false);
        $clear = imagecolorallocatealpha($layer, 0, 0, 0, 127);
        for ($row = $top; $row <= $bottom; ++$row) {
            // The runs inside all the masks
            $inside = [[$left, $right]];
            foreach ($masks as $mask) {
                $kept = [];
                foreach ($mask[$row] ?? [] as [$from, $to]) {
                    foreach ($inside as [$a, $b]) {
                        if (max($a, $from) <= min($b, $to)) {
                            $kept[] = [max($a, $from), min($b, $to)];
                        }
                    }
                }
                $inside = self::merge($kept);
            }
            $x = $left;
            foreach ($inside as [$from, $to]) {
                if ($from > $x) {
                    imageline($layer, $x, $row, $from - 1, $row, $clear);
                }
                $x = $to + 1;
            }
            if ($x <= $right) {
                imageline($layer, $x, $row, $right, $row, $clear);
            }
        }
        imagecopy($canvas, $layer, $left, $top, $left, $top, $right - $left + 1, $bottom - $top + 1);
        imagefilledrectangle($layer, $left, $top, $right, $bottom, $clear);
        imagealphablending($layer, true);

        return true;
    }

    private const IDENTITY = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];

    /** The CSS named colors that are ANSI colors for text */
    private const CSS_NAMES = ['black' => '#000000', 'red' => '#ff0000', 'green' => '#008000', 'yellow' => '#ffff00', 'blue' => '#0000ff', 'magenta' => '#ff00ff', 'cyan' => '#00ffff', 'white' => '#ffffff', 'gray' => '#808080', 'grey' => '#808080'];

    /** The initial values of the properties of the shapes, inherited from the elements around them */
    private const INITIAL = ['fill' => 'black', 'stroke' => 'none', 'stroke-width' => '1', 'fill-opacity' => '1', 'stroke-opacity' => '1', 'fill-rule' => 'nonzero', 'visibility' => 'visible', 'color' => 'currentcolor', 'opacity' => '1', 'stroke-dasharray' => 'none', 'stroke-dashoffset' => '0', 'stroke-linecap' => 'butt', 'stroke-linejoin' => 'miter', 'stroke-miterlimit' => '4', 'paint-order' => 'normal'];
    /** The most dashes drawn on a shape: a pattern that would make more is drawn solid, like a line too fine to see dashed */
    private const MAX_DASHES = 4_096;

    /**
     * @param array{float, float, float, float, float, float} $matrix     The transform of the user space of the parent: a, b, c, d, e, f
     * @param array<string, string>                           $properties The properties inherited
     */
    private function walk(\Dom\Element $element, array $matrix, array $properties, bool $root = false): void
    {
        if (++$this->walks > self::MAX_WALKS || \count($this->shapes) >= self::MAX_SHAPES || $this->points >= self::MAX_POINTS) {
            return;
        }
        $tag = strtolower($element->localName);
        if (!$root && \in_array($tag, self::SKIPPED, true)) {
            return;
        }
        $own = $this->properties($element);
        if ('none' === strtolower($own['display'] ?? '')) {
            return;
        }
        // opacity is not inherited: a group's fades what is in it, like the opacity of its layer
        $opacity = (float) ($properties['opacity'] ?? 1) * self::fraction($own['opacity'] ?? '1');
        $properties = ['opacity' => (string) $opacity] + $own + $properties;
        // clip-path and mask are not inherited: what the element draws is clipped or masked, its descendants with it
        unset($properties['clip-path'], $properties['mask'], $properties['filter']);
        $matrix = self::multiply($matrix, self::transform($element->getAttribute('transform') ?? ''));
        if ('svg' === $tag && !$root) {
            $matrix = self::multiply($matrix, [1.0, 0.0, 0.0, 1.0, self::length($element->getAttribute('x'), $this->viewBox[2]), self::length($element->getAttribute('y'), $this->viewBox[3])]);
        }

        $clip = isset($own['clip-path']) ? $this->clip($own['clip-path'], $element, $tag, $matrix) : null;
        if (null !== $clip) {
            $this->clips[] = $clip;
        }
        // A mask: its content fades what the element draws, inside its region, a clip
        $mask = isset($own['mask']) ? $this->mask($own['mask'], $element, $tag, $matrix) : null;
        if (null !== $mask) {
            $this->masks[] = $mask[0];
            $this->clips[] = $mask[1];
        }
        $filter = isset($own['filter']) ? $this->filter($own['filter'], $element, $tag, $matrix) : null;
        if (null !== $filter) {
            $this->filters[] = $filter;
        }
        $this->draws($element, $tag, $matrix, $properties);
        if (null !== $filter) {
            array_pop($this->filters);
        }
        if (null !== $mask) {
            array_pop($this->masks);
            array_pop($this->clips);
        }
        if (null !== $clip) {
            array_pop($this->clips);
        }
    }

    /**
     * Adds what an element draws: its shape, its text, the copy of a <use>, or what its children draw.
     *
     * @param array{float, float, float, float, float, float} $matrix
     * @param array<string, string>                           $properties
     */
    private function draws(\Dom\Element $element, string $tag, array $matrix, array $properties): void
    {
        // A copy of the element it names, like #star, moved by its x and y; a <symbol> drawn like a group
        if ('use' === $tag) {
            $href = trim($element->getAttribute('href') ?? $element->getAttributeNS('http://www.w3.org/1999/xlink', 'href') ?? '');
            $target = str_starts_with($href, '#') ? $this->ids[substr($href, 1)] ?? null : null;
            if (null !== $target && \count($this->using) < 8 && !\in_array($element, $this->using, true) && !$target->contains($element)) {
                $this->using[] = $element;
                $moved = self::multiply($matrix, [1.0, 0.0, 0.0, 1.0, self::length($element->getAttribute('x'), $this->viewBox[2]), self::length($element->getAttribute('y'), $this->viewBox[3])]);
                if ('symbol' === strtolower($target->localName)) {
                    for ($child = $target->firstElementChild; null !== $child; $child = $child->nextElementSibling) {
                        $this->walk($child, $moved, $properties);
                    }
                } else {
                    $this->walk($target, $moved, $properties);
                }
                array_pop($this->using);
            }

            return;
        }

        // Its text, in the bitmap font of GD scaled to its size, at its baseline
        if ('text' === $tag) {
            if ('hidden' !== strtolower($properties['visibility'] ?? '') && '' !== $content = trim((string) preg_replace('/\s+/', ' ', $element->textContent ?? ''))) {
                $this->addText($element, $content, $matrix, $properties);
            }

            return;
        }

        if (null !== $subpaths = $this->geometry($element, $tag, $vertices)) {
            if ('hidden' !== strtolower($properties['visibility'] ?? '') && 'collapse' !== strtolower($properties['visibility'] ?? '')) {
                $this->draw($subpaths, $vertices, \in_array($tag, ['line', 'polyline', 'polygon', 'path'], true), $matrix, $properties);
            }

            return;
        }
        for ($child = $element->firstElementChild; null !== $child; $child = $child->nextElementSibling) {
            if (\count($this->shapes) >= self::MAX_SHAPES || $this->points >= self::MAX_POINTS) {
                return;
            }
            $this->walk($child, $matrix, $properties);
        }
    }

    /**
     * The outline of a basic shape or a path, in its user space; null for an element that is not one.
     *
     * @param list<list<int>>|null $vertices Set to the points of each subpath that end a command of a path, null when every point does
     *
     * @return list<array{points: list<array{float, float}>, closed: bool}>|null
     */
    private function geometry(\Dom\Element $element, string $tag, ?array &$vertices = null): ?array
    {
        $vertices = null;
        [, , $boxWidth, $boxHeight] = $this->viewBox;
        $diagonal = sqrt(($boxWidth ** 2 + $boxHeight ** 2) / 2);
        $css = $this->geometryDeclared($element, $tag);
        $value = static fn (string $name): ?string => $css[$name] ?? $element->getAttribute($name);
        $attribute = static fn (string $name, float $reference): float => self::length($value($name), $reference);

        return match ($tag) {
            'rect' => self::rect($attribute('x', $boxWidth), $attribute('y', $boxHeight), $attribute('width', $boxWidth), $attribute('height', $boxHeight), $value('rx'), $value('ry'), $boxWidth, $boxHeight),
            'circle' => self::ellipse($attribute('cx', $boxWidth), $attribute('cy', $boxHeight), $attribute('r', $diagonal), $attribute('r', $diagonal)),
            'ellipse' => self::ellipse($attribute('cx', $boxWidth), $attribute('cy', $boxHeight), $attribute('rx', $boxWidth), $attribute('ry', $boxHeight)),
            'line' => [['points' => [[$attribute('x1', $boxWidth), $attribute('y1', $boxHeight)], [$attribute('x2', $boxWidth), $attribute('y2', $boxHeight)]], 'closed' => false]],
            'polyline', 'polygon' => self::polyline($element->getAttribute('points') ?? '', 'polygon' === $tag, self::MAX_POINTS - $this->points),
            'path' => self::path($value('d') ?? '', self::MAX_POINTS - $this->points, $vertices),
            default => null,
        };
    }

    /** The properties of the geometry of each shape, see geometryDeclared() */
    private const GEOMETRY = ['rect' => ['x', 'y', 'width', 'height', 'rx', 'ry'], 'circle' => ['cx', 'cy', 'r'], 'ellipse' => ['cx', 'cy', 'rx', 'ry'], 'path' => ['d'], 'text' => ['x', 'y']];

    /**
     * The geometry of a shape its style sheets and its style attribute set, over its attributes, like rect { rx: 2px }
     * or d: path("M0 0 L5 5"): the commands of a path, and only lengths, those that are not (auto, calc()...) leaving
     * the attribute.
     *
     * @return array<string, string>
     */
    private function geometryDeclared(\Dom\Element $element, string $tag): array
    {
        if (!isset(self::GEOMETRY[$tag]) || (null === $this->cascade && !$element->hasAttribute('style'))) {
            return [];
        }
        $declared = [];
        foreach (self::GEOMETRY[$tag] as $name) {
            // Without a cascade, declared() reads the attributes too: only those of the style attribute differ
            if (null === $this->cascade && !preg_match('/(?:^|;)\s*'.$name.'\s*:/i', $element->getAttribute('style') ?? '')) {
                continue;
            }
            if (null === $value = $this->declared($element, [$name])[$name] ?? null) {
                continue;
            }
            if ('d' === $name) {
                if ('none' === strtolower($value)) {
                    $declared['d'] = '';
                } elseif (preg_match('/^path\(\s*(["\'])([^"\']*)\1\s*\)$/i', $value, $m)) {
                    $declared['d'] = $m[2];
                }
            } elseif (preg_match('/^[+-]?(?:\d+\.?\d*|\.\d+)(?:e[+-]?\d+)?(?:%|px|em|rem|pt|pc|in|cm|mm)?$/i', $value) || (\in_array($name, ['rx', 'ry'], true) && 'auto' === strtolower($value))) {
                $declared[$name] = $value;
            }
        }

        return $declared;
    }

    /**
     * The pattern a paint names, url() of a <pattern>: its content as an image of its tile, the tile in the user space
     * of the shape (x, y, width and height, of the box of the shape by default), the transform of that space with the
     * patternTransform, and the fallback color after the url(); null for another paint.
     *
     * @param list<array{points: list<array{float, float}>, closed: bool}> $subpaths
     * @param array{float, float, float, float, float, float}              $matrix
     * @param array{int, int, int, float}|'current'|null                   $color    The color, for currentColor
     *
     * @return array{'pattern', self|null, array{float, float, float, float}, array{float, float, float, float, float, float}, array{int, int, int, float}|'current'|null}|null
     */
    private function pattern(string $value, array $subpaths, array $matrix, array|string|null $color): ?array
    {
        if (!preg_match('/^\s*url\(\s*[\'"]?#([^\'")\s]+)[\'"]?\s*\)/i', $value, $m) || null === ($element = $this->ids[$m[1]] ?? null) || 'pattern' !== strtolower($element->localName)) {
            return null;
        }
        $fallback = self::paint($value, $color);
        if (\count($this->patterns) >= 4 || \in_array($element, $this->patterns, true) || ++$this->patternCount > self::MAX_PATTERNS) {
            return ['pattern', null, [0.0, 0.0, 0.0, 0.0], self::IDENTITY, $fallback];
        }
        // Its attributes and its content, those of the patterns its href names when it has none
        $attribute = function (string $name) use ($element): ?string {
            for ($pattern = $element, $depth = 0; null !== $pattern && $depth < 8; ++$depth) {
                if (null !== $value = $pattern->getAttribute($name)) {
                    return $value;
                }
                $href = trim($pattern->getAttribute('href') ?? $pattern->getAttributeNS('http://www.w3.org/1999/xlink', 'href') ?? '');
                $pattern = str_starts_with($href, '#') ? $this->ids[substr($href, 1)] ?? null : null;
                $pattern = null !== $pattern && 'pattern' === strtolower($pattern->localName) ? $pattern : null;
            }

            return null;
        };
        $content = $element;
        for ($depth = 0; null === $content->firstElementChild && $depth < 8; ++$depth) {
            $href = trim($content->getAttribute('href') ?? $content->getAttributeNS('http://www.w3.org/1999/xlink', 'href') ?? '');
            if (null === ($next = str_starts_with($href, '#') ? $this->ids[substr($href, 1)] ?? null : null) || 'pattern' !== strtolower($next->localName)) {
                break;
            }
            $content = $next;
        }

        $byBox = 'userspaceonuse' !== strtolower(trim($attribute('patternUnits') ?? ''));
        $contentByBox = 'objectboundingbox' === strtolower(trim($attribute('patternContentUnits') ?? '')) && null === $attribute('viewBox');
        // The box of the shape, which a line has not: a pattern of its box paints nothing then
        $box = self::bounds($subpaths);
        if (null === $box && ($byBox || $contentByBox)) {
            return ['pattern', null, [0.0, 0.0, 0.0, 0.0], self::IDENTITY, null];
        }
        [$left, $top, $right, $bottom] = $box ?? [0.0, 0.0, 1.0, 1.0];
        [$boxWidth, $boxHeight] = [$right - $left, $bottom - $top];
        $at = fn (string $name, bool $x): float => $byBox
            ? ($x ? $left : $top) + self::length($attribute($name), 1.0) * ($x ? $boxWidth : $boxHeight)
            : self::length($attribute($name), $x ? $this->viewBox[2] : $this->viewBox[3]);
        $size = fn (string $name, bool $x): float => $byBox ? self::length($attribute($name), 1.0) * ($x ? $boxWidth : $boxHeight) : self::length($attribute($name), $x ? $this->viewBox[2] : $this->viewBox[3]);
        $tile = [$at('x', true), $at('y', false), $size('width', true), $size('height', false)];
        // A tile without area paints nothing
        if ($tile[2] <= 0 || $tile[3] <= 0) {
            return ['pattern', null, $tile, self::IDENTITY, null];
        }

        // The content in its viewBox, else in user units from the corner of the tile, or in fractions of the box of the shape
        $viewBox = self::numbers($attribute('viewBox') ?? '');
        if (4 === \count($viewBox) && $viewBox[2] > 0 && $viewBox[3] > 0) {
            [$fit, $align] = self::aspectRatio($attribute('preserveAspectRatio') ?? '');
        } else {
            [$fit, $align] = ['none', [0.0, 0.0]];
            $viewBox = $contentByBox ? [0.0, 0.0, $tile[2] / $boxWidth, $tile[3] / $boxHeight] : [0.0, 0.0, $tile[2], $tile[3]];
        }
        $image = new self($viewBox, $tile[2], $tile[3], $fit, $align);
        $image->ids = $this->ids;
        $image->cascade = $this->cascade;
        $image->markerProperties = new \SplObjectStorage();
        $image->patterns = [...$this->patterns, $element];
        $image->patternCount = $this->patternCount;
        $image->walks = $this->walks;
        $image->points = $this->points;
        $inherited = $this->inheritedProperties($content);
        for ($child = $content->firstElementChild; null !== $child; $child = $child->nextElementSibling) {
            $image->walk($child, self::IDENTITY, $inherited);
        }
        $this->walks = $image->walks;
        $this->points = $image->points;
        $this->patternCount = $image->patternCount;
        $image->ids = [];
        $image->cascade = null;

        return ['pattern', $image->shapes ? $image : null, $tile, self::multiply($matrix, self::transform($attribute('patternTransform') ?? '')), $image->shapes ? $fallback : null];
    }

    /**
     * The properties the content of a <marker> or a <pattern> inherits: those of what holds it, not of the shape.
     *
     * @return array<string, string>
     */
    private function inheritedProperties(\Dom\Element $element): array
    {
        if (!isset($this->markerProperties[$element])) {
            $inherited = self::INITIAL;
            $chain = [];
            for ($ancestor = $element; null !== $ancestor; $ancestor = $ancestor->parentElement) {
                $chain[] = $ancestor;
            }
            foreach (array_reverse($chain) as $ancestor) {
                $inherited = array_diff_key($this->properties($ancestor), ['opacity' => 0, 'display' => 0, 'clip-path' => 0, 'mask' => 0, 'filter' => 0]) + $inherited;
            }
            $this->markerProperties[$element] = $inherited;
        }

        return $this->markerProperties[$element];
    }

    /**
     * Sets the tile of a pattern on the canvas for the next fills, its corner where the tile of the pattern starts:
     * IMG_COLOR_TILED; the fallback color when it cannot be drawn, like a tile smaller than two pixels or turned.
     *
     * @param array{'pattern', self|null, array{float, float, float, float}, array{float, float, float, float, float, float}, array{int, int, int, float}|'current'|null} $pattern
     * @param array{float, float, float, float}                                                                                                                           $view         The scale and the offset of the view box in the canvas
     * @param array{int, int, int}                                                                                                                                        $currentColor
     * @param array<string, \GdImage>                                                                                                                                     $tiles        The tiles set, kept until the canvas is drawn
     */
    private static function tile(\GdImage $canvas, array $pattern, float $opacity, array $view, int $canvasWidth, int $canvasHeight, array $currentColor, int &$work, array &$tiles): ?int
    {
        [, $content, [$x, $y, $width, $height], $matrix, $fallback] = $pattern;
        [$sx, $sy, $tx, $ty] = $view;
        // Sizes past any canvas, even infinite with a huge patternTransform, are its fallback color too
        $tileWidth = (int) round(min(1e6, abs($width * $matrix[0] * $sx)));
        $tileHeight = (int) round(min(1e6, abs($height * $matrix[3] * $sy)));
        // Cells are not turned: a pattern turned or skewed is its fallback color
        $turned = abs($matrix[1]) > 1e-9 || abs($matrix[2]) > 1e-9;
        // No larger than the largest canvas, however deep in patterns, and as many pixels kept in all as a few canvases
        $largest = self::MAX_SIDE * self::SUPERSAMPLE;
        $kept = array_sum(array_map(static fn (\GdImage $tile) => imagesx($tile) * imagesy($tile), $tiles));
        if (null === $content || $turned || $tileWidth < 2 || $tileHeight < 2 || $tileWidth > $largest || $tileHeight > $largest || $kept + $tileWidth * $tileHeight > 4 * $largest ** 2) {
            return self::allocate($canvas, $fallback, $opacity, $currentColor);
        }
        // The same pattern at the same place and opacity is the same tile, like that of many shapes
        $key = spl_object_id($content).':'.implode(',', [$x, $y, $width, $height, ...$matrix, ...$view, $opacity]);
        if (isset($tiles[$key])) {
            imagesettile($canvas, $tiles[$key]);

            return \IMG_COLOR_TILED;
        }
        $work += 1 + (int) ($tileWidth * $tileHeight / 16);
        if ($work > self::MAX_WORK || null === $drawn = $content->canvas($tileWidth, $tileHeight, $currentColor, $work)) {
            return null;
        }
        if ($opacity < 1.0) {
            // Pixel by pixel: counted before
            $work += (int) ($tileWidth * $tileHeight / 4);
            if ($work > self::MAX_WORK) {
                return null;
            }
            imagealphablending($drawn, false);
            for ($row = 0; $row < $tileHeight; ++$row) {
                for ($column = 0; $column < $tileWidth; ++$column) {
                    $pixel = imagecolorat($drawn, $column, $row);
                    $alpha = 127 - (int) round((127 - ($pixel >> 24 & 0x7F)) * max(0.0, $opacity));
                    imagesetpixel($drawn, $column, $row, ($pixel & 0xFFFFFF) | ($alpha << 24));
                }
            }
        }
        // GD starts the tiles at the corner of the canvas: shifted so that one starts where the pattern does
        $left = ($matrix[0] * $x + $matrix[2] * $y + $matrix[4]) * $sx + $tx;
        $top = ($matrix[1] * $x + $matrix[3] * $y + $matrix[5]) * $sy + $ty;
        $dx = (((int) round($left)) % $tileWidth + $tileWidth) % $tileWidth;
        $dy = (((int) round($top)) % $tileHeight + $tileHeight) % $tileHeight;
        if (null === $tile = self::transparent($tileWidth, $tileHeight)) {
            return null;
        }
        imagealphablending($tile, false);
        imagecopy($tile, $drawn, $dx, $dy, 0, 0, $tileWidth - $dx, $tileHeight - $dy);
        imagecopy($tile, $drawn, 0, $dy, $tileWidth - $dx, 0, $dx, $tileHeight - $dy);
        imagecopy($tile, $drawn, $dx, 0, 0, $tileHeight - $dy, $tileWidth - $dx, $dy);
        imagecopy($tile, $drawn, 0, 0, $tileWidth - $dx, $tileHeight - $dy, $dx, $dy);
        imagesettile($canvas, $tile);
        $tiles[$key] = $tile;

        return \IMG_COLOR_TILED;
    }

    /** The weights of red, green and blue in the luminance */
    private const LUMINANCE = [0.2126, 0.7152, 0.0722];

    /**
     * The filter a filter property names: url() of a <filter> and its primitives, or CSS filter functions, like
     * grayscale(1) drop-shadow(1px 1px 2px black); its region, of the box of the shape by default, -10% and 120%.
     *
     * @param array{float, float, float, float, float, float} $matrix
     */
    private function filter(string $value, \Dom\Element $element, string $tag, array $matrix): ?int
    {
        $value = trim($value);
        if ('' === $value || 'none' === strtolower($value) || \count($this->filterDefs) >= self::MAX_CLIPS) {
            return null;
        }
        // Lengths in the user space of the shape, scaled like its stroke
        $scale = sqrt(abs($matrix[0] * $matrix[3] - $matrix[1] * $matrix[2]));
        $box = null !== ($subpaths = $this->geometry($element, $tag)) ? self::bounds($subpaths) : null;
        $corners = static fn (array $r): array => array_map(static fn (array $p) => [$matrix[0] * $p[0] + $matrix[2] * $p[1] + $matrix[4], $matrix[1] * $p[0] + $matrix[3] * $p[1] + $matrix[5]], [[$r[0], $r[1]], [$r[0] + $r[2], $r[1]], [$r[0] + $r[2], $r[1] + $r[3]], [$r[0], $r[1] + $r[3]]]);

        $url = (bool) preg_match('/^url\(\s*([\'"]?)#([^\'")\s]+)\1\s*\)$/i', $value, $m);
        if ($url) {
            if (null === ($filter = $this->ids[$m[2]] ?? null) || 'filter' !== strtolower($filter->localName) || null === $primitives = $this->primitives($filter, $scale)) {
                return null;
            }
            $byBox = 'userspaceonuse' !== strtolower(trim($filter->getAttribute('filterUnits') ?? ''));
            $region = null;
            if ($byBox && null !== $box) {
                [$left, $top, $right, $bottom] = $box;
                $at = static fn (string $name, string $default, bool $x): float => ($x ? $left : $top) + self::length($filter->getAttribute($name) ?? $default, 1.0) * ($x ? $right - $left : $bottom - $top);
                $region = [$at('x', '-10%', true), $at('y', '-10%', false), self::length($filter->getAttribute('width') ?? '120%', 1.0) * ($right - $left), self::length($filter->getAttribute('height') ?? '120%', 1.0) * ($bottom - $top)];
            } elseif (!$byBox) {
                $region = [self::length($filter->getAttribute('x') ?? '-10%', $this->viewBox[2]), self::length($filter->getAttribute('y') ?? '-10%', $this->viewBox[3]), self::length($filter->getAttribute('width') ?? '120%', $this->viewBox[2]), self::length($filter->getAttribute('height') ?? '120%', $this->viewBox[3])];
            }
        } else {
            if (null === $primitives = self::functions($value, $scale)) {
                return null;
            }
            $region = null !== $box ? [$box[0] - 0.1 * ($box[2] - $box[0]), $box[1] - 0.1 * ($box[3] - $box[1]), 1.2 * ($box[2] - $box[0]), 1.2 * ($box[3] - $box[1])] : null;
            // A shadow or a blur reaches past the box: the region grows with them
            if (null !== $region) {
                $reach = 0.0;
                foreach ($primitives as $primitive) {
                    $reach += (3 * ($primitive['std'] ?? 0) + abs($primitive['dx'] ?? 0) + abs($primitive['dy'] ?? 0)) / max(1e-9, $scale);
                }
                $region = [$region[0] - $reach, $region[1] - $reach, $region[2] + 2 * $reach, $region[3] + 2 * $reach];
            }
        }
        // A region without area draws nothing
        if (null !== $region && ($region[2] <= 0 || $region[3] <= 0)) {
            $primitives = [['type' => 'flood', 'color' => null, 'opacity' => 0.0, 'in' => null, 'in2' => null, 'result' => null]];
        }
        $this->filterDefs[] = [$primitives, null !== $region && $region[2] > 0 && $region[3] > 0 ? $corners($region) : null];

        return \count($this->filterDefs) - 1;
    }

    /**
     * The primitives of a <filter>; null when it has one this does not draw, like feTurbulence: then not applied.
     *
     * @return list<array<string, mixed>>|null
     */
    private function primitives(\Dom\Element $filter, float $scale): ?array
    {
        $primitives = [];
        for ($element = $filter->firstElementChild; null !== $element; $element = $element->nextElementSibling) {
            $own = $this->properties($element);
            $number = static fn (string $name, float $default): float => self::numbers($element->getAttribute($name) ?? '')[0] ?? $default;
            $color = static fn () => self::paint($own['flood-color'] ?? $element->getAttribute('flood-color') ?? 'black', null);
            $opacity = static fn (): float => self::fraction($own['flood-opacity'] ?? $element->getAttribute('flood-opacity') ?? '1');
            $primitive = match (strtolower($element->localName)) {
                'fegaussianblur' => ['type' => 'blur', 'std' => max(0.0, $number('stdDeviation', 0.0)) * $scale],
                'feoffset' => ['type' => 'offset', 'dx' => $number('dx', 0.0) * $scale, 'dy' => $number('dy', 0.0) * $scale],
                'feflood' => ['type' => 'flood', 'color' => $color(), 'opacity' => $opacity()],
                'fedropshadow' => ['type' => 'shadow', 'dx' => $number('dx', 2.0) * $scale, 'dy' => $number('dy', 2.0) * $scale, 'std' => max(0.0, $number('stdDeviation', 2.0)) * $scale, 'color' => $color(), 'opacity' => $opacity()],
                'fecolormatrix' => ['type' => 'matrix', 'matrix' => self::colorMatrix(strtolower(trim($element->getAttribute('type') ?? 'matrix')), $element->getAttribute('values'))],
                'femerge' => ['type' => 'merge', 'inputs' => \array_slice(array_values(array_map(static fn (\Dom\Element $node) => $node->getAttribute('in'), array_filter(array_values(array_filter(iterator_to_array($element->childNodes, false), static fn (\Dom\Node $node): bool => $node instanceof \Dom\Element)), static fn (\Dom\Element $node) => 'femergenode' === strtolower($node->localName)))), 0, 16)],
                'fecomposite' => ['type' => 'composite', 'operator' => \in_array($operator = strtolower(trim($element->getAttribute('operator') ?? 'over')), ['over', 'in', 'out', 'atop', 'xor'], true) ? $operator : 'over'],
                default => null,
            };
            if (null === $primitive || ('matrix' === $primitive['type'] && null === $primitive['matrix']) || \count($primitives) >= 32) {
                return null;
            }
            $primitives[] = $primitive + ['in' => $element->getAttribute('in'), 'in2' => $element->getAttribute('in2'), 'result' => $element->getAttribute('result')];
        }

        return $primitives;
    }

    /**
     * The matrix of a feColorMatrix: 20 numbers, rows of red, green, blue and alpha; null when invalid.
     *
     * @return list<float>|null
     */
    private static function colorMatrix(string $type, ?string $values): ?array
    {
        $numbers = self::numbers($values ?? '');

        return match ($type) {
            'matrix' => null === $values ? [1.0, 0.0, 0.0, 0.0, 0.0, 0.0, 1.0, 0.0, 0.0, 0.0, 0.0, 0.0, 1.0, 0.0, 0.0, 0.0, 0.0, 0.0, 1.0, 0.0] : (20 === \count($numbers) ? $numbers : null),
            'saturate' => self::saturation($numbers[0] ?? 1.0),
            'huerotate' => self::hueRotation($numbers[0] ?? 0.0),
            'luminancetoalpha' => [0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, ...self::LUMINANCE, 0.0, 0.0],
            default => null,
        };
    }

    /**
     * @return list<float>
     */
    private static function saturation(float $s): array
    {
        [$r, $g, $b] = self::LUMINANCE;

        return [$r + (1 - $r) * $s, $g - $g * $s, $b - $b * $s, 0.0, 0.0, $r - $r * $s, $g + (1 - $g) * $s, $b - $b * $s, 0.0, 0.0, $r - $r * $s, $g - $g * $s, $b + (1 - $b) * $s, 0.0, 0.0, 0.0, 0.0, 0.0, 1.0, 0.0];
    }

    /**
     * @return list<float>
     */
    private static function hueRotation(float $degrees): array
    {
        [$cos, $sin] = [cos(deg2rad($degrees)), sin(deg2rad($degrees))];

        return [
            0.213 + $cos * 0.787 - $sin * 0.213, 0.715 - $cos * 0.715 - $sin * 0.715, 0.072 - $cos * 0.072 + $sin * 0.928, 0.0, 0.0,
            0.213 - $cos * 0.213 + $sin * 0.143, 0.715 + $cos * 0.285 + $sin * 0.140, 0.072 - $cos * 0.072 - $sin * 0.283, 0.0, 0.0,
            0.213 - $cos * 0.213 - $sin * 0.787, 0.715 - $cos * 0.715 + $sin * 0.715, 0.072 + $cos * 0.928 + $sin * 0.072, 0.0, 0.0,
            0.0, 0.0, 0.0, 1.0, 0.0,
        ];
    }

    /**
     * The primitives of CSS filter functions, one after the other; null when one is not valid.
     *
     * @return list<array<string, mixed>>|null
     */
    private static function functions(string $value, float $scale): ?array
    {
        if (!preg_match_all('/([a-z-]+)\(((?:[^()]|\([^()]*\))*)\)/i', $value, $calls, \PREG_SET_ORDER) || \count($calls) > 16) {
            return null;
        }
        $primitives = [];
        foreach ($calls as [, $name, $argument]) {
            $argument = trim($argument);
            $amount = static fn (float $default): float => '' === $argument ? $default : (str_ends_with($argument, '%') ? (float) $argument / 100 : (float) $argument);
            $name = strtolower($name);
            $matrix = match ($name) {
                'grayscale' => self::saturation(1 - min(1.0, max(0.0, $amount(1.0)))),
                'saturate' => self::saturation(max(0.0, $amount(1.0))),
                'sepia' => self::sepia(min(1.0, max(0.0, $amount(1.0)))),
                'hue-rotate' => self::hueRotation(self::angle(strtolower($argument ?: '0'))),
                'invert' => (static fn (float $a): array => [1 - 2 * $a, 0.0, 0.0, 0.0, $a, 0.0, 1 - 2 * $a, 0.0, 0.0, $a, 0.0, 0.0, 1 - 2 * $a, 0.0, $a, 0.0, 0.0, 0.0, 1.0, 0.0])(min(1.0, max(0.0, $amount(1.0)))),
                'brightness' => (static fn (float $b): array => [$b, 0.0, 0.0, 0.0, 0.0, 0.0, $b, 0.0, 0.0, 0.0, 0.0, 0.0, $b, 0.0, 0.0, 0.0, 0.0, 0.0, 1.0, 0.0])(max(0.0, $amount(1.0))),
                'contrast' => (static fn (float $c): array => [$c, 0.0, 0.0, 0.0, 0.5 - 0.5 * $c, 0.0, $c, 0.0, 0.0, 0.5 - 0.5 * $c, 0.0, 0.0, $c, 0.0, 0.5 - 0.5 * $c, 0.0, 0.0, 0.0, 1.0, 0.0])(max(0.0, $amount(1.0))),
                'opacity' => (static fn (float $o): array => [1.0, 0.0, 0.0, 0.0, 0.0, 0.0, 1.0, 0.0, 0.0, 0.0, 0.0, 0.0, 1.0, 0.0, 0.0, 0.0, 0.0, 0.0, $o, 0.0])(min(1.0, max(0.0, $amount(1.0)))),
                default => null,
            };
            $none = ['in' => null, 'in2' => null, 'result' => null];
            if (null !== $matrix) {
                $primitives[] = ['type' => 'matrix', 'matrix' => $matrix] + $none;
            } elseif ('blur' === $name) {
                $primitives[] = ['type' => 'blur', 'std' => max(0.0, self::length($argument ?: '0', 1.0)) * $scale] + $none;
            } elseif ('drop-shadow' === $name) {
                // Two or three lengths and a color, in any order
                $color = trim((string) preg_replace('/(?<![\w#(-])[+-]?(?:\d+\.?\d*|\.\d+)(?:px|em|rem)?(?![\w(%])/i', '', $argument));
                preg_match_all('/(?<![\w#(-])[+-]?(?:\d+\.?\d*|\.\d+)(?:px|em|rem)?(?![\w(%])/i', (string) preg_replace('/[a-z-]+\([^)]*\)/i', '', $argument), $lengths);
                [$dx, $dy, $std] = array_map(static fn (string $length) => self::length($length, 1.0), $lengths[0]) + [0.0, 0.0, 0.0];
                $primitives[] = ['type' => 'shadow', 'dx' => $dx * $scale, 'dy' => $dy * $scale, 'std' => max(0.0, $std / 2) * $scale, 'color' => self::paint('' !== $color ? $color : 'black', null), 'opacity' => 1.0] + $none;
            } else {
                return null;
            }
        }

        return $primitives;
    }

    /**
     * @return list<float>
     */
    private static function sepia(float $a): array
    {
        $b = 1 - $a;

        return [0.393 + 0.607 * $b, 0.769 - 0.769 * $b, 0.189 - 0.189 * $b, 0.0, 0.0, 0.349 - 0.349 * $b, 0.686 + 0.314 * $b, 0.168 - 0.168 * $b, 0.0, 0.0, 0.272 - 0.272 * $b, 0.534 - 0.534 * $b, 0.131 + 0.869 * $b, 0.0, 0.0, 0.0, 0.0, 0.0, 1.0, 0.0];
    }

    /**
     * Applies a filter to the layer within its region, which the box grows to: the primitives one after the other on
     * images of the region, each counted as work, the last result put back.
     *
     * @param \Closure(array{float, float}): array{float, float} $map
     *
     * @param-out array{int, int, int, int} $box
     *
     * @param array{int, int, int, int} $box   Left, top, right and bottom, in the canvas
     * @param array{float, float}       $scale The pixels of the canvas per unit of the view box
     */
    private function applyFilter(\GdImage $layer, int $filter, \Closure $map, array &$box, array $scale, int $canvasWidth, int $canvasHeight, int &$work): bool
    {
        [$primitives, $corners] = $this->filterDefs[$filter];
        if (null !== $corners) {
            $points = array_map($map, $corners);
            [$left, $top, $right, $bottom] = [max(0, (int) floor(min(array_column($points, 0)))), max(0, (int) floor(min(array_column($points, 1)))), min($canvasWidth - 1, (int) ceil(max(array_column($points, 0)))), min($canvasHeight - 1, (int) ceil(max(array_column($points, 1))))];
        } else {
            [$left, $top, $right, $bottom] = [0, 0, $canvasWidth - 1, $canvasHeight - 1];
        }
        $width = $right - $left + 1;
        $height = $bottom - $top + 1;
        if ($width <= 0 || $height <= 0) {
            return true;
        }
        $work += $width * $height;
        if ($work > self::MAX_WORK || null === $source = self::transparent($width, $height)) {
            return false;
        }
        imagealphablending($source, false);
        imagecopy($source, $layer, 0, 0, $left, $top, $width, $height);
        $pixels = sqrt(abs($scale[0] * $scale[1]));
        $results = [];
        $last = $source;
        // The alpha of the source, made once
        $alpha = null;
        foreach ($primitives as $primitive) {
            $input = static function (?string $name) use (&$alpha, $source, &$last, &$results): ?\GdImage {
                return match ($name) {
                    null, '' => $last,
                    'SourceGraphic' => $source,
                    'SourceAlpha' => $alpha ??= self::alphaOf($source),
                    default => $results[$name] ?? $last,
                };
            };
            // Pixel by pixel for colors and composites, and an image for each input merged: counted as such
            $work += $width * $height * (\in_array($primitive['type'], ['matrix', 'shadow', 'composite'], true) ? 4 : 1 + \count($primitive['inputs'] ?? []));
            if ($work > self::MAX_WORK) {
                return false;
            }
            $result = match ($primitive['type']) {
                'blur' => self::blur($input($primitive['in']), $primitive['std'] * $pixels),
                'offset' => self::offset($input($primitive['in']), (int) round($primitive['dx'] * $scale[0]), (int) round($primitive['dy'] * $scale[1])),
                'flood' => self::flood($width, $height, $primitive['color'], $primitive['opacity']),
                'matrix' => self::recolor($input($primitive['in']), $primitive['matrix']),
                'shadow' => self::shadow($input($primitive['in']), (int) round($primitive['dx'] * $scale[0]), (int) round($primitive['dy'] * $scale[1]), $primitive['std'] * $pixels, $primitive['color'], $primitive['opacity']),
                'merge' => self::layered(array_map($input, $primitive['inputs'] ?: [null])),
                'composite' => self::compose($input($primitive['in']), $input($primitive['in2']), $primitive['operator']),
                default => $last,
            };
            if (null === $result) {
                return false;
            }
            if (null !== $primitive['result'] && '' !== $primitive['result']) {
                $results[$primitive['result']] = $result;
            }
            $last = $result;
        }
        // Back on the layer, in place of what was drawn in the region
        imagealphablending($layer, false);
        imagecopy($layer, $last, $left, $top, 0, 0, $width, $height);
        imagealphablending($layer, true);
        $box = [min($box[0], $left), min($box[1], $top), max($box[2], $right), max($box[3], $bottom)];

        return true;
    }

    private static function copy(?\GdImage $image): ?\GdImage
    {
        if (null === $image || null === $copy = self::transparent(imagesx($image), imagesy($image))) {
            return null;
        }
        imagealphablending($copy, false);
        imagecopy($copy, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        return $copy;
    }

    /**
     * The alpha of an image, in black.
     */
    private static function alphaOf(\GdImage $image): ?\GdImage
    {
        if (null !== $copy = self::copy($image)) {
            imagefilter($copy, \IMG_FILTER_BRIGHTNESS, -255);
        }

        return $copy;
    }

    /**
     * Blurred by about that many pixels each way: scaled down, then up, which averages the colors and the alpha.
     */
    private static function blur(?\GdImage $image, float $deviation): ?\GdImage
    {
        if (null === $image || $deviation < 0.5) {
            return $image;
        }
        [$width, $height] = [imagesx($image), imagesy($image)];
        $factor = min(64.0, 1 + 2 * $deviation);
        $smallWidth = max(1, (int) round($width / $factor));
        $smallHeight = max(1, (int) round($height / $factor));
        if (null === ($small = self::transparent($smallWidth, $smallHeight)) || null === $blurred = self::transparent($width, $height)) {
            return null;
        }
        imagealphablending($small, false);
        imagealphablending($blurred, false);
        imagecopyresampled($small, $image, 0, 0, 0, 0, $smallWidth, $smallHeight, $width, $height);
        imagecopyresampled($blurred, $small, 0, 0, 0, 0, $width, $height, $smallWidth, $smallHeight);

        return $blurred;
    }

    private static function offset(?\GdImage $image, int $dx, int $dy): ?\GdImage
    {
        if (null === $image || null === $moved = self::transparent(imagesx($image), imagesy($image))) {
            return null;
        }
        imagealphablending($moved, false);
        imagecopy($moved, $image, $dx, $dy, 0, 0, imagesx($image), imagesy($image));

        return $moved;
    }

    /**
     * @param array{int, int, int, float}|'current'|null $color
     */
    private static function flood(int $width, int $height, array|string|null $color, float $opacity): ?\GdImage
    {
        if (null === $image = self::transparent($width, $height)) {
            return null;
        }
        [$r, $g, $b, $alpha] = \is_array($color) ? $color : [0, 0, 0, null === $color ? 0.0 : 1.0];
        if (($alpha *= $opacity) > 0) {
            imagealphablending($image, false);
            imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, (int) imagecolorallocatealpha($image, $r, $g, $b, (int) round(127 * (1 - min(1.0, $alpha)))));
        }

        return $image;
    }

    /**
     * Each pixel through a color matrix, on its colors from 0 to 1, unpremultiplied.
     *
     * @param list<float> $matrix
     */
    private static function recolor(?\GdImage $image, array $matrix): ?\GdImage
    {
        if (null === $out = self::copy($image)) {
            return null;
        }
        [$width, $height] = [imagesx($out), imagesy($out)];
        for ($y = 0; $y < $height; ++$y) {
            for ($x = 0; $x < $width; ++$x) {
                $pixel = imagecolorat($out, $x, $y);
                $c = [($pixel >> 16 & 0xFF) / 255, ($pixel >> 8 & 0xFF) / 255, ($pixel & 0xFF) / 255, (127 - ($pixel >> 24 & 0x7F)) / 127];
                if (0.0 === $c[3] && 0.0 === (float) $matrix[19]) {
                    continue;
                }
                $v = [];
                for ($row = 0; $row < 20; $row += 5) {
                    $v[] = max(0.0, min(1.0, $matrix[$row] * $c[0] + $matrix[$row + 1] * $c[1] + $matrix[$row + 2] * $c[2] + $matrix[$row + 3] * $c[3] + $matrix[$row + 4]));
                }
                imagesetpixel($out, $x, $y, (127 - (int) round($v[3] * 127)) << 24 | (int) round($v[0] * 255) << 16 | (int) round($v[1] * 255) << 8 | (int) round($v[2] * 255));
            }
        }

        return $out;
    }

    /**
     * The image over its shadow: its alpha in the color, blurred and moved.
     *
     * @param array{int, int, int, float}|'current'|null $color
     */
    private static function shadow(?\GdImage $image, int $dx, int $dy, float $deviation, array|string|null $color, float $opacity): ?\GdImage
    {
        [$r, $g, $b, $alpha] = \is_array($color) ? [$color[0] / 255, $color[1] / 255, $color[2] / 255, $color[3]] : [0.0, 0.0, 0.0, 1.0];
        $shade = self::recolor($image, [0.0, 0.0, 0.0, 0.0, $r, 0.0, 0.0, 0.0, 0.0, $g, 0.0, 0.0, 0.0, 0.0, $b, 0.0, 0.0, 0.0, $alpha * $opacity, 0.0]);

        return null === $image ? null : self::layered([self::offset(self::blur($shade, $deviation), $dx, $dy), $image]);
    }

    /**
     * @param list<\GdImage|null> $images The first under the others
     */
    private static function layered(array $images): ?\GdImage
    {
        if (!$images || \in_array(null, $images, true) || null === $out = self::transparent(imagesx($images[0]), imagesy($images[0]))) {
            return null;
        }
        foreach ($images as $image) {
            imagecopy($out, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        }

        return $out;
    }

    /**
     * Porter-Duff: the first image over the second, in it, out of it, atop it, or either but where both are.
     */
    private static function compose(?\GdImage $image, ?\GdImage $other, string $operator): ?\GdImage
    {
        if (null === $image || null === $other) {
            return null;
        }
        if ('over' === $operator) {
            return self::layered([$other, $image]);
        }
        if (null === $out = self::transparent(imagesx($image), imagesy($image))) {
            return null;
        }
        imagealphablending($out, false);
        for ($y = 0, $height = imagesy($out); $y < $height; ++$y) {
            for ($x = 0, $width = imagesx($out); $x < $width; ++$x) {
                $p = imagecolorat($image, $x, $y);
                $q = imagecolorat($other, $x, $y);
                $a = (127 - ($p >> 24 & 0x7F)) / 127;
                $b = (127 - ($q >> 24 & 0x7F)) / 127;
                [$alpha, $color] = match ($operator) {
                    'in' => [$a * $b, $p],
                    'out' => [$a * (1 - $b), $p],
                    'atop' => [$b, $a >= 0.5 ? $p : $q],
                    default => [$a * (1 - $b) + $b * (1 - $a), $a >= $b ? $p : $q],
                };
                if ($alpha > 0) {
                    imagesetpixel($out, $x, $y, (127 - (int) round($alpha * 127)) << 24 | ($color & 0xFFFFFF));
                }
            }
        }

        return $out;
    }

    /**
     * The mask a mask property names, url() of a <mask>: its content as an image of the view box, in the user space
     * of the element (or in fractions of the box of its shape, maskContentUnits="objectBoundingBox"), and the clip of
     * its region (x, y, width and height, of the box of the shape by default, -10% and 120%); null for none.
     *
     * @param array{float, float, float, float, float, float} $matrix
     *
     * @return array{int, int}|null
     */
    private function mask(string $value, \Dom\Element $element, string $tag, array $matrix): ?array
    {
        if (!preg_match('/^url\(\s*([\'"]?)#([^\'")\s]+)\1\s*\)$/i', trim($value), $m) || null === ($mask = $this->ids[$m[2]] ?? null) || 'mask' !== strtolower($mask->localName)) {
            return null;
        }
        if (\count($this->maskElements) >= 4 || \in_array($mask, $this->maskElements, true) || \count($this->maskImages) >= self::MAX_CLIPS || \count($this->clipPaths) >= self::MAX_CLIPS) {
            return null;
        }
        // The box of the shape; a group has none here: its region is the view box, its content in user units
        $box = null !== ($subpaths = $this->geometry($element, $tag)) ? self::bounds($subpaths) : null;
        $byBox = 'userspaceonuse' !== strtolower(trim($mask->getAttribute('maskUnits') ?? ''));
        if ($byBox && null !== $box) {
            [$left, $top, $right, $bottom] = $box;
            $at = static fn (string $name, string $default, bool $x): float => ($x ? $left : $top) + self::length($mask->getAttribute($name) ?? $default, 1.0) * ($x ? $right - $left : $bottom - $top);
            $size = static fn (string $name, bool $x): float => self::length($mask->getAttribute($name) ?? '120%', 1.0) * ($x ? $right - $left : $bottom - $top);
            $region = [$at('x', '-10%', true), $at('y', '-10%', false), $size('width', true), $size('height', false)];
        } elseif ($byBox) {
            $region = $this->viewBox;
            $matrix = self::IDENTITY;
        } else {
            $region = [self::length($mask->getAttribute('x') ?? '-10%', $this->viewBox[2]), self::length($mask->getAttribute('y') ?? '-10%', $this->viewBox[3]), self::length($mask->getAttribute('width') ?? '120%', $this->viewBox[2]), self::length($mask->getAttribute('height') ?? '120%', $this->viewBox[3])];
        }
        if ($region[2] <= 0 || $region[3] <= 0) {
            // A region without area masks everything: a clip of nothing
            $this->clipPaths[] = [];

            return [$this->emptyMask(), \count($this->clipPaths) - 1];
        }
        $corners = [[$region[0], $region[1]], [$region[0] + $region[2], $region[1]], [$region[0] + $region[2], $region[1] + $region[3]], [$region[0], $region[1] + $region[3]]];
        $this->clipPaths[] = [[[['points' => array_map(static fn (array $p) => [$matrix[0] * $p[0] + $matrix[2] * $p[1] + $matrix[4], $matrix[1] * $p[0] + $matrix[3] * $p[1] + $matrix[5]], $corners), 'closed' => true]], false]];
        $clip = \count($this->clipPaths) - 1;

        $content = $matrix;
        if ('objectboundingbox' === strtolower(trim($mask->getAttribute('maskContentUnits') ?? '')) && null !== $box) {
            $content = self::multiply($matrix, [$box[2] - $box[0], 0.0, 0.0, $box[3] - $box[1], $box[0], $box[1]]);
        }
        $image = new self($this->viewBox, $this->width, $this->height, $this->fit, $this->align);
        $image->ids = $this->ids;
        $image->cascade = $this->cascade;
        $image->markerProperties = new \SplObjectStorage();
        $image->patterns = $this->patterns;
        $image->patternCount = $this->patternCount;
        $image->maskElements = [...$this->maskElements, $mask];
        $image->walks = $this->walks;
        $image->points = $this->points;
        $inherited = $this->inheritedProperties($mask);
        for ($child = $mask->firstElementChild; null !== $child; $child = $child->nextElementSibling) {
            $image->walk($child, $content, $inherited);
        }
        $this->walks = $image->walks;
        $this->points = $image->points;
        $this->patternCount = $image->patternCount;
        $image->ids = [];
        $image->cascade = null;
        $type = strtolower(trim($this->properties($mask)['mask-type'] ?? 'luminance'));
        $this->maskImages[] = [$image, 'alpha' !== $type];

        return [\count($this->maskImages) - 1, $clip];
    }

    /**
     * A mask without content, which hides what it masks.
     */
    private function emptyMask(): int
    {
        $this->maskImages[] = [new self($this->viewBox, $this->width, $this->height, $this->fit, $this->align), false];

        return \count($this->maskImages) - 1;
    }

    /**
     * The clip a clip-path names, url() of a <clipPath>: the union of its shapes, in the user space of the element
     * (clipPathUnits="userSpaceOnUse") or in fractions of the box of its shape (objectBoundingBox); null for none.
     *
     * @param array{float, float, float, float, float, float} $matrix
     */
    private function clip(string $value, \Dom\Element $element, string $tag, array $matrix): ?int
    {
        if (!preg_match('/^url\(\s*([\'"]?)#([^\'")\s]+)\1\s*\)$/i', trim($value), $m) || null === ($path = $this->ids[$m[2]] ?? null) || 'clippath' !== strtolower($path->localName)) {
            return null;
        }
        if (\in_array($path, $this->clipping, true) || \count($this->clipPaths) >= self::MAX_CLIPS) {
            return null;
        }
        $matrix = self::multiply($matrix, self::transform($path->getAttribute('transform') ?? ''));
        $byBox = 'objectboundingbox' === strtolower(trim($path->getAttribute('clipPathUnits') ?? ''));
        // The same clip path in the same user space is the same clip, like that of many shapes of a group
        $key = spl_object_id($path).':'.implode(',', $matrix);
        if (!$byBox && isset($this->clipKeys[$key])) {
            return $this->clipKeys[$key];
        }
        if ($byBox) {
            // The box of the shape; a group has none here: drawn without the clip
            if (null === ($subpaths = $this->geometry($element, $tag)) || null === $box = self::bounds($subpaths)) {
                return null;
            }
            $matrix = self::multiply($matrix, [$box[2] - $box[0], 0.0, 0.0, $box[3] - $box[1], $box[0], $box[1]]);
        }

        $this->clipping[] = $path;
        $shapes = [];
        $rule = $this->properties($path)['clip-rule'] ?? 'nonzero';
        for ($child = $path->firstElementChild; null !== $child; $child = $child->nextElementSibling) {
            $this->clipShapes($child, $matrix, $rule, $shapes);
        }
        array_pop($this->clipping);
        $this->clipPaths[] = $shapes;

        return $byBox ? \count($this->clipPaths) - 1 : $this->clipKeys[$key] = \count($this->clipPaths) - 1;
    }

    /**
     * Adds the outline of a shape of a <clipPath>, or of the one a <use> there names, to its shapes.
     *
     * @param array{float, float, float, float, float, float}                                 $matrix
     * @param list<array{list<array{points: list<array{float, float}>, closed: bool}>, bool}> $shapes
     */
    private function clipShapes(\Dom\Element $element, array $matrix, string $rule, array &$shapes, int $depth = 0): void
    {
        if (++$this->walks > self::MAX_WALKS || $this->points >= self::MAX_POINTS) {
            return;
        }
        $own = $this->properties($element);
        $visibility = strtolower(trim($own['visibility'] ?? ''));
        if ('none' === strtolower(trim($own['display'] ?? '')) || 'hidden' === $visibility || 'collapse' === $visibility) {
            return;
        }
        $rule = $own['clip-rule'] ?? $rule;
        $matrix = self::multiply($matrix, self::transform($element->getAttribute('transform') ?? ''));
        $tag = strtolower($element->localName);
        if ('use' === $tag && $depth < 8) {
            $href = trim($element->getAttribute('href') ?? $element->getAttributeNS('http://www.w3.org/1999/xlink', 'href') ?? '');
            if (null !== ($target = str_starts_with($href, '#') ? $this->ids[substr($href, 1)] ?? null : null) && !$target->contains($element)) {
                $this->clipShapes($target, self::multiply($matrix, [1.0, 0.0, 0.0, 1.0, self::length($element->getAttribute('x'), $this->viewBox[2]), self::length($element->getAttribute('y'), $this->viewBox[3])]), $rule, $shapes, $depth + 1);
            }

            return;
        }
        if (null === $subpaths = $this->geometry($element, $tag)) {
            return;
        }
        foreach ($subpaths as $i => $subpath) {
            $this->points += \count($subpath['points']);
            $subpaths[$i]['points'] = array_map(static fn (array $p) => [$matrix[0] * $p[0] + $matrix[2] * $p[1] + $matrix[4], $matrix[1] * $p[0] + $matrix[3] * $p[1] + $matrix[5]], $subpath['points']);
        }
        $shapes[] = [$subpaths, 'evenodd' === strtolower(trim($rule))];
    }

    /**
     * The box of the points of subpaths: left, top, right and bottom; null for one without area.
     *
     * @param list<array{points: list<array{float, float}>, closed: bool}> $subpaths
     *
     * @return array{float, float, float, float}|null
     */
    private static function bounds(array $subpaths): ?array
    {
        $points = array_merge(...array_column($subpaths, 'points'));
        if (!$points) {
            return null;
        }
        $xs = array_column($points, 0);
        $ys = array_column($points, 1);
        $box = [min($xs), min($ys), max($xs), max($ys)];

        return $box[2] > $box[0] && $box[3] > $box[1] ? $box : null;
    }

    /**
     * Adds a shape, its fill, its stroke and its markers in the paint-order.
     *
     * @param list<array{points: list<array{float, float}>, closed: bool}> $subpaths
     * @param list<list<int>>|null                                         $vertices
     * @param array{float, float, float, float, float, float}              $matrix
     * @param array<string, string>                                        $properties
     */
    private function draw(array $subpaths, ?array $vertices, bool $markable, array $matrix, array $properties): void
    {
        $order = self::paintOrder($properties['paint-order'] ?? 'normal');
        $markers = $markable && !$this->marking ? $this->markers($subpaths, $vertices, $properties) : [];
        if (!$markers && 'fill' === $order[0]) {
            $this->add($subpaths, $matrix, $properties);

            return;
        }
        foreach ($order as $i => $part) {
            if ('markers' === $part && $markers) {
                $this->marking = true;
                foreach ($markers as [$marker, $at, $angle, $start]) {
                    $this->marker($marker, $at, $angle, $start, $matrix, $properties);
                }
                $this->marking = false;
            } elseif ('fill' === $part && 'stroke' === ($order[$i + 1] ?? null)) {
                $this->add($subpaths, $matrix, $properties);
            } elseif ('fill' === $part) {
                $this->add($subpaths, $matrix, ['stroke' => 'none'] + $properties);
            } elseif ('fill' !== ($order[$i - 1] ?? null)) {
                $this->add($subpaths, $matrix, ['fill' => 'none'] + $properties);
            }
        }
    }

    /**
     * The parts of a paint-order, those it leaves out after the others, in the order of normal.
     *
     * @return list<'fill'|'stroke'|'markers'>
     */
    private static function paintOrder(string $value): array
    {
        $parts = preg_split('/\s+/', strtolower(trim($value)), -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        if (\count($parts) > 3 || \count($parts) !== \count(array_unique($parts)) || array_diff($parts, ['fill', 'stroke', 'markers'])) {
            $parts = [];
        }

        return array_values(array_unique([...$parts, 'fill', 'stroke', 'markers']));
    }

    /**
     * The markers of a shape at its vertices: the <marker> each takes, the vertex, the direction there in degrees, and
     * whether it is the first one.
     *
     * @param list<array{points: list<array{float, float}>, closed: bool}> $subpaths
     * @param list<list<int>>|null                                         $vertices
     * @param array<string, string>                                        $properties
     *
     * @return list<array{\Dom\Element, array{float, float}, float, bool}>
     */
    private function markers(array $subpaths, ?array $vertices, array $properties): array
    {
        $found = [];
        foreach (['start', 'mid', 'end'] as $position) {
            $value = trim($properties['marker-'.$position] ?? 'none');
            if (preg_match('/^url\(\s*([\'"]?)#([^\'")\s]+)\1\s*\)$/i', $value, $m) && null !== ($marker = $this->ids[$m[2]] ?? null) && 'marker' === strtolower($marker->localName)) {
                $found[$position] = $marker;
            }
        }
        if (!$found) {
            return [];
        }

        // Each vertex with the directions of the segments in and out of it; a closed subpath ends where it starts
        $total = 0;
        foreach ($subpaths as $s => $subpath) {
            $total += \count($vertices[$s] ?? $subpath['points']) + ($subpath['closed'] ? 1 : 0);
        }
        if ($total > self::MAX_MARKERS) {
            return [];
        }
        $list = [];
        foreach ($subpaths as $s => $subpath) {
            // The points at the same place as the one before merged, so that the segments around each have a length
            $unique = [];
            $map = [];
            foreach ($subpath['points'] as $i => $point) {
                if (!$unique || hypot($point[0] - $unique[\count($unique) - 1][0], $point[1] - $unique[\count($unique) - 1][1]) > 1e-9) {
                    $unique[] = $point;
                }
                $map[$i] = \count($unique) - 1;
            }
            $closed = $subpath['closed'];
            $n = \count($unique);
            if ($closed && $n > 1 && hypot($unique[0][0] - $unique[$n - 1][0], $unique[0][1] - $unique[$n - 1][1]) <= 1e-9) {
                array_pop($unique);
                --$n;
                $map = array_map(static fn (int $u) => $u === $n ? 0 : $u, $map);
            }
            $unit = static function (array $from, array $to): array {
                $length = hypot($to[0] - $from[0], $to[1] - $from[1]);

                return [($to[0] - $from[0]) / $length, ($to[1] - $from[1]) / $length];
            };
            foreach ([...($vertices[$s] ?? array_keys($subpath['points'])), ...($closed ? [0] : [])] as $i) {
                $u = $map[$i];
                $in = $u > 0 ? $unit($unique[$u - 1], $unique[$u]) : ($closed && $n > 1 ? $unit($unique[$n - 1], $unique[$u]) : null);
                $out = $u < $n - 1 ? $unit($unique[$u], $unique[$u + 1]) : ($closed && $n > 1 ? $unit($unique[$u], $unique[0]) : null);
                $list[] = [$subpath['points'][$i], $in, $out];
            }
        }
        $markers = [];
        $last = \count($list) - 1;
        foreach ($list as $k => [$at, $in, $out]) {
            $marker = $found[0 === $k ? 'start' : ($k === $last ? 'end' : 'mid')] ?? null;
            if (null === $marker) {
                continue;
            }
            // The bisector of the two directions, or the one there is
            [$dx, $dy] = null !== $in && null !== $out ? [$in[0] + $out[0], $in[1] + $out[1]] : ($in ?? $out ?? [1.0, 0.0]);
            if (abs($dx) < 1e-9 && abs($dy) < 1e-9) {
                [$dx, $dy] = $in ?? [1.0, 0.0];
            }
            $markers[] = [$marker, $at, rad2deg(atan2($dy, $dx)), 0 === $k];
        }

        return $markers;
    }

    /**
     * Adds the content of a <marker> at a vertex: its refX and refY there, turned by its orient, as large as its
     * markerWidth and markerHeight in widths of the stroke (or user units), its viewBox fit into them.
     *
     * @param array{float, float}                             $at
     * @param array{float, float, float, float, float, float} $matrix
     * @param array<string, string>                           $properties The properties of the shape
     */
    private function marker(\Dom\Element $marker, array $at, float $angle, bool $start, array $matrix, array $properties): void
    {
        if (++$this->walks > self::MAX_WALKS) {
            return;
        }
        $diagonal = sqrt(($this->viewBox[2] ** 2 + $this->viewBox[3] ** 2) / 2);
        $units = 'userspaceonuse' === strtolower(trim($marker->getAttribute('markerUnits') ?? '')) ? 1.0 : self::length($properties['stroke-width'] ?? '1', $diagonal);
        $width = null !== ($w = $marker->getAttribute('markerWidth')) ? self::length($w, $this->viewBox[2]) : 3.0;
        $height = null !== ($h = $marker->getAttribute('markerHeight')) ? self::length($h, $this->viewBox[3]) : 3.0;
        if ($width <= 0 || $height <= 0 || $units <= 0) {
            return;
        }
        $box = self::numbers($marker->getAttribute('viewBox') ?? '');
        [$sx, $sy, $box] = 4 === \count($box) && $box[2] > 0 && $box[3] > 0 ? [$width / $box[2], $height / $box[3], $box] : [1.0, 1.0, [0.0, 0.0, $width, $height]];
        [$fit] = self::aspectRatio($marker->getAttribute('preserveAspectRatio') ?? '');
        if ('none' !== $fit) {
            $sx = $sy = 'slice' === $fit ? max($sx, $sy) : min($sx, $sy);
        }
        $reference = static fn (?string $value, array $keywords, float $from, float $size): float => match (strtolower(trim($value ?? ''))) {
            $keywords[0] => $from,
            'center' => $from + $size / 2,
            $keywords[1] => $from + $size,
            default => self::length($value, $size),
        };
        $refX = $reference($marker->getAttribute('refX'), ['left', 'right'], $box[0], $box[2]);
        $refY = $reference($marker->getAttribute('refY'), ['top', 'bottom'], $box[1], $box[3]);
        $orient = strtolower(trim($marker->getAttribute('orient') ?? ''));
        $rotation = match ($orient) {
            'auto' => $angle,
            'auto-start-reverse' => $start ? $angle + 180 : $angle,
            default => self::angle($orient),
        };
        $matrix = self::multiply($matrix, self::transform(\sprintf('translate(%F %F) rotate(%F) scale(%F %F) translate(%F %F)', $at[0], $at[1], $rotation, $units * $sx, $units * $sy, -$refX, -$refY)));

        // Its content inherits the properties of what holds the <marker>, not those of the shape
        $inherited = $this->inheritedProperties($marker);
        for ($child = $marker->firstElementChild; null !== $child; $child = $child->nextElementSibling) {
            if (\count($this->shapes) >= self::MAX_SHAPES || $this->points >= self::MAX_POINTS) {
                return;
            }
            $this->walk($child, $matrix, ['opacity' => $properties['opacity'] ?? '1'] + $inherited);
        }
    }

    /**
     * An angle in degrees, like the orient of a <marker>: a number, or with deg, grad, rad or turn; 0 when invalid.
     */
    private static function angle(string $value): float
    {
        if (!preg_match('/^([+-]?(?:\d+\.?\d*|\.\d+)(?:e[+-]?\d+)?)(deg|grad|rad|turn)?$/', $value, $m)) {
            return 0.0;
        }
        $angle = (float) $m[1] * ['' => 1.0, 'deg' => 1.0, 'grad' => 0.9, 'rad' => 180 / \M_PI, 'turn' => 360.0][$m[2] ?? ''];

        return is_finite($angle) ? fmod($angle, 360.0) : 0.0;
    }

    /**
     * @param list<array{points: list<array{float, float}>, closed: bool}> $subpaths
     * @param array{float, float, float, float, float, float}              $matrix
     * @param array<string, string>                                        $properties
     */
    private function add(array $subpaths, array $matrix, array $properties): void
    {
        $subpaths = array_values(array_filter($subpaths, static fn (array $subpath) => \count($subpath['points']) > 1));
        if (!$subpaths) {
            return;
        }
        foreach ($subpaths as $i => $subpath) {
            $this->points += \count($subpath['points']);
            $subpaths[$i]['points'] = array_map(static fn (array $p) => [$matrix[0] * $p[0] + $matrix[2] * $p[1] + $matrix[4], $matrix[1] * $p[0] + $matrix[3] * $p[1] + $matrix[5]], $subpath['points']);
        }
        $opacity = (float) ($properties['opacity'] ?? 1);
        $color = self::paint($properties['color'] ?? 'currentcolor', null);
        // The lengths of the stroke: percentages of the normalized diagonal of the view box, scaled like the shape
        $diagonal = sqrt(($this->viewBox[2] ** 2 + $this->viewBox[3] ** 2) / 2);
        $scale = sqrt(abs($matrix[0] * $matrix[3] - $matrix[1] * $matrix[2]));
        $this->shapes[] = [
            'subpaths' => $subpaths,
            'fill' => $this->pattern($properties['fill'] ?? '', $subpaths, $matrix, $color) ?? $this->gradient($properties['fill'] ?? '', $subpaths, $matrix) ?? self::paint($properties['fill'] ?? 'black', $color),
            'fillOpacity' => $opacity * self::fraction($properties['fill-opacity'] ?? '1'),
            'stroke' => $this->pattern($properties['stroke'] ?? '', $subpaths, $matrix, $color) ?? self::averageColor($this->gradient($properties['stroke'] ?? '', $subpaths, $matrix)) ?? self::paint($properties['stroke'] ?? 'none', $color),
            'strokeOpacity' => $opacity * self::fraction($properties['stroke-opacity'] ?? '1'),
            'strokeWidth' => self::length($properties['stroke-width'] ?? '1', $diagonal) * $scale,
            'dashes' => null === ($dashes = self::dashes($properties['stroke-dasharray'] ?? 'none', $diagonal)) ? null : array_map(static fn (float $dash) => $dash * $scale, $dashes),
            'dashOffset' => self::length($properties['stroke-dashoffset'] ?? '0', $diagonal) * $scale,
            'cap' => \in_array($cap = strtolower(trim($properties['stroke-linecap'] ?? '')), ['round', 'square'], true) ? $cap : 'butt',
            // arcs and miter-clip, the newer joins, are drawn like miter
            'join' => \in_array($join = strtolower(trim($properties['stroke-linejoin'] ?? '')), ['round', 'bevel'], true) ? $join : 'miter',
            'miterLimit' => is_numeric($limit = trim($properties['stroke-miterlimit'] ?? '4')) && (float) $limit >= 1 ? (float) $limit : 4.0,
            'evenOdd' => 'evenodd' === strtolower(trim($properties['fill-rule'] ?? '')),
            'clips' => $this->clips,
            'masks' => $this->masks,
            'filters' => $this->filters,
        ];
    }

    /**
     * @param array{float, float, float, float, float, float} $matrix
     * @param array<string, string>                           $properties
     */
    private function addText(\Dom\Element $element, string $content, array $matrix, array $properties): void
    {
        $css = $this->geometryDeclared($element, 'text');
        $x = self::length($css['x'] ?? self::numbers($element->getAttribute('x') ?? '0')[0] ?? null, $this->viewBox[2]);
        $y = self::length($css['y'] ?? self::numbers($element->getAttribute('y') ?? '0')[0] ?? null, $this->viewBox[3]);
        // Inherited, like in SVG: from a group around the text too
        $size = self::length($properties['font-size'] ?? '16', 16.0) ?: 16.0;
        $anchor = strtolower(trim($properties['text-anchor'] ?? 'start'));
        $this->points += mb_strlen($content);
        $color = self::paint($properties['color'] ?? 'currentcolor', null);
        $this->shapes[] = [
            'text' => substr(self::ascii($content), 0, 200),
            'label' => mb_substr($content, 0, 200),
            // Its point the middle of the text, not its baseline, like dominant-baseline: central
            'middle' => \in_array(strtolower(trim($properties['dominant-baseline'] ?? '')), ['central', 'middle'], true),
            'clips' => $this->clips,
            'masks' => $this->masks,
            'filters' => $this->filters,
            'at' => [$matrix[0] * $x + $matrix[2] * $y + $matrix[4], $matrix[1] * $x + $matrix[3] * $y + $matrix[5]],
            'size' => max(0.0, min(self::MAX_LENGTH, $size * sqrt(abs($matrix[0] * $matrix[3] - $matrix[1] * $matrix[2])))),
            'anchor' => \in_array($anchor, ['middle', 'end'], true) ? $anchor : 'start',
            'fill' => self::paint($properties['fill'] ?? 'black', $color),
            'fillOpacity' => (float) ($properties['opacity'] ?? 1) * self::fraction($properties['fill-opacity'] ?? '1'),
        ];
    }

    /**
     * The text as the font of GD draws it: ASCII, the accents dropped (é is e), the other characters as question marks.
     */
    private static function ascii(string $text): string
    {
        $converted = \function_exists('transliterator_transliterate') ? transliterator_transliterate('Latin-ASCII', $text) : false;

        // A character, not each of its bytes
        return (string) preg_replace('/[^\x20-\x7E]/u', '?', false !== $converted ? $converted : $text);
    }

    /**
     * Draws a text in the largest bitmap font of GD, scaled to its size: as tall as it, its baseline at its point.
     *
     * @param array{float, float} $at
     * @param bool                $middle Whether its point is its middle, not its baseline
     */
    private static function drawText(\GdImage $canvas, string $text, array $at, float $size, string $anchor, int $color, int &$work, bool $middle = false): bool
    {
        $font = 5;
        [$glyphWidth, $glyphHeight] = [imagefontwidth($font), imagefontheight($font)];
        // A size past any canvas, even infinite under a huge scale, is not drawn
        $size = min($size, 1e6);
        $height = (int) round($size);
        $width = (int) round(\strlen($text) * $glyphWidth * $size / $glyphHeight);
        if ($height < 1 || $width < 1 || $height > 4 * imagesy($canvas) || $width > 8 * imagesx($canvas)) {
            return true;
        }
        $work += $width * $height;
        if ($work > self::MAX_WORK) {
            return false;
        }
        if (false === $glyphs = imagecreatetruecolor(\strlen($text) * $glyphWidth, $glyphHeight)) {
            return true;
        }
        imagealphablending($glyphs, false);
        imagefill($glyphs, 0, 0, imagecolorallocatealpha($glyphs, 0, 0, 0, 127));
        $rgba = imagecolorsforindex($canvas, $color);
        imagestring($glyphs, $font, 0, 0, $text, imagecolorallocatealpha($glyphs, $rgba['red'], $rgba['green'], $rgba['blue'], $rgba['alpha']));
        imagesavealpha($glyphs, true);
        $left = (int) round($at[0] - match ($anchor) {
            'middle' => $width / 2,
            'end' => $width,
            default => 0,
        });
        // The baseline is about four fifths down the glyphs; the point their middle with dominant-baseline: central
        $top = (int) round($at[1] - $height * ($middle ? 0.5 : 0.8));
        imagecopyresampled($canvas, $glyphs, $left, $top, 0, 0, $width, $height, imagesx($glyphs), $glyphHeight);

        return true;
    }

    /**
     * The gradient a paint names, like url(#sky): its stops and where it runs, in the user space of the <svg>; null
     * for another paint, or a gradient without stops.
     *
     * @param list<array{points: list<array{float, float}>, closed: bool}> $subpaths The shape, for the units of its bounding box
     * @param array{float, float, float, float, float, float}              $matrix
     *
     * @return array{'gradient', 'linear'|'radial', list<array{float, int, int, int, float}>, list<float>}|null
     */
    private function gradient(string $value, array $subpaths, array $matrix): ?array
    {
        if (!preg_match('/^\s*url\(\s*[\'"]?#([^\'")\s]+)[\'"]?\s*\)/i', $value, $m) || null === $element = $this->ids[$m[1]] ?? null) {
            return null;
        }
        $type = match (strtolower($element->localName)) {
            'lineargradient' => 'linear',
            'radialgradient' => 'radial',
            default => null,
        };
        if (null === $type) {
            return null;
        }
        // The stops, those of the gradient it refers to when it has none
        $stops = $this->stops($element);
        $href = trim($element->getAttribute('href') ?? $element->getAttributeNS('http://www.w3.org/1999/xlink', 'href') ?? '');
        if (!$stops && str_starts_with($href, '#') && null !== $other = $this->ids[substr($href, 1)] ?? null) {
            $stops = $this->stops($other);
        }
        if (!$stops) {
            return null;
        }

        $user = 'userspaceonuse' === strtolower(trim($element->getAttribute('gradientUnits') ?? ''));
        $points = array_merge(...array_column($subpaths, 'points'));
        [$left, $top] = [min(array_column($points, 0)), min(array_column($points, 1))];
        [$width, $height] = [max(1e-9, max(array_column($points, 0)) - $left), max(1e-9, max(array_column($points, 1)) - $top)];
        // A coordinate of the gradient, in the user space of the <svg>: of the bounding box of the shape by default
        $at = function (string $name, string $default, bool $x) use ($element, $user, $left, $top, $width, $height): float {
            $value = $element->getAttribute($name) ?? $default;
            if ($user) {
                return self::length($value, $x ? $this->viewBox[2] : $this->viewBox[3]);
            }
            // A fraction of the box, or a percentage of it
            $fraction = self::length($value, 1.0);

            return $x ? $left + $fraction * $width : $top + $fraction * $height;
        };
        $place = static fn (float $x, float $y): array => $user ? [$matrix[0] * $x + $matrix[2] * $y + $matrix[4], $matrix[1] * $x + $matrix[3] * $y + $matrix[5]] : [$x, $y];
        if ('linear' === $type) {
            $from = $place($at('x1', '0%', true), $at('y1', '0%', false));
            $to = $place($at('x2', '100%', true), $at('y2', '0%', false));

            return ['gradient', 'linear', $stops, [...$from, ...$to]];
        }
        $center = $place($at('cx', '50%', true), $at('cy', '50%', false));
        $radius = $element->getAttribute('r') ?? '50%';
        $scale = $user ? sqrt(abs($matrix[0] * $matrix[3] - $matrix[1] * $matrix[2])) : 1.0;
        $rx = $user ? self::length($radius, sqrt(($this->viewBox[2] ** 2 + $this->viewBox[3] ** 2) / 2)) * $scale : self::length($radius, 1.0) * $width;
        $ry = $user ? $rx : self::length($radius, 1.0) * $height;

        return ['gradient', 'radial', $stops, [...$center, max(1e-9, $rx), max(1e-9, $ry)]];
    }

    /**
     * The stops of a gradient: their offset from 0 to 1, in order, and their color.
     *
     * @return list<array{float, int, int, int, float}>
     */
    private function stops(\Dom\Element $gradient): array
    {
        $stops = [];
        $last = 0.0;
        for ($stop = $gradient->firstElementChild; null !== $stop; $stop = $stop->nextElementSibling) {
            if ('stop' !== strtolower($stop->localName) || \count($stops) >= 32) {
                continue;
            }
            $style = $this->declared($stop, ['stop-color', 'stop-opacity']);
            $color = self::paint($style['stop-color'] ?? 'black', [0, 0, 0, 1.0]);
            $offset = trim($stop->getAttribute('offset') ?? '0');
            // Never before the one before, like SVG
            $last = max($last, min(1.0, max(0.0, str_ends_with($offset, '%') ? (float) $offset / 100 : (float) $offset)));
            [$r, $g, $b, $alpha] = \is_array($color) ? $color : [0, 0, 0, 0.0];
            $stops[] = [$last, $r, $g, $b, $alpha * self::fraction($style['stop-opacity'] ?? '1')];
        }

        return $stops;
    }

    /**
     * The average color of the stops of a gradient, for a stroke; null for another paint.
     *
     * @param array{'gradient', string, list<array{float, int, int, int, float}>, list<float>}|null $gradient
     *
     * @return array{int, int, int, float}|null
     */
    private static function averageColor(?array $gradient): ?array
    {
        if (null === $gradient) {
            return null;
        }
        $stops = $gradient[2];
        $count = \count($stops);

        return [
            (int) round(array_sum(array_column($stops, 1)) / $count),
            (int) round(array_sum(array_column($stops, 2)) / $count),
            (int) round(array_sum(array_column($stops, 3)) / $count),
            array_sum(array_column($stops, 4)) / $count,
        ];
    }

    /**
     * The presentation attributes of an element, then the rules of the page that match it, then the declarations of
     * its style attribute, which win.
     *
     * @return array<string, string>
     */
    private function properties(\Dom\Element $element): array
    {
        $properties = $this->declared($element, ['fill', 'stroke', 'stroke-width', 'fill-opacity', 'stroke-opacity', 'fill-rule', 'opacity', 'display', 'visibility', 'color', 'font-size', 'text-anchor', 'dominant-baseline', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'marker', 'marker-start', 'marker-mid', 'marker-end', 'paint-order', 'clip-path', 'clip-rule', 'mask', 'mask-type', 'filter', 'flood-color', 'flood-opacity']);
        // The shorthand sets the markers the element does not set one by one
        if (isset($properties['marker'])) {
            foreach (['marker-start', 'marker-mid', 'marker-end'] as $name) {
                $properties[$name] ??= $properties['marker'];
            }
            unset($properties['marker']);
        }

        return $properties;
    }

    /**
     * The values of some properties an element sets: from the cascade of the page, else from its presentation
     * attributes, then the declarations of its style attribute, which win.
     *
     * @param list<string> $names
     *
     * @return array<string, string>
     */
    private function declared(\Dom\Element $element, array $names): array
    {
        if (null !== $this->cascade) {
            $properties = [];
            foreach (($this->cascade)($element, $names) as $name => $value) {
                $value = trim((string) preg_replace('/\s*!important\s*$/i', '', $value));
                // initial is the value of SVG, unset and inherit the value around it but for opacity and display, which are not inherited
                $keyword = strtolower($value);
                if ('initial' === $keyword || ('unset' === $keyword && \in_array($name, ['opacity', 'display', 'stop-color', 'stop-opacity'], true))) {
                    $value = self::INITIAL[$name] ?? ['display' => 'inline', 'stop-color' => 'black', 'stop-opacity' => '1', 'font-size' => '16', 'text-anchor' => 'start'][$name] ?? '';
                } elseif (\in_array($keyword, ['unset', 'revert', 'revert-layer'], true)) {
                    continue;
                }
                $properties[$name] = $value;
            }

            return array_filter($properties, static fn (string $value) => '' !== $value && 'inherit' !== strtolower($value));
        }
        $properties = [];
        foreach ($names as $name) {
            if (null !== $value = $element->getAttribute($name)) {
                $properties[$name] = trim($value);
            }
        }
        $wanted = array_flip($names);
        foreach (explode(';', $element->getAttribute('style') ?? '') as $declaration) {
            $parts = explode(':', $declaration, 2);
            $name = strtolower(trim($parts[0]));
            if (isset($parts[1], $wanted[$name])) {
                $properties[$name] = trim((string) preg_replace('/\s*!important\s*$/i', '', $parts[1]));
            }
        }

        // inherit takes the value around it, like no value
        return array_filter($properties, static fn (string $value) => '' !== $value && 'inherit' !== strtolower($value));
    }

    /**
     * A paint: a color with its alpha, 'current' for currentColor, null for none; a paint server, like a gradient, is
     * its fallback color, else none.
     *
     * @param array{int, int, int, float}|'current'|null $color The color property, for currentColor
     *
     * @return array{int, int, int, float}|'current'|null
     */
    private static function paint(string $value, array|string|null $color): array|string|null
    {
        $value = strtolower(trim($value));
        if (str_starts_with($value, 'url(')) {
            $value = trim((string) preg_replace('/^url\([^)]*\)/', '', $value));
            if ('' === $value) {
                return null;
            }
        }
        if ('none' === $value || 'transparent' === $value) {
            return null;
        }
        if ('currentcolor' === $value) {
            return $color ?? 'current';
        }
        // The CSS colors of the names that are ANSI ones for text: an image has no terminal theme
        $value = self::CSS_NAMES[$value] ?? $value;
        $media = new MediaContext();
        if (!($opaque = Value::opaqueColor($value, $media)) instanceof Color) {
            return null;
        }
        $rgb = $opaque->toRgb();

        return [$rgb['r'], $rgb['g'], $rgb['b'], Value::alpha($value, $media)];
    }

    /**
     * The color of a gradient at each point of the canvas, its user space mapped like the shapes; it pads past its
     * ends, like spreadMethod="pad".
     *
     * @param array{'gradient', 'linear'|'radial', list<array{float, int, int, int, float}>, list<float>} $gradient
     *
     * @return \Closure(float, float): int
     */
    private static function gradientColors(\GdImage $canvas, array $gradient, float $opacity, float $sx, float $sy, float $tx, float $ty): \Closure
    {
        [, $type, $stops, $geometry] = $gradient;
        $colors = [];
        $color = static function (float $t) use ($canvas, $stops, $opacity, &$colors): int {
            // 64 shades are enough for the cells they become
            $key = (int) round(max(0.0, min(1.0, $t)) * 64);
            if (isset($colors[$key])) {
                return $colors[$key];
            }
            $t = $key / 64;
            $before = $stops[0];
            $after = $stops[\count($stops) - 1];
            foreach ($stops as $stop) {
                if ($stop[0] <= $t) {
                    $before = $stop;
                }
                if ($stop[0] >= $t) {
                    $after = $stop;
                    break;
                }
            }
            $mix = $after[0] > $before[0] ? ($t - $before[0]) / ($after[0] - $before[0]) : 0.0;
            $channel = static fn (int $i): float => $before[$i] + ($after[$i] - $before[$i]) * $mix;
            $alpha = max(0.0, min(1.0, $channel(4) * $opacity));

            return $colors[$key] = (int) imagecolorallocatealpha($canvas, (int) round($channel(1)), (int) round($channel(2)), (int) round($channel(3)), (int) round(127 * (1 - $alpha)));
        };
        if ('linear' === $type) {
            [$x1, $y1, $x2, $y2] = [$geometry[0] * $sx + $tx, $geometry[1] * $sy + $ty, $geometry[2] * $sx + $tx, $geometry[3] * $sy + $ty];
            $dx = $x2 - $x1;
            $dy = $y2 - $y1;
            $length = max(1e-9, $dx * $dx + $dy * $dy);

            return static fn (float $x, float $y): int => $color((($x - $x1) * $dx + ($y - $y1) * $dy) / $length);
        }
        [$cx, $cy, $rx, $ry] = [$geometry[0] * $sx + $tx, $geometry[1] * $sy + $ty, max(1e-9, $geometry[2] * abs($sx)), max(1e-9, $geometry[3] * abs($sy))];

        return static fn (float $x, float $y): int => $color(sqrt((($x - $cx) / $rx) ** 2 + (($y - $cy) / $ry) ** 2));
    }

    /**
     * @param array{int, int, int, float}|'current'|null $paint
     * @param array{int, int, int}                       $currentColor
     */
    private static function allocate(\GdImage $canvas, array|string|null $paint, float $opacity, array $currentColor): ?int
    {
        if (null === $paint) {
            return null;
        }
        [$r, $g, $b, $alpha] = 'current' === $paint ? [...$currentColor, 1.0] : $paint;
        $alpha *= max(0.0, min(1.0, $opacity));
        if ($alpha <= 0.0) {
            return null;
        }
        $color = imagecolorallocatealpha($canvas, $r, $g, $b, (int) round(127 * (1 - $alpha)));

        return false === $color ? null : $color;
    }

    /**
     * Fills the subpaths row by row, with the nonzero or the evenodd rule, like SVG.
     *
     * @param list<array{points: list<array{float, float}>, closed: bool}> $subpaths
     * @param int|\Closure(float, float): int                              $color    A color, or that of each point, like a gradient
     */
    private static function fill(\GdImage $canvas, array $subpaths, bool $evenOdd, int|\Closure $color, int $height, int &$work): bool
    {
        if (null === $rows = self::spans($subpaths, $evenOdd, $height, $work)) {
            return false;
        }
        if (\is_int($color)) {
            // The rows with the same runs as the ones above them drawn with them, like the rows of a rectangle
            $start = 0;
            $last = -2;
            $previous = null;
            foreach ($rows as $row => $spans) {
                if (null !== $previous && $spans === $previous && $row === $last + 1) {
                    $last = $row;
                    continue;
                }
                foreach ($previous ?? [] as [$from, $to]) {
                    imagefilledrectangle($canvas, $from, $start, $to, $last, $color);
                }
                [$start, $last, $previous] = [$row, $row, $spans];
            }
            foreach ($previous ?? [] as [$from, $to]) {
                imagefilledrectangle($canvas, $from, $start, $to, $last, $color);
            }

            return true;
        }
        foreach ($rows as $row => $spans) {
            foreach ($spans as [$from, $to]) {
                // Two pixels at a time in the color of the gradient there
                $from = max(-1, $from);
                $to = min(\PHP_INT_MAX >> 1, $to);
                $work += intdiv($to - $from, 2);
                for ($x = $from; $x <= $to; $x += 2) {
                    imageline($canvas, $x, $row, min($x + 1, $to), $row, $color($x + 1.0, $row + 0.5));
                }
            }
        }

        return true;
    }

    /**
     * The pixels inside the subpaths, by row: the first and the last of each run; null past the most work.
     *
     * @param list<array{points: list<array{float, float}>, closed: bool}> $subpaths
     *
     * @return array<int, list<array{int, int}>>|null
     */
    private static function spans(array $subpaths, bool $evenOdd, int $height, int &$work): ?array
    {
        $edges = [];
        $top = \PHP_FLOAT_MAX;
        $bottom = -\PHP_FLOAT_MAX;
        foreach ($subpaths as $subpath) {
            $points = $subpath['points'];
            // A fill closes every subpath
            $points[] = $points[0];
            for ($i = 1, $count = \count($points); $i < $count; ++$i) {
                [$x1, $y1] = $points[$i - 1];
                [$x2, $y2] = $points[$i];
                if ($y1 === $y2) {
                    continue;
                }
                $edges[] = $y1 < $y2 ? [$x1, $y1, $x2, $y2, 1] : [$x2, $y2, $x1, $y1, -1];
                $top = min($top, $y1, $y2);
                $bottom = max($bottom, $y1, $y2);
            }
        }
        $rows = [];
        if (!$edges) {
            return $rows;
        }
        $work += \count($edges);
        // The edges by their top, those a row crosses taken from them as the rows go down
        usort($edges, static fn (array $a, array $b) => $a[1] <=> $b[1]);
        $next = 0;
        $active = [];
        for ($row = max(0, (int) floor($top)); $row <= min($height - 1, (int) ceil($bottom)); ++$row) {
            $y = $row + 0.5;
            while (isset($edges[$next]) && $edges[$next][1] <= $y) {
                $active[] = $edges[$next++];
            }
            $crossings = [];
            foreach ($active as $k => [$x1, $y1, $x2, $y2, $direction]) {
                if ($y2 <= $y) {
                    unset($active[$k]);
                    continue;
                }
                $crossings[] = [$x1 + ($y - $y1) * ($x2 - $x1) / ($y2 - $y1), $direction];
            }
            $work += \count($crossings);
            if ($work > self::MAX_WORK) {
                return null;
            }
            if (\count($crossings) > 2) {
                usort($crossings, static fn (array $a, array $b) => $a[0] <=> $b[0]);
            } elseif (2 === \count($crossings) && $crossings[0][0] > $crossings[1][0]) {
                $crossings = [$crossings[1], $crossings[0]];
            }
            // Inside past a crossing: an odd number of them for evenodd, a winding not zero for nonzero
            $winding = 0;
            foreach ($crossings as $i => [$x, $direction]) {
                $winding += $direction;
                if (($evenOdd ? 0 === $i % 2 : 0 !== $winding) && isset($crossings[$i + 1])) {
                    $from = (int) round($x);
                    $to = (int) round($crossings[$i + 1][0]) - 1;
                    if ($to >= $from) {
                        $rows[$row][] = [$from, $to];
                        // Blending the pixels is work too, most of it for large shapes
                        $work += ($to - $from) >> 4;
                    }
                }
            }
        }

        return $rows;
    }

    /**
     * Draws the outline of the subpaths, each segment as a band of the width, with their caps and joins.
     *
     * @param list<array{points: list<array{float, float}>, closed: bool}> $subpaths
     * @param 'butt'|'round'|'square'                                      $cap
     * @param 'miter'|'round'|'bevel'                                      $join
     */
    private static function stroke(\GdImage $canvas, array $subpaths, float $width, int $color, int $canvasWidth, int $canvasHeight, int &$work, string $cap = 'butt', string $join = 'miter', float $miterLimit = 4.0): bool
    {
        $half = $width / 2;
        $diagonal = hypot($canvasWidth, $canvasHeight);
        $near = static fn (float $x, float $y): bool => $x > -$width && $y > -$width && $x < $canvasWidth + $width && $y < $canvasHeight + $width;
        // A dot, a cap or a join covers about a square of the width, a miter up to the limit
        $dot = 1 + (int) ($width * $width / 4);
        $corner = 1 + (int) ($width * $width * min($miterLimit, 16.0) / 4);
        foreach ($subpaths as $subpath) {
            // The segments, each with its unit direction, those without length left out
            $segments = [];
            $points = $subpath['points'];
            if ($subpath['closed']) {
                $points[] = $points[0];
            }
            for ($i = 1, $count = \count($points); $i < $count; ++$i) {
                [$x1, $y1] = $points[$i - 1];
                [$x2, $y2] = $points[$i];
                if (($length = hypot($x2 - $x1, $y2 - $y1)) > 0.0) {
                    $segments[] = [$x1, $y1, $x2, $y2, ($x2 - $x1) / $length, ($y2 - $y1) / $length, $length];
                }
            }
            if (!$segments) {
                // A dash of no length: a dot with round caps, a square with square ones
                [$x, $y] = $points[0];
                if (!$subpath['closed'] && 'butt' !== $cap && $near($x, $y)) {
                    if (($work += $dot) > self::MAX_WORK) {
                        return false;
                    }
                    if ('round' === $cap) {
                        imagefilledellipse($canvas, (int) round($x), (int) round($y), (int) round($width), (int) round($width), $color);
                    } else {
                        imagefilledrectangle($canvas, (int) round($x - $half), (int) round($y - $half), (int) round($x + $half), (int) round($y + $half), $color);
                    }
                }
                continue;
            }
            // Square caps go on past the ends by half the width
            $last = \count($segments) - 1;
            if (!$subpath['closed'] && 'square' === $cap) {
                $segments[0][0] -= $segments[0][4] * $half;
                $segments[0][1] -= $segments[0][5] * $half;
                $segments[$last][2] += $segments[$last][4] * $half;
                $segments[$last][3] += $segments[$last][5] * $half;
            }
            foreach ($segments as $i => [$x1, $y1, $x2, $y2, $dx, $dy, $length]) {
                // The pixels the band covers, at most those of the canvas
                $work += 1 + (int) (min($length, $diagonal) * $width / 8);
                if ($work > self::MAX_WORK) {
                    return false;
                }
                // The normal of the segment, half the width long
                $nx = -$dy * $half;
                $ny = $dx * $half;
                imagefilledpolygon($canvas, [(int) round($x1 + $nx), (int) round($y1 + $ny), (int) round($x2 + $nx), (int) round($y2 + $ny), (int) round($x2 - $nx), (int) round($y2 - $ny), (int) round($x1 - $nx), (int) round($y1 - $ny)], $color);

                // The join with the next segment, on the outer side of the turn
                $next = $segments[$i + 1] ?? ($subpath['closed'] ? $segments[0] : null);
                if (null === $next || $width < 2 || !$near($x2, $y2) || $next === $segments[$i]) {
                    continue;
                }
                $turn = $dx * $next[5] - $dy * $next[4];
                if (abs($turn) < 1e-3 && $dx * $next[4] + $dy * $next[5] > 0) {
                    continue;
                }
                if (($work += 'round' === $join ? $dot : $corner) > self::MAX_WORK) {
                    return false;
                }
                [$px, $py] = [$next[0], $next[1]];
                if ('round' === $join) {
                    imagefilledellipse($canvas, (int) round($px), (int) round($py), (int) round($width), (int) round($width), $color);
                    continue;
                }
                $side = $turn > 0 ? -1 : 1;
                $ax = $px + $side * $nx;
                $ay = $py + $side * $ny;
                $bx = $px + $side * -$next[5] * $half;
                $by = $py + $side * $next[4] * $half;
                // The miter is where the outer edges meet, unless it is too long for the limit: then bevelled
                $mx = $ax + $bx - 2 * $px;
                $my = $ay + $by - 2 * $py;
                $cosine = ($m = hypot($mx, $my)) > 0 ? $m / 2 / $half : 0.0;
                if ('miter' === $join && $cosine > 0 && 1 / $cosine <= $miterLimit) {
                    $tip = $half / $cosine / $m;
                    imagefilledpolygon($canvas, [(int) round($px), (int) round($py), (int) round($ax), (int) round($ay), (int) round($px + $mx * $tip), (int) round($py + $my * $tip), (int) round($bx), (int) round($by)], $color);
                } else {
                    imagefilledpolygon($canvas, [(int) round($px), (int) round($py), (int) round($ax), (int) round($ay), (int) round($bx), (int) round($by)], $color);
                }
            }
            if (!$subpath['closed'] && 'round' === $cap && $width >= 2) {
                foreach ([[$segments[0][0], $segments[0][1]], [$segments[$last][2], $segments[$last][3]]] as [$x, $y]) {
                    if ($near($x, $y)) {
                        if (($work += $dot) > self::MAX_WORK) {
                            return false;
                        }
                        imagefilledellipse($canvas, (int) round($x), (int) round($y), (int) round($width), (int) round($width), $color);
                    }
                }
            }
        }

        return true;
    }

    /**
     * The dashes of a stroke, as open subpaths: the pattern runs along each subpath from its start, shifted by the
     * offset; drawn solid when it would make too many.
     *
     * @param list<array{points: list<array{float, float}>, closed: bool}> $subpaths
     * @param list<float>                                                  $pattern  An even number of lengths, dashes then gaps, their sum above 0
     *
     * @return list<array{points: list<array{float, float}>, closed: bool}>
     */
    private static function dash(array $subpaths, array $pattern, float $offset): array
    {
        $period = array_sum($pattern);
        $total = 0.0;
        foreach ($subpaths as $subpath) {
            $points = $subpath['points'];
            if ($subpath['closed']) {
                $points[] = $points[0];
            }
            for ($i = 1, $count = \count($points); $i < $count; ++$i) {
                $total += hypot($points[$i][0] - $points[$i - 1][0], $points[$i][1] - $points[$i - 1][1]);
            }
        }
        if ($period <= 0 || $total / $period * \count($pattern) / 2 > self::MAX_DASHES) {
            return $subpaths;
        }

        $dashes = [];
        foreach ($subpaths as $subpath) {
            $points = $subpath['points'];
            if ($subpath['closed']) {
                $points[] = $points[0];
            }
            // Where the pattern starts: the offset into it, the index of the dash or gap, and what is left of it
            $into = fmod($offset, $period);
            if ($into < 0) {
                $into += $period;
            }
            $index = 0;
            while ($into >= $pattern[$index]) {
                $into -= $pattern[$index];
                $index = ($index + 1) % \count($pattern);
            }
            $left = $pattern[$index] - $into;
            $current = 0 === $index % 2 ? [$points[0]] : null;
            for ($i = 1, $count = \count($points); $i < $count; ++$i) {
                [$x1, $y1] = $points[$i - 1];
                [$x2, $y2] = $points[$i];
                $length = hypot($x2 - $x1, $y2 - $y1);
                $at = 0.0;
                while ($length - $at > $left) {
                    $at += $left;
                    $point = [$x1 + ($x2 - $x1) * $at / $length, $y1 + ($y2 - $y1) * $at / $length];
                    if (null !== $current) {
                        $current[] = $point;
                        $dashes[] = ['points' => $current, 'closed' => false];
                        $current = null;
                    } else {
                        $current = [$point];
                    }
                    $index = ($index + 1) % \count($pattern);
                    $left = $pattern[$index];
                }
                $left -= $length - $at;
                if (null !== $current) {
                    $current[] = [$x2, $y2];
                }
            }
            if (null !== $current && \count($current) > 1) {
                $dashes[] = ['points' => $current, 'closed' => false];
            }
        }

        return $dashes;
    }

    /**
     * The lengths of a stroke-dasharray, repeated once when their count is odd; null for none, or for a list that is
     * not valid or adds up to 0, which draws the stroke solid.
     *
     * @return list<float>|null
     */
    private static function dashes(string $value, float $reference): ?array
    {
        $value = trim($value);
        if ('' === $value || 'none' === strtolower($value)) {
            return null;
        }
        $dashes = [];
        foreach (preg_split('/\s*,\s*|\s+/', $value) ?: [] as $part) {
            if (!preg_match('/^(?:\d+\.?\d*|\.\d+)(?:e[+-]?\d+)?(?:%|px|em|rem|pt|pc|in|cm|mm)?$/i', $part) || \count($dashes) >= 64) {
                return null;
            }
            $dashes[] = self::length($part, $reference);
        }
        if (array_sum($dashes) <= 0) {
            return null;
        }

        return 1 === \count($dashes) % 2 ? [...$dashes, ...$dashes] : $dashes;
    }

    /**
     * @return list<array{points: list<array{float, float}>, closed: bool}>|null
     */
    private static function rect(float $x, float $y, float $width, float $height, ?string $rx, ?string $ry, float $boxWidth, float $boxHeight): ?array
    {
        if ($width <= 0 || $height <= 0) {
            return null;
        }
        // A missing radius is the other one, each at most half the side
        $radiusX = null !== $rx && 'auto' !== strtolower(trim($rx)) ? self::length($rx, $boxWidth) : null;
        $radiusY = null !== $ry && 'auto' !== strtolower(trim($ry)) ? self::length($ry, $boxHeight) : null;
        $radiusX = min($width / 2, max(0.0, $radiusX ?? $radiusY ?? 0.0));
        $radiusY = min($height / 2, max(0.0, $radiusY ?? $radiusX));
        if ($radiusX <= 0 || $radiusY <= 0) {
            return [['points' => [[$x, $y], [$x + $width, $y], [$x + $width, $y + $height], [$x, $y + $height]], 'closed' => true]];
        }
        $points = [];
        foreach ([[$x + $width - $radiusX, $y + $radiusY, -90], [$x + $width - $radiusX, $y + $height - $radiusY, 0], [$x + $radiusX, $y + $height - $radiusY, 90], [$x + $radiusX, $y + $radiusY, 180]] as [$cx, $cy, $from]) {
            for ($step = 0; $step <= 8; ++$step) {
                $angle = deg2rad($from + $step * 90 / 8);
                $points[] = [$cx + $radiusX * cos($angle), $cy + $radiusY * sin($angle)];
            }
        }

        return [['points' => $points, 'closed' => true]];
    }

    /**
     * @return list<array{points: list<array{float, float}>, closed: bool}>|null
     */
    private static function ellipse(float $cx, float $cy, float $rx, float $ry): ?array
    {
        if ($rx <= 0 || $ry <= 0) {
            return null;
        }
        $steps = max(16, min(128, (int) ceil(max($rx, $ry))));
        $points = [];
        for ($step = 0; $step < $steps; ++$step) {
            $angle = 2 * \M_PI * $step / $steps;
            $points[] = [$cx + $rx * cos($angle), $cy + $ry * sin($angle)];
        }

        return [['points' => $points, 'closed' => true]];
    }

    /**
     * @return list<array{points: list<array{float, float}>, closed: bool}>
     */
    private static function polyline(string $value, bool $closed, int $budget = self::MAX_POINTS): array
    {
        $numbers = self::numbers($value);
        $points = [];
        for ($i = 0; $i + 1 < \count($numbers) && \count($points) < $budget; $i += 2) {
            $points[] = [$numbers[$i], $numbers[$i + 1]];
        }

        return [['points' => $points, 'closed' => $closed]];
    }

    /**
     * The subpaths of path data, its curves and arcs flattened into lines; drawn up to the first error, like SVG.
     *
     * @param-out list<list<int>> $vertices
     *
     * @param list<list<int>>|null $vertices Set to the indexes of the points of each subpath that end a command, where markers go
     *
     * @return list<array{points: list<array{float, float}>, closed: bool}>
     */
    public static function path(string $data, int $budget = self::MAX_POINTS, ?array &$vertices = null): array
    {
        $vertices = [];
        $marks = [];
        preg_match_all('/([MmLlHhVvCcSsQqTtAaZz])|([+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?)/', $data, $tokens, \PREG_SET_ORDER);
        $subpaths = [];
        $points = [];
        $x = $y = $startX = $startY = 0.0;
        // The control point of the last curve, for the smooth ones
        $control = null;
        $command = null;
        $index = 0;
        $count = \count($tokens);
        $number = static function () use (&$index, &$tokens, $count): ?float {
            if ($index < $count && '' === ($tokens[$index][1] ?? '') && isset($tokens[$index][2])) {
                return max(-self::MAX_LENGTH, min(self::MAX_LENGTH, (float) $tokens[$index++][2]));
            }

            return null;
        };
        $flag = static function () use (&$index, &$tokens, $count): ?bool {
            // Flags may be written without a separator, like a 0 0 1 1 1 as "a0 011 1"
            if ($index >= $count || '' !== ($tokens[$index][1] ?? '') || !isset($tokens[$index][2])) {
                return null;
            }
            $token = &$tokens[$index][2];
            if ('0' === $token[0] || '1' === $token[0]) {
                $value = '1' === $token[0];
                $token = substr($token, 1);
                if ('' === $token) {
                    ++$index;
                }

                return $value;
            }

            return null;
        };
        $close = static function (bool $closed, array &$points, array &$marks) use (&$subpaths, &$kept, &$vertices): void {
            if (\count($points) > 1) {
                $subpaths[] = ['points' => $points, 'closed' => $closed];
                $vertices[] = $marks;
                $kept += \count($points);
            }
            $points = [];
            $marks = [];
        };

        $kept = 0;
        while ($index < $count) {
            // At most that many points, the rest of the path left out
            if ($kept + \count($points) >= $budget) {
                break;
            }
            if ('' !== ($tokens[$index][1] ?? '')) {
                $command = $tokens[$index++][1];
                // A path starts with a moveto, else it is in error from the start
                if (0 === $index - 1 && 'M' !== strtoupper($command)) {
                    break;
                }
            } elseif (null === $command) {
                break;
            }
            $relative = ctype_lower($command);
            $dx = $relative ? $x : 0.0;
            $dy = $relative ? $y : 0.0;
            switch (strtoupper($command)) {
                case 'Z':
                    $close(true, $points, $marks);
                    [$x, $y] = [$startX, $startY];
                    $control = null;
                    // Another command follows; numbers after Z are an error
                    $command = null;
                    continue 2;
                case 'M':
                    if (null === ($px = $number()) || null === ($py = $number())) {
                        break 2;
                    }
                    $close(false, $points, $marks);
                    [$x, $y] = [$startX, $startY] = [$px + $dx, $py + $dy];
                    $points = [[$x, $y]];
                    $marks = [0];
                    // Pairs after a moveto are linetos
                    $command = $relative ? 'l' : 'L';
                    $control = null;
                    continue 2;
                case 'L':
                    if (null === ($px = $number()) || null === ($py = $number())) {
                        break 2;
                    }
                    [$x, $y] = [$px + $dx, $py + $dy];
                    $control = null;
                    break;
                case 'H':
                    if (null === $px = $number()) {
                        break 2;
                    }
                    $x = $px + $dx;
                    $control = null;
                    break;
                case 'V':
                    if (null === $py = $number()) {
                        break 2;
                    }
                    $y = $py + $dy;
                    $control = null;
                    break;
                case 'C':
                case 'S':
                case 'Q':
                case 'T':
                    $upper = strtoupper($command);
                    $values = [];
                    foreach (range(1, match ($upper) {
                        'C' => 6, 'S', 'Q' => 4, default => 2,
                    }) as $ignored) {
                        if (null === $value = $number()) {
                            break 3;
                        }
                        $values[] = $value;
                    }
                    $smooth = 'S' === $upper || 'T' === $upper;
                    $q = [$x, $y];
                    // The reflection of the last control point, when the last curve is of the same kind
                    $reflected = null !== $control && $control[2] === ('S' === $upper || 'C' === $upper ? 'cubic' : 'quadratic') ? [2 * $x - $control[0], 2 * $y - $control[1]] : [$x, $y];
                    if ('C' === $upper) {
                        [$c1, $c2, $end] = [[$values[0] + $dx, $values[1] + $dy], [$values[2] + $dx, $values[3] + $dy], [$values[4] + $dx, $values[5] + $dy]];
                    } elseif ('S' === $upper) {
                        [$c1, $c2, $end] = [$reflected, [$values[0] + $dx, $values[1] + $dy], [$values[2] + $dx, $values[3] + $dy]];
                    } else {
                        $q = 'Q' === $upper ? [$values[0] + $dx, $values[1] + $dy] : $reflected;
                        $end = $smooth ? [$values[0] + $dx, $values[1] + $dy] : [$values[2] + $dx, $values[3] + $dy];
                        // A quadratic curve as a cubic one
                        $c1 = [$x + 2 / 3 * ($q[0] - $x), $y + 2 / 3 * ($q[1] - $y)];
                        $c2 = [$end[0] + 2 / 3 * ($q[0] - $end[0]), $end[1] + 2 / 3 * ($q[1] - $end[1])];
                    }
                    if (!$points) {
                        $points[] = [$x, $y];
                        $marks = [0];
                    }
                    foreach (self::cubic([$x, $y], $c1, $c2, $end) as $point) {
                        $points[] = $point;
                    }
                    $marks[] = \count($points) - 1;
                    [$x, $y] = $end;
                    $control = 'C' === $upper || 'S' === $upper ? [$c2[0], $c2[1], 'cubic'] : [$q[0], $q[1], 'quadratic'];
                    continue 2;
                case 'A':
                    if (null === ($rx = $number()) || null === ($ry = $number()) || null === ($rotation = $number()) || null === ($large = $flag()) || null === ($sweep = $flag()) || null === ($px = $number()) || null === ($py = $number())) {
                        break 2;
                    }
                    if (!$points) {
                        $points[] = [$x, $y];
                        $marks = [0];
                    }
                    foreach (self::arc([$x, $y], abs($rx), abs($ry), $rotation, $large, $sweep, [$px + $dx, $py + $dy]) as $point) {
                        $points[] = $point;
                    }
                    $marks[] = \count($points) - 1;
                    [$x, $y] = [$px + $dx, $py + $dy];
                    $control = null;
                    continue 2;
            }
            if (!$points) {
                $points[] = [$startX, $startY];
                $marks = [0];
            }
            $points[] = [$x, $y];
            $marks[] = \count($points) - 1;
        }
        $close(false, $points, $marks);

        return $subpaths;
    }

    /**
     * A cubic Bézier curve as points, its start excluded.
     *
     * @param array{float, float} $p0
     * @param array{float, float} $p1
     * @param array{float, float} $p2
     * @param array{float, float} $p3
     *
     * @return list<array{float, float}>
     */
    private static function cubic(array $p0, array $p1, array $p2, array $p3): array
    {
        $length = hypot($p1[0] - $p0[0], $p1[1] - $p0[1]) + hypot($p2[0] - $p1[0], $p2[1] - $p1[1]) + hypot($p3[0] - $p2[0], $p3[1] - $p2[1]);
        $steps = max(4, min(64, (int) ceil($length / 2)));
        $points = [];
        for ($i = 1; $i <= $steps; ++$i) {
            $t = $i / $steps;
            $u = 1 - $t;
            $points[] = [
                $u ** 3 * $p0[0] + 3 * $u ** 2 * $t * $p1[0] + 3 * $u * $t ** 2 * $p2[0] + $t ** 3 * $p3[0],
                $u ** 3 * $p0[1] + 3 * $u ** 2 * $t * $p1[1] + 3 * $u * $t ** 2 * $p2[1] + $t ** 3 * $p3[1],
            ];
        }

        return $points;
    }

    /**
     * An elliptical arc as points, its start excluded, from its endpoint form (SVG 2, appendix B.2.4).
     *
     * @param array{float, float} $from
     * @param array{float, float} $to
     *
     * @return list<array{float, float}>
     */
    private static function arc(array $from, float $rx, float $ry, float $rotation, bool $large, bool $sweep, array $to): array
    {
        if ($from === $to) {
            return [];
        }
        // Radii of no length, or too small to square, are a line
        if (0.0 === $rx ** 2 || 0.0 === $ry ** 2) {
            return [$to];
        }
        $phi = deg2rad(fmod($rotation, 360));
        [$cos, $sin] = [cos($phi), sin($phi)];
        $mx = ($from[0] - $to[0]) / 2;
        $my = ($from[1] - $to[1]) / 2;
        $x1 = $cos * $mx + $sin * $my;
        $y1 = -$sin * $mx + $cos * $my;
        // Radii too small are scaled up
        $lambda = $x1 ** 2 / $rx ** 2 + $y1 ** 2 / $ry ** 2;
        if ($lambda > 1) {
            $rx *= sqrt($lambda);
            $ry *= sqrt($lambda);
        }
        $factor = sqrt(max(0.0, ($rx ** 2 * $ry ** 2 - $rx ** 2 * $y1 ** 2 - $ry ** 2 * $x1 ** 2) / ($rx ** 2 * $y1 ** 2 + $ry ** 2 * $x1 ** 2)));
        if ($large === $sweep) {
            $factor = -$factor;
        }
        $cx1 = $factor * $rx * $y1 / $ry;
        $cy1 = -$factor * $ry * $x1 / $rx;
        $cx = $cos * $cx1 - $sin * $cy1 + ($from[0] + $to[0]) / 2;
        $cy = $sin * $cx1 + $cos * $cy1 + ($from[1] + $to[1]) / 2;
        $angle = static fn (float $ux, float $uy, float $vx, float $vy): float => atan2($ux * $vy - $uy * $vx, $ux * $vx + $uy * $vy);
        $start = $angle(1, 0, ($x1 - $cx1) / $rx, ($y1 - $cy1) / $ry);
        $delta = $angle(($x1 - $cx1) / $rx, ($y1 - $cy1) / $ry, (-$x1 - $cx1) / $rx, (-$y1 - $cy1) / $ry);
        if (!$sweep && $delta > 0) {
            $delta -= 2 * \M_PI;
        } elseif ($sweep && $delta < 0) {
            $delta += 2 * \M_PI;
        }
        $steps = max(4, min(128, (int) ceil(abs($delta) * max($rx, $ry) / 2)));
        $points = [];
        for ($i = 1; $i <= $steps; ++$i) {
            $theta = $start + $delta * $i / $steps;
            $points[] = [$cos * $rx * cos($theta) - $sin * $ry * sin($theta) + $cx, $sin * $rx * cos($theta) + $cos * $ry * sin($theta) + $cy];
        }
        $points[\count($points) - 1] = $to;

        return $points;
    }

    /**
     * The matrix of a transform attribute: matrix(), translate(), scale(), rotate(), skewX() and skewY(), one after
     * the other; the identity when invalid.
     *
     * @return array{float, float, float, float, float, float}
     */
    public static function transform(string $value): array
    {
        $matrix = self::IDENTITY;
        if (!preg_match_all('/(matrix|translate|scale|rotate|skewx|skewy)\s*\(([^)]*)\)/i', $value, $functions, \PREG_SET_ORDER)) {
            return $matrix;
        }
        foreach ($functions as [, $name, $arguments]) {
            $n = self::numbers($arguments);
            $step = match (strtolower($name)) {
                'matrix' => 6 === \count($n) ? [$n[0], $n[1], $n[2], $n[3], $n[4], $n[5]] : null,
                'translate' => isset($n[0]) ? [1.0, 0.0, 0.0, 1.0, $n[0], $n[1] ?? 0.0] : null,
                'scale' => isset($n[0]) ? [$n[0], 0.0, 0.0, $n[1] ?? $n[0], 0.0, 0.0] : null,
                'rotate' => isset($n[0]) ? self::rotation($n[0], $n[1] ?? 0.0, $n[2] ?? 0.0) : null,
                'skewx' => isset($n[0]) ? [1.0, 0.0, tan(deg2rad($n[0])), 1.0, 0.0, 0.0] : null,
                'skewy' => isset($n[0]) ? [1.0, tan(deg2rad($n[0])), 0.0, 1.0, 0.0, 0.0] : null,
                default => null,
            };
            if (null === $step) {
                return self::IDENTITY;
            }
            $matrix = self::multiply($matrix, $step);
        }

        return $matrix;
    }

    /**
     * @return array{float, float, float, float, float, float}
     */
    private static function rotation(float $degrees, float $cx, float $cy): array
    {
        $angle = deg2rad($degrees);
        $rotate = [cos($angle), sin($angle), -sin($angle), cos($angle), 0.0, 0.0];

        return self::multiply(self::multiply([1.0, 0.0, 0.0, 1.0, $cx, $cy], $rotate), [1.0, 0.0, 0.0, 1.0, -$cx, -$cy]);
    }

    /**
     * @param array{float, float, float, float, float, float} $m
     * @param array{float, float, float, float, float, float} $n
     *
     * @return array{float, float, float, float, float, float}
     */
    private static function multiply(array $m, array $n): array
    {
        return [
            $m[0] * $n[0] + $m[2] * $n[1],
            $m[1] * $n[0] + $m[3] * $n[1],
            $m[0] * $n[2] + $m[2] * $n[3],
            $m[1] * $n[2] + $m[3] * $n[3],
            $m[0] * $n[4] + $m[2] * $n[5] + $m[4],
            $m[1] * $n[4] + $m[3] * $n[5] + $m[5],
        ];
    }

    /**
     * @return array{'meet'|'slice'|'none', array{float, float}}
     */
    private static function aspectRatio(string $value): array
    {
        $parts = preg_split('/\s+/', strtolower(trim($value)), -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        if ('none' === ($parts[0] ?? null)) {
            return ['none', [0.5, 0.5]];
        }
        if (!preg_match('/^x(min|mid|max)y(min|mid|max)$/', $parts[0] ?? '', $m)) {
            return ['meet', [0.5, 0.5]];
        }
        $position = ['min' => 0.0, 'mid' => 0.5, 'max' => 1.0];

        return ['slice' === ($parts[1] ?? null) ? 'slice' : 'meet', [$position[$m[1]], $position[$m[2]]]];
    }

    /**
     * The width or height attribute of an <svg> in CSS pixels; null for auto or a percentage.
     */
    private static function size(?string $value): ?float
    {
        if (null === $value || !preg_match('/^\s*(\d*\.?\d+)\s*(px|em|rem|pt|pc|in|cm|mm)?\s*$/i', $value, $m)) {
            return null;
        }

        $unit = strtolower($m[2] ?? 'px') ?: 'px';
        $factor = self::UNITS[$unit] ?? null;
        if (null === $factor) {
            return null;
        }
        $size = (float) $m[1] * $factor;

        return $size > 0 && $size <= self::MAX_LENGTH ? $size : null;
    }

    /** CSS pixels per unit */
    private const UNITS = ['px' => 1.0, 'em' => 16.0, 'rem' => 16.0, 'pt' => 4 / 3, 'pc' => 16.0, 'in' => 96.0, 'cm' => 96 / 2.54, 'mm' => 96 / 25.4];

    /**
     * A length or a coordinate in user units, a percentage of the reference; 0 when missing or invalid.
     */
    private static function length(?string $value, float $reference): float
    {
        if (null === $value || !preg_match('/^\s*([+-]?(?:\d+\.?\d*|\.\d+)(?:e[+-]?\d+)?)\s*(%|px|em|rem|pt|pc|in|cm|mm)?\s*$/i', $value, $m)) {
            return 0.0;
        }
        $unit = strtolower($m[2] ?? '');

        $length = '%' === $unit ? (float) $m[1] * $reference / 100 : (float) $m[1] * (self::UNITS[$unit] ?? 1.0);

        return is_finite($length) ? max(-self::MAX_LENGTH, min(self::MAX_LENGTH, $length)) : 0.0;
    }

    /**
     * A number or a percentage between 0 and 1, like an opacity.
     */
    private static function fraction(string $value): float
    {
        $value = trim($value);
        if (!is_numeric($number = rtrim($value, '%'))) {
            return 1.0;
        }

        return max(0.0, min(1.0, (float) $number / (str_ends_with($value, '%') ? 100 : 1)));
    }

    /**
     * @return list<float>
     */
    private static function numbers(string $value): array
    {
        preg_match_all('/[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?/', $value, $m);

        return array_map(static fn (string $number) => max(-self::MAX_LENGTH, min(self::MAX_LENGTH, (float) $number)), $m[0]);
    }
}
