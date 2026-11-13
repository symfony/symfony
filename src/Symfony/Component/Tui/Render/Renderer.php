<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Render;

use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Ansi\TextWrapper;
use Symfony\Component\Tui\Exception\RenderException;
use Symfony\Component\Tui\Style\Align;
use Symfony\Component\Tui\Style\AutoMargin;
use Symfony\Component\Tui\Style\BackgroundLayout;
use Symfony\Component\Tui\Style\Border;
use Symfony\Component\Tui\Style\BorderPattern;
use Symfony\Component\Tui\Style\Color;
use Symfony\Component\Tui\Style\ColorScheme;
use Symfony\Component\Tui\Style\Columns;
use Symfony\Component\Tui\Style\DefaultStyleSheet;
use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\Filter;
use Symfony\Component\Tui\Style\Gradient;
use Symfony\Component\Tui\Style\IntrinsicSizeAwareInterface;
use Symfony\Component\Tui\Style\LayoutAwareStyleProviderInterface;
use Symfony\Component\Tui\Style\MediaContext;
use Symfony\Component\Tui\Style\Outline;
use Symfony\Component\Tui\Style\Padding;
use Symfony\Component\Tui\Style\Position;
use Symfony\Component\Tui\Style\PositionMode;
use Symfony\Component\Tui\Style\PseudoElementProviderInterface;
use Symfony\Component\Tui\Style\RasterImage;
use Symfony\Component\Tui\Style\Scroll;
use Symfony\Component\Tui\Style\ScrollStateAwareInterface;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Style\TableRole;
use Symfony\Component\Tui\Style\TextAlign;
use Symfony\Component\Tui\Style\VerticalAlign;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\CheckboxWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\Figlet\FontRegistry;
use Symfony\Component\Tui\Widget\FocusableInterface;
use Symfony\Component\Tui\Widget\FormWidget;
use Symfony\Component\Tui\Widget\HorizontalRuleWidget;
use Symfony\Component\Tui\Widget\ImageWidget;
use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\ParentInterface;
use Symfony\Component\Tui\Widget\ReplacedElementInterface;
use Symfony\Component\Tui\Widget\RichTextWidget;
use Symfony\Component\Tui\Widget\SvgWidget;
use Symfony\Component\Tui\Widget\Text\InlineImages;
use Symfony\Component\Tui\Widget\Text\InlineNode;
use Symfony\Component\Tui\Widget\TextWidget;

/**
 * Renders the widget tree with style resolution, layout, and chrome.
 *
 * The Renderer:
 * 1. Resolves styles through cascade (* → FQCN → CSS class → state → instance)
 * 2. Computes layout (vertical/horizontal with gap and fill children)
 * 3. Calls widget->render() with enriched context
 * 4. Applies chrome (padding, border, background) around widget content
 *
 * All widget types are rendered through the Renderer: containers via
 * renderContainer(), and leaf widgets by delegating to widget->render().
 *
 * @experimental
 *
 * @internal
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Renderer implements WidgetRendererInterface
{
    /** The most lines a box draws, past which they are cut */
    public const MAX_LINES = 100_000;

    /**
     * The widest a child of a box that clips sideways is laid out, cut at the side of the box.
     */
    public const MAX_CLIPPED_COLUMNS = 2_000;

    private StyleSheet $styleSheet;

    /** @var \WeakMap<ContainerWidget, array<int, RichTextWidget>> The items drawing the ::before and ::after of flex rows and grids */
    private \WeakMap $pseudoItems;
    private FontRegistry $fontRegistry;
    private PositionTracker $positionTracker;
    private LayoutEngine $layoutEngine;
    private ChromeApplier $chromeApplier;

    /** @var array<class-string, bool> */
    private array $postRenderOverridden = [];

    /** Current terminal columns, set during render() for breakpoint resolution */
    private ?int $currentColumns = null;

    private MediaContext $mediaContext;
    /** The media of the last frame but its size, which the render caches hold */
    private ?string $renderedMedia = null;

    /**
     * Resolved styles for the current frame, keyed on the widget's render
     * revision so that a style changed from beforeRender() is picked up.
     *
     * @var \WeakMap<AbstractWidget, array{int, Style, Style}> Each style, and the one of the style sheets it was resolved from
     */
    private \WeakMap $styleCache;

    /**
     * Counts the changes that can give a widget another resolved style within a frame, for the caches built on them.
     */
    private int $styleEpoch = 0;

    /**
     * The content rows of the containers of definite height in the current frame, for percentage heights.
     *
     * @var \WeakMap<AbstractWidget, int>
     */
    private \WeakMap $definiteRows;
    /** @var \WeakMap<AbstractWidget, int> The rows of the containing block of each absolute or fixed widget, which its percentage heights are of */
    private \WeakMap $blockRows;

    /**
     * The relative widgets of a positive z-index, or moved past the lines of their container, lifted out of them,
     * drawn with the absolute and fixed ones: their z-index, their lines and their width; no lines for those left in
     * their lines, only drawn over the absolute ones before them, their rows then.
     *
     * @var \WeakMap<AbstractWidget, array{int, list<string>|null, int, int}>
     */
    private \WeakMap $liftedRelatives;

    /**
     * The focused widget each scroll container last scrolled into view.
     *
     * @var \WeakMap<ContainerWidget, \WeakReference<AbstractWidget>>
     */
    private \WeakMap $revealed;

    /** @var \WeakReference<AbstractWidget>|null The widget to scroll into view, see reveal() */
    private ?\WeakReference $revealing = null;

    /** @var \WeakMap<ContainerWidget, true> The scroll containers that scrolled to it already */
    private \WeakMap $revealedTarget;

    /** @var \WeakMap<ContainerWidget, int> The first row of the content each scroll container showed last */
    private \WeakMap $shownTops;

    /** @var \WeakMap<ContainerWidget, int> The first column of the content each container scrolled sideways showed last */
    private \WeakMap $shownLefts;

    /** @var \WeakMap<ContainerWidget, array{int, int, float}> The containers gliding sideways: from, to, since when */
    private \WeakMap $sidewaysGlides;

    /** @var \WeakMap<ContainerWidget, \WeakReference<AbstractWidget>> The focused widget each container scrolled sideways brought into view last */
    private \WeakMap $revealedSideways;

    /** @var \WeakMap<ContainerWidget, array{int, int, array<string, string>}> The column and the width each container scrolled sideways showed its lines from last, and each line cut, by its text */
    private \WeakMap $slicedSideways;

    /** @var \WeakMap<ContainerWidget, true> The containers scrolled sideways to the widget to reveal already */
    private \WeakMap $revealedTargetSideways;

    /** @var \WeakMap<AbstractWidget, array{int, int, int, int}> The min-content widths measured: the render revision, the columns and rows it was measured in, the width */
    private \WeakMap $minContentWidths;

    /** @var \WeakMap<AbstractWidget, array{int, int, int, int}> The soft min-content widths, see measureSoftMinContentWidth() */
    private \WeakMap $softMinContentWidths;

    /** @var \WeakMap<AbstractWidget, array{int, bool}> Whether a widget holds a control or an image, by its revision */
    private \WeakMap $softens;

    /** @var \WeakMap<AbstractWidget, array{int, array<string, array{int, ?int}>}> The intrinsic widths measured: the render revision, and by the columns and rows it was measured in, the width and the definite height it gave */
    private \WeakMap $intrinsicWidths;

    /** @var \WeakMap<AbstractWidget, int> The widths the children of a container scrolled sideways keep, the visible ones, when they fit in it */
    private \WeakMap $sidewaysCaps;

    /** @var \WeakMap<AbstractWidget, bool> The absolute and fixed widgets measured past the height they set, and whether that height is definite, see renderPositioned() */
    private \WeakMap $unboundedRows;
    /** @var \WeakMap<AbstractWidget, array{array{int, int, int, ?int}, LineBufferInterface}> The absolute and fixed widgets whose content was found to fit the height they set, keyed on what it was measured with, see renderPositioned() */
    private \WeakMap $fitsSetHeight;

    /** @var \WeakMap<AbstractWidget, array{int, int, bool, int}> Whether each widget lets its floats out, by revision and style epoch, and the depth its answer was cut short at, -1 for none, see letsFloatsOut() */
    private \WeakMap $floatsOut;

    /** @var \WeakMap<AbstractWidget, array{int, int, bool, array{int, int}, int}> Whether the floats of each widget go on past it, by revision and style epoch, its parent and its revision, and the depth its answer was cut short at, -1 for none, see escapesFloats() */
    private \WeakMap $floatsEscape;

    /** @var \WeakMap<ContainerWidget, array{int, bool, int}> Whether each block holds items of a list, by revision and style epoch, see holdsMarkedItems() */
    private \WeakMap $markedItems;

    /** @var \WeakMap<ContainerWidget, int> The row each snapping scroll container snapped to last */
    private \WeakMap $snappedTops;

    /** @var \WeakMap<ContainerWidget, int> The column each container snapping sideways snapped to last */
    private \WeakMap $snappedLefts;

    /** @var \WeakMap<ContainerWidget, array{int, int, float}> The scroll containers gliding: from, to, since when */
    private \WeakMap $glides;

    /** @var \Closure(): float */
    private \Closure $clock;

    /** @var list<array{int, int, int, string}> The links to a fragment of the page in the last frame: row, first and last column, fragment */
    private array $fragmentLinks = [];

    /** @var list<array{int, int, int, string}> The links of the last frame by their id: row, first and last column, id */
    private array $linkSpans = [];

    /**
     * The absolute and fixed widgets, found when their parent renders.
     *
     * @var \WeakMap<AbstractWidget, true>
     */
    /** @var \WeakMap<AbstractWidget, array<string, TextWidget>> The widgets of the absolute and fixed ::before and ::after of each widget, drawn over the frame */
    private \WeakMap $pseudoWidgets;

    private \WeakMap $overlays;

    /**
     * The column the box of each widget starts at in its slot, past its margin, and its width.
     *
     * @var \WeakMap<AbstractWidget, array{int, int}>
     */
    private \WeakMap $boxes;

    /**
     * The widgets whose last render came from their cache, see wasRenderCached().
     *
     * @var \WeakMap<AbstractWidget, true>
     */
    private \WeakMap $cachedRenders;

    /**
     * The widgets with an outline, found when they render: kept while their render is cached.
     *
     * @var \WeakMap<AbstractWidget, true>
     */
    private \WeakMap $outlines;

    /** @var \WeakMap<AbstractWidget, \WeakReference<AbstractWidget>> The anchor of each widget its style names none for */
    private \WeakMap $implicitAnchors;

    /** @var \WeakMap<AbstractWidget, array<string, list<string>>> The scroll state of the widgets where they were last drawn: stuck, snapped and scrollable */
    private \WeakMap $scrollStates;

    /** @var \WeakMap<ContainerWidget, array<'stuck'|'x'|'y', list<\WeakReference<AbstractWidget>>>> The widgets each scroll container marked stuck or snapped */
    private \WeakMap $scrollMarks;

    /** @var \WeakMap<ContainerWidget, array{?int, ?int, list<string>}> The row and column each scroll container was scrolled to, and the sides it was last scrolled to */
    private \WeakMap $scrolled;

    /** @var \WeakMap<AbstractWidget, int> The rows of the content of each widget where it was last drawn or measured, before max-height clips them */
    private \WeakMap $contentRows;

    /** @var array{AbstractWidget, array<string, list<array{AbstractWidget, int}>>}|null The widgets of the frame with an anchor-name, by name, in document order, with their depth: see findAnchor() */
    private ?array $anchorIndex = null;

    /**
     * The keys in the stacking order of the absolute and fixed widgets of a z-index not negative met in a render, null
     * for those not drawn, and the places of the children they were found with.
     *
     * @var array{keys: array<int, array{\WeakReference<AbstractWidget>, array<string, mixed>|null}>, indexes: array<int, array<int, int>>}
     */
    private array $absoluteKeys = ['keys' => [], 'indexes' => []];

    /** @var \WeakMap<ContainerWidget, list<array{int, int, int, string}>> The controls drawn in each scroll container: their row and first and last columns from its top left, and what they do */
    private \WeakMap $scrollControls;

    /** @var \WeakMap<AbstractWidget, array<string, LineBufferInterface>> */
    private \WeakMap $flowPaints;

    /** @var \WeakMap<AbstractWidget, list<array{int, int, int, int}>> */
    private \WeakMap $flowFragments;

    /** @var \WeakMap<AbstractWidget, array{int, int, int, int}|null> */
    private \WeakMap $flowClips;

    /** @var \WeakMap<AbstractWidget, array{int, LineBufferInterface, int, int}> */
    private \WeakMap $flowOverflow;

    public function __construct(?StyleSheet $styleSheet = null, ?FontRegistry $fontRegistry = null)
    {
        $this->fontRegistry = $fontRegistry ?? new FontRegistry();
        $this->styleCache = new \WeakMap();
        $this->pseudoItems = new \WeakMap();
        $this->scrollStates = new \WeakMap();
        $this->scrollMarks = new \WeakMap();
        $this->scrolled = new \WeakMap();
        $this->scrollControls = new \WeakMap();
        $this->liftedRelatives = new \WeakMap();
        $this->definiteRows = new \WeakMap();
        $this->blockRows = new \WeakMap();
        $this->revealed = new \WeakMap();
        $this->revealedTarget = new \WeakMap();
        $this->shownTops = new \WeakMap();
        $this->snappedTops = new \WeakMap();
        $this->snappedLefts = new \WeakMap();
        $this->sidewaysCaps = new \WeakMap();
        $this->unboundedRows = new \WeakMap();
        $this->fitsSetHeight = new \WeakMap();
        $this->floatsOut = new \WeakMap();
        $this->floatsEscape = new \WeakMap();
        $this->markedItems = new \WeakMap();
        $this->minContentWidths = new \WeakMap();
        $this->softMinContentWidths = new \WeakMap();
        $this->softens = new \WeakMap();
        $this->flowFragments = new \WeakMap();
        $this->flowClips = new \WeakMap();
        $this->flowPaints = new \WeakMap();
        $this->intrinsicWidths = new \WeakMap();
        $this->revealedSideways = new \WeakMap();
        $this->slicedSideways = new \WeakMap();
        $this->shownLefts = new \WeakMap();
        $this->sidewaysGlides = new \WeakMap();
        $this->revealedTargetSideways = new \WeakMap();
        $this->glides = new \WeakMap();
        $this->clock = static fn (): float => microtime(true);
        $this->overlays = new \WeakMap();
        $this->pseudoWidgets = new \WeakMap();
        $this->boxes = new \WeakMap();
        $this->cachedRenders = new \WeakMap();
        $this->outlines = new \WeakMap();
        $this->implicitAnchors = new \WeakMap();
        $this->positionTracker = new PositionTracker();
        $this->layoutEngine = new LayoutEngine($this, $this->positionTracker, $this->fontRegistry);
        $this->flowOverflow = $this->layoutEngine->getOverflowingItems();
        $this->chromeApplier = new ChromeApplier($this);
        $this->contentRows = new \WeakMap();
        // Measured as it is drawn, or measured: a widget drawn from its render cache was measured before
        $this->chromeApplier->setContentRowsListener(function (AbstractWidget $widget, int $rows): void {
            $this->contentRows[$widget] = $rows;
        });
        $this->mediaContext = MediaContext::fromEnvironment();

        if (null !== $styleSheet) {
            // Clone the user stylesheet to preserve its runtime type
            // (e.g. TailwindStylesheet) so that its resolve() override
            // is used. Merge the defaults underneath: default rules are
            // added only for selectors the user hasn't already defined.
            $this->styleSheet = clone $styleSheet;
            $this->styleSheet->mergeDefaults(DefaultStyleSheet::create());
        } else {
            $this->styleSheet = DefaultStyleSheet::create();
        }
    }

    /**
     * Add a stylesheet.
     */
    public function addStyleSheet(StyleSheet $styleSheet): void
    {
        $this->styleSheet->merge($styleSheet);
    }

    /**
     * Get the stylesheet.
     */
    public function getStyleSheet(): StyleSheet
    {
        return $this->styleSheet;
    }

    public function getMediaContext(): MediaContext
    {
        return $this->mediaContext;
    }

    /**
     * Set the media context style providers resolve media queries against.
     *
     * The terminal size of the context is replaced on each frame.
     */
    public function setMediaContext(MediaContext $mediaContext): void
    {
        $this->mediaContext = $mediaContext;
    }

    /**
     * Get the tracked position of a widget from the last render pass.
     *
     * Returns null if the widget was not rendered or is not being tracked.
     */
    public function getWidgetRect(AbstractWidget $widget): ?WidgetRect
    {
        if (null === $rect = $this->positionTracker->getWidgetRect($widget)) {
            return null;
        }
        // Rects outlive the frames that drew them, for the subtrees drawn from the cache: not those of what is hidden
        for ($current = $widget; null !== $current; $current = $current->getParent()) {
            if ($this->resolveStyle($current)->getHidden()) {
                return null;
            }
        }

        return $rect;
    }

    /**
     * Whether a scroll container is gliding to where it was scrolled (scroll-behavior: smooth): more frames to draw.
     */
    public function isScrolling(): bool
    {
        return \count($this->glides) > 0 || \count($this->sidewaysGlides) > 0;
    }

    /**
     * Has the scroll containers gliding drawn again on the next frame, their rows shown having moved.
     */
    public function invalidateGlides(): void
    {
        foreach ([$this->glides, $this->sidewaysGlides] as $glides) {
            foreach ($glides as $widget => $_) {
                $widget->invalidate();
            }
        }
    }

    /**
     * @internal for tests
     *
     * @param \Closure(): float $clock
     */
    public function setClock(\Closure $clock): void
    {
        $this->clock = $clock;
    }

    /**
     * Scrolls a widget into view in the scroll containers around it on the next frame, its top at theirs, like
     * Element::scrollIntoView(); once: the user can scroll away from it.
     */
    public function reveal(AbstractWidget $widget): void
    {
        $this->revealing = \WeakReference::create($widget);
        $this->revealedTarget = new \WeakMap();
        $this->revealedTargetSideways = new \WeakMap();
    }

    /**
     * Returns the fragment (#id) a link drawn at a cell of the last frame goes to, null when there is none.
     */
    /**
     * Returns the id of the link drawn at a cell of the last frame, see RichTextWidget::linkId(), null when there is none.
     */
    public function getLinkAt(int $row, int $col): ?string
    {
        foreach ($this->linkSpans as [$linkRow, $first, $last, $id]) {
            if ($row === $linkRow && $col >= $first && $col <= $last) {
                return $id;
            }
        }

        return null;
    }

    public function getFragmentLinkAt(int $row, int $col): ?string
    {
        foreach ($this->fragmentLinks as [$linkRow, $first, $last, $fragment]) {
            if ($row === $linkRow && $col >= $first && $col <= $last) {
                return $fragment;
            }
        }

        return null;
    }

    /**
     * Paints the gradient of a box in its clip box, sized, placed and repeated in its positioning area, or the glyphs
     * in the colors of its background.
     *
     * @param list<string> $lines The lines of the box, its margins included
     *
     * @return list<string>
     */
    private function paintBackground(array $lines, Style $style, BackgroundLayout $layout, Gradient|Color|RasterImage $paint, ?Color $background): array
    {
        // Turned as its EXIF data says, and sharp, as the style wants, before it is measured
        if ($paint instanceof RasterImage) {
            $paint = $paint->withRendering($style->getImageFromImage() ?? true, $style->getPixelated() ?? false);
        }
        [$clipTop, $clipLeft, $clipColumns, $clipRows, $sample, $rowOf] = $this->backgroundSampler($lines, $style, $layout, $paint);

        // background-blend-mode: the image or the gradient blended with the background color
        $blend = null;
        if (null !== ($mode = $style->getBackgroundBlend()) && null !== $background && !$paint instanceof Color) {
            $under = $background->toRgb();
            $under = [$under['r'], $under['g'], $under['b']];
            $blend = static fn (array $color): array => $mode->blend($under, [(int) $color[0], (int) $color[1], (int) $color[2]]);
        }

        return GradientPainter::paint($lines, $paint, $background, $clipTop, $clipLeft, $clipColumns, $clipRows, $sample, BackgroundLayout::TEXT === $layout->clip, $blend, $rowOf);
    }

    /**
     * Paints the lines of a border in the colors of a gradient or an image stretched over the box, like border-image.
     *
     * @param list<string> $lines The lines of the box, its margins included
     *
     * @return list<string>
     */
    private static function paintBorderImage(array $lines, Style $style, Border $border, Gradient|RasterImage $source): array
    {
        $margin = $style->getMargin();
        $width = max(0, ...array_map(AnsiUtils::visibleWidth(...), $lines));
        $columns = $width - ($margin->left ?? 0) - ($margin->right ?? 0);
        $rows = \count($lines) - ($margin->top ?? 0) - ($margin->bottom ?? 0);
        if ($columns <= 0 || $rows <= 0) {
            return $lines;
        }
        // The cells of the border only, the source being as large as the box
        $sample = static fn (int $x, int $y): ?array => $x < $border->left || $x >= $columns - $border->right || $y < $border->top || $y >= $rows - $border->bottom
            ? [$x, $y, $columns, $rows]
            : null;

        return GradientPainter::paint($lines, $source, null, $margin->top ?? 0, $margin->left ?? 0, $columns, $rows, $sample, true);
    }

    /**
     * Where a layer of the background is painted, its clip box as [top, left, columns, rows], and which cell of its
     * image, or of its gradient, a cell of the clip box shows, sized, placed and repeated in its positioning area: the
     * column and row in it, and its size; null where none is. And the row of the image a row of the clip box shows,
     * null for none: the rows that show the same one are painted the same.
     *
     * @param list<string> $lines The lines of the box, its margins included
     *
     * @return array{int, int, int, int, \Closure(int, int): ?array{int, int, int, int}, \Closure(int): ?int}
     */
    private function backgroundSampler(array $lines, Style $style, BackgroundLayout $layout, Gradient|Color|RasterImage $paint): array
    {
        $margin = $style->getMargin();
        $width = max(0, ...array_map(AnsiUtils::visibleWidth(...), $lines));
        $box = [$margin->top ?? 0, $margin->left ?? 0, $width - ($margin->left ?? 0) - ($margin->right ?? 0), \count($lines) - ($margin->top ?? 0) - ($margin->bottom ?? 0)];
        $border = $style->getBorder();
        $padding = $style->getPadding();
        // A box as [top, left, columns, rows], inset by the border, and by the padding
        $area = static function (string $edge) use ($box, $border, $padding): array {
            [$top, $left, $columns, $rows] = $box;
            if (BackgroundLayout::BORDER_BOX !== $edge && BackgroundLayout::TEXT !== $edge) {
                $insets = [$border->top ?? 0, $border->right ?? 0, $border->bottom ?? 0, $border->left ?? 0];
                if (BackgroundLayout::CONTENT_BOX === $edge) {
                    $insets = [$insets[0] + ($padding->top ?? 0), $insets[1] + ($padding->right ?? 0), $insets[2] + ($padding->bottom ?? 0), $insets[3] + ($padding->left ?? 0)];
                }
                [$top, $left, $columns, $rows] = [$top + $insets[0], $left + $insets[3], $columns - $insets[1] - $insets[3], $rows - $insets[0] - $insets[2]];
            }

            return [$top, $left, max(0, $columns), max(0, $rows)];
        };
        [$clipTop, $clipLeft, $clipColumns, $clipRows] = $area($layout->clip);
        [$originTop, $originLeft, $originColumns, $originRows] = $area($layout->origin);

        // The size of the image: of the positioning area unless set, a gradient having none of its own
        $tile = $paint instanceof RasterImage
            ? self::imageTile($paint, $layout, $originColumns, $originRows)
            : [max(1, $layout->size[0]?->resolve($originColumns) ?? $originColumns), max(1, $layout->size[1]?->resolve($originRows) ?? $originRows)];
        $axes = [];
        foreach ([[0, $originLeft, $originColumns, $clipLeft], [1, $originTop, $originRows, $clipTop]] as [$axis, $originStart, $originLength, $clipStart]) {
            $size = $tile[$axis];
            $repeat = $layout->repeat[$axis];
            // The cells from an image to the next: a fraction of the area for round, which stretches as many whole
            // images as fit best across it
            $step = (float) $size;
            if (BackgroundLayout::ROUND === $repeat && $originLength > 0) {
                $step = $originLength / max(1, (int) round($originLength / $size));
                $size = $tile[$axis] = max(1, (int) round($step));
            }
            $count = intdiv(max(0, $originLength), $size);
            if (BackgroundLayout::SPACE === $repeat && $count < 2) {
                $repeat = BackgroundLayout::NO_REPEAT;
            }
            $axes[$axis] = [$clipStart, $originStart, $originLength, $originStart + $layout->position[$axis]->resolve($originLength, $size), $size, $repeat, $count, $step];
        }
        // The column or row of the image a column or row of the clip box shows, null where none is
        $at = static function (int $axis, int $offset) use ($axes): ?int {
            [$clipStart, $originStart, $originLength, $start, $size, $repeat, $count, $step] = $axes[$axis];
            $at = $clipStart + $offset - $start;
            if (BackgroundLayout::ROUND === $repeat) {
                $at = min($size - 1, (int) floor(fmod(fmod($at, $step) + $step, $step) * $size / $step));
            } elseif (BackgroundLayout::SPACE === $repeat) {
                // Whole images, the first and the last at the edges of the area, the room left between them
                $at = $clipStart + $offset - $originStart;
                $step = $size + ($originLength - $count * $size) / ($count - 1);
                $at = $at < 0 || $at >= $originLength ? -1 : (int) floor($at - floor($at / $step) * $step);
            } elseif (BackgroundLayout::NO_REPEAT !== $repeat) {
                $at = (($at % $size) + $size) % $size;
            }

            return $at < 0 || $at >= $size ? null : (int) $at;
        };
        $sample = static fn (int $x, int $y): ?array => null === ($column = $at(0, $x)) || null === ($row = $at(1, $y)) ? null : [$column, $row, $axes[0][4], $axes[1][4]];
        $rowOf = static fn (int $y): ?int => $at(1, $y);

        return [$clipTop, $clipLeft, $clipColumns, $clipRows, $sample, $rowOf];
    }

    /**
     * Paints several background layers, the first one over the others, each in its clip box with its layout: the
     * colors of each cell composed from the bottom one up, the translucent colors of gradients blended over what is
     * under them, the images two pixels a cell, over the color under them all.
     *
     * @param list<string>                                                              $lines  The lines of the box, its margins included
     * @param list<array{0: Gradient|RasterImage, 1: BackgroundLayout, 2?: ?BlendMode}> $layers
     *
     * @return list<string>
     */
    private function paintBackgroundLayers(array $lines, Style $style, array $layers, Color $under, ?Color $background, bool $opaque = true): array
    {
        $samplers = [];
        $area = null;
        foreach (array_reverse($layers) as $layer) {
            [$paint, $layout] = $layer;
            $mode = $layer[2] ?? null;
            if ($paint instanceof RasterImage) {
                $paint = $paint->withRendering($style->getImageFromImage() ?? true, $style->getPixelated() ?? false);
            }
            [$top, $left, $columns, $rows, $sample, $rowOf] = $this->backgroundSampler($lines, $style, $layout, $paint);
            if ($columns <= 0 || $rows <= 0) {
                continue;
            }
            $samplers[] = [$paint, $top, $left, $columns, $rows, $sample, $mode, $rowOf];
            $area = null === $area ? [$top, $left, $top + $rows, $left + $columns] : [min($area[0], $top), min($area[1], $left), max($area[2], $top + $rows), max($area[3], $left + $columns)];
        }
        if (null === $area) {
            return $lines;
        }
        $rgb = static function (Color $color): array {
            $rgb = $color->toRgb();

            return [(int) $rgb['r'], (int) $rgb['g'], (int) $rgb['b']];
        };
        $base = $rgb($under);
        $pixels = static function (int $x, int $y) use ($samplers, $area, $base, $rgb, $opaque): ?array {
            // The two pixels of the cell, from the color under the layers up; a mode blends a layer with what is under
            // it, nothing without a background color, the layers being a group of their own, like CSS
            $halves = [$base, $base];
            $real = [$opaque, $opaque];
            $painted = false;
            foreach ($samplers as [$paint, $top, $left, $columns, $rows, $sample, $mode]) {
                $column = $area[1] + $x - $left;
                $row = $area[0] + $y - $top;
                if ($column < 0 || $row < 0 || $column >= $columns || $row >= $rows || null === $cell = $sample($column, $row)) {
                    continue;
                }
                if ($paint instanceof RasterImage) {
                    foreach ($paint->cellAt(max(1, $cell[2]), max(1, $cell[3]), $cell[0], $cell[1]) as $half => $pixel) {
                        if (null !== $pixel) {
                            $halves[$half] = null !== $mode && $real[$half] ? $mode->blend($halves[$half], $pixel) : $pixel;
                            $real[$half] = $painted = true;
                        }
                    }
                    continue;
                }
                [$color, $alpha] = $paint->sampleAt(...$cell);
                if ($alpha < 0.005) {
                    continue;
                }
                $painted = true;
                $over = $rgb($color);
                foreach ($halves as $half => $pixel) {
                    $color = null !== $mode && $real[$half] ? $mode->blend($pixel, $over) : $over;
                    $halves[$half] = $alpha >= 1.0 ? $color : array_map(static fn (int $a, int $b): int => (int) round($a + ($b - $a) * $alpha), $pixel, $color);
                    $real[$half] = true;
                }
            }

            return $painted ? $halves : null;
        };

        // The row of the image of each layer a row shows, a horizontal gradient being the same on all of them: the rows
        // that show the same ones are painted the same
        $rowKey = static function (int $y) use ($samplers, $area): string {
            $key = '';
            foreach ($samplers as [$paint, $top, , , $rows, , , $rowOf]) {
                $row = $area[0] + $y - $top;
                $key .= ($row < 0 || $row >= $rows ? '-' : (null === ($imageRow = $rowOf($row)) ? 'n' : ($paint instanceof Gradient && $paint->isHorizontal() ? 's' : $imageRow))).',';
            }

            return $key;
        };

        return GradientPainter::paintPixels($lines, $pixels, $background, $area[0], $area[1], $area[3] - $area[1], $area[2] - $area[0], $rowKey);
    }

    /**
     * The columns and rows of a background image: its own size (8 pixels per column, two per row, like an <img>),
     * the size set, the other one following its proportions, or the positioning area it covers or is contained in.
     *
     * @return array{int<1, max>, int<1, max>}
     */
    private static function imageTile(RasterImage $image, BackgroundLayout $layout, int $areaColumns, int $areaRows): array
    {
        // The rows of a column, a cell showing two pixels of it
        $ratio = $image->getHeight() / max(1, $image->getWidth()) / 2;
        // Its pixels per CSS pixel, like the 2x of image-set(), make it smaller
        $columns = max(1, (int) ceil($image->getWidth() / $image->getResolution() / 8));
        $rows = max(1, (int) round($columns * $ratio));
        if (null !== $layout->fit) {
            $scales = [$areaColumns / $columns, $areaRows / $rows];
            $scale = 'cover' === $layout->fit ? max($scales) : min($scales);

            return [max(1, min(1000, (int) round($columns * $scale))), max(1, min(1000, (int) round($rows * $scale)))];
        }
        $width = $layout->size[0]?->resolve($areaColumns);
        $height = $layout->size[1]?->resolve($areaRows);
        [$columns, $rows] = match (true) {
            null === $width && null === $height => [$columns, $rows],
            null === $height => [$width, (int) round($width * $ratio)],
            null === $width => [(int) round($height / $ratio), $height],
            default => [$width, $height],
        };

        return [max(1, min(1000, $columns)), max(1, min(1000, $rows))];
    }

    /**
     * Render the widget tree starting from root.
     */
    public function renderFrame(ContainerWidget $root, int $columns, int $rows): LineBufferInterface
    {
        foreach ($this->flowFragments as $widget => $fragments) {
            if (null !== $rect = $this->positionTracker->getWidgetRect($widget)) {
                $this->positionTracker->setWidgetRect($widget, new WidgetRect($rect->row, $rect->col, $rect->columns, $rect->rows, $fragments, $rect->clip));
            }
        }
        foreach ($this->flowClips as $widget => $clip) {
            if (null !== $rect = $this->positionTracker->getWidgetRect($widget)) {
                $this->positionTracker->setWidgetRect($widget, new WidgetRect($rect->row, $rect->col, $rect->columns, $rect->rows, $rect->fragments, $clip));
            }
        }
        $this->flowFragments = new \WeakMap();
        $this->flowClips = new \WeakMap();
        $context = new RenderContext($columns, $rows, null, $this->fontRegistry, fillRows: true);
        $this->currentColumns = $columns;
        $this->styleCache = new \WeakMap();
        ++$this->styleEpoch;
        $this->pseudoItems = new \WeakMap();
        $this->anchorIndex = null;
        $this->absoluteKeys = ['keys' => [], 'indexes' => []];
        $this->positionTracker->reset();

        if ($providers = $this->styleSheet->getProviders()) {
            $this->mediaContext = $this->mediaContext->withSize($columns, $rows);
            foreach ($providers as $provider) {
                $provider->prepare($root, $this->mediaContext);
            }
        }

        // Other media, like another color scheme: what widgets draw from it themselves, like the shapes of an <svg> styled
        // for it, is not in their styles, which their caches follow
        $media = $this->mediaContext->withSize(0, 0)->getKey();
        if (null !== $this->renderedMedia && $media !== $this->renderedMedia) {
            $clear = static function (AbstractWidget $widget) use (&$clear): void {
                $widget->clearRenderCache();
                if ($widget instanceof ContainerWidget) {
                    foreach ($widget->all() as $child) {
                        $clear($child);
                    }
                }
            };
            $clear($root);
        }
        $this->renderedMedia = $media;

        $result = $this->renderWidgetLines($root, $context);

        // Styles that depend on the layout, like container queries: once more when they changed
        foreach ($providers as $provider) {
            if ($provider instanceof ScrollStateAwareInterface) {
                $provider->setScrollStateResolver($this->getScrollState(...));
            }
            if ($provider instanceof IntrinsicSizeAwareInterface) {
                $provider->setIntrinsicSizeResolver($this->getIntrinsicRows(...));
            }
            if ($provider instanceof LayoutAwareStyleProviderInterface && $provider->afterLayout($root, $this->layoutRect(...))) {
                $this->styleCache = new \WeakMap();
                ++$this->styleEpoch;
                $this->anchorIndex = null;
                $this->absoluteKeys = ['keys' => [], 'indexes' => []];
                $this->positionTracker->reset();
                $result = $this->renderWidgetLines($root, $context);
                break;
            }
        }

        $this->positionTracker->setWidgetRect($root, new WidgetRect(0, 0, $columns, \count($result)));

        $result = $this->drawFlowOverflow($result, $root, $columns, $rows);
        if ($this->outlines->count()) {
            $result = $this->drawOutlines($result, $columns, $rows, $root);
        }
        $overlays = $this->overlays->count() ? $this->collectOverlays($root) : [];

        // The absolute and fixed widgets, and the relative ones of a positive z-index lifted out of their lines
        return $this->extractFragmentLinks($overlays || \count($this->liftedRelatives) > 0 ? $this->drawOverlays($result, $overlays, $columns, $rows, $root) : $result);
    }

    /**
     * Takes the marks of the links to fragments out of a frame, keeping where they are: they are not terminal links.
     */
    private function extractFragmentLinks(LineBufferInterface $frame): LineBufferInterface
    {
        $this->fragmentLinks = [];
        $this->linkSpans = [];
        $marker = "\x1b]8;".RichTextWidget::FRAGMENT_LINK;
        $lines = $frame->toArray();
        $found = false;
        foreach ($lines as $row => $line) {
            if (!str_contains($line, "\x1b]8;")) {
                continue;
            }
            $found = $found || str_contains($line, "\x1b]8;tui-");
            $out = '';
            $col = 0;
            $open = null;
            $openId = null;
            foreach (preg_split('/(\x1b\]8;[^;\x07\x1b]*;[^\x07\x1b]*(?:\x07|\x1b\\\\))/', $line, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
                if (!str_starts_with($part, "\x1b]8;")) {
                    $out .= $part;
                    $col += AnsiUtils::visibleWidth($part);
                    continue;
                }
                $uri = rtrim(substr($part, strpos($part, ';', 4) + 1), "\x07\x1b\\");
                // The marks of the renderer, like those of the links to fragments, which are not terminal links
                $ours = str_starts_with($part, "\x1b]8;tui-");
                $fragment = str_starts_with($part, $marker);
                // Any link ends one: a terminal has one at a time
                if (null !== $open && $col > $open[0]) {
                    $this->fragmentLinks[] = [$row, $open[0], $col - 1, $open[1]];
                }
                if (null !== $openId && $col > $openId[0]) {
                    $this->linkSpans[] = [$row, $openId[0], $col - 1, $openId[1]];
                }
                $open = $fragment && '' !== $uri ? [$col, $uri] : null;
                $openId = '' !== $uri && preg_match('/^\x1b\]8;[^;]*\bid=([^:;]+)/', $part, $id) ? [$col, $id[1]] : null;
                if (!$ours) {
                    $out .= $part;
                }
            }
            if (null !== $open && $col > $open[0]) {
                $this->fragmentLinks[] = [$row, $open[0], $col - 1, $open[1]];
            }
            if (null !== $openId && $col > $openId[0]) {
                $this->linkSpans[] = [$row, $openId[0], $col - 1, $openId[1]];
            }
            $lines[$row] = $out;
        }

        return $found ? new ArrayLineBuffer($lines) : $frame;
    }

    /**
     * The children laid out in a container: those of a child without a box (display: contents) in its place.
     *
     * @return list<AbstractWidget>
     */
    private function boxChildren(ParentInterface $widget, int $depth = 0): array
    {
        $children = [];
        foreach ($widget->all() as $child) {
            if ($depth < 32 && $child instanceof ParentInterface && $this->resolveStyle($child)->getContents() && !$this->resolveStyle($child)->getHidden()) {
                array_push($children, ...$this->boxChildren($child, $depth + 1));
            } else {
                $children[] = $child;
            }
        }

        return $children;
    }

    /**
     * The absolute and fixed widgets still in the tree, in document order; not those of a hidden subtree.
     *
     * @return list<AbstractWidget>
     */
    private function collectOverlays(ContainerWidget $root): array
    {
        $overlays = [];
        foreach ($this->overlays as $overlay => $_) {
            // Its path from the root, which orders it in the document; none when it left the tree or is not shown
            $path = [];
            for ($current = $overlay; $current !== $root; $current = $parent) {
                $parent = $current->getParent();
                if (null === $parent || $this->resolveStyle($current)->getHidden() || !$this->resolveStyle($overlay)->getPosition()?->isOutOfFlow()) {
                    continue 2;
                }
                array_unshift($path, (int) array_search($current, $parent instanceof ParentInterface ? $parent->all() : [], true));
            }
            $overlays[] = [$path, $overlay];
        }
        usort($overlays, static fn (array $a, array $b) => $a[0] <=> $b[0]);

        return array_column($overlays, 1);
    }

    /**
     * Draws the absolute and fixed widgets over the frame: placed in document order, so that the ones inside others
     * find their containing block, then drawn by z-index.
     *
     * @param list<AbstractWidget> $overlays
     */
    private function drawOverlays(LineBufferInterface $frame, array $overlays, int $columns, int $rows, ContainerWidget $root): LineBufferInterface
    {
        // The places of the children met by the keys of the stacking order, found once
        $indexes = [];
        $placed = [];
        $seen = [];
        for ($pass = 0; $overlays && $pass < 32; ++$pass) {
            // Drawing them can add children, like those of a ::before: the places are found again each pass
            $indexes = [];
            // The overlays known before the pass, to find those it registers, even if others left meanwhile
            $known = [];
            foreach ($this->overlays as $overlay => $_) {
                $known[spl_object_id($overlay)] = true;
            }
            foreach ($overlays as $widget) {
                $seen[spl_object_id($widget)] = true;
                $style = $this->resolveStyle($widget);
                $position = $style->getPosition();
                if (null === $position) {
                    continue;
                }
                [$blockRow, $blockCol, $blockColumns, $blockRows] = $this->containingBlock($widget, $position, $columns, $rows);
                $top = $position->inset('top', $blockRows);
                $right = $position->inset('right', $blockColumns);
                $bottom = $position->inset('bottom', $blockRows);
                $left = $position->inset('left', $blockColumns);
                $centered = null !== $style->getAutoMargin();

                // Both insets: the room between them, auto margins centering a narrower widget in it; else its content
                // Never larger than the screen, whatever the insets: what is past it is not drawn
                $room = max(1, min($columns, $blockColumns - ($left ?? 0) - ($right ?? 0)));
                $width = null !== $left && null !== $right ? $room : max(1, min($room, $style->getMaxColumns() ?? $this->measureIntrinsicWidth($widget, $room, $rows)));
                // Both insets and a width, like the left: 0; right: 0 then width: 9.6em of the menu of LWN: the width,
                // from the left inset, like CSS, the right one ignored
                if (null !== $left && null !== $right && !$centered) {
                    if (null !== $style->getMaxColumns()) {
                        $width = max(1, min($room, $style->getMaxColumns()));
                    } elseif (null !== $style->getWidthPercent()) {
                        $width = max(1, min($room, (int) $style->resolveWidthPercent($blockColumns)));
                    }
                }
                // Squeezed past its containing block, like a badge at left: 100%: at least its longest word, overflowing it
                if ((null === $left || null === $right) && null === $style->getMaxColumns() && $width >= $room && $room < $columns) {
                    $width = max($width, min($columns, $this->computeMinContentWidth($widget, $columns, $rows, 0, true)));
                }
                // A percentage width: of its containing block, like the labels of the cells of EasyAdmin on a narrow screen
                if ((null === $left || null === $right) && null !== $style->getWidthPercent()) {
                    $width = max(1, min($columns, (int) $style->resolveWidthPercent($blockColumns)));
                }
                $shift = 0;
                if (null !== $left && null !== $right && $centered) {
                    // Only the box is drawn, at its width or its content's: the margins around it let what is behind show
                    $width = max(1, min($room, $style->getMaxColumns() ?? $this->measureIntrinsicWidth($widget, $room, $rows)));
                    $shift = intdiv($room - $width, 2);
                }
                $height = null !== $top && null !== $bottom ? max(1, min($rows, $blockRows - $top - $bottom)) : null;
                // A height its insets give, its content drawn past it; not one it sets
                $overflows = null !== $height;
                // Its anchor: the one its style names, else the implicit one, like the button that opened it; without one,
                // like CSS, position-area and anchor() do nothing
                $named = $position->anchor ?? (array_values($position->anchorEdges)[0][0] ?? null);
                $implicit = null === $named ? ($this->implicitAnchors[$widget] ?? null)?->get() : null;
                $anchored = $position->isAnchored() || null !== $position->anchor;
                $anchor = !$anchored ? null : (null !== $implicit ? $this->boxRect($implicit) : $this->anchorRect($root, $named, $widget));
                // position-visibility: not drawn without its anchor, or while its anchor is not drawn, like scrolled out
                if (null === $anchor && $anchored && [] !== $position->visibility) {
                    $exists = null !== $implicit || null !== $this->findAnchor($root, $named, $widget, false);
                    if ($exists ? \in_array('anchors-visible', $position->visibility, true) : \in_array('anchors-valid', $position->visibility, true)) {
                        continue;
                    }
                }
                if (!$position->isAnchored()) {
                    $anchor = null;
                }

                // Placed in an area next to its anchor: its insets are the area's, it is at its width or its content's
                if (null !== $position->area && null !== $anchor) {
                    $width = max(1, min($columns, $style->getMaxColumns() ?? $this->measureIntrinsicWidth($widget, $columns, $rows)));
                    $height = null;
                    $centered = false;
                }
                // Sized after an anchor, like width: anchor-size(width)
                $heightBounds = [null, null];
                if ([] !== $position->anchorSizes) {
                    $sizes = $position->anchorSizes(function (?string $name) use ($root, $implicit, $position, $widget): ?array {
                        $rect = null === $name && null !== $implicit ? $this->boxRect($implicit) : $this->anchorRect($root, $name ?? $position->anchor, $widget);

                        return null === $rect ? null : [$rect->columns, $rect->rows];
                    });
                    if (isset($sizes['width']) || isset($sizes['min-width']) || isset($sizes['max-width'])) {
                        $width = max(1, min($columns, $sizes['max-width'] ?? \PHP_INT_MAX, max($sizes['min-width'] ?? 1, $sizes['width'] ?? $width)));
                        $shift = 0;
                    }
                    if (isset($sizes['height'])) {
                        $height = max(1, min($rows, $sizes['height']));
                        $centered = false;
                        $overflows = true;
                    }
                    $heightBounds = [$sizes['min-height'] ?? null, $sizes['max-height'] ?? null];
                }

                $saved = $this->positionTracker->suppressStack();
                // Its percentages are of its containing block
                $context = new RenderContext($width, $rows, null, $this->fontRegistry)->withContainingColumns($blockColumns);
                // Its percentage heights are of its containing block too
                $this->blockRows[$widget] = $blockRows;
                // Placed by its box, its content past it going on under it, like CSS
                [$lines, $boxRows] = $this->renderPositioned($widget, $context, null !== $height && !$centered ? $height : null, $overflows);
                // min-height and max-height of the anchor: the content padded or clipped to them
                if ([null, null] !== $heightBounds && null !== ($bounded = max($heightBounds[0] ?? 0, min($heightBounds[1] ?? \PHP_INT_MAX, $boxRows))) && $bounded !== $boxRows) {
                    $height = max(1, min($rows, $bounded));
                    $centered = false;
                    // Its content still drawn on past that height, where it is drawn too
                    $overflows = true;
                    [$lines, $boxRows] = $this->renderPositioned($widget, $context, $height, true);
                }
                $this->positionTracker->restoreStack($saved);
                $count = $boxRows;

                // No inset on an axis: where it would be in the flow, like CSS, like a dropdown menu under its button
                $static = (null === $top && null === $bottom) || (null === $left && null === $right) ? $this->staticPlace($widget) : null;
                $row = match (true) {
                    null !== $height && $centered => $blockRow + $top + max(0, intdiv($height - $count, 2)),
                    null !== $top => $blockRow + $top,
                    null !== $bottom => $blockRow + $blockRows - $bottom - $count,
                    default => $static[0] ?? $blockRow,
                };
                $col = $shift + (null !== $left ? $blockCol + $left : (null !== $right ? $blockCol + $blockColumns - $right - $width : $static[1] ?? $blockCol));
                // Anchor positioning: next to the widget its anchor names
                $fits = $row >= 0 && $col >= 0 && $row + $count <= $rows && $col + $width <= $columns;
                if (null !== $anchor) {
                    // The size a try sets, after its anchor or not, and the rows the element takes at it
                    $measure = function (Position $try) use ($root, $implicit, $widget, $width, $count, $columns, $rows, $context): array {
                        $sizes = $try->anchorSizes(function (?string $name) use ($root, $implicit, $try, $widget): ?array {
                            $rect = null === $name && null !== $implicit ? $this->boxRect($implicit) : $this->anchorRect($root, $name ?? $try->anchor, $widget);

                            return null === $rect ? null : [$rect->columns, $rect->rows];
                        });
                        $tryWidth = max(1, min($columns, $sizes['width'] ?? $width));
                        if (isset($sizes['height'])) {
                            return [$tryWidth, max(1, min($rows, $sizes['height']))];
                        }
                        $saved = $this->positionTracker->suppressStack();
                        $tryCount = $tryWidth === $width ? $count : \count($this->renderWidgetLines($widget, $context->withColumns($tryWidth)));
                        $this->positionTracker->restoreStack($saved);

                        return [$tryWidth, $tryCount];
                    };
                    [$row, $col, $fits] = self::anchored($position, $anchor, $width, $count, $row, $col, $columns, $rows, $measure, $chosen);
                    // Placed by a try of its own size: drawn at it
                    if (null !== $chosen && [] !== $chosen->anchorSizes) {
                        [$width, $tryHeight] = $measure($chosen);
                        $context = $context->withColumns($width);
                        $sizes = $chosen->anchorSizes(fn (?string $name): ?array => null === ($rect = null === $name && null !== $implicit ? $this->boxRect($implicit) : $this->anchorRect($root, $name ?? $chosen->anchor, $widget)) ? null : [$rect->columns, $rect->rows]);
                        if (isset($sizes['height'])) {
                            $height = $tryHeight;
                            $centered = false;
                            $overflows = true;
                        }
                        $count = $tryHeight;
                    }
                }
                // position-visibility: no-overflow, not drawn when it does not fit on the screen, its fallbacks tried
                if (!$fits && \in_array('no-overflow', $position->visibility, true)) {
                    continue;
                }
                // transform: translate() in percents of its own box, like left: 50% with translate(-50%) to center it
                [$tx, $ty] = $position->translation($width, $count);
                $col += $tx;
                $row += $ty;

                // Rendered again where it is drawn, so that the widgets inside it are tracked there
                $widget->clearRenderCache();
                $this->positionTracker->push($row, $col);
                [$lines, $boxRows] = $this->renderPositioned($widget, $context, null !== $height && !$centered ? $height : null, $overflows);
                $this->positionTracker->pop();
                $this->positionTracker->setWidgetRect($widget, new WidgetRect($row, $col, $width, $boxRows));
                // Transparent, like opacity: 0: what is under it shows, its place still taking the pointer, but what a box
                // that clips cuts of it
                if ($style->getTransparent()) {
                    if (null !== $clip = $this->overlayClip($widget, $position)) {
                        $this->clipPointer($widget, $clip);
                    }
                    continue;
                }
                // A modal dialog lays its ::backdrop over what is under it: its background, translucent or not, else dim
                $backdrop = null;
                if ($widget->hasState('modal')) {
                    $backdropStyle = $this->styleSheet->resolveElement($widget, 'backdrop');
                    if (null !== $tint = $backdropStyle->getTranslucentBackground() ?? (null !== ($background = $backdropStyle->getBackground()) ? [$background, 100] : null)) {
                        $backdrop = new BackdropTint($tint[0], $tint[1], $this->mediaContext->colorScheme);
                    } elseif ($backdropStyle->getDim()) {
                        $backdrop = true;
                    }
                }
                // Cut by the boxes that clip what overflows them, up to its containing block, like the decorations of a
                // card of overflow: hidden, not by those around its containing block
                // Kept when cut whole, for the boxes in it placed by it, its rect that of its whole box
                if (null !== $clip = $this->overlayClip($widget, $position)) {
                    [$lines, $row, $col, $width] = self::clipLines($lines instanceof LineBufferInterface ? $lines->toArray() : $lines, $row, $col, $width, $clip);
                    // What is cut takes no pointer, in it neither
                    $this->clipPointer($widget, $clip);
                }
                if (null !== $key = $this->stackingKey($widget, $root, $indexes)) {
                    $placed[] = [$key, $widget, $lines, $row, $col, $width, $backdrop, false];
                }
            }
            // Those inside the ones just drawn, which they found as they rendered the first time
            $added = false;
            foreach ($this->overlays as $overlay => $_) {
                if (!isset($known[spl_object_id($overlay)])) {
                    $added = true;
                    break;
                }
            }
            $overlays = $added ? array_values(array_filter($this->collectOverlays($root), static fn (AbstractWidget $widget) => !isset($seen[spl_object_id($widget)]))) : [];
        }

        // The relative widgets of a nonzero z-index or moved past their container, at their moved box, still in the
        // tree and shown
        // Those left in their lines after those lifted out of them, which can hold them
        $inPlace = [];
        foreach ($this->liftedRelatives as $widget => [, $lines, $width, $height]) {
            for ($current = $widget; $current !== $root; $current = $current->getParent()) {
                if (null === $current || $this->resolveStyle($current)->getHidden()) {
                    continue 2;
                }
            }
            if (PositionMode::Relative !== $this->resolveStyle($widget)->getPosition()?->mode || null === $rect = $this->boxRect($widget)) {
                continue;
            }
            if (null === $lines) {
                $inPlace[] = [$widget, $rect, $width, $height];
                continue;
            }
            // Its box below its top margin, the lines lifted
            if (null !== $key = $this->stackingKey($widget, $root, $indexes)) {
                $placed[] = [$key, $widget, $lines, $rect->row + max(0, $this->resolveStyle($widget)->getMargin()->top ?? 0), $rect->col, $width, null, false];
            }
        }
        // Its rows in the lines that hold it: of the frame, or of the box drawn over it that it is in
        // Copied only for those left in their lines
        $frameLines = $inPlace ? $frame->toArray() : [];
        $holders = [];
        foreach ($placed as $index => $item) {
            $holders[spl_object_id($item[1])] = $index;
        }
        foreach ($inPlace as [$widget, $rect, $width, $height]) {
            if (null === $key = $this->stackingKey($widget, $root, $indexes)) {
                continue;
            }
            $row = $rect->row + max(0, $this->resolveStyle($widget)->getMargin()->top ?? 0);
            $holder = null;
            for ($current = $widget->getParent(); null !== $current && null === $holder; $current = $current->getParent()) {
                $holder = $placed[$holders[spl_object_id($current)] ?? -1] ?? null;
            }
            $source = null !== $holder ? (\is_array($holder[2]) ? $holder[2] : $holder[2]->toArray()) : $frameLines;
            $lines = [];
            for ($i = 0; $i < $height; ++$i) {
                $lines[] = AnsiUtils::sliceToWidth($source[$row + $i - ($holder[3] ?? 0)] ?? '', $rect->col - ($holder[4] ?? 0), $width);
            }
            $placed[] = [$key, $widget, $lines, $row, $rect->col, $width, null, null !== $holder ? spl_object_id($holder[1]) : 0];
        }
        // By stacking context, then z-index, then in document order
        usort($placed, static fn (array $a, array $b) => StackingOrder::compare($a[0], $b[0]));
        $drawn = [];
        foreach ($placed as $item) {
            $drawn[spl_object_id($item[1])] = $item;
        }
        $below = [];
        $over = [];
        $inside = [];
        foreach ($placed as $item) {
            $under = $item[0]['under'];
            if ($under instanceof AbstractWidget && isset($drawn[spl_object_id($under)])) {
                $inside[spl_object_id($under)][] = $item;
            } elseif (false === $under) {
                $over[] = $item;
            } else {
                $below[] = $item;
            }
        }
        // A negative z-index is under the flow: drawn under what is drawn already, the closest to it first; inside a
        // context drawn over the flow, right after it, under its content
        $sequence = array_map(static fn (array $item) => [$item, true], array_reverse($below));
        foreach ($over as $item) {
            $sequence[] = [$item, false];
            if (isset($inside[spl_object_id($item[1])])) {
                $item[2] = \is_array($item[2]) ? $item[2] : $item[2]->toArray();
                foreach ($inside[spl_object_id($item[1])] as $child) {
                    $sequence[] = [$child, $item];
                }
            }
        }
        $out = $frame->toArray();
        // The rows a layer was drawn on, under which a raised block cannot simply be put back, with the first column it
        // changed and the one past the last
        $touched = [];
        // The background of the page, which CSS draws under everything, a negative z-index too
        $canvas = $below ? $this->canvasBackground($root) : null;
        // The rows of the boxes holding one left in their lines, as drawn
        $held = [];
        foreach ($placed as $item) {
            if (\is_int($item[7])) {
                $held[$item[7]] = [];
            }
        }
        $holding = null;
        foreach ($sequence as [[$key, $overlay, $lines, $row, $col, $width, $backdrop, $inPlace], $beneath]) {
            if (null !== $holding) {
                $held[$holding] = $out;
                $holding = null;
            }
            if (isset($held[spl_object_id($overlay)])) {
                $holding = spl_object_id($overlay);
            }
            // Its rows as they are drawn there, the box that holds it drawn
            $reference = 0 === $inPlace ? $frameLines : (\is_int($inPlace) && [] !== ($held[$inPlace] ?? []) ? $held[$inPlace] : null);
            // Hidden, like the chevrons of the breadcrumbs of GitHub Docs with visibility: hidden, or transparent: out of
            // the flow, it has no room to keep, and draws nothing over what is under it
            if (true === ($overlayStyle = $this->resolveStyle($overlay))->getInvisible() || true === $overlayStyle->getTransparent()) {
                continue;
            }
            // Its blank cells, without a background of its own, show what is under it, like CSS, like the floating label
            // of a field of Bootstrap over the field; not those of the lists of controls, of dialogs nor of popovers,
            // on the background of the page in browsers
            // A positioned ::before or ::after, styled by the page, is clear too, like the labels of the cells of EasyAdmin
            $pseudo = null !== ($owner = $overlay->getParent()) && \in_array($overlay, $this->pseudoWidgets[$owner] ?? [], true);
            $raised = PositionMode::Relative === $this->resolveStyle($overlay)->getPosition()?->mode;
            // Taken out of the flow with a background of its own, opaque, like a dropdown menu of Bootstrap of the color of
            // the page; one raised in the flow still joins its borders to those beside it
            $clear = ($pseudo || null === $overlay->getStyle()?->getPosition()) && !$overlay->hasState('modal') && !$overlay->hasState('popover-open')
                && ($raised || !$this->paintsBackground($overlay));
            $seeThrough = $clear && null !== ($inherited = $this->getInheritedBackground($overlay)) ? substr($inherited->toBackgroundCode(), 2, -1) : null;
            // Under the flow inside a context in it: over that context's background, within its box
            $context = true === $beneath && null !== ($key['context'] ?? null) ? $key['context'] : null;
            $contextBox = null !== $context ? $this->boxRect($context) : null;
            $contextBackground = null !== $context ? $this->resolveStyle($context)->getBackground() : null;
            if ($backdrop instanceof BackdropTint) {
                $out = $backdrop->apply($out, $columns, $rows);
            } elseif ($backdrop) {
                // Dim after every style change, so that no reset or intensity change undoes it
                $out = array_map(static fn (string $line) => "\e[2m".(preg_replace('/\e\[([0-9;]*)m/', "\e[\$1;2m", $line) ?? $line), $out);
            }
            // A backdrop over every row, all its columns
            if ($backdrop) {
                foreach (array_keys($out) as $target) {
                    $touched[$target] = [0, \PHP_INT_MAX];
                }
            }
            $visible = min($width, $columns - $col);
            if ($visible <= 0 || $col < 0) {
                continue;
            }
            // A fixed one on the screen; the others in the frame, as tall as the screen or taller, like the content of a long
            // page after a fixed skip link, and what is placed in it
            $limit = PositionMode::Fixed === $overlayStyle->getPosition()?->mode ? $rows : max($rows, \count($frame));
            foreach ($lines as $i => $line) {
                $target = $row + $i;
                if ($target < 0 || $target >= $limit) {
                    continue;
                }
                // Cut to what is shown, unless as wide already, like most lines of a box: the slice would be the line
                if (AnsiUtils::visibleWidth($line) !== $visible) {
                    $line = AnsiUtils::sliceToWidth($line, 0, $visible);
                }
                while (\count($out) <= $target) {
                    $out[] = '';
                }
                $under = $out[$target];
                // Left in its lines: drawn as it is there, nothing when nothing was drawn over it since, like where no other
                // box was drawn on its row
                if (null !== $reference && 0 === $inPlace && (!isset($touched[$target]) || $touched[$target][1] <= $col || $touched[$target][0] >= $col + $visible)) {
                    continue;
                }
                if (null !== $reference && isset($reference[$target])) {
                    $line = AnsiUtils::sliceToWidth($reference[$target], $col, $visible);
                    if (AnsiUtils::sliceToWidth($under, $col, $visible) === $line) {
                        continue;
                    }
                }
                $drawnOn = isset($touched[$target]);
                // The columns drawn on in that row, from the first to the last: a column more on each side, where a wide
                // character cut is blanked, and those of the context it is drawn in
                $from = $col - 1;
                $to = $col + $visible + 1;
                if (\is_array($beneath)) {
                    $from = min($from, $beneath[4]);
                    $to = max($to, $beneath[4] + $beneath[5]);
                } elseif ($beneath && null !== $contextBox) {
                    $from = min($from, $contextBox->col);
                    $to = max($to, $contextBox->col + $contextBox->columns);
                }
                $touched[$target] = [min($touched[$target][0] ?? $from, $from), max($touched[$target][1] ?? 0, $to)];
                if (\is_array($beneath)) {
                    // Over what is drawn, under the content of its context: its blank cells and its background let it through
                    [, $context, $contextLines, $contextRow, $contextCol, $contextWidth] = $beneath;
                    $lineWidth = max($columns, AnsiUtils::visibleWidth($under));
                    $layers = [new Layer([$under], width: $lineWidth, height: 1), new Layer([$line], 0, $col)];
                    if (isset($contextLines[$target - $contextRow]) && $contextCol >= 0 && $contextCol < $columns) {
                        $contextLine = AnsiUtils::sliceToWidth($contextLines[$target - $contextRow], 0, min($contextWidth, $columns - $contextCol));
                        $background = $this->resolveStyle($context)->getBackground();
                        $layers[] = new Layer([null !== $background ? self::withoutBackground($contextLine, substr($background->toBackgroundCode(), 2, -1)) : $contextLine], 0, $contextCol, true);
                    }
                    $out[$target] = Compositor::composite(...$layers)[0];
                    continue;
                }
                if ($beneath) {
                    // What is drawn shows over it: its blank cells let it through, their text over its background; the
                    // background of the page is under it
                    $lineWidth = max($columns, AnsiUtils::visibleWidth($under));
                    $layers = [new Layer([null !== $canvas ? "\e[".$canvas.'m'.str_repeat(' ', $lineWidth)."\e[0m" : ''], width: $lineWidth, height: 1)];
                    // Inside a context in the flow: its background under it, the one of the flow taken away
                    if (null !== $contextBox && null !== $contextBackground && $target >= $contextBox->row && $target < $contextBox->row + $contextBox->rows) {
                        $code = substr($contextBackground->toBackgroundCode(), 2, -1);
                        $layers[] = new Layer(["\e[".$code.'m'.str_repeat(' ', $contextBox->columns)."\e[0m"], 0, $contextBox->col);
                        $under = AnsiUtils::sliceToWidth($under, 0, $contextBox->col)
                            .self::withoutBackground(AnsiUtils::sliceToWidth($under, $contextBox->col, $contextBox->columns), $code)
                            .AnsiUtils::sliceToWidth($under, $contextBox->col + $contextBox->columns, max(0, AnsiUtils::visibleWidth($under) - $contextBox->col - $contextBox->columns));
                    }
                    $layers[] = new Layer([$line], 0, $col);
                    $layers[] = new Layer([null !== $canvas ? self::withoutBackground($under, $canvas) : $under], transparent: true);
                    $out[$target] = Compositor::composite(...$layers)[0];
                    continue;
                }
                if (!$clear) {
                    // What is under it before and after, cut in one pass
                    [$left, $right] = AnsiUtils::splitAround($under, $col, $visible);
                    $out[$target] = $left."\e[0m".$line."\e[0m".($col + $visible < $columns ? $right : '');
                    continue;
                }
                // A blank row of it without any style lets what is under it show as it is, like the rows of a tall card
                if (self::drawsNothing($line)) {
                    continue;
                }
                // Raised in the flow over its own room, blank in one background, and nothing else drawn there: its line on
                // that background, not composited, like the thousands of rows of the content of a long page
                if ($raised && !$drawnOn && null !== $background = self::blankBackground(AnsiUtils::sliceToWidth($under, $col, $visible))) {
                    [$before, $after] = AnsiUtils::splitAround($under, $col, $visible);
                    $line = str_pad($line, \strlen($line) + max(0, $visible - AnsiUtils::visibleWidth($line)));
                    $out[$target] = $before."\e[0m".('' === $background ? $line : $background.AnsiUtils::reapplyBackgroundAfterResets($line, $background))."\e[0m".($col + $visible < $columns ? $after : '');
                    continue;
                }
                // Raised in the flow, like the active link of a pagination of Bootstrap: its lines join those under it
                if ($raised) {
                    $line = LayoutEngine::joinGlyphs($line, AnsiUtils::sliceToWidth($under, $col, $visible));
                }
                $lineWidth = max($columns, AnsiUtils::visibleWidth($under));
                $out[$target] = Compositor::composite(new Layer([$under], width: $lineWidth, height: 1), new Layer([$line], 0, $col, true, seeThrough: $seeThrough))[0];
            }
        }

        return new ArrayLineBuffer($out);
    }

    /**
     * The rows and columns an absolute widget is cut to by the boxes that clip what overflows them from its containing
     * block up, like CSS, not those between it and that block: their padding boxes, as the first row, column, and the
     * row and column past; null when none clips it, or for a fixed one, of the screen.
     *
     * @return array{int, int, int, int}|null
     */
    private function overlayClip(AbstractWidget $widget, Position $position): ?array
    {
        // Nor one in the top layer, like a modal dialog or a popover open
        if (PositionMode::Absolute !== $position->mode || $widget->hasState('modal') || $widget->hasState('popover-open')) {
            return null;
        }
        $clip = null;
        $block = false;
        for ($ancestor = $widget->getParent(); null !== $ancestor; $ancestor = $ancestor->getParent()) {
            $style = $this->resolveStyle($ancestor);
            // From its containing block up, a sticky one too: those between it and that block do not clip it
            $block = $block || self::isContainingBlock($style);
            if ($block && (null !== $style->getScroll() || $this->clipsOverflow($ancestor, true) || $this->clipsSideways($ancestor)) && null !== $box = $this->paddingBox($ancestor)) {
                $rect = [$box[0], $box[1], $box[0] + $box[3], $box[1] + $box[2]];
                $clip = null === $clip ? $rect : [max($clip[0], $rect[0]), max($clip[1], $rect[1]), min($clip[2], $rect[2]), min($clip[3], $rect[3])];
            }
            // Not past a fixed box, of the screen, nor one in the top layer, like a modal dialog
            if (PositionMode::Fixed === $style->getPosition()?->mode || $ancestor->hasState('modal') || $ancestor->hasState('popover-open')) {
                break;
            }
        }

        return $clip;
    }

    /**
     * Whether a box of that style is the containing block of the absolute elements in it: positioned, sticky too.
     */
    private static function isContainingBlock(Style $style): bool
    {
        return null !== $style->getPosition() || null !== $style->getStickyTop() || null !== $style->getStickyBottom();
    }

    /**
     * Cuts the rects of a widget and of what it holds to a clip for the pointer, see WidgetRect::withClip().
     *
     * @param array{int, int, int, int} $clip
     */
    private function clipPointer(AbstractWidget $widget, array $clip): void
    {
        if (null !== $rect = $this->positionTracker->getWidgetRect($widget)) {
            $this->positionTracker->setWidgetRect($widget, $rect->withClip($clip));
        }
        foreach ($widget instanceof ParentInterface ? $widget->all() : [] as $child) {
            // Not a fixed one, of the screen, nor one in the top layer
            if (PositionMode::Fixed !== $this->resolveStyle($child)->getPosition()?->mode && !$child->hasState('modal') && !$child->hasState('popover-open')) {
                $this->clipPointer($child, $clip);
            }
        }
    }

    /**
     * The lines of a box at a row and column cut to a rectangle, with where they start and their width then.
     *
     * @param list<string>              $lines
     * @param array{int, int, int, int} $clip  The first row, column, and the row and column past, see overlayClip()
     *
     * @return array{list<string>, int, int, int}
     */
    private static function clipLines(array $lines, int $row, int $col, int $width, array $clip): array
    {
        $from = max($clip[1], $col);
        $to = min($clip[3], $col + $width);
        if ($to <= $from) {
            return [[], $row, $col, $width];
        }
        $out = [];
        $first = null;
        foreach ($lines as $i => $line) {
            if ($row + $i >= $clip[0] && $row + $i < $clip[2]) {
                $first ??= $row + $i;
                $out[] = AnsiUtils::sliceToWidth($line, $from - $col, $to - $from);
            }
        }

        return [$out, $first ?? $row, $from, $to - $from];
    }

    /**
     * The key in the stacking order of an absolute or fixed widget shown in the root, of a z-index not negative; null
     * for another.
     *
     * @return array{path: list<int>, levels: list<array{int, int}>, under: bool|AbstractWidget, context?: AbstractWidget|null}|null
     */
    private function absoluteKey(AbstractWidget $overlay, ContainerWidget $root): ?array
    {
        $style = $this->resolveStyle($overlay);
        $position = $style->getPosition();
        // Nor one that draws nothing, invisible or transparent
        if (true !== $position?->isOutOfFlow() || ($position->zIndex ?? 0) < 0 || true === $style->getInvisible() || true === $style->getTransparent()) {
            return null;
        }
        for ($current = $overlay; $current !== $root; $current = $current->getParent()) {
            if (null === $current || $this->resolveStyle($current)->getHidden()) {
                return null;
            }
        }

        return $this->stackingKey($overlay, $root, $this->absoluteKeys['indexes']);
    }

    /**
     * The key of a widget in the stacking order; an absolute ::before before the children of its owner, an ::after
     * after them, like CSS, not in its list of children.
     *
     * @param array<int, array<int, int>> $indexes
     *
     * @return array{path: list<int>, levels: list<array{int, int}>, under: bool|AbstractWidget, context?: AbstractWidget|null}|null
     */
    private function stackingKey(AbstractWidget $widget, AbstractWidget $root, array &$indexes): ?array
    {
        for ($current = $widget; $current !== $root && null !== $parent = $current->getParent(); $current = $parent) {
            if (isset($this->pseudoWidgets[$parent])) {
                $children = $parent instanceof ParentInterface ? $parent->all() : [];
                if (!isset($indexes[$parentId = spl_object_id($parent)])) {
                    $indexes[$parentId] = [];
                    foreach ($children as $index => $child) {
                        $indexes[$parentId][spl_object_id($child)] = $index;
                    }
                }
                foreach ($this->pseudoWidgets[$parent] as $name => $pseudo) {
                    $indexes[$parentId][spl_object_id($pseudo)] ??= 'before' === $name ? -1 : \count($children);
                }
            }
        }

        return StackingOrder::key($widget, $root, $this->resolveStyle(...), $indexes);
    }

    /**
     * Whether a line of a see-through layer draws nothing: blanks, in colors of text only, no background, reverse
     * video, underline, strike or overline painting them.
     */
    private static function drawsNothing(string $line): bool
    {
        if ('' !== trim(preg_replace('/\e\[[0-9;:]*m/', '', $line) ?? $line)) {
            return false;
        }
        preg_match_all('/\e\[([0-9;:]*)m/', $line, $codes);
        foreach ($codes[1] as $code) {
            $parts = explode(';', $code);
            for ($i = 0, $count = \count($parts); $i < $count; ++$i) {
                $part = (int) explode(':', $parts[$i])[0];
                // The colors of text, with their 256 and RGB forms: no cell painted
                if (38 === $part || 58 === $part) {
                    $i += '5' === ($parts[$i + 1] ?? '') ? 2 : 4;
                    continue;
                }
                if (\in_array($part, [4, 7, 9, 21, 53], true) || ($part >= 40 && $part <= 49) || ($part >= 100 && $part <= 107)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * The background of a run of blank cells, as an SGR sequence, '' for none; null when it is not blank in one
     * background, like text, two backgrounds, or reverse video, underline or dim cells.
     */
    private static function blankBackground(string $cells): ?string
    {
        if ('' !== trim(preg_replace('/\e\[[0-9;:]*m/', '', $cells) ?? $cells)) {
            return null;
        }
        $background = '';
        preg_match_all('/\e\[([0-9;:]*)m/', $cells, $codes);
        foreach ($codes[1] as $code) {
            $parts = explode(';', $code);
            for ($i = 0, $count = \count($parts); $i < $count; ++$i) {
                $part = (int) $parts[$i];
                if (38 === $part || 48 === $part) {
                    $length = '5' === ($parts[$i + 1] ?? '') ? 3 : 5;
                    if (48 === $part) {
                        $sequence = "\e[".implode(';', \array_slice($parts, $i, $length)).'m';
                        if ('' !== $background && $sequence !== $background) {
                            return null;
                        }
                        $background = $sequence;
                    }
                    $i += $length - 1;
                    continue;
                }
                if (($part >= 40 && $part <= 47) || ($part >= 100 && $part <= 107)) {
                    $sequence = "\e[".$part.'m';
                    if ('' !== $background && $sequence !== $background) {
                        return null;
                    }
                    $background = $sequence;
                    continue;
                }
                // Resets and the colors of text, which blank cells do not show
                if (0 !== $part && 39 !== $part && 49 !== $part && !($part >= 30 && $part <= 37) && !($part >= 90 && $part <= 97)) {
                    return null;
                }
            }
        }

        return $background;
    }

    /**
     * Whether a widget is inside a box that clips, scrolls, filters, blends or fades what it holds: drawn over the
     * page, it would be out of it, so it stays in its lines.
     */
    private function isInsideEffect(AbstractWidget $widget, bool $heightClips = true, bool $widthClips = true): bool
    {
        for ($ancestor = $widget->getParent(); null !== $ancestor; $ancestor = $ancestor->getParent()) {
            $style = $this->resolveStyle($ancestor);
            // A height clips what is past it, overflow: hidden or not, when it is past it; with overflow: hidden, whatever
            // A percentage height only once it is a share of a definite one, as the layout resolves it; a box that clips
            // sideways keeps in what is moved sideways
            $height = null !== $style->getMaxRows() || (null !== $style->getMaxRowsPercent() && null !== ($this->blockRows[$ancestor] ?? (null !== ($parent = $ancestor->getParent()) ? $this->definiteRows[$parent] ?? null : null)));
            // Not moved past it, but laid out past it all the same, like items wrapped below a bar of a set height
            $pastHeight = $height && !$heightClips && null !== ($rect = $this->positionTracker->getWidgetRect($widget)) && null !== ($box = $this->paddingBox($ancestor)) && $rect->row + $rect->rows > $box[0] + $box[3];
            if (null !== $style->getScroll() || ($height && ($heightClips || $pastHeight || $this->clipsOverflow($ancestor, true))) || $style->getFilter() instanceof Filter || null !== $style->getBlend() || true === $style->getDim() || true === $style->getTransparent() || ($widthClips && $this->clipsSideways($ancestor))) {
                return true;
            }
        }

        return false;
    }

    /**
     * The SGR parameters of the background of the page: of the root, or of the first boxes under it, like
     * <html> and <body>; null when it has none.
     */
    private function canvasBackground(ContainerWidget $root): ?string
    {
        $widget = $root;
        for ($depth = 0; $depth < 4 && null !== $widget; ++$depth) {
            if (null !== $background = $this->resolveStyle($widget)->getBackground()) {
                return substr($background->toBackgroundCode(), 2, -1);
            }
            // Down to <html> and <body>, which pass the height of the screen: not the first box of the page
            $child = $widget instanceof ParentInterface ? ($widget->all()[0] ?? null) : null;
            $widget = $child instanceof ContainerWidget && $child->passesDefiniteHeight() ? $child : null;
        }

        return null;
    }

    /**
     * The line with the cells of that background as cells without one.
     */
    private static function withoutBackground(string $line, string $background): string
    {
        if (!str_contains($line, "\e[")) {
            return $line;
        }
        $target = array_map(intval(...), explode(';', $background));

        // Read by parameter, a color of several, of semicolons or of colons with its color space id or not, as one
        return preg_replace_callback('/\e\[([0-9;:]*)m/', static function (array $m) use ($target): string {
            $params = '' === $m[1] ? [] : explode(';', $m[1]);
            $out = [];
            for ($i = 0, $count = \count($params); $i < $count; ++$i) {
                if (str_contains($params[$i], ':')) {
                    $sub = array_map(intval(...), explode(':', $params[$i]));
                    $color = 2 === ($sub[1] ?? null) ? [48, 2, ...\array_slice($sub, \count($sub) >= 6 ? 3 : 2, 3)] : [48, $sub[1] ?? null, $sub[2] ?? null];
                    $out[] = 48 === $sub[0] && $color === $target ? '49' : $params[$i];
                    continue;
                }
                $code = (int) $params[$i];
                if (\in_array($code, [38, 48, 58], true) && isset($params[$i + 1])) {
                    $length = match ((int) $params[$i + 1]) {
                        5 => 3,
                        2 => 5,
                        default => 1,
                    };
                    $group = \array_slice($params, $i, $length);
                    $out[] = array_map(intval(...), $group) === $target ? '49' : implode(';', $group);
                    $i += $length - 1;
                    continue;
                }
                $out[] = [$code] === $target ? '49' : $params[$i];
            }

            return "\e[".implode(';', $out).'m';
        }, $line) ?? $line;
    }

    /**
     * Draws the outlines of the widgets in the flow over the frame, like CSS outline: around their boxes, over what is
     * next to them, cut by the screen.
     */
    private function drawOutlines(LineBufferInterface $frame, int $columns, int $rows, ContainerWidget $root): LineBufferInterface
    {
        $out = null;
        foreach ($this->outlines as $widget => $_) {
            // Shown, in the tree, and in the flow: out of it, an overlay is drawn over the outlines
            for ($current = $widget; $current !== $root; $current = $current->getParent()) {
                if (null === $current || $this->resolveStyle($current)->getHidden() || $this->resolveStyle($current)->getPosition()?->isOutOfFlow()) {
                    continue 2;
                }
            }
            $outline = $this->resolveStyle($widget)->getOutline();
            if (!$outline instanceof Outline || null === $box = $this->boxRect($widget)) {
                continue;
            }

            $out ??= $frame->toArray();
            // The box without its margins above and below
            $margin = $this->resolveStyle($widget)->withResolvedBox($box->columns)->getMargin();
            $boxTop = $box->row + ($margin->top ?? 0);
            $boxRows = $box->rows - ($margin->top ?? 0) - ($margin->bottom ?? 0);
            // Around the border box: not the room its shadow takes on the right and below
            $shadow = $this->resolveStyle($widget)->getShadow();
            [$shadowColumns, $shadowRows] = null !== $shadow && !$shadow->inset ? $shadow->extents() : [0, 0];
            // A ring spread inside a box too short to hold it, like a field or a button of a row: around it
            $pattern = $outline->pattern;
            $offset = $outline->offset;
            if (BorderPattern::RING_INSET === $pattern && $boxRows < 3) {
                [$pattern, $offset] = [BorderPattern::RING, 0];
            }
            $distance = $offset + 1;
            $top = $boxTop - $distance;
            $left = $box->col - $distance;
            $width = $box->columns - $shadowColumns + 2 * $distance;
            $height = $boxRows - $shadowRows + 2 * $distance;
            if ($width < 2 || $height < 2) {
                continue;
            }
            $chars = BorderPattern::fromName($pattern)->getChars();
            $style = new Style(color: $outline->color ?? $this->resolveStyle($widget)->getColor());
            // A ring of a box-shadow is only drawn over what is blank: the text and the lines around it stay readable
            $ring = \in_array($outline->pattern, [BorderPattern::RING, BorderPattern::RING_THICK, BorderPattern::RING_INSET], true);
            $draw = static function (int $row, int $col, string $text) use (&$out, $style, $columns, $rows, $ring, &$draw): void {
                if ($ring && mb_strlen($text) > 1) {
                    // A line of a ring over a row with text there, like the feedback under a field or the code under
                    // an active tab: after the text only, not between its words, and its corners when they are not
                    // the line, like ▗ and ▖ beside a label
                    $chars = mb_str_split($text);
                    $start = max(0, $col);
                    $under = isset($out[$row]) ? rtrim(AnsiUtils::stripAnsiCodes(AnsiUtils::sliceToWidth($out[$row], $start, max(0, \count($chars) + min(0, $col))))) : '';
                    if ('' !== trim($under)) {
                        $end = $start - $col + AnsiUtils::visibleWidth($under);
                        $last = \count($chars) - 1;
                        foreach ($chars as $i => $char) {
                            if ($i >= $end || ((0 === $i || $last === $i) && $char !== $chars[min($last, 1)])) {
                                $draw($row, $col + $i, $char);
                            }
                        }

                        return;
                    }
                    foreach ($chars as $i => $char) {
                        $draw($row, $col + $i, $char);
                    }

                    return;
                }
                if ($ring && isset($out[$row]) && '' !== trim(AnsiUtils::stripAnsiCodes(AnsiUtils::sliceToWidth($out[$row], max(0, $col), 1)))) {
                    return;
                }
                // Cut by the screen
                if ($row < 0 || $row >= $rows) {
                    return;
                }
                if ($col < 0) {
                    $text = mb_substr($text, -$col);
                    $col = 0;
                }
                $text = mb_substr($text, 0, max(0, $columns - $col));
                if ('' === $text) {
                    return;
                }
                while (\count($out) <= $row) {
                    $out[] = '';
                }
                $under = $out[$row];
                $length = mb_strlen($text);
                // The slice is padded to the column already when the row is shorter
                [$left, $right] = AnsiUtils::splitAround($under, $col, $length);
                $out[$row] = $left."\e[0m".$style->apply($text)."\e[0m".$right;
            };
            $line = static fn (int $row, int $column) => '' !== $chars[$row][$column] ? $chars[$row][$column] : ' ';
            $draw($top, $left, $line(0, 0).str_repeat($line(0, 1), $width - 2).$line(0, 2));
            for ($row = $top + 1; $row < $top + $height - 1; ++$row) {
                $draw($row, $left, $line(1, 0));
                $draw($row, $left + $width - 1, $line(1, 2));
            }
            $draw($top + $height - 1, $left, $line(2, 0).str_repeat($line(2, 1), $width - 2).$line(2, 2));
        }

        return null === $out ? $frame : new ArrayLineBuffer($out);
    }

    /**
     * @internal see Tui::setImplicitAnchor()
     */
    public function setImplicitAnchor(AbstractWidget $widget, ?AbstractWidget $anchor): void
    {
        if (null === $anchor) {
            unset($this->implicitAnchors[$widget]);
        } else {
            $this->implicitAnchors[$widget] = \WeakReference::create($anchor);
        }
    }

    /**
     * Gives the absolute and fixed ::before and ::after of a widget widgets of their own, under it, drawn over the
     * frame like the absolute widgets: the widget is their containing block when it is positioned.
     */
    private function syncPseudoElements(AbstractWidget $widget): void
    {
        $pseudos = [];
        foreach ($this->styleSheet->getProviders() as $provider) {
            if ($provider instanceof PseudoElementProviderInterface) {
                $pseudos += $provider->getPositionedPseudoElements($widget);
            }
        }
        $existing = $this->pseudoWidgets[$widget] ?? [];
        if (!$pseudos && !$existing) {
            return;
        }
        $kept = [];
        foreach ($pseudos as $name => [$text, $style]) {
            $pseudo = $existing[$name] ?? new TextWidget($text);
            if ($pseudo->getText() !== $text) {
                $pseudo->setText($text);
            }
            if (null !== ($context = $widget->getContext()) && $pseudo->getParent() !== $widget) {
                $pseudo->attach($widget, $context);
            }
            $pseudo->setStyle($style);
            $this->overlays[$pseudo] = true;
            $kept[$name] = $pseudo;
        }
        foreach ($existing as $name => $pseudo) {
            if (!isset($kept[$name])) {
                unset($this->overlays[$pseudo]);
            }
        }
        if ($kept) {
            $this->pseudoWidgets[$widget] = $kept;
        } else {
            unset($this->pseudoWidgets[$widget]);
        }
    }

    /**
     * Where a widget was drawn, its margins included, as wide as its box: a width narrower than the row it is in
     * leaves the rest of the row out, like the size of a container.
     */
    private function layoutRect(AbstractWidget $widget): ?WidgetRect
    {
        if (null === $rect = $this->positionTracker->getWidgetRect($widget)) {
            return null;
        }
        if (null === [$offset, $width] = $this->boxes[$widget] ?? null) {
            return $rect;
        }
        $right = $this->resolveStyle($widget)->getMargin()->right ?? 0;

        return new WidgetRect($rect->row, $rect->col, max(0, min($rect->columns, $offset + $width + $right)), $rect->rows);
    }

    /**
     * The box of a widget, past its margins and at its width, where it was drawn.
     */
    private function boxRect(AbstractWidget $widget): ?WidgetRect
    {
        if (null === $rect = $this->positionTracker->getWidgetRect($widget)) {
            return null;
        }
        [$offset, $width] = $this->boxes[$widget] ?? [0, $rect->columns];

        return new WidgetRect($rect->row, $rect->col + $offset, min($width, max(1, $rect->columns - $offset)), $rect->rows);
    }

    /**
     * The rect of the widget an anchor name names, like anchor-name: the first one drawn that the positioned widget
     * sees, the names an anchor-scope keeps inside an element hidden from what is outside it.
     */
    private function anchorRect(AbstractWidget $widget, ?string $name, AbstractWidget $positioned): ?WidgetRect
    {
        // Its box, not the slot it was given
        return null !== ($anchor = $this->findAnchor($widget, $name, $positioned, true)) ? $this->boxRect($anchor) : null;
    }

    /**
     * The widget an anchor name names that the positioned widget sees: the first one, or the first one drawn.
     */
    private function findAnchor(AbstractWidget $widget, ?string $name, AbstractWidget $positioned, bool $drawn): ?AbstractWidget
    {
        if (null === $name) {
            return null;
        }
        // The widgets with an anchor name, found once for the frame: each positioned widget asks for its anchor
        if (null === $this->anchorIndex || $this->anchorIndex[0] !== $widget) {
            $index = [];
            $walk = function (AbstractWidget $node, int $depth) use (&$walk, &$index): void {
                if ($depth > 64) {
                    return;
                }
                if (null !== $anchorName = $this->resolveStyle($node)->getAnchorName()) {
                    $index[$anchorName][] = [$node, $depth];
                }
                foreach ($node instanceof ParentInterface ? $node->all() : [] as $child) {
                    $walk($child, $depth + 1);
                }
            };
            $walk($widget, 0);
            $this->anchorIndex = [$widget, $index];
        }

        // The first in document order that no anchor-scope around it hides from the positioned widget
        foreach ($this->anchorIndex[1][$name] ?? [] as [$anchor]) {
            if ($drawn && null === $this->boxRect($anchor)) {
                continue;
            }
            for ($node = $anchor; null !== $node; $node = $node === $widget ? null : $node->getParent()) {
                $scope = $this->resolveStyle($node)->getAnchorScope();
                if (null !== $scope && (\in_array('all', $scope, true) || \in_array($name, $scope, true)) && !self::contains($node, $positioned)) {
                    continue 2;
                }
            }

            return $anchor;
        }

        return null;
    }

    /**
     * Whether a widget is another or one of its descendants.
     */
    private static function contains(AbstractWidget $ancestor, AbstractWidget $widget): bool
    {
        for ($node = $widget; null !== $node; $node = $node->getParent()) {
            if ($node === $ancestor) {
                return true;
            }
        }

        return false;
    }

    /**
     * The row and column of an element next to its anchor: by position-area, or anchor() in its insets; on the other
     * side when it would leave the screen and position-try-fallbacks says to flip; kept on the screen.
     *
     * @return array{int, int, bool} The row and column, and whether it fits before it is kept on the screen
     */
    private static function anchored(Position $position, WidgetRect $anchor, int $width, int $height, int $row, int $col, int $columns, int $rows, ?\Closure $measure = null, ?Position &$chosen = null): array
    {
        $chosen = null;
        [$placedRow, $placedCol] = self::placeNextTo($position, $anchor, $width, $height, $row, $col, $columns, $rows);
        $fits = static fn (int $r, int $c, int $w = -1, int $h = -1): bool => $r >= 0 && $c >= 0 && $r + (-1 === $h ? $height : $h) <= $rows && $c + (-1 === $w ? $width : $w) <= $columns;
        // The other places position-try-fallbacks lists, in order: the first that fits, else the element's own
        if (!$fits($placedRow, $placedCol)) {
            $tries = $position->tries;
            // position-try-order: the tries leaving the most room next to the anchor first, in order between equals
            if ('normal' !== $position->tryOrder) {
                $axis = 'most-width' === $position->tryOrder ? 0 : 1;
                usort($tries, static fn (Position $a, Position $b) => self::roomNextTo($b, $anchor, $columns, $rows)[$axis] <=> self::roomNextTo($a, $anchor, $columns, $rows)[$axis]);
            }
            foreach ($tries as $try) {
                // A try of its own size, like width: anchor-size(width): measured at it
                [$tryWidth, $tryHeight] = [] !== $try->anchorSizes && null !== $measure ? $measure($try) : [$width, $height];
                [$tryRow, $tryCol] = self::placeNextTo($try, $anchor, $tryWidth, $tryHeight, $row, $col, $columns, $rows);
                if ($fits($tryRow, $tryCol, $tryWidth, $tryHeight)) {
                    [$placedRow, $placedCol, $width, $height] = [$tryRow, $tryCol, $tryWidth, $tryHeight];
                    $chosen = $try;
                    break;
                }
            }
        }

        return [max(0, min($placedRow, $rows - $height)), max(0, min($placedCol, $columns - $width)), $fits($placedRow, $placedCol, $width, $height)];
    }

    /**
     * The columns and the rows of the screen a place next to an anchor has, like the containing block position-area
     * makes: the side of the anchor it is on, or from the edge an anchor() inset sets.
     *
     * @return array{int, int}
     */
    private static function roomNextTo(Position $position, WidgetRect $anchor, int $columns, int $rows): array
    {
        $room = [$columns, $rows];
        if (null !== $area = $position->area) {
            $room[1] = match ($area[0]) {
                'top' => $anchor->row,
                'bottom' => $rows - $anchor->row - $anchor->rows,
                'span-top' => $anchor->row + $anchor->rows,
                'span-bottom' => $rows - $anchor->row,
                'span-all' => $rows,
                default => $anchor->rows,
            };
            $room[0] = match ($area[1]) {
                'left' => $anchor->col,
                'right' => $columns - $anchor->col - $anchor->columns,
                'span-left' => $anchor->col + $anchor->columns,
                'span-right' => $columns - $anchor->col,
                'span-all' => $columns,
                default => $anchor->columns,
            };
        }
        $edge = static fn (string $side, bool $vertical): int => match ($side) {
            'bottom' => $anchor->row + $anchor->rows,
            'right' => $anchor->col + $anchor->columns,
            'center' => $vertical ? $anchor->row + intdiv($anchor->rows, 2) : $anchor->col + intdiv($anchor->columns, 2),
            default => $vertical ? $anchor->row : $anchor->col,
        };
        foreach ($position->anchorEdges as $side => [, $at]) {
            match ($side) {
                'top' => $room[1] = $rows - $edge($at, true),
                'bottom' => $room[1] = $edge($at, true),
                'left' => $room[0] = $columns - $edge($at, false),
                'right' => $room[0] = $edge($at, false),
            };
        }

        return [max(0, $room[0]), max(0, $room[1])];
    }

    /**
     * The row and column of an element next to its anchor, by its position-area or the anchor() of its insets, flipped
     * to the other side when it would leave the screen and its flips say so; not kept on the screen.
     *
     * @return array{int, int}
     */
    private static function placeNextTo(Position $position, WidgetRect $anchor, int $width, int $height, int $row, int $col, int $columns, int $rows): array
    {
        $edgeRow = static fn (string $edge): int => match ($edge) {
            'bottom' => $anchor->row + $anchor->rows,
            'center' => $anchor->row + intdiv($anchor->rows, 2),
            default => $anchor->row,
        };
        $edgeCol = static fn (string $edge): int => match ($edge) {
            'right' => $anchor->col + $anchor->columns,
            'center' => $anchor->col + intdiv($anchor->columns, 2),
            default => $anchor->col,
        };
        $blockSide = null;
        $inlineSide = null;
        if (null !== $area = $position->area) {
            [$block, $inline] = $area;
            $blockSide = $block;
            $inlineSide = $inline;
            $row = match ($block) {
                'top' => $anchor->row - $height,
                'bottom' => $anchor->row + $anchor->rows,
                'span-top' => $anchor->row + $anchor->rows - $height,
                default => $anchor->row,
            };
            $col = match ($inline) {
                'left' => $anchor->col - $width,
                'right' => $anchor->col + $anchor->columns,
                'center' => $anchor->col + intdiv($anchor->columns - $width, 2),
                'span-left' => $anchor->col + $anchor->columns - $width,
                default => $anchor->col,
            };
        }
        foreach ($position->anchorEdges as $side => [, $edge]) {
            match ($side) {
                'top' => $row = $edgeRow($edge),
                'bottom' => $row = $edgeRow($edge) - $height,
                'left' => $col = $edgeCol($edge),
                'right' => $col = $edgeCol($edge) - $width,
            };
            $blockSide ??= 'top' === $side ? 'bottom' : ('bottom' === $side ? 'top' : null);
            $inlineSide ??= 'left' === $side ? 'right' : ('right' === $side ? 'left' : null);
        }
        // justify-self and align-self: anchor-center, centered on the anchor
        if ($position->anchorCenter[0]) {
            $col = $anchor->col + intdiv($anchor->columns - $width, 2);
        }
        if ($position->anchorCenter[1]) {
            $row = $anchor->row + intdiv($anchor->rows - $height, 2);
        }

        // Flipped to the other side of the anchor when it would not fit on the screen
        if (\in_array('flip-block', $position->flips, true)) {
            if ('bottom' === $blockSide && $row + $height > $rows) {
                $row = $anchor->row - $height;
            } elseif ('top' === $blockSide && $row < 0) {
                $row = $anchor->row + $anchor->rows;
            }
        }
        if (\in_array('flip-inline', $position->flips, true)) {
            if ('right' === $inlineSide && $col + $width > $columns) {
                $col = $anchor->col - $width;
            } elseif ('left' === $inlineSide && $col < 0) {
                $col = $anchor->col + $anchor->columns;
            }
        }

        return [$row, $col];
    }

    /**
     * Where an absolute widget would be in the flow of its parent: under the box before it, at the start of the parent's
     * content, like the static position of CSS; none when its parent was not drawn.
     *
     * @return array{int, int}|null
     */
    private function staticPlace(AbstractWidget $widget): ?array
    {
        $parent = $widget->getParent();
        if (!$parent instanceof ParentInterface || null === $rect = $this->positionTracker->getWidgetRect($parent)) {
            return null;
        }
        $style = $this->resolveStyle($parent);
        $row = $rect->row + ($style->getMargin()->top ?? 0) + ($style->getBorder()?->top ?? 0) + ($style->getPadding()->top ?? 0);
        $col = $rect->col + ($style->getMargin()->left ?? 0) + ($style->getBorder()?->left ?? 0) + ($style->getPadding()->left ?? 0);
        // In a flow of blocks, under the last one before it that was drawn; in a row or a grid, at the start, like CSS
        if (null === $style->getDirection() && null === $style->getGridTemplate()) {
            foreach ($parent->all() as $child) {
                if ($child === $widget) {
                    break;
                }
                $childStyle = $this->resolveStyle($child);
                if (!$childStyle->getHidden() && !$childStyle->getPosition()?->isOutOfFlow() && null !== $childRect = $this->positionTracker->getWidgetRect($child)) {
                    $row = $childRect->row + $childRect->rows;
                }
            }
        }

        return [$row, $col];
    }

    /**
     * The padding box of a widget where it is drawn, inside its margin and its border, as its first row, column, its
     * columns and rows; null when it is not drawn.
     *
     * @return array{int, int, int, int}|null
     */
    private function paddingBox(AbstractWidget $widget): ?array
    {
        if (null === $rect = $this->positionTracker->getWidgetRect($widget)) {
            return null;
        }
        $style = $this->resolveStyle($widget);
        $border = $style->getBorder();
        $margin = $style->getMargin();
        $top = ($border->top ?? 0) + max(0, $margin->top ?? 0);
        $left = ($border->left ?? 0) + max(0, $margin->left ?? 0);
        $right = ($border->right ?? 0) + max(0, $margin->right ?? 0);
        $bottom = ($border->bottom ?? 0) + max(0, $margin->bottom ?? 0);
        // At the width of its box, narrower than the row it is in, like a tab of width: 20ch whose caret is at left: 50%
        // of it; not of a box that draws nothing, like that of an image with no text to draw of Astro, which a link over
        // it fills
        // Its margin past its side none, like that above, and only a box that fits where it was drawn
        if ($rect->rows > 0 && null !== ([$offset, $width] = $this->boxes[$widget] ?? null) && max(0, $offset) + $width <= $rect->columns) {
            $left = max(0, $offset) + ($border->left ?? 0);
            $right = max(0, $rect->columns - max(0, $offset) - $width) + ($border->right ?? 0);
        }

        return [$rect->row + $top, $rect->col + $left, $rect->columns - $left - $right, $rect->rows - $top - $bottom];
    }

    /**
     * The padding box of the closest positioned ancestor, or the screen: its row, column, columns and rows.
     *
     * @return array{int, int, int, int}
     */
    private function containingBlock(AbstractWidget $widget, Position $position, int $columns, int $rows): array
    {
        if (PositionMode::Fixed !== $position->mode) {
            for ($ancestor = $widget->getParent(); null !== $ancestor; $ancestor = $ancestor->getParent()) {
                $style = $this->resolveStyle($ancestor);
                if (self::isContainingBlock($style) && null !== $box = $this->paddingBox($ancestor)) {
                    return [$box[0], $box[1], max(1, min($columns, $box[2])), max(1, min($rows, $box[3]))];
                }
                // One that draws no rows, like an empty <div class="position-relative">: where it would be in the flow of its
                // parent, as wide as the content of that parent
                if (null !== $style->getPosition() && !$style->getPosition()->isOutOfFlow() && !$style->getHidden() && null !== ($place = $this->staticPlace($ancestor))
                    && null !== ($parentRect = $this->positionTracker->getWidgetRect($ancestor->getParent()))) {
                    $parentStyle = $this->resolveStyle($ancestor->getParent());
                    $end = $parentRect->col + $parentRect->columns - ($parentStyle->getMargin()->right ?? 0) - ($parentStyle->getBorder()?->right ?? 0) - ($parentStyle->getPadding()->right ?? 0);

                    return [$place[0], $place[1], max(1, min($columns, $end - $place[1])), 1];
                }
            }
        }

        return [0, 0, $columns, $rows];
    }

    public function renderWidgetLines(AbstractWidget $widget, RenderContext $context): LineBufferInterface
    {
        return $this->recordCacheHit($widget, $this->doRenderWidgetLines($widget, $context, null, $hit), $hit, $context);
    }

    public function renderStretchedLines(AbstractWidget $widget, RenderContext $context, int $rows): LineBufferInterface
    {
        return $this->recordCacheHit($widget, $this->doRenderWidgetLines($widget, $context, $rows, $hit), $hit, $context);
    }

    /**
     * Records whether the render just made of a widget came from its cache, once it is made, see wasRenderCached(): not
     * a render of it inside it, like a measure.
     */
    private function recordCacheHit(AbstractWidget $widget, LineBufferInterface $lines, bool $hit, RenderContext $context): LineBufferInterface
    {
        if (\count($this->flowOverflow) > 0) {
            $paints = $this->flowPaints[$widget] ?? [];
            $key = $context->getColumns().':'.\count($lines);
            unset($paints[$key]);
            $paints[$key] = $lines;
            if (\count($paints) > 4) {
                unset($paints[array_key_first($paints)]);
            }
            $this->flowPaints[$widget] = $paints;
        }
        if ($hit) {
            $this->cachedRenders[$widget] = true;
        } else {
            unset($this->cachedRenders[$widget]);
        }

        return $lines;
    }

    private function doRenderWidgetLines(AbstractWidget $widget, RenderContext $context, ?int $stretchRows, ?bool &$hit = null): LineBufferInterface
    {
        $hit = false;
        $widget->beforeRender();
        // Beside the floats of a layout that is gone no more, see TextWidget::dropStaleFloats()
        if ($widget instanceof TextWidget || $widget instanceof ContainerWidget) {
            $widget->dropStaleFloats();
        }

        $resolvedStyle = $this->resolveStyle($widget);
        // Percentage padding and margins are shares of the width of the containing block
        $containing = $context->getContainingColumns();
        $boxPercents = true === $resolvedStyle->getPadding()?->hasPercents() || true === $resolvedStyle->getMargin()?->hasPercents();
        if ($boxPercents) {
            $resolvedStyle = $resolvedStyle->withResolvedBox($containing);
        }
        // A block that fits in a box laid out wider, for a child cut at its side or scrolled to, is laid out in the box:
        // its auto margins center it there, not in the wider layout, like CSS; its render cached at the width of the box
        if (isset($this->sidewaysCaps[$widget]) && $context->getColumns() > $this->sidewaysCaps[$widget]) {
            $context = $context->withColumns($this->sidewaysCaps[$widget]);
        }

        // A percentage height depends on the parent's height, when it is definite
        $definite = null;
        // So does the text written vertically, whose lines are as long as the height of the parent
        if ((null !== $resolvedStyle->getMinRowsPercent() || null !== $resolvedStyle->getMaxRowsPercent() || null !== $resolvedStyle->getWritingMode()) && null !== $parent = $widget->getParent()) {
            // Of its containing block when it is placed out of the flow, like a modal of Pico of min-height: 100%
            $definite = $this->blockRows[$widget] ?? $this->definiteRows[$parent] ?? null;
        }

        $cacheColumns = $context->getColumns();
        // A stretched render is cached apart from the natural one, and a percentage one by the height it is a share of
        $cacheRows = null !== $stretchRows ? -1 - $stretchRows : $context->getRows();
        if (null !== $definite) {
            $cacheRows = $cacheRows * 20_011 + $definite + 1;
        }
        // In a row, two containing blocks can give the same share: the render of each is its own, as is that of a width
        // that is a share of its containing block, not of an item of a row, which the row gives its share, see below
        $sharedWidth = null !== $resolvedStyle->getWidthPercent() && !(($parent = $widget->getParent()) instanceof ContainerWidget && Direction::Horizontal === $this->resolveStyle($parent)->getDirection());
        // The block the box is sized from: the one around, or the box that clips it, see above
        $sizedFrom = $boxPercents ? $containing : ($sharedWidth ? $context->getContainingColumns() : $cacheColumns);
        if ($sizedFrom !== $cacheColumns) {
            $cacheRows = $cacheRows * 30_011 - $sizedFrom - 1;
        }
        $cached = $widget->getRenderCache($cacheColumns, $cacheRows);
        // Under its own key, which the sizes it is a share of change, not only that of its columns and rows
        $hit = null !== $cached;
        // The floats going on past it of the render cached, kept with its lines, or none until its render gives them,
        // see ContainerWidget::setEscapedFloats()
        if (null !== $cached) {
            if ($widget instanceof ContainerWidget) {
                $widget->setEscapedFloats($widget->getRenderCacheExtra($cacheColumns, $cacheRows));
            }

            return $cached;
        }
        if ($widget instanceof ContainerWidget) {
            $widget->setEscapedFloats([]);
        }

        if ($resolvedStyle->getHidden()) {
            $lines = new ArrayLineBuffer([]);
            $widget->setRenderCache($lines, $cacheColumns, $cacheRows);

            return $lines;
        }

        // max-width and percentage widths size the box: the margins come on top, like in CSS
        $allotted = $context->getColumns();
        $margin = $resolvedStyle->getMargin();
        $marginColumns = null !== $margin ? $margin->left + $margin->right : 0;
        $box = null;
        // A percentage of the container; in a row, the layout already gave the widget its share
        if ($sharedWidth) {
            // Of the containing block, not of the room the layout gave it, like a bubble of max-width: 70% placed at its
            // content width in a flex column
            $box = min($allotted, (int) $resolvedStyle->resolveWidthPercent($context->getContainingColumns()));
        }
        $maxColumns = $resolvedStyle->getMaxColumns();
        // An item of a row whose width only starts it there, like a flex item of flex: 1, takes what the row gives it
        if (null !== $maxColumns && $maxColumns !== $resolvedStyle->getRowMaxColumns() && ($row = $widget->getParent()) instanceof ContainerWidget
            && Direction::Horizontal === $this->resolveStyle($row)->getDirection()) {
            $maxColumns = $resolvedStyle->getRowMaxColumns();
        }
        if (null !== $maxColumns) {
            $box = min($box ?? $maxColumns, $maxColumns);
        }
        // width: fit-content or min-content, the box as wide as its content, or as narrow as it can be
        // A text block written vertically is as wide as its columns already
        $contentWidth = $resolvedStyle->getContentWidth();
        // An image without a width: as wide as it is drawn, like the inline images of browsers, a border around it
        if ($widget instanceof ReplacedElementInterface && null === $contentWidth && null === $box && null === $resolvedStyle->getMinColumns()) {
            $contentWidth = 'fit-content';
        }
        if (null !== $contentWidth && $allotted > $marginColumns && !($widget instanceof TextWidget && null !== $resolvedStyle->getWritingMode())) {
            $room = $allotted - $marginColumns;
            $content = 'min-content' === $contentWidth
                ? $this->computeMinContentWidth($widget, $room, $context->getRows(), 0) - $marginColumns
                // Like CSS fit-content: the max-content width, measured without wrapping, within the room; the
                // measure is of the margin box
                : min($room, $this->measureIntrinsicWidth($widget, 4096, $context->getRows()) - $marginColumns);
            $box = min($box ?? $room, max(1, $content));
        }
        // field-sizing: content, an input as wide as its value, its border and padding, within its width
        if ($widget instanceof InputWidget && $widget->isContentSized()) {
            [$inner] = $this->chromeApplier->computeInnerDimensions(max(1, $allotted - $marginColumns), $context->getRows(), $resolvedStyle);
            $content = $widget->getContentColumns() + max(0, $allotted - $marginColumns - $inner);
            $box = min($box ?? $content, $content);
        }
        if (null !== $box && null !== $minColumns = $resolvedStyle->getMinColumns()) {
            $box = max($box, $minColumns);
        }
        // min-width as a percentage, like 100%: a box sized by its content is at least that share of the room
        if (null !== $box && null !== $minPercent = $resolvedStyle->getMinWidthPercent()) {
            $box = max($box, min($allotted - $marginColumns, (int) floor(($allotted - $marginColumns) * $minPercent / 100)));
        }
        if (null !== $box && $allotted > $box + $marginColumns) {
            $context = $context->withColumns($box + $marginColumns);
        }

        // Auto margins take the columns a narrower block does not use, like "margin: 0 auto"
        if (null !== ($autoMargin = $resolvedStyle->getAutoMargin()) && 0 < $free = $allotted - $context->getColumns()) {
            $left = match ($autoMargin) {
                AutoMargin::Left => $free,
                AutoMargin::Right => 0,
                AutoMargin::Both => intdiv($free, 2),
            };
            $margin = $resolvedStyle->getMargin() ?? new Padding(0, 0, 0, 0);
            $resolvedStyle = $resolvedStyle->withMargin(new Padding($margin->top, $margin->right + $free - $left, $margin->bottom, $margin->left + $left));
            $context = $context->withColumns($allotted);
        }

        // The box inside its margins, for what is placed next to it (anchor positioning)
        $boxMargin = $resolvedStyle->getMargin();
        $this->boxes[$widget] = [$boxMargin->left ?? 0, max(1, $context->getColumns() - ($boxMargin->left ?? 0) - ($boxMargin->right ?? 0))];
        if ($resolvedStyle->getOutline() instanceof Outline) {
            $this->outlines[$widget] = true;
        } else {
            unset($this->outlines[$widget]);
        }
        $this->syncPseudoElements($widget);

        if (null !== $definite) {
            // The share counts the border and the padding, like box-sizing: border-box
            // Plus the rows of a calc(), like calc(100% - 2rem)
            $contentRows = fn (?int $percent, int $offset) => null === $percent ? null : $this->chromeApplier->computeInnerDimensions($context->getColumns(), max(0, intdiv($definite * $percent, 100) + $offset), $resolvedStyle)[1];
            [$minOffset, $maxOffset] = $resolvedStyle->getRowsPercentOffsets();
            $min = $contentRows($resolvedStyle->getMinRowsPercent(), $minOffset);
            $max = $contentRows($resolvedStyle->getMaxRowsPercent(), $maxOffset);
            $resolvedStyle = $resolvedStyle->withRows(
                null === $min ? $resolvedStyle->getMinRows() : max($min, $resolvedStyle->getMinRows() ?? 0),
                null === $max ? $resolvedStyle->getMaxRows() : min($max, $resolvedStyle->getMaxRows() ?? \PHP_INT_MAX),
            );
        }

        // aspect-ratio: without a height, the box is as tall as its width says, a row being two columns high
        $fixedRows = null !== $resolvedStyle->getMinRows() && $resolvedStyle->getMinRows() === $resolvedStyle->getMaxRows() ? $resolvedStyle->getMinRows() : null;
        if (null !== ($ratio = $resolvedStyle->getAspectRatio()) && null === $fixedRows) {
            [$contentColumns] = $this->chromeApplier->computeInnerDimensions($context->getColumns(), $context->getRows(), $resolvedStyle);
            $fixedRows = max(1, min(1000, (int) round($contentColumns / $ratio / 2)));
            $resolvedStyle = $resolvedStyle->withRows(max($fixedRows, $resolvedStyle->getMinRows() ?? 0), $resolvedStyle->getMaxRows());
        }
        // A checkbox or a radio CSS draws as a box too short for its border, like a 1em one: a mark in its colors, the
        // border and the background it has no room for left out
        if ($widget instanceof CheckboxWidget) {
            $border = $resolvedStyle->getBorder();
            $compact = $widget->isDrawnByCss() && null !== $border && $border->top + $border->bottom > 0 && ($resolvedStyle->getMaxRows() ?? \PHP_INT_MAX) <= $border->top + $border->bottom;
            $widget->setCompactMark($border?->color ?? $resolvedStyle->getColor(), $resolvedStyle->getBackground(), $compact);
            if ($compact) {
                $resolvedStyle = $resolvedStyle->withBorder(new Border(0, 0, 0, 0))->withBackground(null)->withRows(1, 1);
            }
        }
        // An image is told the size its style sets: its width, its height, how it fills them
        if ($widget instanceof ReplacedElementInterface) {
            // The rows of a style are those of the content already, inside the border and the padding
            [$contentColumns] = $this->chromeApplier->computeInnerDimensions($context->getColumns(), $context->getRows(), $resolvedStyle);
            $widget->setBox(
                null !== $resolvedStyle->getMaxColumns() || null !== $resolvedStyle->getWidthPercent() ? $contentColumns : null,
                $fixedRows,
                $resolvedStyle->getObjectFit(),
                $resolvedStyle->getObjectPosition(),
            );
            if ($widget instanceof ImageWidget) {
                $widget->setRendering($resolvedStyle->getImageFromImage() ?? true, $resolvedStyle->getPixelated() ?? false);
            }
        }

        if (null !== $stretchRows) {
            [, $contentRows] = $this->chromeApplier->computeInnerDimensions($context->getColumns(), $stretchRows, $resolvedStyle);
            // Stretched, its height is definite, like CSS: its percentages and the free rows of a flex column are of it
            if ($contentRows >= ($resolvedStyle->getMinRows() ?? 0)) {
                $resolvedStyle = $resolvedStyle->withRows($contentRows, $resolvedStyle->getMaxRows() ?? $contentRows);
            }
        }

        $styledContext = $context->withStyle($resolvedStyle);

        if ($resolvedStyle->getContentHidden()) {
            // content-visibility: hidden, the box without its children or its text
            $lines = $this->chromeApplier->apply(new ArrayLineBuffer([]), $context->getColumns(), $resolvedStyle, $widget);
        } elseif ($widget instanceof ContainerWidget) {
            $lines = $this->renderContainer($widget, $styledContext, $resolvedStyle);
        } elseif ($widget instanceof HorizontalRuleWidget && ($resolvedStyle->getBorder()?->top ?? 0) + ($resolvedStyle->getBorder()?->bottom ?? 0) > 0) {
            // A rule given a border, like hr { border: 0; border-top: 1px solid }: the border is the rule, like CSS
            $lines = $this->chromeApplier->apply(new ArrayLineBuffer([]), $context->getColumns(), $resolvedStyle, $widget);
        } else {
            $innerContext = $this->chromeApplier->computeInnerContext($styledContext, $resolvedStyle);
            $vertical = null !== $resolvedStyle->getWritingMode() && $widget instanceof TextWidget;
            $scale = $this->mediaContext->textSizing && $widget instanceof TextWidget ? $resolvedStyle->getFontScale() : null;
            $content = match (true) {
                $vertical => $this->renderVertically($widget, $innerContext, $resolvedStyle, $context, $definite),
                null !== $scale && $widget instanceof TextWidget => $this->renderScaled($widget, $innerContext, $scale),
                default => $widget->render($innerContext),
            };
            // Written vertically, a column holds a character: one wider than the room, like a CJK one in a column of
            // one, is cut at the edge
            if ($vertical) {
                $room = max(1, $innerContext->getColumns());
                $content = array_map(static fn (string $line): string => AnsiUtils::visibleWidth($line) > $room ? AnsiUtils::sliceToWidth($line, 0, $room) : $line, $content);
            }
            $boxColumns = $context->getColumns();
            // Written vertically, its width is its block size: as wide as its columns, unless set
            if ($vertical && null === $resolvedStyle->getMaxColumns() && null === $resolvedStyle->getWidthPercent()) {
                $chrome = $boxColumns - $innerContext->getColumns();
                $boxColumns = min($boxColumns, $chrome + max(0, ...array_map(AnsiUtils::visibleWidth(...), $content ?: [''])));
            }
            // A ::before that clears, see TextWidget::setClearance(): its lines below the floats, its box beside them
            // Not text written vertically, whose rows are its lines
            if (!$vertical && 0 < $clearance = $this->clearance($widget, $resolvedStyle)) {
                $content = [...array_fill(0, $clearance, ''), ...$content];
            }
            $lines = $this->chromeApplier->apply(new ArrayLineBuffer($content), $boxColumns, $resolvedStyle, $widget);
        }

        // A gradient over the cells of the box that have its background, within the margins; or the background over
        // its glyphs, for background-clip: text
        $layout = $resolvedStyle->getBackgroundLayout() ?? new BackgroundLayout();
        $background = $resolvedStyle->getBackground();
        $paint = $resolvedStyle->getBackgroundImage() ?? $resolvedStyle->getGradient() ?? (BackgroundLayout::TEXT === $layout->clip ? $background : null);
        // Several layers, composed cell by cell, each blended with what is under it by its mode; not over the glyphs
        // (background-clip: text), which the first one alone is
        if (null !== ($layers = $resolvedStyle->getBackgroundLayers()) && BackgroundLayout::TEXT !== $layout->clip && \count($lines) > 0) {
            $lines = new ArrayLineBuffer($this->paintBackgroundLayers($lines->toArray(), $resolvedStyle, $layers[0], $layers[1], $background, $layers[2]));
        } elseif (null !== $paint && \count($lines) > 0) {
            $lines = new ArrayLineBuffer($this->paintBackground($lines->toArray(), $resolvedStyle, $layout, $paint, $background));
        }
        // border-image: the lines of the border in the colors of its source, stretched over the box
        if (null !== ($borderImage = $resolvedStyle->getBorderImage()) && null !== ($border = $resolvedStyle->getBorder()) && $border->top + $border->right + $border->bottom + $border->left > 0 && \count($lines) > 0) {
            $lines = new ArrayLineBuffer(self::paintBorderImage($lines->toArray(), $resolvedStyle, $border, $borderImage));
        }

        // Reflection once per class: the hook costs nothing to widgets that
        // do not override it, since only then do the lines need materializing
        if ($this->postRenderOverridden[$widget::class] ??= AbstractWidget::class !== new \ReflectionMethod($widget, 'postRender')->getDeclaringClass()->name) {
            $lines = new ArrayLineBuffer($widget->postRender($lines->toArray(), $context));
        }

        $lines = $this->paintEffects($resolvedStyle, $lines);

        // Given no columns, like a track of a grid squeezed to none: its rows, nothing drawn in them
        if ($context->getColumns() < 1 && \count($lines) > 0) {
            $lines = new ArrayLineBuffer(array_fill(0, \count($lines), ''));
        }
        // A box no taller than a bound, like boxes in boxes each of a margin of a billion pixels: what is past it is not
        // drawn, the memory of the lines of each level staying bounded; not a text, like a long log, nor the content of
        // a scroll container, seen through its window
        if (\count($lines) > self::MAX_LINES && $widget instanceof ContainerWidget && null === $resolvedStyle->getScroll()) {
            $lines = new ArrayLineBuffer($lines->slice(0, self::MAX_LINES));
        }
        $this->validateLines($widget, $lines, $context->getColumns());
        $widget->setRenderCache($lines, $cacheColumns, $cacheRows, $widget instanceof ContainerWidget ? $widget->getEscapedFloats() : []);
        $this->positionTracker->markRendered($widget);

        return $lines;
    }

    /**
     * Drops the margin rows a parent and its first or last child share: the larger margin stays.
     *
     * @param list<AbstractWidget> $children
     */
    private function collapseParentMargins(Style $style, array $children, LineBufferInterface $lines, int $columns, ?int &$dropped = null, bool $bottomOnly = false): LineBufferInterface
    {
        $dropped = 0;
        $margin = $style->getMargin();
        $border = $style->getBorder();
        $padding = $style->getPadding();
        if (null === $margin || !$children) {
            return $lines;
        }

        $top = 0;
        // Not with a first child whose ::before cleared floats in the layout just made: that clearance counts its margin
        // rows, its content under the floats
        $first = $children[0];
        $cleared = LayoutEngine::hasClearance($first);
        if (!$bottomOnly && !$cleared && 0 === ($border->top ?? 0) && 0 === ($padding->top ?? 0)) {
            $top = min($margin->top, $this->layoutEngine->collapsedMargin($first, true, $columns));
        }
        $bottom = 0;
        if (0 === ($border->bottom ?? 0) && 0 === ($padding->bottom ?? 0)) {
            $bottom = min($margin->bottom, $this->layoutEngine->collapsedMargin($children[array_key_last($children)], false, $columns));
        }
        if (0 >= $top + $bottom) {
            return $lines;
        }
        // Nothing but margins, like those of an empty child collapsed through it, counted on both sides: all of them go,
        // collapsed with its own, like CSS
        if ($top + $bottom >= \count($lines)) {
            $dropped = min($top, \count($lines));

            return new ArrayLineBuffer([]);
        }

        // Its content moved up by the caller
        $dropped = $top;

        // Sliced from the buffer, not from all its lines copied first: millions of rows of margins, too
        return new ArrayLineBuffer($lines->slice($top, \count($lines) - $top - $bottom));
    }

    /**
     * A widget changed: the styles resolved from it may be stale.
     *
     * @internal
     */
    public function invalidateStyles(): void
    {
        ++$this->styleEpoch;
    }

    /**
     * @internal
     */
    public function wasRenderCached(AbstractWidget $widget): bool
    {
        return isset($this->cachedRenders[$widget]);
    }

    /**
     * The rows the children of a container pull into its bottom border by their negative bottom margins.
     *
     * @internal
     */
    public function getBottomPull(ParentInterface $container): int
    {
        return $this->layoutEngine->bottomPullInto($container, Direction::Horizontal === $this->resolveStyle($container)->getDirection());
    }

    /**
     * The background a widget is drawn on, that of its closest ancestor with one: its spaces show it.
     *
     * @internal
     */
    public function getInheritedBackground(AbstractWidget $widget): ?Color
    {
        return $this->chromeApplier->resolveOuterStyle($widget)?->getBackground();
    }

    public function getStyleEpoch(): int
    {
        return $this->styleEpoch + $this->layoutEngine->getColumnBackgroundRevision();
    }

    public function resolveStyle(AbstractWidget $widget): Style
    {
        $revision = $widget->getRenderRevision();
        $cached = $this->styleCache[$widget] ?? null;

        if (null !== $cached && $cached[0] === $revision) {
            return $this->layoutOverrides($widget, $cached[1]);
        }
        // Changed since it was resolved in this frame: what was built on its style is stale
        if (null !== $cached) {
            ++$this->styleEpoch;
        }

        $declared = $style = $this->styleSheet->resolve($widget, $this->currentColumns);
        // A list of fonts, like font-family: the first registered one, else the font of the terminal
        if (str_contains($style->getFont() ?? '', ',')) {
            $style = $style->withFont($this->fontRegistry->first($style->getFont()));
        }
        // A block of rows, like table { display: block; overflow-x: auto } to scroll a wide table: they make an anonymous
        // table, like CSS, the block drawn as it
        if (null === $style->getTableRole() && $widget instanceof ContainerWidget && $this->holdsRows($widget)) {
            $style = $style->withTableRole(TableRole::Table);
        }
        if (TableRole::Cell === $style->getTableRole()) {
            $collapsed = null !== $this->collapsedTableOf($widget);
            // The cells of a collapsed table have no border of their own: the table draws the lines
            if ($collapsed && null !== $style->getBorder()) {
                $style = $style->withBorder(new Border(0, 0, 0, 0));
            }
            // empty-cells: hide, in a separate table: a cell without content keeps its room and draws nothing
            if (!$collapsed && true === $style->getHideEmpty() && $widget instanceof ParentInterface && !$widget->all()) {
                $style = $style->withInvisible(true);
            }
        }
        $this->styleCache[$widget] = [$revision, $style, $declared];

        return $this->layoutOverrides($widget, $style);
    }

    /**
     * The style of a widget as the style sheets give it, before the layout changes it, like the border of a cell of a
     * collapsed table, which the table draws: resolved once a frame, with resolveStyle().
     */
    private function declaredStyle(AbstractWidget $widget): Style
    {
        if (($this->styleCache[$widget][0] ?? null) !== $widget->getRenderRevision()) {
            $this->resolveStyle($widget);
        }

        return $this->styleCache[$widget][2];
    }

    /**
     * The captions of a table above its rows and below them, by caption-side: the rows they take in its content, and
     * their lines as wide as its box, outside it; none when they would be all of it.
     *
     * @param list<AbstractWidget> $children
     *
     * @return array{int, list<string>, int, list<string>, list<array{AbstractWidget, int, bool}>} The rows above, the lines drawn there, the rows below, the lines drawn there, and each caption, its rows and whether it is below
     */
    private function captionRows(array $children, int $columns, int $rows, int $total, int $boxColumns): array
    {
        $captions = [];
        $take = function (iterable $children, bool $bottom) use ($columns, $rows, $boxColumns, &$captions): array {
            [$count, $lines] = [0, []];
            foreach ($children as $child) {
                $style = $this->resolveStyle($child);
                if (null !== $style->getTableRole() || $bottom !== $style->getCaptionBottom()) {
                    break;
                }
                if (true !== $style->getHidden()) {
                    $count += \count($this->renderWidgetLines($child, new RenderContext(max(1, $columns), $rows, null, $this->fontRegistry)));
                    $box = $this->renderWidgetLines($child, new RenderContext($boxColumns, $rows, null, $this->fontRegistry))->toArray();
                    $lines = $bottom ? [...$box, ...$lines] : [...$lines, ...$box];
                    $captions[] = [$child, \count($box), $bottom];
                }
            }

            return [$count, $lines];
        };
        [$top, $topLines] = $take($children, false);
        [$bottom, $bottomLines] = $take(array_reverse($children), true);

        if ($top + $bottom > $total) {
            return [0, [], 0, [], []];
        }
        // In the order they are drawn: those above, then those below from the first
        $above = array_values(array_filter($captions, static fn (array $caption) => !$caption[2]));
        $below = array_reverse(array_values(array_filter($captions, static fn (array $caption) => $caption[2])));

        return [$top, $topLines, $bottom, $bottomLines, [...$above, ...$below]];
    }

    /**
     * Whether the text of a ::before or ::after shows nothing: spaces and invisible characters only, like the zero
     * width space of the items of a nav of Pico, which takes no line of its own.
     */
    private static function isBlankContent(string $text): bool
    {
        return '' === (preg_replace('/[\s\p{Z}\p{Cf}]+/u', '', $text) ?? $text);
    }

    /**
     * The ::before and ::after of a flex row or a grid, items of their own, like CSS.
     *
     * @return array<0|1, RichTextWidget>
     */
    private function rowPseudoItems(AbstractWidget $widget, Style $resolvedStyle): array
    {
        if (!(ContainerWidget::class === $widget::class || $widget instanceof FormWidget) || (Direction::Horizontal !== $resolvedStyle->getDirection() && null === $resolvedStyle->getGridTemplate())) {
            return [];
        }
        $found = [];
        foreach ($widget->getGeneratedContent() as $i => $text) {
            if (null === $text) {
                continue;
            }
            $elementStyle = $this->styleSheet->resolveElement($widget, 0 === $i ? 'before' : 'after');
            if (true === $elementStyle->getHidden() || 0 === $elementStyle->getMaxRows()) {
                continue;
            }
            // A triangle its borders drew, like a caret after a menu: the glyph, its margins in it already, not boxed
            if (preg_match('/^ *[▾◂▴▸] *$/u', $text)) {
                $elementStyle = $elementStyle->withBorder([0, 0, 0, 0])->withMargin([0, 0, 0, 0])->withPadding([0, 0, 0, 0]);
                $text = str_replace(' ', "\u{00A0}", $text);
            }
            // Empty but of a width, like the separator of a breadcrumb of MDN, a chevron its mask draws: its room
            if (self::isBlankContent($text)) {
                if (null === $width = $elementStyle->getMaxColumns()) {
                    continue;
                }
                $text = str_repeat("\u{00A0}", max(1, min(1000, $width)));
            }
            // Its images, like content: url(icon.png), drawn in it
            $items = $this->pseudoItems[$widget] ?? [];
            if (($items[$i] ?? null)?->getSourceText() !== $text) {
                $items[$i] = new RichTextWidget([InlineNode::text($text)]);
            }
            $item = $items[$i];
            $item->setGeneratedImages($widget->getGeneratedImages());
            $item->setStyle($elementStyle);
            $this->pseudoItems[$widget] = $items;
            $found[$i] = $item;
        }

        return $found;
    }

    /**
     * Whether a widget, or a box nested in it, paints a background of its own, like the .dropdown-content of the
     * .dropdown-menu of Bulma.
     */
    private function paintsBackground(AbstractWidget $widget, int $depth = 0): bool
    {
        if (null !== $this->resolveStyle($widget)->getBackground()) {
            return true;
        }
        if ($depth >= 3 || !$widget instanceof ContainerWidget) {
            return false;
        }
        foreach ($widget->all() as $child) {
            // Not one out of the flow, drawn on its own, like the handle of the resizable example of Tailwind in a box
            // without a background over the example
            if ($child instanceof ContainerWidget && !($childStyle = $this->resolveStyle($child))->getHidden() && !$childStyle->getPosition()?->isOutOfFlow() && $this->paintsBackground($child, $depth + 1)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether all the children of a container drawn are rows or groups of rows of a table.
     */
    private function holdsRows(ContainerWidget $container): bool
    {
        $rows = false;
        foreach ($container->all() as $child) {
            // A row or a group of them holds cells: text is none
            if (!$child instanceof ContainerWidget) {
                return false;
            }
            // Their styles from the style sheet: a cell resolved here would look for its table through this container
            $style = $this->styleSheet->resolve($child, $this->currentColumns);
            if ($style->getHidden() || $style->getPosition()?->isOutOfFlow()) {
                continue;
            }
            $role = $style->getTableRole();
            if (TableRole::Row !== $role && TableRole::RowGroup !== $role && TableRole::Column !== $role) {
                return false;
            }
            $rows = $rows || TableRole::Column !== $role;
        }

        return $rows;
    }

    /**
     * What the layout gives a style: the width of a child of a container scrolled sideways, and the background of the
     * column of a table cell without one.
     */
    private function layoutOverrides(AbstractWidget $widget, Style $style): Style
    {
        if (isset($this->sidewaysCaps[$widget])) {
            $style = $style->withMaxColumns(min($style->getMaxColumns() ?? \PHP_INT_MAX, $this->sidewaysCaps[$widget]));
        }
        // The content of an absolute box past the height it sets, its least height kept
        if (isset($this->unboundedRows[$widget])) {
            $style = $style->withRows($style->getMinRows(), null)->withRowsPercent($style->getMinRowsPercent(), null, $style->getRowsPercentOffsets()[0]);
        }
        // The background of the column is under the cell, and under its row and row group too, like CSS
        if (TableRole::Cell === $style->getTableRole() && null === $style->getBackground() && null !== $background = $this->layoutEngine->getColumnBackground($widget)) {
            for ($ancestor = $widget->getParent(); null !== $ancestor; $ancestor = $ancestor->getParent()) {
                $ancestorStyle = $this->resolveStyle($ancestor);
                if (TableRole::Table === $ancestorStyle->getTableRole()) {
                    break;
                }
                if (null !== $ancestorStyle->getBackground()) {
                    return $style;
                }
            }
            $style = $style->withBackground($background);
        }

        return $style;
    }

    /** The depth of the containers whose content is being measured: the percentage widths inside them are auto */
    private int $contentDepth = 0;

    /**
     * The table a cell is in when its borders collapse, null otherwise.
     */
    private function collapsedTableOf(AbstractWidget $cell): ?AbstractWidget
    {
        for ($ancestor = $cell->getParent(), $depth = 0; null !== $ancestor && $depth < 3; $ancestor = $ancestor->getParent(), ++$depth) {
            $style = $this->resolveStyle($ancestor);
            if (TableRole::Table === $style->getTableRole()) {
                return $style->getCollapse() ? $ancestor : null;
            }
        }

        return null;
    }

    /**
     * The rows of its content box a ::before that clears takes, see Style::withBeforeClear(): no more than a box of a set
     * height holds, its content then past its end, cut like the rest.
     */
    private function clearance(AbstractWidget $widget, Style $resolvedStyle): int
    {
        if (!$widget instanceof TextWidget && !$widget instanceof ContainerWidget) {
            return 0;
        }

        return min($widget->getClearance(), $resolvedStyle->getMaxRows() ?? \PHP_INT_MAX);
    }

    public function measureIntrinsicWidth(AbstractWidget $widget, int $maxColumns, int $rows): int
    {
        // Measured again only when the widget changed, like the cells of a table each frame; not while some width
        // is capped for a container scrolled sideways, which changes the styles of what is in it
        // Several widths each, as a layout may try a few, like a grid written vertically, one after the other
        $revision = $widget->getRenderRevision();
        // Inside a container measured, its percentage width is auto: measured apart
        $key = $maxColumns.':'.$rows.(0 < $this->contentDepth ? ':c' : '').':'.($widget->getParent()?->getRenderRevision() ?? 0);
        $widths = $this->intrinsicWidths[$widget] ?? null;
        if (null !== $widths && $revision !== $widths[0]) {
            $widths = null;
        }
        if (null !== ($cached = $widths[1][$key] ?? null) && !$this->sidewaysCaps->count()) {
            if (null !== $cached[1]) {
                $this->definiteRows[$widget] = $cached[1];
            }

            return $cached[0];
        }
        // A text beside floats measured as if none were, like CSS sizes it, see TextWidget::measureWithoutFloats()
        $width = $widget instanceof TextWidget ? $widget->measureWithoutFloats(fn (): int => $this->computeIntrinsicWidth($widget, $maxColumns, $rows)) : $this->computeIntrinsicWidth($widget, $maxColumns, $rows);
        if (!$this->sidewaysCaps->count()) {
            // Measuring what it holds may have changed it: kept for the revision it has now, bounded
            $widths = $widget->getRenderRevision() === ($widths[0] ?? null) && \count($widths[1]) < 64 ? $widths : [$widget->getRenderRevision(), []];
            $widths[1][$key] = [$width, $this->definiteRows[$widget] ?? null];
            $this->intrinsicWidths[$widget] = $widths;
        }

        return $width;
    }

    private function computeIntrinsicWidth(AbstractWidget $widget, int $maxColumns, int $rows): int
    {
        $resolvedStyle = $this->resolveStyle($widget)->withResolvedBox($maxColumns);

        // Apply maxColumns from the widget's own style
        $styleMaxColumns = $resolvedStyle->getMaxColumns();
        if (null !== $styleMaxColumns) {
            $maxColumns = min($maxColumns, $styleMaxColumns);
        }
        // A percentage of the width of what holds it: auto inside a container sized by its content, like CSS, like the
        // field of an input group of Bootstrap (width: 1%) in a toolbar
        if (null !== $resolvedStyle->getWidthPercent() && 0 === $this->contentDepth) {
            $maxColumns = (int) $resolvedStyle->resolveWidthPercent($maxColumns);
        }

        // Compute chrome (border + padding)
        [$innerColumns] = $this->chromeApplier->computeInnerDimensions($maxColumns, $rows, $resolvedStyle);
        // field-sizing: content, an input as wide as its value
        if ($widget instanceof InputWidget && $widget->isContentSized()) {
            return min($maxColumns, $widget->getContentColumns() + $maxColumns - $innerColumns);
        }
        // Sized by its content, like in a row: the columns it prefers, like the size attribute of HTML
        if ($widget instanceof InputWidget && null !== $preferred = $widget->getPreferredColumns()) {
            return min($maxColumns, $preferred + $maxColumns - $innerColumns);
        }

        // Written vertically, a text block is as wide as its columns: drawn to know them
        if ($widget instanceof TextWidget && null !== $resolvedStyle->getWritingMode()) {
            $saved = $this->positionTracker->suppressStack();
            try {
                $lines = $this->renderWidgetLines($widget, new RenderContext($maxColumns, $rows, null, $this->fontRegistry));
            } finally {
                $this->positionTracker->restoreStack($saved);
            }

            return min($maxColumns, max(0, ...array_map(AnsiUtils::visibleWidth(...), $lines->toArray() ?: [''])));
        }

        // A table whose borders are separate: as wide as its columns, laid out
        if ($widget instanceof ContainerWidget && TableRole::Table === $resolvedStyle->getTableRole() && !$resolvedStyle->getCollapse()) {
            [$innerColumns] = $this->chromeApplier->computeInnerDimensions($maxColumns, $rows, $resolvedStyle);
            $saved = $this->positionTracker->suppressStack();
            try {
                $content = $this->layoutEngine->tableContentWidth($widget, $innerColumns, $rows);
                // Its captions as wide as their longest word, like CSS, wrapped in the table; what is in no row, like
                // the image of a <figure> displayed as a table, or the items of a menu displayed as an inline table,
                // as wide as it is
                foreach ($this->boxChildren($widget) as $child) {
                    $childStyle = $this->resolveStyle($child);
                    if (null === $childStyle->getTableRole() && true !== $childStyle->getHidden()) {
                        $content = max($content ?? 0, null !== $childStyle->getCaptionBottom() ? $this->measureMinContentWidth($child, $innerColumns, $rows) : $this->measureIntrinsicWidth($child, $innerColumns, $rows));
                    }
                }
            } finally {
                $this->positionTracker->restoreStack($saved);
            }
            if (null !== $content) {
                return min($maxColumns, $content + $maxColumns - $innerColumns);
            }
        }

        // A table whose borders collapse: as wide as it draws its columns, their lines between them
        if ($widget instanceof ContainerWidget && TableRole::Table === $resolvedStyle->getTableRole() && $resolvedStyle->getCollapse()) {
            $saved = $this->positionTracker->suppressStack();
            try {
                $lines = $this->renderCollapsedTable($widget, $this->boxChildren($widget), new RenderContext($maxColumns, $rows, null, $this->fontRegistry), $resolvedStyle, true);
            } finally {
                $this->positionTracker->restoreStack($saved);
            }
            if (null !== $lines) {
                return min($maxColumns, max(0, ...array_map(AnsiUtils::visibleWidth(...), $lines->toArray() ?: [''])));
            }
            // Without rows, like a <figure> displayed as a table, its image and its caption: like a separate one
            [$innerColumns] = $this->chromeApplier->computeInnerDimensions($maxColumns, $rows, $resolvedStyle);
            $content = null;
            $saved = $this->positionTracker->suppressStack();
            try {
                foreach ($this->boxChildren($widget) as $child) {
                    $childStyle = $this->resolveStyle($child);
                    if (null === $childStyle->getTableRole() && true !== $childStyle->getHidden()) {
                        $content = max($content ?? 0, null !== $childStyle->getCaptionBottom() ? $this->measureMinContentWidth($child, $innerColumns, $rows) : $this->measureIntrinsicWidth($child, $innerColumns, $rows));
                    }
                }
            } finally {
                $this->positionTracker->restoreStack($saved);
            }
            if (null !== $content) {
                return min($maxColumns, $content + $maxColumns - $innerColumns);
            }
        }

        if ($widget instanceof ContainerWidget) {
            // For containers, render children within inner dimensions and measure
            $children = array_values(array_filter(
                $this->boxChildren($widget),
                // Absolute and fixed children are drawn over the frame, not in the flow
                fn (AbstractWidget $child) => !$this->resolveStyle($child)->getHidden() && !$this->resolveStyle($child)->getPosition()?->isOutOfFlow(),
            ));

            $direction = $resolvedStyle->getDirection() ?? Direction::Vertical;
            $gap = $resolvedStyle->getGap() ?? 0;
            // Its height, when set, is that of the text its children write vertically: known before it is drawn
            if (null !== ($fixed = $resolvedStyle->getMaxRows()) && $fixed === $resolvedStyle->getMinRows()) {
                $this->definiteRows[$widget] = $fixed;
            }

            if (null !== ($grid = $resolvedStyle->getGridTemplate())) {
                // A grid is as wide as its columns sized by their content, side by side, like the equal segments of
                // grid-auto-columns: 1fr of the tabs of the exception page of Symfony; one written vertically, as wide as
                // its rows: laid out to know them
                $saved = $this->positionTracker->suppressStack();
                try {
                    $lines = $this->layoutEngine->layoutGrid($children, $innerColumns, $rows, $grid, $gap, $resolvedStyle->getRowGap() ?? 0, contentRows: $fixed === $resolvedStyle->getMinRows() ? $fixed : null, contentSized: true);
                } finally {
                    $this->positionTracker->restoreStack($saved);
                }
                $contentWidth = max(0, ...array_map(AnsiUtils::visibleWidth(...), $lines->toArray() ?: ['']));
            } else {
                ++$this->contentDepth;
                try {
                    if (Direction::Horizontal === $direction) {
                        // Horizontal container: sum of children's intrinsic widths + gaps, its ::before and ::after items too
                        array_push($children, ...array_values($this->rowPseudoItems($widget, $resolvedStyle)));
                        $contentWidth = $gap * max(0, \count($children) - 1);
                        // Those on a line of their own, like a block among inline boxes, as wide as the widest of them
                        $ownLines = 0;
                        foreach (array_values($children) as $index => $child) {
                            $childStyle = $this->resolveStyle($child);
                            $childWidth = $this->measureIntrinsicWidth($child, $innerColumns, $rows);
                            if ($childStyle->getOwnLine()) {
                                $ownLines = max($ownLines, $childWidth);
                                continue;
                            }
                            $contentWidth += $childWidth + ($index > 0 && $childStyle->getSpaceBefore() ? 1 : 0);
                        }
                        $contentWidth = max($contentWidth, $ownLines);
                    } else {
                        // Vertical container: widest child; floats one after the other side by side, their widths
                        // added with the column a terminal leaves between them, like CSS, until one that clears or
                        // something in the flow
                        $contentWidth = 0;
                        // The columns the floats of the row take on each side, the column after each included, and
                        // whether the last on a side is of a percentage
                        $run = ['left' => 0, 'right' => 0];
                        $percents = ['left' => false, 'right' => false];
                        foreach ($children as $child) {
                            $childStyle = $this->resolveStyle($child);
                            // Out of the flow, nothing to the line, not measured
                            if (true === $childStyle->getPosition()?->isOutOfFlow()) {
                                continue;
                            }
                            $childWidth = $this->measureIntrinsicWidth($child, $innerColumns, $rows);
                            if (null === $side = $childStyle->getFloat()) {
                                // A run of text on the line of the floats before it, beside them, like the label after an
                                // icon floated; a block below them
                                $contentWidth = max($contentWidth, $childWidth + ($this->inLineOfFloats($child) ? $run['left'] + $run['right'] : 0));
                                $run = ['left' => 0, 'right' => 0];
                                $percents = ['left' => false, 'right' => false];
                                continue;
                            }
                            // Below the floats of a side it clears, beside those of the other
                            foreach (match ($childStyle->getClear()) {
                                'both' => ['left', 'right'], 'left' => ['left'], 'right' => ['right'], default => [],
                            } as $cleared) {
                                $run[$cleared] = 0;
                                $percents[$cleared] = false;
                            }
                            $percent = null !== $childStyle->getWidthPercent() && null === $childStyle->getMaxColumns();
                            // Against the last on its side when both are percentages, see LayoutEngine::layoutFloats()
                            $run[$side] += $childWidth + ($percent && $percents[$side] ? 0 : 1);
                            $percents[$side] = $percent;
                            $contentWidth = max($contentWidth, $run['left'] + $run['right'] - 1);
                        }
                    }
                } finally {
                    --$this->contentDepth;
                }
            }
        } elseif ($widget instanceof HorizontalRuleWidget) {
            // A rule has no content: as wide as what it is in, like the divider of a dropdown menu of Bootstrap
            $contentWidth = 0;
        } else {
            // An image is measured at the size its style sets, like a height alone, which narrows it with it
            if ($widget instanceof ReplacedElementInterface) {
                $widget->setBox(
                    null !== $resolvedStyle->getMaxColumns() || null !== $resolvedStyle->getWidthPercent() ? $innerColumns : null,
                    null !== $resolvedStyle->getMinRows() && $resolvedStyle->getMinRows() === $resolvedStyle->getMaxRows() ? $resolvedStyle->getMinRows() : null,
                    $resolvedStyle->getObjectFit(),
                    $resolvedStyle->getObjectPosition(),
                );
                if ($widget instanceof ImageWidget) {
                    $widget->setRendering($resolvedStyle->getImageFromImage() ?? true, $resolvedStyle->getPixelated() ?? false);
                }
            }
            // An image: the columns it is drawn in, found without drawing it, which a large room would make long
            if (($widget instanceof ImageWidget || $widget instanceof SvgWidget) && null !== $drawn = $widget->drawnColumns($innerColumns)) {
                return min(max(1, $drawn + $maxColumns - $innerColumns), $maxColumns);
            }
            // For leaf widgets, render content at inner dimensions and measure widest line
            $innerContext = $this->chromeApplier->computeInnerContext(
                new RenderContext($maxColumns, $rows, null, $this->fontRegistry)->withStyle($resolvedStyle),
                $resolvedStyle,
            );
            $widget->beforeRender();
            $contentLines = $widget->render($innerContext);
            $widget->clearRenderCache();

            $contentWidth = 0;
            foreach ($contentLines as $line) {
                $contentWidth = max($contentWidth, AnsiUtils::visibleWidth($line));
            }
        }

        $chromeWidth = $maxColumns - $innerColumns;

        // A width set, like the width: 40px of the empty switch of VitePress: that width, its margins around it, whatever
        // its content
        if (null !== ($basis = $resolvedStyle->getFlexBasis()) && $basis > 0 && null === $resolvedStyle->getWidthPercent()) {
            $margin = $resolvedStyle->getMargin();

            return min(max(1, $basis + max(0, $margin->left ?? 0) + max(0, $margin->right ?? 0)), $maxColumns);
        }

        $parent = $widget->getParent();
        $minimum = null !== $parent && null !== $this->resolveStyle($parent)->getGridTemplate() ? 0 : 1;

        return min(max($minimum, $contentWidth + $chromeWidth), $maxColumns);
    }

    /**
     * The least width a widget needs not to be cut, like the CSS min-content width: text that wraps needs its longest
     * word, text that does not (pre, nowrap) its widest line, a row the sum of its items, a column its widest child;
     * chrome included.
     */
    public function measureMinContentWidth(AbstractWidget $widget, int $columns, int $rows, int $depth = 0): int
    {
        // Measured again only when the widget changed: a container changes with what is inside it
        $revision = $widget->getRenderRevision();
        $cached = $this->minContentWidths[$widget] ?? null;
        if (null !== $cached && [$revision, $columns, $rows] === [$cached[0], $cached[1], $cached[2]]) {
            return $cached[3];
        }
        $width = $this->computeMinContentWidth($widget, $columns, $rows, $depth);
        $this->minContentWidths[$widget] = [$revision, $columns, $rows, $width];

        return $width;
    }

    /**
     * The least width a widget needs not to break a word, its controls ellipsizing their labels and its images scaled
     * down to half their width, like a row of links beside a search field that does not fit at their min-content.
     */
    public function measureSoftMinContentWidth(AbstractWidget $widget, int $columns, int $rows): int
    {
        return $this->softMinContentWidth($widget, $columns, $rows, 0);
    }

    /**
     * The soft min-content width, measured again only when the widget changed; that of a widget holding no control nor
     * image, its min-content width.
     */
    private function softMinContentWidth(AbstractWidget $widget, int $columns, int $rows, int $depth): int
    {
        if (!$this->softens($widget)) {
            return $this->measureMinContentWidth($widget, $columns, $rows, $depth);
        }
        $revision = $widget->getRenderRevision();
        $cached = $this->softMinContentWidths[$widget] ?? null;
        if (null !== $cached && [$revision, $columns, $rows] === [$cached[0], $cached[1], $cached[2]]) {
            return $cached[3];
        }
        $width = $this->computeMinContentWidth($widget, $columns, $rows, $depth, false, true);
        $this->softMinContentWidths[$widget] = [$revision, $columns, $rows, $width];

        return $width;
    }

    /**
     * Whether a widget is or holds a control or an image, which the soft min-content width ellipsizes or scales.
     */
    private function softens(AbstractWidget $widget, int $depth = 0): bool
    {
        // Too deep to tell: it may, not kept
        if ($depth >= 32) {
            return true;
        }
        $revision = $widget->getRenderRevision();
        if (null !== ($cached = $this->softens[$widget] ?? null) && $cached[0] === $revision) {
            return $cached[1];
        }
        $softens = ($widget instanceof FocusableInterface && !$widget instanceof TextWidget) || $widget instanceof ReplacedElementInterface
            || ($widget instanceof ParentInterface && array_any($widget->all(), fn (AbstractWidget $child): bool => $this->softens($child, $depth + 1)));
        $this->softens[$widget] = [$revision, $softens];

        return $softens;
    }

    /**
     * @param bool $placed Whether the widget is the positioned one being placed, measured though out of the flow
     * @param bool $soft   Its controls ellipsized and its images scaled down, see measureSoftMinContentWidth()
     */
    private function computeMinContentWidth(AbstractWidget $widget, int $columns, int $rows, int $depth, bool $placed = false, bool $soft = false): int
    {
        $style = $this->resolveStyle($widget);
        if ($depth > 32 || $style->getHidden() || (!$placed && $style->getPosition()?->isOutOfFlow())) {
            return 0;
        }
        $resolved = $style->withResolvedBox($columns);
        [$innerColumns] = $this->chromeApplier->computeInnerDimensions($columns, $rows, $resolved);
        $chrome = $columns - $innerColumns;

        if ($widget instanceof ContainerWidget) {
            // A scroll container scrolls what is too wide itself, or clips it
            if (null !== $style->getScroll()) {
                return $chrome;
            }
            // A table: the least width of each column, its widest cell's, side by side
            if (TableRole::Table === $resolved->getTableRole()) {
                $columnsMin = [];
                $rowGap = 0;
                foreach ($widget->all() as $child) {
                    $role = $this->resolveStyle($child)->getTableRole();
                    $rowsOf = TableRole::Row === $role ? [$child] : (TableRole::RowGroup === $role && $child instanceof ParentInterface ? $child->all() : []);
                    foreach ($rowsOf as $row) {
                        $rowGap = max($rowGap, $this->resolveStyle($row)->getGap() ?? 0);
                        foreach ($row instanceof ParentInterface ? array_values($row->all()) : [] as $i => $cell) {
                            $columnsMin[$i] = max($columnsMin[$i] ?? 0, $soft ? $this->softMinContentWidth($cell, $innerColumns, $rows, $depth + 1) : $this->measureMinContentWidth($cell, $innerColumns, $rows, $depth + 1));
                        }
                    }
                }

                return array_sum($columnsMin) + $rowGap * max(0, \count($columnsMin) - 1) + $chrome;
            }
            $children = $this->boxChildren($widget);
            $gap = $resolved->getGap() ?? 0;
            $content = 0;
            if (Direction::Horizontal === $resolved->getDirection() && !$resolved->getWrap()) {
                // Its ::before and ::after items too, like the separators of a breadcrumb
                array_push($children, ...array_values($this->rowPseudoItems($widget, $style)));
                foreach ($children as $child) {
                    $content += $soft ? $this->softMinContentWidth($child, $innerColumns, $rows, $depth + 1) : $this->measureMinContentWidth($child, $innerColumns, $rows, $depth + 1);
                }
                $content += $gap * max(0, \count($children) - 1);
            } else {
                foreach ($children as $child) {
                    $content = max($content, $soft ? $this->softMinContentWidth($child, $innerColumns, $rows, $depth + 1) : $this->measureMinContentWidth($child, $innerColumns, $rows, $depth + 1));
                }
            }

            return $content + $chrome;
        }

        // Not drawn wide to be measured: a control in the room it has, which an input fills and a button does not; an
        // image is as wide as its style says
        // field-sizing: content, an input as wide as its value
        if ($widget instanceof InputWidget && $widget->isContentSized()) {
            return min(max($columns, $chrome), $widget->getContentColumns() + $chrome);
        }
        if ($widget instanceof FocusableInterface && !$widget instanceof TextWidget) {
            $width = $this->measureIntrinsicWidth($widget, $columns, $rows);
            // Soft: its label ellipsized, a few columns of it left
            if ($soft) {
                return min($width >= $columns ? $chrome : $width, $chrome + 2);
            }

            // Filling the room it has, it fits any
            return $width >= $columns ? $chrome : $width;
        }
        // An image is as wide as its style says, else its own width, like the width attribute: it does not shrink
        if ($widget instanceof ReplacedElementInterface) {
            // Soft: scaled down to half its width, like a logo beside links that do not fit, still drawn
            if ($soft && null === $resolved->getMaxColumns()) {
                return min($columns, $chrome + max(1, intdiv($this->measureIntrinsicWidth($widget, $columns, $rows) - $chrome, 2)));
            }

            return null !== $resolved->getMaxColumns() ? $resolved->getMaxColumns() + $chrome : min($columns, $this->measureIntrinsicWidth($widget, $columns, $rows));
        }

        // Laid out at its widest, then in the room it has: when it takes more lines there, it wraps
        $render = function (int $width) use ($widget, $rows, $resolved): array {
            $context = $this->chromeApplier->computeInnerContext(new RenderContext($width, $rows, null, $this->fontRegistry)->withStyle($resolved), $resolved);
            $widget->beforeRender();
            // Its no-break spaces kept, which join words, like the 23&nbsp;/&nbsp;23 of a coverage report
            if ($widget instanceof RichTextWidget) {
                $widget->keepNoBreakSpaces(true);
            }
            try {
                // As if no float were beside it, see TextWidget::measureWithoutFloats()
                $lines = $widget instanceof TextWidget ? $widget->measureWithoutFloats(static fn (): array => $widget->render($context)) : $widget->render($context);
            } finally {
                if ($widget instanceof RichTextWidget) {
                    $widget->keepNoBreakSpaces(false);
                }
            }
            $widget->clearRenderCache();

            return $lines;
        };
        $room = max($innerColumns, 4096);
        $widest = $render($room + $chrome);
        $width = 0;
        foreach ($widest as $line) {
            $width = max($width, AnsiUtils::visibleWidth($line));
        }
        // A widget filling the room it gets, like an input or a rule, fits any
        if ($width >= $room) {
            return $chrome;
        }
        // Text that wraps needs its longest word, wherever it is; other widgets, their width unless it wraps there
        $wraps = $widget instanceof RichTextWidget ? $widget->wrapsLines() : null;
        if (false === $wraps || (null === $wraps && ($width <= $innerColumns || \count($render($columns)) <= \count($widest)))) {
            return $width + $chrome;
        }
        $word = 0;
        foreach ($widest as $line) {
            // At the spaces the lines wrap at: not a no-break space, like the 23&nbsp;/&nbsp;23 of a coverage report; at the
            // zero-width spaces between the letters of word-break: break-all too, like the code of the documentation of Django
            $line = AnsiUtils::stripAnsiCodes($line);
            // At the spaces alone in a line of bytes that are not UTF-8
            foreach (preg_split('/[ \t\x{200B}]+/u', $line) ?: preg_split('/[ \t]+/', $line) ?: [] as $part) {
                $word = max($word, AnsiUtils::visibleWidth($part));
            }
        }

        return $word + $chrome;
    }

    /**
     * Render a container widget with its children.
     */
    private function renderContainer(ContainerWidget $widget, RenderContext $context, Style $resolvedStyle): LineBufferInterface
    {
        // A legend in the top border, out of the flow, like the one of a <fieldset>
        $legend = $widget->getLegend();
        if (null !== $legend && ($legend->getParent() !== $widget || ($resolvedStyle->getBorder()?->top ?? 0) < 1 || $this->resolveStyle($legend)->getHidden())) {
            $legend = null;
        }
        $children = [];
        foreach ($this->boxChildren($widget) as $child) {
            if ($child === $legend) {
                continue;
            }
            $style = $this->resolveStyle($child);
            // Absolute and fixed children are drawn over the frame, not in the flow: remembered until their parent renders again
            if ($style->getPosition()?->isOutOfFlow()) {
                $this->overlays[$child] = true;
            } else {
                unset($this->overlays[$child]);
                if (!$style->getHidden()) {
                    $children[] = $child;
                }
            }
        }

        // ::before and ::after of a flex row or a grid: items of their own, before and after the children, like CSS
        foreach ($this->rowPseudoItems($widget, $resolvedStyle) as $i => $item) {
            0 === $i ? array_unshift($children, $item) : $children[] = $item;
        }

        $columns = $context->getColumns();
        $rows = $context->getRows();

        if (!$children) {
            // A table row of none of its own cells, those spanning rows from above in it
            [$innerColumns] = $this->chromeApplier->computeInnerDimensions($columns, $context->getRows(), $resolvedStyle);

            // An empty block whose ::before clears: as tall as the rows of the floats it clears, what follows it below them
            return $this->chromeApplier->apply(new ArrayLineBuffer($this->layoutEngine->spannedRowLines($widget, $innerColumns) ?? array_fill(0, $this->clearance($widget, $resolvedStyle), '')), $columns, $resolvedStyle, $widget);
        }

        // A collapsed table draws its cells and their borders as one grid
        // Not one of rows displayed as blocks, like the tables of EasyAdmin on a narrow screen: laid out like blocks
        if (TableRole::Table === $resolvedStyle->getTableRole() && $resolvedStyle->getCollapse() && null !== $collapsed = $this->renderCollapsedTable($widget, $children, $context, $resolvedStyle)) {
            return $collapsed;
        }

        [$innerColumns, $innerRows] = $this->chromeApplier->computeInnerDimensions($columns, $rows, $resolvedStyle);

        // A table sizes its columns across its rows before they are laid out
        if (TableRole::Table === $resolvedStyle->getTableRole()) {
            $this->layoutEngine->prepareTable($widget, $innerColumns, $innerRows, null !== $resolvedStyle->getWidthPercent() || null !== $resolvedStyle->getMinWidthPercent() || (null !== $resolvedStyle->getMaxColumns() && null === $resolvedStyle->getContentWidth()));
        }

        // Its children can have percentage heights when its own height is definite: the frame, a fixed height, or the
        // one its aspect-ratio gives, like CSS
        $fixedRows = $resolvedStyle->getMinRows();
        // Not lost to an absolute box measured past its height, see renderPositioned()
        if ($context->shouldFillRows() || (null !== $fixedRows && ($fixedRows === $resolvedStyle->getMaxRows() || null !== $resolvedStyle->getAspectRatio() || true === ($this->unboundedRows[$widget] ?? null)))) {
            $this->definiteRows[$widget] = $context->shouldFillRows() && null === $fixedRows ? $innerRows : (int) $fixedRows;
        } elseif ((null === $fixedRows || $resolvedStyle->getDocumentHeight()) && $widget instanceof ContainerWidget && $widget->passesDefiniteHeight() && null !== ($parent = $widget->getParent()) && null !== ($parentRows = $this->definiteRows[$parent] ?? null)) {
            // <html> and <body>, as tall as their content, pass the height of the frame on to percentages, their height
            // of 100% too, which their content overflows, see CssStyleSheet::computeDeclarations()
            $this->definiteRows[$widget] = max((int) ($fixedRows ?? 0), $this->chromeApplier->computeInnerDimensions($columns, $parentRows, $resolvedStyle)[1]);
        } else {
            unset($this->definiteRows[$widget]);
        }

        $direction = $resolvedStyle->getDirection() ?? Direction::Vertical;
        $gap = $resolvedStyle->getGap() ?? 0;

        $gapLine = null;
        if ($gap > 0) {
            $gapContent = str_repeat(' ', max(1, $innerColumns));
            $gapLine = $resolvedStyle->isPlain() ? $gapContent : $resolvedStyle->apply($gapContent);
        }

        $align = $resolvedStyle->getAlign();
        $hasAlign = null !== $align && Align::Left !== $align;
        $verticalAlign = $resolvedStyle->getVerticalAlign();
        $hasVerticalAlign = null !== $verticalAlign;

        // align-content shares the free rows of a set height: the frame only when it is asked for
        $alignContent = $resolvedStyle->getAlignContent();
        // A document height too, like the height: 100% of a <body> its content can overflow
        $contentRows = null !== $fixedRows && ($fixedRows === $resolvedStyle->getMaxRows() || $resolvedStyle->getDocumentHeight()) ? (int) $fixedRows : ($context->shouldFillRows() && null !== $alignContent ? $innerRows : null);

        // A separate table draws its captions with caption-side: bottom after its rows, like a collapsed one
        if (TableRole::Table === $resolvedStyle->getTableRole()) {
            $bottom = array_filter($children, fn (AbstractWidget $child) => null === $this->resolveStyle($child)->getTableRole() && $this->resolveStyle($child)->getCaptionBottom());
            if ($bottom) {
                $children = [...array_filter($children, static fn (AbstractWidget $child) => !\in_array($child, $bottom, true)), ...$bottom];
            }
        }

        $contentRow = 0;
        $contentCol = 0;
        $all = $children;
        // A flex column of a set height, or stretched to one, shares its free rows: those of its content
        $columnRows = null;
        if (Direction::Vertical === $resolvedStyle->getDirection()) {
            // Its height, or its least height, like min-height: 100vh: what its content does not take is free
            if (null !== $fixedRows) {
                $columnRows = (int) $fixedRows;
            } elseif ($context->shouldFillRows() && null === $fixedRows) {
                $columnRows = $innerRows;
            }
        }
        $inlineFlow = $widget->isInlineFlow();
        // The floats going on past it, as its content laid them out, and how far its content moved since in its box, see
        // $shiftContent
        $escaped = null;
        $origin = [0, 0];
        // A ::before that clears, see Style::withBeforeClear(): an empty block as tall as the rows of the floats beside it
        // it clears, like CSS, its border and its background beside them; what follows it below them, laid out among
        // the floats left, which the block around gives moved up as much, see LayoutEngine::layoutFloats()
        $clearance = $this->clearance($widget, $resolvedStyle);
        // Its content laid out in the rows left under the clearance, which a box of a set height takes from them: the
        // values the layout takes, the box keeping its own after
        $boxRows = [$innerRows, $contentRows, $columnRows];
        if ($clearance > 0) {
            $innerRows = max(1, $innerRows - $clearance);
            $contentRows = null !== $contentRows ? max(0, $contentRows - $clearance) : null;
            $columnRows = null !== $columnRows ? max(0, $columnRows - $clearance) : null;
        }
        $layout = function (int $innerColumns, ?array $children = null) use ($widget, $resolvedStyle, $all, $innerRows, $gap, $gapLine, $direction, $verticalAlign, $alignContent, $contentRows, $columnRows, $inlineFlow, &$contentRow, &$contentCol, &$escaped, &$origin): LineBufferInterface {
            $children ??= $all;
            $escaped = null;
            $origin = [0, 0];
            if ($this->positionTracker->isActive()) {
                [$parentRow, $parentCol] = $this->positionTracker->currentOffset();
                [$chromeTop, $chromeLeft] = $this->chromeApplier->computeChromeOffset($resolvedStyle);
                $this->positionTracker->push($parentRow + $chromeTop, $parentCol + $chromeLeft);
                $contentRow = $parentRow + $chromeTop;
                $contentCol = $parentCol + $chromeLeft;
            }
            try {
                $grid = $resolvedStyle->getGridTemplate();

                // A block container with floats lays its children out around them, those of a block around it too
                $exclusion = $widget->getFloatExclusion();
                if (null === $grid && Direction::Horizontal !== $direction && ($exclusion || array_any($children, fn (AbstractWidget $child) => null !== $this->resolveStyle($child)->getFloat() || $this->letsFloatsOut($child) || $this->escapesFloats($child)))) {
                    // Block layout, like CSS: the margins of a column without a direction collapse; the floats of a plain
                    // block holding nothing else laid out here, beside the floats after it, like the menu of jQuery; those
                    // of a plain block going on past its content, not making it taller, the blocks after it beside them
                    $escaped = $widget->hasFloatsPast() && $this->escapesFloats($widget) ? [] : null;

                    return $this->layoutEngine->layoutFloats($this->unwrapFloats($children), $innerColumns, $innerRows, $gap, $exclusion, null === $resolvedStyle->getDirection() && 0 === $gap, $escaped, $widget->getMarginAbove());
                }
                // No float around its children any more
                foreach ($children as $child) {
                    LayoutEngine::clearFloatsAround($child, false);
                }

                return null !== $grid ? $this->layoutEngine->layoutGrid($children, $innerColumns, $innerRows, $grid, $gap, $resolvedStyle->getRowGap() ?? 0, $verticalAlign, $resolvedStyle->getReverseOrder() ?? false, $resolvedStyle->getJustifyItems(), $contentRows, $alignContent, maxRows: null !== $resolvedStyle->getScroll() ? null : self::MAX_LINES) : $this->layoutEngine->layout(
                    $children,
                    $innerColumns,
                    $innerRows,
                    $gap,
                    $direction,
                    $gapLine,
                    Direction::Horizontal === $direction ? $verticalAlign : null,
                    $resolvedStyle->getJustify(),
                    $resolvedStyle->getWrap() ? ($resolvedStyle->getRowGap() ?? 0) : null,
                    $resolvedStyle->getReverseOrder() ?? false,
                    // Block layout, like CSS: the margins of a column without a direction collapse, those of a flex column do not
                    null === $resolvedStyle->getDirection() && 0 === $gap,
                    // A flex column places each item across: align-items, or align-self; a row of inline content aligns
                    // each of its lines like text-align
                    // A row of inline content: by its text-align, right to left too, else as its row
                    $inlineFlow ? match ($resolvedStyle->getTextAlign()) {
                        TextAlign::Center => Align::Center,
                        TextAlign::Right => Align::Right,
                        default => $resolvedStyle->getAlign(),
                    } : $resolvedStyle->getAlign(),
                    Direction::Vertical === $resolvedStyle->getDirection(),
                    // A flex column of a set height shares its free rows
                    $columnRows ?? $contentRows,
                    $alignContent,
                    true === $resolvedStyle->getWrapReverse(),
                    $inlineFlow,
                );
            } finally {
                $this->positionTracker->pop();
            }
        };
        [$innerRows, $contentRows, $columnRows] = $boxRows;

        // A scroll container of bounded height shows a window of its content; the scrollbar takes the last column
        $scroll = $resolvedStyle->getScroll();
        $viewport = null === $scroll || !$scroll->vertical ? null : ($resolvedStyle->getMaxRows() ?? ($context->shouldFillRows() ? $innerRows : null));
        // The rows of the ::scroll-button() are not in it: the up one above, the others below
        if (null !== $viewport && [] !== $scrollButtons = $widget->getScrollButtons()) {
            $viewport = max(1, $viewport - (isset($scrollButtons['up']) ? 1 : 0) - (array_intersect_key($scrollButtons, ['down' => 1, 'left' => 1, 'right' => 1]) ? 1 : 0));
        }
        // Nor is the row of the scroll-marker-group
        if (null !== $viewport && null !== $widget->getScrollMarkers()[0]) {
            $viewport = max(1, $viewport - 1);
        }
        $bar = null !== $viewport && $scroll->bar && $innerColumns > 1 ? 1 : 0;
        // Multi-column: laid out at the width of a column, then cut into columns
        $multiColumn = $resolvedStyle->getColumns();
        [$columnCount, $columnWidth] = $multiColumn?->resolve($innerColumns) ?? [1, $innerColumns];
        // overflow-x: auto, the content is laid out as wide as it needs not to be cut, and shown through a window
        $contentWidth = 0;
        $needs = [];
        if (null !== $scroll && $scroll->horizontal && 1 === $columnCount && $innerColumns > 1) {
            $horizontal = Direction::Horizontal === $resolvedStyle->getDirection() && !$resolvedStyle->getWrap();
            foreach ($children as $child) {
                $needs[] = [$child, $need = $this->measureMinContentWidth($child, $innerColumns, $innerRows)];
                $contentWidth = $horizontal ? $contentWidth + $need : max($contentWidth, $need);
            }
            $contentWidth += $horizontal ? ($resolvedStyle->getGap() ?? 0) * max(0, \count($children) - 1) : 0;
            if ($horizontal) {
                $needs = [];
            }
            // A grid: as wide as its tracks, like the cards of the Guardian in tracks wider than the screen, side by side
            if (null !== $grid = $resolvedStyle->getGridTemplate()) {
                $tracks = $grid->resolve($innerColumns, $gap, static fn (): int => 0);
                $contentWidth = max($contentWidth, min(self::MAX_CLIPPED_COLUMNS, array_sum($tracks) + $gap * max(0, \count($tracks) - 1)));
            }
        }
        // overflow-x: hidden or clip: a child of a width wider than the box laid out at it, cut at its side, like the
        // list of slides of 625rem of the carousel of Go showing its first, not all of them squeezed in it; the others at
        // the width of the box, like those of a box scrolled sideways
        $clipped = false;
        if (null === $scroll && 1 === $columnCount) {
            $wide = [];
            foreach ($children as $child) {
                $childStyle = $this->resolveStyle($child);
                // Of a width set as a length, not a max-width nor a percentage, which are as wide as the box at most
                if (null !== ($width = $childStyle->getMaxColumns()) && $width > $innerColumns && !$childStyle->getHidden() && !($childStyle->getPosition()?->isOutOfFlow() ?? false)) {
                    $wide[] = [$child, min($width + ($childStyle->getMargin()?->left ?? 0) + ($childStyle->getMargin()?->right ?? 0), self::MAX_CLIPPED_COLUMNS)];
                }
            }
            $wide = array_values(array_filter($wide, fn (array $entry): bool => $this->hasLengthWidth($entry[0])));
            if ($wide && $this->clipsSideways($widget)) {
                $clipped = true;
                $contentWidth = max(array_column($wide, 1));
                $horizontal = Direction::Horizontal === $resolvedStyle->getDirection();
                $needs = $horizontal ? [] : array_map(static fn (AbstractWidget $child): array => [$child, \in_array($child, array_column($wide, 0), true) ? $contentWidth : 0], $children);
            }
        }
        // The blocks that fit keep the width of the window, like CSS: only what does not fit goes past it
        $layoutSideways = function (int $width, int $visible) use ($layout, $needs): LineBufferInterface {
            foreach ($needs as [$child, $need]) {
                if ($need <= $visible && $width > $visible) {
                    $this->sidewaysCaps[$child] = $visible;
                    ++$this->styleEpoch;
                }
            }
            try {
                $lines = $layout($width);
                // Their slots are as wide as the content: their boxes are not
                foreach ($needs as [$child]) {
                    if (isset($this->sidewaysCaps[$child]) && null !== ($rect = $this->positionTracker->getWidgetRect($child)) && $rect->columns > $visible) {
                        $this->positionTracker->setWidgetRect($child, new WidgetRect($rect->row, $rect->col, $visible, $rect->rows));
                    }
                }

                return $lines;
            } finally {
                foreach ($needs as [$child]) {
                    if (isset($this->sidewaysCaps[$child])) {
                        unset($this->sidewaysCaps[$child]);
                        ++$this->styleEpoch;
                    }
                }
            }
        };
        $sidewaysBar = static fn (int $visible): int => null !== $scroll && $scroll->horizontal && $scroll->bar && ($scroll->alwaysSideways || $contentWidth > $visible) ? 1 : 0;
        $rowsShown = null === $viewport ? null : max(1, $viewport - $sidewaysBar($innerColumns));
        // overflow: scroll, or scrollbar-gutter: stable, keeps the column of the scrollbar from the start
        // ::before and ::after of a block: their text on lines of their own, before and after the children; not an empty
        // or hidden one, like the boxes of a clearfix
        $generated = [null, null];
        if ((ContainerWidget::class === $widget::class || $widget instanceof FormWidget) && Direction::Horizontal !== $resolvedStyle->getDirection() && null === $resolvedStyle->getGridTemplate() && 1 === $columnCount) {
            foreach ($widget->getGeneratedContent() as $i => $text) {
                $element = 0 === $i ? 'before' : 'after';
                if (null === $text || self::isBlankContent($text)) {
                    continue;
                }
                $elementStyle = $this->styleSheet->resolveElement($widget, $element);
                if (true !== $elementStyle->getHidden() && true !== $elementStyle->getInvisible() && 0 !== $elementStyle->getMaxRows()) {
                    $generated[$i] = [$text, $elementStyle];
                }
            }
        }
        // Their images, like content: url(icon.png), drawn in their lines
        $images = new InlineImages([], $widget->getGeneratedImages());
        $scheme = $this->mediaContext->colorScheme;
        // Every move of its laid out content in its box goes through here: what is tracked in it moves, and so do the
        // floats going on past it, placed once its content is where it is drawn, see ContainerWidget::setEscapedFloats()
        $shiftContent = function (int $columns, int $rows) use ($children, &$origin): void {
            $this->positionTracker->shiftContentPositions($children, $columns, $rows);
            $origin[0] += $columns;
            $origin[1] += $rows;
        };
        $decorate = static function (LineBufferInterface $lines, int $width) use ($generated, $images, $scheme, $shiftContent, $clearance): LineBufferInterface {
            $wrap = static fn (array $content) => $images->draw(array_map(static fn (string $line) => $content[1]->apply($line), TextWrapper::wrapTextWithAnsi($content[0], max(1, $width))), $scheme);
            if (null !== $generated[0]) {
                $before = $wrap($generated[0]);
                $lines = new ConcatenatedLineBuffer([new ArrayLineBuffer($before), $lines]);
                $shiftContent(0, \count($before));
            }
            // The rows of the ::before that clears, above its text
            if ($clearance > 0) {
                $lines = new ConcatenatedLineBuffer([new ArrayLineBuffer(array_fill(0, $clearance, '')), $lines]);
                $shiftContent(0, $clearance);
            }
            if (null !== $generated[1]) {
                $lines = new ConcatenatedLineBuffer([$lines, new ArrayLineBuffer($wrap($generated[1]))]);
            }

            return $lines;
        };

        $visible = $innerColumns - ($scroll?->always || $scroll?->gutter ? $bar : 0);
        $layoutWidth = $columnCount > 1 ? $columnWidth : max($visible, $contentWidth);
        $childLines = $columnCount > 1 ? $layout($layoutWidth) : $decorate($layoutSideways($layoutWidth, $visible), $layoutWidth);
        if (1 === $columnCount && null !== $viewport && !$scroll->always && !$scroll->gutter && $bar && \count($childLines) > $rowsShown) {
            $layoutWidth = max($innerColumns - $bar, $contentWidth);
            $childLines = $decorate($layoutSideways($layoutWidth, $innerColumns - $bar), $layoutWidth);
        }

        // Block layout, like CSS: with nothing between them, the margins of a parent and of its first and last children collapse
        // Not its top margin past a ::before that clears, which separates them: its bottom one still
        if (null === $resolvedStyle->getDirection() && \count($childLines) > 0 && [null, null] === $generated) {
            $childLines = $this->collapseParentMargins($resolvedStyle, $children, $childLines, $innerColumns, $dropped, $clearance > 0);
            if ($dropped > 0) {
                $shiftContent(0, -$dropped);
            }
        }

        if ($columnCount > 1 && null !== $multiColumn) {
            if (array_any($children, fn (AbstractWidget $child) => true === $this->resolveStyle($child)->getSpanAll())) {
                $childLines = $this->flowAroundSpans($children, $layout, $multiColumn, $columnCount, $columnWidth, $innerColumns, $contentRow);
            } else {
                $childLines = $this->flowIntoColumns($children, $childLines, $multiColumn, $columnCount, $columnWidth, $contentRow, $multiColumn->balance ? null : $resolvedStyle->getMaxRows());
            }
        }

        if ($hasVerticalAlign && \count($childLines) < $innerRows
            && ($context->shouldFillRows() || $widget->isVerticallyExpanded())
        ) {
            if (0 < $verticalOffset = $this->layoutEngine->computeVerticalAlignOffset(\count($childLines), $innerRows, $verticalAlign)) {
                $childLines = new ConcatenatedLineBuffer([
                    new ArrayLineBuffer(array_fill(0, $verticalOffset, '')),
                    $childLines,
                ]);
                $shiftContent(0, $verticalOffset);
            }
        }

        // A table cell stretched to the height of its row places its content in it, like CSS vertical-align
        $cellRows = TableRole::Cell === $resolvedStyle->getTableRole() ? $resolvedStyle->getMinRows() : null;
        if ($hasVerticalAlign && null !== $cellRows && \count($childLines) < $cellRows
            && !$context->shouldFillRows() && !$widget->isVerticallyExpanded()
            && 0 < $verticalOffset = $this->layoutEngine->computeVerticalAlignOffset(\count($childLines), $cellRows, $verticalAlign)
        ) {
            $childLines = new ConcatenatedLineBuffer([
                new ArrayLineBuffer(array_fill(0, $verticalOffset, '')),
                $childLines,
            ]);
            $shiftContent(0, $verticalOffset);
        }

        $shouldPad = ($widget->isVerticallyExpanded() && !$context->shouldFillRows())
            || ($context->shouldFillRows() && $hasVerticalAlign);
        if ($shouldPad && \count($childLines) < $innerRows) {
            $childLines = new ConcatenatedLineBuffer([
                $childLines,
                new ArrayLineBuffer(array_fill(0, $innerRows - \count($childLines), '')),
            ]);
        }

        // A wrapping row places each of its lines, see LayoutEngine::layoutWrapped()
        $wrapsRow = Direction::Horizontal === $resolvedStyle->getDirection() && $resolvedStyle->getWrap() && null === $resolvedStyle->getJustify() && null === $resolvedStyle->getGridTemplate();
        if ($hasAlign && !$wrapsRow && Direction::Vertical !== $resolvedStyle->getDirection() && 0 < $alignOffset = $this->layoutEngine->computeAlignOffset($childLines, $innerColumns, $align)) {
            $childLines = $this->layoutEngine->shiftLines($childLines, $alignOffset);
            $shiftContent($alignOffset, 0);
        }

        // position: relative with offsets: the child is drawn moved, its room in the flow kept empty
        if ($this->positionTracker->isActive()) {
            $childLines = $this->offsetRelative($children, $childLines, $innerColumns, $contentRow, $contentCol);
        }

        if (null === $scroll && $this->positionTracker->isActive() && $this->clipsOverflow($widget, true)) {
            $height = \count($childLines);
            $pending = $children;
            while ($pending) {
                $child = array_pop($pending);
                if (PositionMode::Fixed === $this->resolveStyle($child)->getPosition()?->mode) {
                    continue;
                }
                if (null !== $rect = $this->positionTracker->getWidgetRect($child)) {
                    $height = max($height, $rect->row + $rect->rows - $contentRow);
                }
                if ($child instanceof ParentInterface && !$this->clipsOverflow($child, true) && null === $this->resolveStyle($child)->getScroll()) {
                    array_push($pending, ...$child->all());
                }
            }
            $limit = $resolvedStyle->getMaxRows();
            $client = null === $limit ? $height : min($height, $this->chromeApplier->computeInnerDimensions($columns, (int) $limit, $resolvedStyle)[1]);
            $widget->setScrollMetrics(0, $height, $client);
            $widget->setHorizontalScrollMetrics(0, max($innerColumns, $childLines->getMaxVisibleWidth()), $innerColumns);
        }

        if ($clipped) {
            $childLines = new ArrayLineBuffer(array_map(static fn (string $line): string => AnsiUtils::truncateToWidth($line, $visible, ''), $childLines->toArray()));
        }

        // The window sideways, then the one down, then the horizontal scrollbar under them
        $barShown = null !== $viewport && $bar && (\count($childLines) > $rowsShown || $scroll->always || $scroll->gutter);
        $visibleColumns = $innerColumns - ($barShown ? 1 : 0);
        $sideways = null !== $scroll && $scroll->horizontal && 1 === $columnCount ? $sidewaysBar($visibleColumns) : 0;
        if (null !== $scroll && $scroll->horizontal && 1 === $columnCount) {
            $childLines = $this->scrollSideways($widget, $children, $childLines, $visibleColumns, max($layoutWidth, $contentWidth), $contentCol, $resolvedStyle);
        }
        if (null !== $viewport) {
            $childLines = $this->scrollContent($widget, $children, $childLines, max(1, $viewport - $sideways), $bar ? $innerColumns - 1 : null, $contentRow, $contentCol, $resolvedStyle);
        }
        if ($sideways) {
            $childLines = new ConcatenatedLineBuffer([$childLines, new ArrayLineBuffer([$this->horizontalBar($widget, $visibleColumns, $scroll).($barShown ? ' ' : '')])]);
        }
        if (null !== $scroll && ([] !== $widget->getScrollButtons() || null !== $widget->getScrollMarkers()[0])) {
            $childLines = $this->addScrollButtons($widget, $children, $childLines, $innerColumns, $resolvedStyle);
        }

        // What cannot shrink to the room, like the tracks and gaps of a grid of more columns than the box has: cut at
        // its edge, which a line of the terminal cannot go past, the overflow of CSS clipped
        if ($childLines->getMaxVisibleWidth() > $innerColumns) {
            $childLines = new ArrayLineBuffer(array_map(static fn (string $line): string => AnsiUtils::visibleWidth($line) > $innerColumns && !AnsiUtils::containsImage($line) ? AnsiUtils::sliceToWidth($line, 0, $innerColumns) : $line, $childLines->toArray()));
        }

        // The captions of a table drawn here, its borders separate or without rows, are outside its box, like CSS: above
        // and below its border and its background, like the caption under the image of a thumbnail of Wikipedia
        if (TableRole::Table === $resolvedStyle->getTableRole()) {
            $margin = $resolvedStyle->withResolvedBox($columns)->getMargin();
            [$top, $topLines, $bottom, $bottomLines, $captions] = $this->captionRows($children, $innerColumns, $innerRows, \count($childLines), $boxColumns = max(1, $columns - ($margin->left ?? 0) - ($margin->right ?? 0)));
            $this->chromeApplier->setOutsideRows($widget, $top, $topLines, $bottom, $bottomLines);
        }
        // The floats going on past it where its content is in its box, its first row and column 0
        if ($escaped) {
            [$right, $down] = $origin;
            // Drawn with its effects, like its filter, as the rest of its content
            $widget->setEscapedFloats(array_map(fn (array $float): array => [$float[0], $float[1] + $down, $float[2], $float[3], $float[4], $float[5] + ('left' === $float[0] ? $right : -$right), $float[6], $this->paintEffects($resolvedStyle, $float[7]), $float[8] + $down], $escaped));
        }
        $lines = $this->chromeApplier->apply($childLines, $columns, $resolvedStyle, $widget);
        // The captions where they are drawn, outside the box: what is in them too, like their links
        if ([] !== ($captions ?? []) && $this->positionTracker->isActive()) {
            [$originRow, $originCol] = $this->positionTracker->currentOffset();
            $topRow = $originRow + ($margin->top ?? 0);
            $bottomRow = $originRow + \count($lines) - ($margin->bottom ?? 0) - \count($bottomLines);
            foreach ($captions as [$caption, $height, $below]) {
                $rect = new WidgetRect($below ? $bottomRow : $topRow, $originCol + ($margin->left ?? 0), $boxColumns, $height);
                $below ? $bottomRow += $height : $topRow += $height;
                if (!$this->positionTracker->moveSubtree($caption, $rect)) {
                    $this->positionTracker->setWidgetRect($caption, $rect);
                }
            }
        }

        return null !== $legend ? $this->drawLegend($legend, $lines, $columns, $resolvedStyle) : $lines;
    }

    /**
     * Draws a legend over the top border of its container, a column after its left corner, as wide as its content
     * within the border.
     */
    private function drawLegend(AbstractWidget $legend, LineBufferInterface $lines, int $columns, Style $style): LineBufferInterface
    {
        $border = $style->getBorder();
        $margin = $style->getMargin();
        $row = $margin->top ?? 0;
        $column = ($margin->left ?? 0) + ($border->left ?? 0) + 1;
        $room = $columns - $column - ($border->right ?? 0) - ($margin->right ?? 0) - 1;
        $all = $lines->toArray();
        if ($room < 1 || !isset($all[$row])) {
            return $lines;
        }
        $width = max(1, min($room, $this->measureIntrinsicWidth($legend, $room, 1)));
        $tracked = $this->positionTracker->isActive();
        [$baseRow, $baseCol] = $tracked ? $this->positionTracker->currentOffset() : [0, 0];
        $tracked && $this->positionTracker->push($baseRow + $row, $baseCol + $column);
        $text = $this->renderWidgetLines($legend, new RenderContext($width, 1, null, $this->fontRegistry))->toArray()[0] ?? '';
        $tracked && $this->positionTracker->pop();
        $this->positionTracker->setWidgetRect($legend, new WidgetRect($baseRow + $row, $baseCol + $column, $width, 1));
        $line = $all[$row];
        $all[$row] = AnsiUtils::sliceToWidth($line, 0, $column).AnsiUtils::sliceToWidth($text, 0, $width).AnsiUtils::sliceToWidth($line, $column + $width, max(0, AnsiUtils::visibleWidth($line) - $column - $width));

        return new ArrayLineBuffer($all);
    }

    /**
     * Draws a table whose borders collapse, like CSS border-collapse: collapse: one grid of lines, with junctions,
     * around and between the cells, the table as wide as its columns.
     *
     * A line is there when the table, a row group, a row or a cell beside it has a border on that side, like CSS: the
     * border-bottom of the cells draws the lines under them only, that of a <thead> the line under it. Their style
     * and color are the table's, or the first border's. A cell spanning columns or rows spans the lines between them,
     * its text going on through them; the junctions follow.
     *
     * @param AbstractWidget[] $children
     */
    /**
     * @param bool $widthOnly Whether only its width is wanted: one blank line as wide as it, its cells not drawn
     */
    private function renderCollapsedTable(ContainerWidget $table, array $children, RenderContext $context, Style $style, bool $widthOnly = false): ?LineBufferInterface
    {
        $chrome = $style->withBorder(new Border(0, 0, 0, 0));
        [$innerColumns, $innerRows] = $this->chromeApplier->computeInnerDimensions($context->getColumns(), $context->getRows(), $chrome);

        // The rows, through their groups, and the captions above and below
        $rows = [];
        $captions = [[], []];
        foreach ($children as $child) {
            $role = $this->resolveStyle($child)->getTableRole();
            if (TableRole::Row === $role) {
                $rows[] = $child;
            } elseif (TableRole::RowGroup === $role && $child instanceof ParentInterface) {
                foreach ($child->all() as $row) {
                    if (TableRole::Row === $this->resolveStyle($row)->getTableRole() && !$this->resolveStyle($row)->getHidden()) {
                        $rows[] = $row;
                    }
                }
            } elseif (TableRole::Column !== $role) {
                $captions[$this->resolveStyle($child)->getCaptionBottom() ? 1 : 0][] = $child;
            }
        }
        if (!$rows) {
            return null;
        }

        // The cells, each with its first row and column and its spans; $owners[row][column] is the cell there
        $cells = [];
        $owners = [];
        $count = 0;
        $firstCellStyle = null;
        $firstLineStyle = null;
        // The sides with a border, top, right, bottom and left: of each cell, row and row group, and of the table
        $sidesOf = static fn (?Border $border): array => null === $border ? [false, false, false, false] : [$border->top > 0, $border->right > 0, $border->bottom > 0, $border->left > 0];
        $cellSides = [];
        $rowSides = [];
        $groupSides = [];
        foreach ($rows as $r => $row) {
            $rowStyle = $this->declaredStyle($row);
            $rowSides[$r] = $sidesOf($rowStyle->getBorder());
            $group = $row->getParent();
            $groupStyle = $group !== $table && null !== $group ? $this->declaredStyle($group) : null;
            // A group's top is of its first row, its bottom of its last
            $groupSides[$r] = [$sidesOf($groupStyle?->getBorder()), $group !== ($rows[$r - 1] ?? null)?->getParent(), $group !== ($rows[$r + 1] ?? null)?->getParent()];
            foreach ([$rowStyle, $groupStyle] as $lineStyle) {
                if (null !== $lineStyle && \in_array(true, $sidesOf($lineStyle->getBorder()), true)) {
                    $firstLineStyle ??= $lineStyle;
                }
            }
        }
        foreach ($rows as $r => $row) {
            $column = 0;
            foreach ($row instanceof ParentInterface ? $row->all() : [] as $cell) {
                $cellStyle = $this->declaredStyle($cell);
                if ($cellStyle->getHidden()) {
                    continue;
                }
                // Past the columns a cell spanning rows from above takes
                while (isset($owners[$r][$column])) {
                    ++$column;
                }
                if ($column >= 200) {
                    break;
                }
                $span = max(1, min($cellStyle->getColumnSpan() ?? 1, 200 - $column));
                $rowSpan = max(1, min($cellStyle->getRowSpan() ?? 1, \count($rows) - $r));
                $index = \count($cells);
                $cells[] = [$cell, $r, $column, $rowSpan, $span];
                for ($y = $r; $y < $r + $rowSpan; ++$y) {
                    for ($x = $column; $x < $column + $span; ++$x) {
                        $owners[$y][$x] = $index;
                    }
                }
                $column += $span;
                $cellSides[$index] = $sidesOf($cellStyle->getBorder());
                if (\in_array(true, $cellSides[$index], true)) {
                    $firstCellStyle ??= $cellStyle;
                }
            }
            $count = max($count, $column, ...array_map(static fn (int $c) => $c + 1, array_keys($owners[$r] ?? [-1 => true])));
        }
        // The lines in the style of the first cell with a border, else of the first row or row group
        $firstCellStyle ??= $firstLineStyle;
        $tableBorder = $style->getBorder();
        $tableSides = $sidesOf($tableBorder);
        if (0 === $count) {
            return $this->chromeApplier->apply(new ArrayLineBuffer([]), $context->getColumns(), $chrome, $table);
        }

        $owner = static fn (?int $r, int $c): ?int => null === $r || $c < 0 || $c >= $count ? null : $owners[$r][$c] ?? -1;
        $last = \count($rows) - 1;
        // A side of the cell at a row and column, when it is at that edge of its spans
        $cellSide = static function (int $r, int $c, int $side) use ($owner, $cells, $cellSides): bool {
            $i = $owner($r, $c) ?? -1;
            if ($i < 0) {
                return false;
            }
            [, $row, $column, $rowSpan, $span] = $cells[$i];

            return $cellSides[$i][$side] && match ($side) {
                0 => $row === $r,
                1 => $column + $span - 1 === $c,
                2 => $row + $rowSpan - 1 === $r,
                default => $column === $c,
            };
        };
        // A side of a row, or of its group when it is the first or last row of it
        $rowSide = static fn (int $r, int $side): bool => $rowSides[$r][$side] || ($groupSides[$r][0][$side] && match ($side) {
            0 => $groupSides[$r][1],
            2 => $groupSides[$r][2],
            default => true,
        });
        // A vertical line at the boundary before column $c, in row $r; a horizontal one over column $c, between rows $a and $b
        $vertical = static fn (?int $r, int $c): bool => null !== $r && match (true) {
            0 === $c => $tableSides[3] || $rowSide($r, 3) || $cellSide($r, 0, 3),
            $count === $c => $tableSides[1] || $rowSide($r, 1) || $cellSide($r, $count - 1, 1),
            default => $owner($r, $c - 1) !== $owner($r, $c) && ($cellSide($r, $c - 1, 1) || $cellSide($r, $c, 3)),
        };
        $horizontal = static fn (?int $a, ?int $b, int $c): bool => $c >= 0 && $c < $count && (null !== $a || null !== $b) && (null === $a || null === $b || $owner($a, $c) !== $owner($b, $c) || $owner($a, $c) < 0)
            && ((null === $a ? $tableSides[0] : $rowSide($a, 2) || $cellSide($a, $c, 2)) || (null === $b ? $tableSides[2] : $rowSide($b, 0) || $cellSide($b, $c, 0)));
        // The rows and columns of lines: around the table where a line is on that side, between rows where one is
        $left = array_any(array_keys($rows), static fn (int $r) => $vertical($r, 0));
        $right = array_any(array_keys($rows), static fn (int $r) => $vertical($r, $count));
        $top = array_any(range(0, $count - 1), static fn (int $c) => $horizontal(null, 0, $c));
        $bottom = array_any(range(0, $count - 1), static fn (int $c) => $horizontal($last, null, $c));
        $between = [];
        for ($r = 1; $r <= $last; ++$r) {
            $between[$r] = array_any(range(0, $count - 1), static fn (int $c) => $horizontal($r - 1, $r, $c));
        }
        // The lines between the rows of a span
        $rulesIn = static fn (int $from, int $rows): int => $rows > 1 ? \count(array_filter(range($from, $from + $rows - 2), static fn (int $r) => $between[$r] ?? false)) : 0;
        $frame = ($left ? 1 : 0) + ($right ? 1 : 0);

        // Column widths: single cells first, then those spanning columns widen what they span
        $saved = $this->positionTracker->suppressStack();
        $widths = array_fill(0, $count, 1);
        // A fixed table of a set width does not measure its cells: the columns come from its <col> elements and its first row
        $fixed = true === $style->getFixedLayout() && (null !== $style->getMaxColumns() || null !== $style->getWidthPercent());
        // At their widest, not wrapped to the room: the columns are shrunk to it below, down to their longest words
        $measured = $fixed ? [] : array_map(fn (array $c) => $this->measureIntrinsicWidth($c[0], max($innerColumns, 4096), $innerRows), $cells);
        if ($fixed) {
            $firstRow = array_values(array_map(static fn (array $c) => [$c[0], $c[2], $c[4]], array_filter($cells, static fn (array $c) => 0 === $c[1])));
            $widths = $this->layoutEngine->fixedTableWidths($this->layoutEngine->columnElementWidths($table, $innerColumns), $firstRow, $count, $innerColumns, max($count, $innerColumns - $frame - ($count - 1)), 1);
        }
        foreach ($fixed ? [] : [true, false] as $single) {
            foreach ($cells as $i => [, , $column, , $span]) {
                if ($single !== (1 === $span)) {
                    continue;
                }
                $missing = $measured[$i] - (array_sum(\array_slice($widths, $column, $span)) + $span - 1);
                for ($k = 0; $missing > 0 && $k < $span; ++$k) {
                    $share = intdiv($missing + $span - 1 - $k, $span - $k);
                    $widths[$column + $k] += $share;
                    $missing -= $share;
                }
            }
        }
        $this->positionTracker->restoreStack($saved);
        // The columns are one apart: a line between cells with borders, a space otherwise
        $budget = max($count, $innerColumns - $frame - ($count - 1));
        if (array_sum($widths) > $budget) {
            // Like CSS, the columns give way down to their longest words; past that, they are cut evenly
            $floors = array_fill(0, $count, 1);
            $saved = $this->positionTracker->suppressStack();
            foreach ($fixed ? [] : $cells as $i => [$cell, , $column, , $span]) {
                if (1 === $span) {
                    $floors[$column] = max($floors[$column], min($measured[$i], $this->measureMinContentWidth($cell, $innerColumns, $innerRows)));
                }
            }
            $this->positionTracker->restoreStack($saved);
            // Past their longest words, the columns are cut as long as those words are, not as wide as the columns are
            $widths = array_sum($floors) <= $budget ? LayoutEngine::shrinkAboveFloors($widths, $floors, $budget) : array_values(LayoutEngine::shrinkToFit($floors, $budget));
        } elseif (!$fixed && (null !== $style->getMaxColumns() || null !== $style->getWidthPercent() || null !== $style->getMinWidthPercent()) && ($extra = min($budget, null === $style->getMaxColumns() && null === $style->getWidthPercent() ? (int) floor(($innerColumns - $frame) * $style->getMinWidthPercent() / 100) - ($count - 1) : $budget) - array_sum($widths)) > 0) {
            // A table of a set width, like width: 100%: the columns share the room left, as they are wide
            $sum = array_sum($widths);
            $given = 0;
            foreach ($widths as $c => $width) {
                $share = $c === $count - 1 ? $extra - $given : intdiv($extra * $width, $sum);
                $widths[$c] += $share;
                $given += $share;
            }
        }
        // Its captions are no narrower than their longest words, like CSS: the columns widen to them, as they are wide
        if ([] !== $captions[0] || [] !== $captions[1]) {
            $saved = $this->positionTracker->suppressStack();
            $floor = 0;
            foreach ([...$captions[0], ...$captions[1]] as $caption) {
                $floor = max($floor, $this->measureMinContentWidth($caption, $innerColumns, $innerRows));
            }
            $this->positionTracker->restoreStack($saved);
            if (($extra = min($innerColumns, $floor) - (array_sum($widths) + $frame + $count - 1)) > 0) {
                $sum = array_sum($widths);
                $given = 0;
                foreach ($widths as $c => $width) {
                    $share = $c === $count - 1 ? $extra - $given : intdiv($extra * $width, $sum);
                    $widths[$c] += $share;
                    $given += $share;
                }
            }
        }
        $total = array_sum($widths) + $frame + $count - 1;
        if ($widthOnly) {
            return new ArrayLineBuffer([str_repeat(' ', $total + $context->getColumns() - $innerColumns)]);
        }
        $colX = [];
        for ($c = 0, $x = $left ? 1 : 0; $c < $count; $x += $widths[$c] + 1, ++$c) {
            $colX[$c] = $x;
        }

        // The characters of the lines, in the style of the table or of the first cell
        $corner = (null !== $tableBorder && $tableBorder->top + $tableBorder->left > 0 ? $tableBorder : $firstCellStyle?->getBorder() ?? $tableBorder)?->pattern->getChars()[0][0] ?? '┌';
        [$h, $v, $topLeft, $topJoin, $topRight, $midLeft, $cross, $midRight, $bottomLeft, $bottomJoin, $bottomRight] = match ($corner) {
            '╔' => ['═', '║', '╔', '╦', '╗', '╠', '╬', '╣', '╚', '╩', '╝'],
            '┏' => ['━', '┃', '┏', '┳', '┓', '┣', '╋', '┫', '┗', '┻', '┛'],
            '╭' => ['─', '│', '╭', '┬', '╮', '├', '┼', '┤', '╰', '┴', '╯'],
            default => ['─', '│', '┌', '┬', '┐', '├', '┼', '┤', '└', '┴', '┘'],
        };
        // A junction by the lines that meet there: up, down, left, right
        $junctions = ['1111' => $cross, '1110' => $midRight, '1101' => $midLeft, '1100' => $v, '0111' => $topJoin, '1011' => $bottomJoin, '0110' => $topRight, '0101' => $topLeft, '1010' => $bottomRight, '1001' => $bottomLeft, '0011' => $h, '1000' => $v, '0100' => $v, '0010' => $h, '0001' => $h, '0000' => ' '];
        $color = $tableBorder->color ?? $firstCellStyle?->getBorder()->color;
        $paint = static fn (string $text): string => null === $color || ' ' === $text ? $text : (new Style(color: $color))->apply($text);

        // The cells rendered at their width, and the rows as tall as their cells
        $tracked = $this->positionTracker->isActive();
        $rendered = [];
        $heights = array_fill(0, \count($rows), 1);
        $saved = $this->positionTracker->suppressStack();
        // Not those of the rows that start past the rows drawn, like a table of a thousand rows of cells of a huge padding,
        // which would fill memory with lines never drawn
        $measuredRow = 0;
        $rowsAbove = 0;
        foreach ($cells as $i => [$cell, $r, $column, $rowSpan, $span]) {
            $width = array_sum(\array_slice($widths, $column, $span)) + $span - 1;
            for (; $measuredRow < $r; ++$measuredRow) {
                // Without the rules between them, at most as far down as they are
                $rowsAbove += $heights[$measuredRow];
            }
            if ($rowsAbove > self::MAX_LINES) {
                $rendered[$i] = [[], $width];
                continue;
            }
            $rendered[$i] = [$this->renderWidgetLines($cell, new RenderContext($width, $innerRows, null, $this->fontRegistry))->toArray(), $width];
            if (1 === $rowSpan) {
                $heights[$r] = max($heights[$r], \count($rendered[$i][0]));
            }
        }
        $this->positionTracker->restoreStack($saved);
        foreach ($cells as $i => [, $r, , $rowSpan]) {
            $room = array_sum(\array_slice($heights, $r, $rowSpan)) + $rulesIn($r + 1, $rowSpan);
            if ($rowSpan > 1 && \count($rendered[$i][0]) > $room) {
                $heights[$r + $rowSpan - 1] += \count($rendered[$i][0]) - $room;
            }
        }
        // Where the content of each cell starts in the rows it spans, like CSS vertical-align
        $offsets = [];
        foreach ($cells as $i => [$cell, $r, , $rowSpan]) {
            $room = array_sum(\array_slice($heights, $r, $rowSpan)) + $rulesIn($r + 1, $rowSpan);
            $offsets[$i] = $this->layoutEngine->computeVerticalAlignOffset(\count($rendered[$i][0]), $room, $this->resolveStyle($cell)->getVerticalAlign() ?? VerticalAlign::Top);
        }
        // The line of a cell at a row: what the rows above it in its span and the lines between them took
        $lineOf = static fn (int $i, int $r, int $line): int => array_sum(\array_slice($heights, $cells[$i][1], $r - $cells[$i][1])) + $rulesIn($cells[$i][1] + 1, $r - $cells[$i][1] + 1) + $line;
        // Each line of a cell cut to its column once: the lines alike, like those of a tall padding, share it
        $sliced = [];
        $slice = static function (int $i, int $at) use ($rendered, $offsets, &$sliced): string {
            $line = $rendered[$i][0][$at - $offsets[$i]] ?? '';

            return $sliced[$i][$line] ??= AnsiUtils::sliceToWidth($line, 0, $rendered[$i][1]);
        };
        // The columns a line is cut to below: what is past them is not joined, like the cells of a table far wider
        $room = $this->chromeApplier->computeInnerDimensions($context->getColumns(), $context->getRows(), $chrome)[0];

        // The content row offset of the table, for the positions of its cells
        [$baseRow, $baseCol] = $tracked ? $this->positionTracker->currentOffset() : [0, 0];
        [$chromeTop, $chromeLeft] = $this->chromeApplier->computeChromeOffset($chrome);
        $baseRow += $chromeTop;
        $baseCol += $chromeLeft;

        $out = [];
        $renderCaptions = function (array $list) use (&$out, $total, $innerRows, $tracked, $baseRow, $baseCol): void {
            foreach ($list as $caption) {
                $tracked && $this->positionTracker->push($baseRow + \count($out), $baseCol);
                $lines = $this->renderWidgetLines($caption, new RenderContext($total, $innerRows, null, $this->fontRegistry));
                $this->positionTracker->pop();
                $this->positionTracker->setWidgetRect($caption, new WidgetRect($baseRow + \count($out), $baseCol, $total, \count($lines)));
                array_push($out, ...$lines->toArray());
            }
        };
        $renderCaptions($captions[0]);

        // A line between rows $a and $b (null above the first, below the last): lines and junctions, or the text of a
        // cell spanning both
        $rule = static function (?int $a, ?int $b) use ($count, $widths, $heights, $owner, $vertical, $horizontal, $junctions, $paint, $h, $lineOf, $slice, $cells, $left, $right): string {
            $text = '';
            for ($c = 0; $c <= $count; ++$c) {
                $through = null !== $a && null !== $b && $c > 0 && $c < $count && $owner($a, $c - 1) === $owner($a, $c) && $owner($b, $c - 1) === $owner($b, $c) && $owner($a, $c) === $owner($b, $c) && $owner($a, $c) >= 0;
                if (!$through && (0 === $c ? $left : ($count !== $c || $right))) {
                    $key = ($vertical($a, $c) ? '1' : '0').($vertical($b, $c) ? '1' : '0').($horizontal($a, $b, $c - 1) ? '1' : '0').($horizontal($a, $b, $c) ? '1' : '0');
                    $text .= $paint($junctions[$key]);
                }
                if ($c === $count) {
                    break;
                }
                $spanning = null !== $a && null !== $b && $owner($a, $c) === $owner($b, $c) && $owner($a, $c) >= 0 ? $owner($a, $c) : null;
                if (null !== $spanning) {
                    // A cell spanning both rows goes on through the line, from its first column
                    if ($cells[$spanning][2] === $c) {
                        $text .= $slice($spanning, $lineOf($spanning, $a, $heights[$a]));
                    }
                    continue;
                }
                $text .= $horizontal($a, $b, $c) ? $paint(str_repeat($h, $widths[$c])) : str_repeat(' ', $widths[$c]);
            }

            return $text;
        };
        // The lines: above the first row, each row's content, between the rows, below the last
        $tops = [];
        if ($top) {
            $out[] = $rule(null, 0);
        }
        foreach ($rows as $r => $row) {
            if ($r > 0 && $between[$r]) {
                $out[] = $rule($r - 1, $r);
            }
            $tops[$r] = \count($out);
            // The pieces of the lines of the row, the same on each line: the lines between the cells, the room where there
            // is none, and the cells, by the line of each where the row starts
            $pieces = [$left ? ($vertical($r, 0) ? $paint($v) : ' ') : ''];
            for ($c = 0; $c < $count; ++$c) {
                $at = $owner($r, $c) ?? -1;
                if ($c > 0 && ($at !== $owner($r, $c - 1) || $at < 0)) {
                    $pieces[] = $vertical($r, $c) ? $paint($v) : ' ';
                }
                if ($at < 0) {
                    // No cell there: a row with fewer cells
                    $pieces[] = str_repeat(' ', $widths[$c]);
                } elseif ($cells[$at][2] === $c) {
                    $pieces[] = [$at, $lineOf($at, $r, 0)];
                }
            }
            $pieces[] = $right ? ($vertical($r, $count) ? $paint($v) : ' ') : '';
            $pieceWidths = array_map(static fn (string|array $piece): int => \is_string($piece) ? AnsiUtils::visibleWidth($piece) : $rendered[$piece[0]][1], $pieces);
            for ($line = 0; $line < $heights[$r]; ++$line) {
                $text = '';
                $width = 0;
                foreach ($pieces as $k => $piece) {
                    $text .= \is_string($piece) ? $piece : $slice($piece[0], $piece[1] + $line);
                    if (($width += $pieceWidths[$k]) > $room) {
                        break;
                    }
                }
                $out[] = $text;
            }
            $this->positionTracker->setWidgetRect($row, new WidgetRect($baseRow + $tops[$r], $baseCol, $total, $heights[$r]));
        }
        if ($bottom) {
            $out[] = $rule($last, null);
        }

        // Each cell tracked where it is drawn, over the rows it spans
        foreach ($cells as $i => [$cell, $r, $column, $rowSpan]) {
            $top = $tops[$r];
            // Past the rows drawn, not rendered (see above)
            if ($top > self::MAX_LINES) {
                continue;
            }
            $bottom = $tops[$r + $rowSpan - 1] + $heights[$r + $rowSpan - 1];
            if ($tracked) {
                // Rendered untracked above: again where it is, so that what it contains is tracked there
                $cell->clearRenderCache();
                $this->positionTracker->push($baseRow + $top + $offsets[$i], $baseCol + $colX[$column]);
                $this->renderWidgetLines($cell, new RenderContext($rendered[$i][1], $innerRows, null, $this->fontRegistry));
                $this->positionTracker->pop();
            }
            $this->positionTracker->setWidgetRect($cell, new WidgetRect($baseRow + $top, $baseCol + $colX[$column], $rendered[$i][1], $bottom - $top));
        }
        // A row group spans its rows, like the <tbody> HTML adds
        $groups = [];
        foreach ($rows as $r => $row) {
            $group = $row->getParent();
            if ($group !== $table && null !== $group) {
                $id = spl_object_id($group);
                $groups[$id] = [$group, $groups[$id][1] ?? $tops[$r], $tops[$r] + $heights[$r]];
            }
        }
        foreach ($groups as [$group, $start, $end]) {
            $this->positionTracker->setWidgetRect($group, new WidgetRect($baseRow + $start, $baseCol, $total, $end - $start));
        }

        $renderCaptions($captions[1]);

        // Columns that cannot shrink to the room, like three of them in a column or two: cut at its edge, a line of the
        // terminal not going past it
        // The same line cut once: the rows alike, like those of tall cells, share it
        $cut = [];
        foreach ($out as $i => $line) {
            $out[$i] = $cut[$line] ??= AnsiUtils::visibleWidth($line) > $room ? AnsiUtils::sliceToWidth($line, 0, $room) : $line;
        }

        return $this->chromeApplier->apply(new ArrayLineBuffer($out), $context->getColumns(), $chrome, $table);
    }

    /**
     * The text of a text block drawn larger by the terminal, like font-size: 2em with the text sizing protocol of
     * kitty: laid out in a room that many times narrower, each line a marker the ScreenWriter draws scaled (see
     * AnsiUtils::scaledTextMarker()), then the room it takes, that many rows tall.
     *
     * @return list<string>
     */
    private function renderScaled(TextWidget $widget, RenderContext $innerContext, int $scale): array
    {
        $out = [];
        foreach ($widget->render($innerContext->withColumns(max(1, intdiv($innerContext->getColumns(), $scale)))) as $line) {
            $width = AnsiUtils::visibleWidth($line);
            // Its room, with the marks of its links over the cells they are drawn in, so that a click on them follows them
            $room = self::scaledRoom($line, $width, $scale);
            $out[] = AnsiUtils::scaledTextMarker($line, $scale).$room;
            for ($row = 1; $row < $scale; ++$row) {
                $out[] = $room;
            }
        }

        return $out;
    }

    /**
     * The room of a line of text drawn larger: spaces, the links of the line over the cells they take, as many times
     * as wide as the text is larger.
     */
    private static function scaledRoom(string $line, int $width, int $scale): string
    {
        if (!str_contains($line, "\x1b]8;")) {
            return str_repeat(' ', $width * $scale);
        }
        $room = '';
        $column = 0;
        $open = null;
        foreach (preg_split('/(\x1b\]8;[^;\x07\x1b]*;[^\x07\x1b]*(?:\x07|\x1b\\\\))/', $line, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            if (str_starts_with($part, "\x1b]8;")) {
                $uri = rtrim(substr($part, strpos($part, ';', 4) + 1), "\x07\x1b\\");
                $room .= null !== $open ? "\x1b]8;;\x07" : '';
                $open = '' !== $uri ? $part : null;
                $room .= $open ?? '';
                continue;
            }
            $cells = AnsiUtils::visibleWidth($part);
            $room .= str_repeat(' ', $cells * $scale);
            $column += $cells;
        }

        return $room.(null !== $open ? "\x1b]8;;\x07" : '').str_repeat(' ', max(0, $width - $column) * $scale);
    }

    /**
     * The text of a text block in a vertical writing mode: laid out in lines as long as its height (or the room, or
     * the screen), turned into columns, one character a row, from the right, or from the left for vertical-lr.
     *
     * @return list<string>
     */
    private function renderVertically(TextWidget $widget, RenderContext $innerContext, Style $style, RenderContext $context, ?int $parentRows = null): array
    {
        // The rows of its content: its height, else that of its parent when definite, else the screen's, inside its border and padding
        $inline = $style->getMaxRows() ?? $this->chromeApplier->computeInnerDimensions($context->getColumns(), max(1, $parentRows ?? $this->mediaContext->rows), $style)[1];
        $inline = max(1, min(1000, $inline));
        $lines = $widget->render($innerContext->withColumns($inline));
        // Wide characters, like those of Japanese, take two columns: a line of them is as long in characters as the column
        $text = AnsiUtils::stripAnsiCodes(implode('', $lines));
        if ('' !== trim($text) && preg_match('/^(?:\s|[\x{1100}-\x{115F}\x{2E80}-\x{A4CF}\x{AC00}-\x{D7A3}\x{F900}-\x{FAFF}\x{FE30}-\x{FE4F}\x{FF00}-\x{FF60}\x{FFE0}-\x{FFE6}])+$/u', $text)) {
            $lines = $widget->render($innerContext->withColumns($inline * 2));
        }
        if (!$lines) {
            return [];
        }
        // No more lines than there is room for their columns, two cells wide with wide characters: the others are cut,
        // like an overflow
        $room = max(1, $innerContext->getColumns());
        $kept = [];
        foreach ($lines as $line) {
            $pitch = preg_match('/[\x{1100}-\x{115F}\x{2E80}-\x{A4CF}\x{AC00}-\x{D7A3}\x{F900}-\x{FAFF}\x{FE30}-\x{FE4F}\x{FF00}-\x{FF60}\x{FFE0}-\x{FFE6}]/u', AnsiUtils::stripAnsiCodes($line)) ? 2 : 1;
            // One at least, cut if it is wider than the room
            $room -= $pitch;
            if ($kept && $room < 0) {
                break;
            }
            $kept[] = $line;
        }
        $lines = $kept;
        $width = max(1, ...array_map(AnsiUtils::visibleWidth(...), $lines));
        $cells = new CellBuffer($width, \count($lines));
        $cells->writeAnsiLines($lines);
        $leftToRight = \in_array($style->getWritingMode(), ['vertical-lr', 'sideways-lr'], true);

        return $cells->transpose(!$leftToRight)->toLines();
    }

    /**
     * Whether a widget is on the line of the floats before it, a run of text or the anonymous row of the controls in it:
     * no element's box, which goes below them, nor a block of a tree built without elements.
     */
    private function inLineOfFloats(AbstractWidget $widget): bool
    {
        if ($this->isElementBox($widget)) {
            return false;
        }
        $anonymous = false;
        foreach ($this->styleSheet->getProviders() as $provider) {
            $anonymous = $anonymous || (method_exists($provider, 'isAnonymous') && $provider->isAnonymous($widget));
        }

        // Not the anonymous row of a table around cells, a block
        return $widget instanceof TextWidget || ($anonymous && null === $this->resolveStyle($widget)->getTableRole());
    }

    public function isElementBox(AbstractWidget $widget): bool
    {
        foreach ($this->styleSheet->getProviders() as $provider) {
            if (method_exists($provider, 'isElementBox') && $provider->isElementBox($widget)) {
                return true;
            }
        }

        return false;
    }

    public function establishesFormattingContext(AbstractWidget $widget): bool
    {
        if (null !== $this->resolveStyle($widget)->getScroll()) {
            return true;
        }
        foreach ($this->styleSheet->getProviders() as $provider) {
            if (method_exists($provider, 'isFormattingContext') && $provider->isFormattingContext($widget)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a widget keeps its floats in its box, a formatting context of its own or a box that draws something;
     * a plain block lets them out into the block around it, beside what follows it, like CSS. Not $box: its margins,
     * border and padding aside, those of a block whose floats go on past its content, see escapesFloats(); not ones of
     * percentages, nor negative margins, which move the box in ways the block around does not place its floats by.
     *
     * @internal
     */
    public function containsFloats(AbstractWidget $widget, bool $box = true): bool
    {
        if (!$widget instanceof ContainerWidget || null === $widget->getParent() || $widget->isInlineFlow() || null === $widget->getParent()->getParent()) {
            return true;
        }
        $style = $this->resolveStyle($widget);
        $zero = static fn (?object $box): bool => null === $box || (0 === $box->top && 0 === $box->right && 0 === $box->bottom && 0 === $box->left);
        if (null !== $style->getFloat() || null !== $style->getPosition() || null !== $style->getScroll() || null !== $style->getDirection() || null !== $style->getGridTemplate()
            || null !== $style->getTableRole() || null !== $style->getBackground()
            || ($box ? !$zero($style->getBorder()) || !$zero($style->getPadding()) || !$zero($style->getMargin()) : true === $style->getMargin()?->hasPercents() || true === $style->getPadding()?->hasPercents() || !$zero($style->getMarginPull()) || min($style->getMargin()?->top ?? 0, $style->getMargin()?->right ?? 0, $style->getMargin()?->bottom ?? 0, $style->getMargin()?->left ?? 0) < 0)
            || null !== $style->getAutoMargin() || null !== $style->getMaxColumns() || null !== $style->getWidthPercent() || null !== $style->getMinColumns()
            || null !== $style->getMinRows() || null !== $style->getMaxRows() || null !== $style->getContentWidth() || true === $style->getHidden()) {
            return true;
        }
        foreach ($this->styleSheet->getProviders() as $provider) {
            if (method_exists($provider, 'containsFloats') && $provider->containsFloats($widget)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a widget lets its floats out into the block around it: a plain block holding floats and nothing else in the
     * flow, directly or in such blocks, like the <div> around the floated menu of jQuery, which has no height then, like
     * CSS; known once for each revision of the widget.
     */
    private function letsFloatsOut(AbstractWidget $widget, int $depth = 0, bool &$cut = false): bool
    {
        if (!$widget instanceof ContainerWidget) {
            return false;
        }
        // Too deep to tell: no, not kept, nor what it is part of, like escapesFloats()
        if ($depth > 8) {
            $cut = true;

            return false;
        }
        $revision = $widget->getRenderRevision();
        // An answer cut short by the depth holds for a call as deep or deeper, which would be cut as short
        if (null !== ($known = $this->floatsOut[$widget] ?? null) && $known[0] === $revision && $known[1] === $this->styleEpoch && $depth >= $known[3]) {
            if ($known[3] >= 0) {
                $cut = true;
            }

            return $known[2];
        }
        $lets = false;
        $deep = false;
        if (!$this->containsFloats($widget)) {
            foreach ($widget->all() as $child) {
                $style = $this->resolveStyle($child);
                if ($style->getHidden() || true === $style->getPosition()?->isOutOfFlow() || $this->boxDrawsNothing($child)) {
                    continue;
                }
                if (null === $style->getFloat() && !$this->letsFloatsOut($child, $depth + 1, $deep)) {
                    $lets = false;
                    break;
                }
                $lets = true;
            }
        }
        if ($deep) {
            $cut = true;
        }
        $this->floatsOut[$widget] = [$revision, $this->styleEpoch, $lets, $deep ? $depth : -1];

        return $lets;
    }

    /**
     * Whether the floats of a widget go on past its content, into the block around it, beside the blocks after it, like
     * CSS: a block of no formatting context of its own in a plain block, its margins, border and padding aside, holding
     * floats, directly or in such blocks, among other content, like a paragraph starting with a floated image; it is as
     * tall as its content then, not as its floats.
     *
     * @param bool $cut Set when the answer is cut short by the depth, not kept then: a call from higher up goes deeper
     */
    private function escapesFloats(AbstractWidget $widget, int $depth = 0, bool &$cut = false): bool
    {
        if (!$widget instanceof ContainerWidget || !($parent = $widget->getParent()) instanceof ContainerWidget || $parent->isInlineFlow()) {
            return false;
        }
        // Too deep to tell: no, not kept, nor what it is part of; a leaf is no container, known at any depth
        if ($depth > 8) {
            $cut = true;

            return false;
        }
        $revision = $widget->getRenderRevision();
        // As it is and as its parent is, which a move or a change of the parent alone changes
        if (null !== ($known = $this->floatsEscape[$widget] ?? null) && $known[0] === $revision && $known[1] === $this->styleEpoch && $known[3] === [spl_object_id($parent), $parent->getRenderRevision()] && $depth >= $known[4]) {
            if ($known[4] >= 0) {
                $cut = true;
            }

            return $known[2];
        }
        $escapes = false;
        $deep = false;
        $around = $this->resolveStyle($parent);
        if (!$this->holdsMarkedItems($parent) && null === $around->getDirection() && null === $around->getGridTemplate() && 0 === ($around->getGap() ?? 0) && null === $this->resolveStyle($widget)->getColumns() && !$this->letsFloatsOut($widget) && !$this->containsFloats($widget, false)) {
            foreach ($widget->all() as $child) {
                $style = $this->resolveStyle($child);
                if ($style->getHidden() || true === $style->getPosition()?->isOutOfFlow()) {
                    continue;
                }
                if (null !== $style->getFloat() || $this->letsFloatsOut($child) || $this->escapesFloats($child, $depth + 1, $deep)) {
                    $escapes = true;
                    break;
                }
            }
        }
        // Yes is known whatever was too deep to tell
        // Yes is known whatever was too deep to tell; no, for a call as deep or deeper only
        $partial = $deep && !$escapes;
        if ($partial) {
            $cut = true;
        }
        $this->floatsEscape[$widget] = [$revision, $this->styleEpoch, $escapes, [spl_object_id($parent), $parent->getRenderRevision()], $partial ? $depth : -1];

        return $escapes;
    }

    /**
     * Whether a block holds items of a list, whose markers a float going on past one would cover, like an image floated
     * in an item: kept in it; known once for each revision of the block, not for each of its children.
     */
    private function holdsMarkedItems(ContainerWidget $widget): bool
    {
        $revision = $widget->getRenderRevision();
        if (null !== ($known = $this->markedItems[$widget] ?? null) && $known[0] === $revision && $known[2] === $this->styleEpoch) {
            return $known[1];
        }
        // An item holding blocks: its first text, after the floats it starts with, carries the marker
        $first = fn (ContainerWidget $item): ?AbstractWidget => array_find($item->all(), fn (AbstractWidget $child): bool => null === $this->resolveStyle($child)->getFloat());
        $marked = array_any($widget->all(), static fn (AbstractWidget $item): bool => ($item instanceof RichTextWidget && null !== $item->getMarker())
            || ($item instanceof ContainerWidget && ($text = $first($item)) instanceof RichTextWidget && null !== $text->getMarker()));
        $this->markedItems[$widget] = [$revision, $marked, $this->styleEpoch];

        return $marked;
    }

    /**
     * Whether a widget draws nothing: a box of nothing of its own holding only what is hidden, like the line of a hidden
     * button.
     */
    private function boxDrawsNothing(AbstractWidget $widget, int $depth = 0): bool
    {
        $style = $this->resolveStyle($widget);
        if ($style->getHidden()) {
            return true;
        }
        if (!$widget instanceof ContainerWidget || $depth > 8 || null !== $style->getFloat() || null !== $style->getBackground() || null !== $style->getBorder() || null !== $style->getMinRows()
            || (null !== ($padding = $style->getPadding()) && 0 !== $padding->top + $padding->bottom) || (null !== ($margin = $style->getMargin()) && (0 !== $margin->top || 0 !== $margin->bottom))) {
            return false;
        }

        return array_all($widget->all(), fn (AbstractWidget $child) => $this->boxDrawsNothing($child, $depth + 1));
    }

    /**
     * The children, those that let their floats out replaced by what they hold, see letsFloatsOut().
     *
     * @param list<AbstractWidget> $children
     *
     * @return list<AbstractWidget>
     */
    private function unwrapFloats(array $children): array
    {
        $result = [];
        foreach ($children as $child) {
            if ($child instanceof ContainerWidget && $this->letsFloatsOut($child)) {
                array_push($result, ...$this->unwrapFloats(array_values($child->all())));
                continue;
            }
            $result[] = $child;
        }

        return $result;
    }

    /**
     * The lines of an absolute or fixed widget, as tall as its insets or its height make it: its content taller than that
     * drawn past them, like CSS, unless it clips or scrolls, like a link over an image that draws nothing of Astro, not
     * cut to its top border.
     *
     * @return array{LineBufferInterface, int} Its lines, and the rows of its box, which its content may go on past
     */
    private function renderPositioned(AbstractWidget $widget, RenderContext $context, ?int $height, bool $overflows): array
    {
        $lines = null === $height ? $this->renderWidgetLines($widget, $context) : $this->renderStretchedLines($widget, $context, $height);
        // A height it sets, like height: 100% of a box holding an image Tui draws nothing of, or 1em: its content drawn
        // past it too
        $style = $this->resolveStyle($widget);
        // Not a height in pixels or ems, rounded to rows, which the content of a browser fits but not its rows, like the
        // bar of links of 22px of cppreference
        $sets = (null !== $style->getMaxRows() || null !== $style->getMaxRowsPercent()) && !$this->hasRoundedHeight($widget);
        if ((null === $height ? !$sets : !$overflows) || $this->clipsOverflow($widget, true)) {
            return [$lines, \count($lines)];
        }
        // Measured in an earlier frame with the same content and room: it fitted, kept like its render, see
        // AbstractWidget::getRenderCache(); and the same rows drawn at its height, not those of a render that changed
        // with the media or the styles a layout gives
        $key = [$widget->getRenderRevision(), $context->getColumns(), $context->getRows(), $height];
        $known = $this->fitsSetHeight[$widget] ?? null;
        if (null !== $known && $known[0] === $key && ($known[1] === $lines || $known[1]->toArray() === $lines->toArray())) {
            $this->fitsSetHeight[$widget] = [$key, $lines];

            return [$lines, \count($lines)];
        }
        // Its height still definite for the percentages of its content, like height: 100% of its children
        $definite = null !== $style->getMaxRowsPercent() ? $style->getMinRowsPercent() === $style->getMaxRowsPercent() && $style->getRowsPercentOffsets()[0] === $style->getRowsPercentOffsets()[1] : $style->getMinRows() === $style->getMaxRows();
        $natural = function () use ($widget, $context, $sets, $definite): LineBufferInterface {
            if (!$sets) {
                return $this->renderWidgetLines($widget, $context);
            }
            $this->unboundedRows[$widget] = $definite;
            ++$this->styleEpoch;
            try {
                // Its render at its height kept for the frames after
                return $widget->withoutRenderCache(fn (): LineBufferInterface => $this->renderWidgetLines($widget, $context));
            } finally {
                unset($this->unboundedRows[$widget]);
                ++$this->styleEpoch;
            }
        };
        // Measured untracked, the widgets inside it tracked where it is drawn: drawn again when its content is taller, only
        // while they are tracked
        $tracked = $this->positionTracker->isActive();
        $saved = $this->positionTracker->suppressStack();
        $measured = $natural();
        $this->positionTracker->restoreStack($saved);
        // Past its height, only the rows something is drawn on count, not the bottom margin of its last child: its
        // background and border stay at its size then
        // Its bottom border and margin kept under the rows drawn, its left margin and border not drawn on
        $sides = $style->getBorder();
        // Its margins in percentages too, shares of the width of its containing block
        $margin = $style->withResolvedBox($context->getContainingColumns())->getMargin();
        $border = (($sides?->bottom ?? 0) > 0 ? 1 : 0) + max(0, $margin?->bottom ?? 0);
        $lead = (($sides?->left ?? 0) > 0 ? 1 : 0) + max(0, $margin?->left ?? 0);
        $blank = static function (string $line) use ($sides, $lead): bool {
            $text = rtrim(AnsiUtils::stripAnsiCodes($line));
            if (($sides?->right ?? 0) > 0) {
                $text = mb_substr($text, 0, -1);
            }

            return '' === trim(mb_substr($text, $lead));
        };
        // Its content box ends above its bottom padding, which its content drawn past it goes over
        $padding = $style->withResolvedBox($context->getContainingColumns())->getPadding();
        $inside = \count($lines) - $border - max(0, $padding?->bottom ?? 0);
        for ($drawn = \count($measured) - $border; $drawn > $inside && $blank($measured->getLine($drawn - 1)); --$drawn) {
        }
        if ($drawn <= $inside) {
            $this->fitsSetHeight[$widget] = [$key, $lines];

            return [$lines, \count($lines)];
        }
        unset($this->fitsSetHeight[$widget]);
        // Its box at its height, like CSS, its content drawn on past it: over its bottom padding and border, then under
        // them, without its background nor its side borders
        $box = max(0, \count($lines) - max(0, $margin?->bottom ?? 0));
        $from = max(0, $box - (($sides?->bottom ?? 0) > 0 ? 1 : 0) - max(0, $padding?->bottom ?? 0));
        $start = $lead + max(0, $padding?->left ?? 0);
        // As wide as a row of its box, the room it was given when it draws none, like height: 0
        $width = max(0, ($box > 0 ? AnsiUtils::visibleWidth($lines->getLine($box - 1)) : $context->getColumns()) - $start - (($sides?->right ?? 0) > 0 ? 1 : 0) - max(0, $padding?->right ?? 0) - max(0, $margin?->right ?? 0));
        $background = $style->getBackground()?->toBackgroundCode();
        // Its rows above its bottom padding as its content laid out past its height draws them, those after over its box
        // at its height, its margin under it kept
        $past = static function (LineBufferInterface $all) use ($lines, $box, $from, $drawn, $start, $width, $background): LineBufferInterface {
            $out = $lines->toArray();
            for ($row = 0; $row < $from && $row < \count($all) && $row < \count($out); ++$row) {
                $out[$row] = $all->getLine($row);
            }
            for ($row = $from; $row < $drawn && $row < \count($all); ++$row) {
                $text = AnsiUtils::sliceToWidth($all->getLine($row), $start, $width);
                if ($row >= $box && null !== $background) {
                    $text = self::withoutBackground($text, substr($background, 2, -1));
                }
                $out[$row] = isset($out[$row]) ? Compositor::composite(new Layer([$out[$row]]), new Layer([$text], 0, $start, true))[0] ?? $out[$row] : str_repeat(' ', $start).$text;
            }

            return new ArrayLineBuffer($out);
        };
        if (!$tracked) {
            return [$past($measured), \count($lines)];
        }
        // Rendered again for the positions of what it holds; measured past a height it sets, its render at that height
        // kept, see $natural
        if (!$sets) {
            $widget->clearRenderCache();
        }

        return [$past($natural()), \count($lines)];
    }

    /**
     * The lines of a widget, or of the floats of its content going on past it, as its effects draw them: its filter and
     * its blend mode on every color, and nothing drawn when it is invisible or transparent, like opacity: 0.
     */
    private function paintEffects(Style $style, LineBufferInterface $lines): LineBufferInterface
    {
        // filter: every color of the widget and of what it contains, cell by cell
        if (($filter = $style->getFilter()) instanceof Filter) {
            $rewriter = new ColorRewriter($filter->apply(...), $this->mediaContext->colorScheme);
            $lines = new ArrayLineBuffer(array_map($rewriter->line(...), $lines->toArray()));
        }

        // mix-blend-mode: every color of the widget and of what it contains blended with the color behind it
        if (null !== $blend = $style->getBlend()) {
            [$mode, $backdrop] = $blend;
            $under = ($backdrop ?? Color::hex(ColorScheme::Light === $this->mediaContext->colorScheme ? '#ffffff' : '#000000'))->toRgb();
            $under = [$under['r'], $under['g'], $under['b']];
            $rewriter = new ColorRewriter(static fn (array $color) => $mode->blend($under, $color), $this->mediaContext->colorScheme);
            $lines = new ArrayLineBuffer(array_map($rewriter->line(...), $lines->toArray()));
        }

        // Invisible, or transparent like opacity: 0: the room stays, nothing is drawn in it
        if ($style->getInvisible() || $style->getTransparent()) {
            $blank = [];
            foreach ($lines as $line) {
                $blank[] = str_repeat(' ', AnsiUtils::visibleWidth($line));
            }
            $lines = new ArrayLineBuffer($blank);
        }

        return $lines;
    }

    /**
     * Whether the height of a widget is set as a length rounded to rows, like height: 22px or 1.5em, not as rows or a
     * percentage.
     */
    private function hasRoundedHeight(AbstractWidget $widget): bool
    {
        foreach ($this->styleSheet->getProviders() as $provider) {
            if (!method_exists($provider, 'getComputedValue')) {
                continue;
            }
            // The height, else the max-height of a box of a height in a keyword, like max-height: 22px; a share of its
            // containing block is not rounded, even in a calc() with lengths
            foreach (['height', 'max-height'] as $property) {
                $height = $provider->getComputedValue($widget, $property);
                // A keyword, like auto, fit-content or inherit: the next one
                if (null !== $height && preg_match('/\d/', $height)) {
                    return !str_contains($height, '%') && preg_match('/\d(?:px|r?em|r?lh|ex|ch|cap|ic|[sdl]?v(?:h|w|min|max|b|i)|cq(?:h|w|b|i|min|max)|cm|mm|q|in|pt|pc)\b/i', $height);
                }
            }
        }

        return false;
    }

    /**
     * Whether the width of a widget is set as a length, like width: 625rem, not auto, a percentage or a keyword.
     */
    private function hasLengthWidth(AbstractWidget $widget): bool
    {
        foreach ($this->styleSheet->getProviders() as $provider) {
            if (method_exists($provider, 'getComputedValue') && null !== $width = $provider->getComputedValue($widget, 'width')) {
                // In cells too, like width: 300, or calc() of lengths
                return (bool) preg_match('/^\s*(?:\d*\.?\d+(?:px|r?em|ch|ex|vw|vmin|vmax|cm|mm|in|pt|pc)?|calc\([^%]*\))\s*$/i', $width);
            }
        }

        return false;
    }

    /**
     * Whether a widget clips what is too wide for it, like overflow-x: hidden or clip.
     */
    private function clipsSideways(AbstractWidget $widget): bool
    {
        foreach ($this->styleSheet->getProviders() as $provider) {
            if (method_exists($provider, 'getComputedValue')) {
                $value = $provider->getComputedValue($widget, 'overflow-x') ?? $provider->getComputedValue($widget, 'overflow-inline') ?? explode(' ', trim($provider->getComputedValue($widget, 'overflow') ?? ''))[0];
                if (preg_match('/^\s*(?:hidden|clip)\b/i', $value)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether a widget or one of its ancestors clips what overflows it, like overflow: hidden or a scroll container: a
     * relative box moved past its lines stays inside it, cut.
     */
    private function clipsOverflow(?AbstractWidget $widget, bool $alone = false): bool
    {
        for ($depth = 0; null !== $widget && $depth < ($alone ? 1 : 64); $widget = $widget->getParent(), ++$depth) {
            if (null !== $this->resolveStyle($widget)->getScroll()) {
                return true;
            }
            foreach ($this->styleSheet->getProviders() as $provider) {
                if (method_exists($provider, 'getComputedValue') && array_any(['overflow', 'overflow-y', 'overflow-block'], static fn (string $property) => (bool) preg_match('/\b(?:hidden|clip|auto|scroll|overlay)\b/i', $provider->getComputedValue($widget, $property) ?? ''))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Moves the children positioned relatively by their offsets, like CSS position: relative with top or left.
     *
     * The room they take in the flow stays, empty; they are drawn over what is where they move, within the container.
     *
     * @param AbstractWidget[] $children
     */
    private function offsetRelative(array $children, LineBufferInterface $lines, int $columns, int $contentRow, int $contentCol): LineBufferInterface
    {
        $moves = [];
        // Those after an absolute sibling are drawn over it, like CSS paints positioned elements in the order of the
        // document, like the label of a divider over the line placed under it
        $lifted = new \SplObjectStorage();
        // Those lifted for being moved past the lines of their container, not only drawn over absolute siblings
        $past = new \SplObjectStorage();
        // Or after an absolute or fixed box painted before it, like CSS paints positioned elements in the order of the
        // stacking contexts; not one of a negative z-index, painted under the flow, nor one around it, which draws it in
        // its lines already
        $followsAbsolute = function (AbstractWidget $child): bool {
            for ($root = $child; null !== $parent = $root->getParent(); $root = $parent) {
            }
            if (!$root instanceof ContainerWidget || null === $childKey = $this->stackingKey($child, $root, $this->absoluteKeys['indexes'])) {
                return false;
            }
            foreach ($this->overlays as $overlay => $_) {
                $id = spl_object_id($overlay);
                if (($this->absoluteKeys['keys'][$id][0] ?? null)?->get() !== $overlay) {
                    $this->absoluteKeys['keys'][$id] = [\WeakReference::create($overlay), $this->absoluteKey($overlay, $root)];
                }
                $key = $this->absoluteKeys['keys'][$id][1];
                if (null !== $key && StackingOrder::compare($key, $childKey) < 0 && $key['path'] !== \array_slice($childKey['path'], 0, \count($key['path']))) {
                    return true;
                }
            }

            return false;
        };
        foreach ($children as $child) {
            $position = $this->resolveStyle($child)->getPosition();
            if (PositionMode::Relative === $position?->mode && (0 !== ($position->zIndex ?? 0) || ($position->zIndex >= 0 && $followsAbsolute($child)))) {
                $lifted[$child] = true;
            } else {
                unset($this->liftedRelatives[$child]);
            }
            if (PositionMode::Relative !== $position?->mode || null === $rect = $this->positionTracker->getWidgetRect($child)) {
                continue;
            }
            // transform: translate() in percents is of its own box
            [$tx, $ty] = $position->translation(($this->boxRect($child) ?? $rect)->columns, $rect->rows);
            $dy = ($position->inset('top', \count($lines)) ?? -($position->inset('bottom', \count($lines)) ?? 0)) + $ty;
            $dx = ($position->inset('left', $columns) ?? -($position->inset('right', $columns) ?? 0)) + $tx;
            // Not moved, only drawn over the absolute elements before it: left in its lines, so that what comes after it
            // in the flow still draws over it, like when nothing is under it, and drawn again from them over those
            if (0 === $dx && 0 === $dy && isset($lifted[$child]) && ($position->zIndex ?? 0) <= 0) {
                unset($lifted[$child]);
                if ($this->isInsideEffect($child, false, false)) {
                    unset($this->liftedRelatives[$child]);
                } else {
                    $margin = $this->resolveStyle($child)->getMargin();
                    $this->liftedRelatives[$child] = [0, null, ($this->boxRect($child) ?? $rect)->columns, max(0, $rect->rows - max(0, $margin->top ?? 0) - max(0, $margin->bottom ?? 0))];
                }
                continue;
            }
            // Moved, or drawn over the absolute elements of a lower z-index
            if (0 !== $dx || 0 !== $dy || isset($lifted[$child])) {
                // Its box, inside its margins: what is beside it, above and below stays
                $box = $this->boxRect($child) ?? $rect;
                $margin = $this->resolveStyle($child)->getMargin();
                $top = max(0, $margin->top ?? 0);
                $boxRows = max(0, $rect->rows - $top - max(0, $margin->bottom ?? 0));
                // Moved past the lines of its container, like the donate button of python.org lowered by its top: drawn
                // over what comes after them, like browsers, not cut by it
                $from = $rect->row + $top - $contentRow + $dy;
                if (0 !== $dy && ($from < 0 || $from + $boxRows > \count($lines))) {
                    $past[$child] = true;
                    // Cut by a container that clips, drawn over nothing past it
                    if ($this->clipsOverflow($child->getParent())) {
                        unset($lifted[$child]);
                    } else {
                        $lifted[$child] = true;
                    }
                }
                $moves[] = [$child, $rect->row + $top - $contentRow, $box->col - $contentCol, $box->columns, $boxRows, max(-1000, min(1000, $dy)), max(-1000, min(1000, $dx))];
            }
        }
        if (!$moves) {
            return $lines;
        }
        // The higher z-index drawn last, over the others, then those after in the document
        $order = array_flip(array_map(static fn (array $move) => spl_object_id($move[0]), $moves));
        usort($moves, fn (array $a, array $b) => [$this->resolveStyle($a[0])->getPosition()->zIndex ?? 0, $order[spl_object_id($a[0])]] <=> [$this->resolveStyle($b[0])->getPosition()->zIndex ?? 0, $order[spl_object_id($b[0])]]);

        $out = $lines->toArray();
        $width = static fn (string $line) => max($columns, AnsiUtils::visibleWidth($line));
        $splice = static function (string $line, int $col, string $part, int $partWidth) use ($width): string {
            $total = $width($line);

            return AnsiUtils::sliceToWidth($line, 0, $col)."\e[0m".$part."\e[0m".AnsiUtils::sliceToWidth($line, $col + $partWidth, max(0, $total - $col - $partWidth));
        };
        // Lifted out of the flow, their room blanked, all of them before any is drawn over the others; the one drawn last
        // first, which keeps the cells they share, like the border of a box pulled up over the next by a negative margin
        $boxes = [];
        foreach (array_reverse($moves, true) as $index => [$child, $row, $col, $boxColumns, $boxRows]) {
            $boxes[$index] = [];
            for ($i = 0; $i < $boxRows; ++$i) {
                if (isset($out[$row + $i])) {
                    $boxes[$index][$i] = AnsiUtils::sliceToWidth($out[$row + $i], $col, $boxColumns);
                    $out[$row + $i] = $splice($out[$row + $i], $col, str_repeat(' ', $boxColumns), $boxColumns);
                }
            }
        }
        // Then drawn at their offsets; those of a positive z-index with the absolute ones, over those of a lower one
        foreach ($moves as $index => [$child, $row, $col, $boxColumns, $boxRows, $dy, $dx]) {
            $box = $boxes[$index];
            $zIndex = $this->resolveStyle($child)->getPosition()?->zIndex ?? 0;
            if (isset($lifted[$child]) && !$this->isInsideEffect($child, isset($past[$child]), 0 !== $dx)) {
                $this->liftedRelatives[$child] = [$zIndex, array_values($box), $boxColumns, $boxRows];
                $this->positionTracker->shiftContentPositions([$child], max(0, $col + $dx) - $col, $dy);
                continue;
            }
            unset($this->liftedRelatives[$child]);
            $toCol = max(0, $col + $dx);
            $visible = min($boxColumns, $columns - $toCol);
            // A box without background or border is transparent: only up to its last character is drawn
            $style = $this->resolveStyle($child);
            $opaque = null !== $style->getBackground() || null !== $style->getBorder();
            foreach ($box as $i => $part) {
                $target = $row + $dy + $i;
                $drawn = $opaque ? $visible : min($visible, AnsiUtils::visibleWidth(rtrim(AnsiUtils::stripAnsiCodes($part))));
                if ($drawn > 0 && isset($out[$target])) {
                    $out[$target] = $splice($out[$target], $toCol, AnsiUtils::sliceToWidth($part, 0, $drawn), $drawn);
                }
            }
            $this->positionTracker->shiftContentPositions([$child], $toCol - $col, $dy);
        }

        return new ArrayLineBuffer($out);
    }

    /**
     * Lays out a multi-column container whose children span its columns (column-span: all): the content between them in
     * columns of its own, each spanning child across them all.
     *
     * @param AbstractWidget[]                                              $children
     * @param \Closure(int, list<AbstractWidget>|null): LineBufferInterface $layout
     */
    private function flowAroundSpans(array $children, \Closure $layout, Columns $columns, int $count, int $width, int $innerColumns, int $contentRow): LineBufferInterface
    {
        $segments = [];
        $run = [];
        foreach ($children as $child) {
            if (true === $this->resolveStyle($child)->getSpanAll()) {
                if ($run) {
                    $segments[] = [false, $run];
                }
                $segments[] = [true, [$child]];
                $run = [];
            } else {
                $run[] = $child;
            }
        }
        if ($run) {
            $segments[] = [false, $run];
        }

        $lines = [];
        foreach ($segments as [$spans, $segment]) {
            $offset = \count($lines);
            $part = $spans ? $layout($innerColumns, $segment) : $this->flowIntoColumns($segment, $layout($width, $segment), $columns, $count, $width, $contentRow);
            // Laid out from the top of the content: moved below what comes before
            $this->positionTracker->shiftContentPositions($segment, 0, $offset);
            array_push($lines, ...$part->toArray());
        }

        return new ArrayLineBuffer($lines);
    }

    /**
     * Cuts the content of a multi-column container into balanced columns, side by side, the rule in the gaps.
     *
     * A column ends where a break is allowed (see fragmentation()), as late as it fits: between blocks, or between the
     * lines of a text block that orphans and widows allow; what cannot be cut there makes the columns taller, like the
     * unbreakable content of CSS. With a height to fill (column-fill: auto), the columns are that tall, the last ones
     * empty when the content is shorter, taller when it does not fit.
     *
     * @param AbstractWidget[] $children
     */
    private function flowIntoColumns(array $children, LineBufferInterface $lines, Columns $columns, int $count, int $width, int $contentRow, ?int $fill = null): LineBufferInterface
    {
        $all = $lines->toArray();
        $total = \count($all);
        if (0 === $total) {
            return $lines;
        }

        // Without positions, every row can start a column
        ['status' => $status, 'forced' => $forced, 'clones' => $clones, 'widgets' => $widgets] = $this->positionTracker->isActive()
            ? $this->fragmentation($children, $contentRow, $total, $width)
            : ['status' => [], 'forced' => [], 'clones' => [], 'widgets' => []];
        ksort($forced);

        // box-decoration-break: clone, a box cut before a row: its bottom edge closing the column, inner boxes first,
        // and its top edge opening the next, outer boxes first
        $edges = static function (int $row) use ($clones, $all): array {
            $closing = [];
            $opening = [];
            foreach ($clones as [$top, $bottom, $topEdge, $bottomEdge]) {
                if ($row > $top && $row < $bottom) {
                    $closing = [...array_map(static fn (int $r) => $all[$r], $bottomEdge), ...$closing];
                    array_push($opening, ...array_map(static fn (int $r) => $all[$r], $topEdge));
                }
            }

            return [$closing, $opening];
        };

        // The rows of the bottom edges closing a column before each row, counted once: the boxes cut there
        $closings = array_fill(0, $total + 2, 0);
        foreach ($clones as [$top, $bottom, , $bottomEdge]) {
            [$from, $to] = [max(0, $top + 1), min($total + 1, $bottom)];
            if ($from < $to) {
                $closings[$from] += \count($bottomEdge);
                $closings[$to] -= \count($bottomEdge);
            }
        }
        for ($row = 1; $row <= $total; ++$row) {
            $closings[$row] += $closings[$row - 1];
        }

        // The content in columns of a height: the rows of each, and where each row goes; null when it does not fit
        $place = static function (int $height) use ($all, $total, $count, $forced, $status, $edges, $closings): ?array {
            $placed = array_fill(0, $count, []);
            $at = [];
            $from = 0;
            $opening = [];
            for ($column = 0; $from < $total; ++$column) {
                if ($column >= $count) {
                    return null;
                }
                $room = $height - \count($opening);
                $limit = min($total, $from + max(1, $room));
                $end = $limit;
                $closing = [];
                if ($column < $count - 1) {
                    // A forced break first, but not past the last column, where what is left goes
                    $break = null;
                    foreach ($forced as $row => $_) {
                        if ($row > $from && $row <= $limit && $row < $total) {
                            $break = $row;
                            break;
                        }
                    }
                    if (null !== $break) {
                        $end = $break;
                    } elseif ($limit < $total) {
                        // The last break allowed that fits, else taller columns: what cannot be cut makes them taller
                        $end = null;
                        for ($row = $limit; $row > $from; --$row) {
                            if (2 === ($status[$row] ?? 2) && $row - $from + $closings[$row] <= $room) {
                                $end = $row;
                                break;
                            }
                        }
                        if (null === $end) {
                            return null;
                        }
                    }
                    if ($end < $total) {
                        [$closing, $next] = $edges($end);
                    }
                } elseif ($total - $from > $room) {
                    return null;
                }

                $rows = $opening;
                for ($row = $from; $row < $end; ++$row) {
                    $at[$row] = [$column, \count($rows)];
                    $rows[] = $all[$row];
                }
                $placed[$column] = [...$rows, ...$closing];
                $opening = $next ?? [];
                $next = null;
                $from = $end;
            }

            return [$placed, $at];
        };

        // The shortest columns the content fits in: taller columns fit what shorter ones fit, so the height is found
        // by doubling the step past the first one tried, then halving the range, not by trying every row
        $low = max(1, $fill ?? (int) ceil($total / $count));
        if (null === $fits = $place($low)) {
            for ($step = 1; null === $fits = $place($high = $low + $step); $step *= 2) {
                $low = $high;
            }
            while ($high - $low > 1) {
                if (null !== $mid = $place($middle = intdiv($low + $high, 2))) {
                    [$high, $fits] = [$middle, $mid];
                } else {
                    $low = $middle;
                }
            }
        }
        [$placed, $at] = $fits;

        // Each widget moves with the column its first row falls in: the outer ones first, then those inside them, which
        // may be in another column
        $pieces = [];
        foreach ($widgets as [$widget, $row, $col]) {
            if (null !== ($rect = $this->positionTracker->getWidgetRect($widget)) && null !== $start = $at[$row] ?? null) {
                $pieces[] = [$widget, $row, $rect->rows];
                $this->positionTracker->shiftContentPositions([$widget], $col + $start[0] * ($width + $columns->gap) - $rect->col, $contentRow + $start[1] - $rect->row);
            }
        }
        // A widget cut across columns: the pieces in the next columns are fragments of its rect, under the pointer too;
        // its rect keeps its rows, past the bottom of the first column
        foreach ($pieces as [$widget, $row, $rows]) {
            $parts = [];
            for ($i = $row; $i < min($total, $row + $rows); ++$i) {
                [$column, $line] = $at[$i];
                if (isset($parts[$column])) {
                    ++$parts[$column][1];
                } else {
                    $parts[$column] = [$line, 1];
                }
            }
            if (\count($parts) < 2 || null === $rect = $this->positionTracker->getWidgetRect($widget)) {
                continue;
            }
            $first = array_key_first($parts);
            $fragments = [];
            foreach ($parts as $column => [$line, $length]) {
                if ($column !== $first) {
                    $fragments[] = [$line - $parts[$first][0], ($column - $first) * ($width + $columns->gap), $rect->columns, $length];
                }
            }
            $this->positionTracker->setWidgetRect($widget, new WidgetRect($rect->row, $rect->col, $rect->columns, $rect->rows, $fragments));
        }
        // Without positions of the widgets, each child moves with the column of its first row
        if ([] === $widgets && $this->positionTracker->isActive()) {
            foreach ($children as $child) {
                if (null !== ($rect = $this->positionTracker->getWidgetRect($child)) && null !== $start = $at[max(0, $rect->row - $contentRow)] ?? null) {
                    $this->positionTracker->shiftContentPositions([$child], $start[0] * ($width + $columns->gap), $start[1] - ($rect->row - $contentRow));
                }
            }
        }

        $gap = str_repeat(' ', $columns->gap);
        if (null !== $columns->rule && $columns->gap > 0) {
            $half = intdiv($columns->gap - 1, 2);
            $gap = str_repeat(' ', $half).(new Style(color: $columns->color))->apply($columns->rule).str_repeat(' ', $columns->gap - 1 - $half);
        }
        $out = [];
        $height = max(0, ...array_map(\count(...), $placed));
        for ($row = 0; $row < $height; ++$row) {
            $line = '';
            for ($column = 0; $column < $count; ++$column) {
                $line .= ($column > 0 ? $gap : '').AnsiUtils::sliceToWidth($placed[$column][$row] ?? '', 0, $width);
            }
            $out[] = $line;
        }

        return new ArrayLineBuffer($out);
    }

    /**
     * Where the content of a multi-column container can be cut, from the positions of the widgets in it.
     *
     * The level of a break before each row: 2 where it is allowed (between blocks, between the lines of a text block
     * with as many lines before and after it as orphans and widows ask), 1 where it is avoided (break-inside,
     * break-before and break-after: avoid), 0 where it cannot be (inside images, form controls, flex rows and grids,
     * and between the edges of a box and its first and last lines); only the allowed ones end a column. With the forced breaks (break-before and
     * break-after: column), the boxes cloned when cut, and the widgets in the flow, outer ones first.
     *
     * @param AbstractWidget[] $children
     *
     * @return array{status: array<int, int>, forced: array<int, true>, clones: list<array{int, int, list<int>, list<int>}>, widgets: list<array{AbstractWidget, int, int}>}
     */
    private function fragmentation(array $children, int $contentRow, int $total, int $width): array
    {
        $status = [];
        $forced = [];
        $clones = [];
        $widgets = [];
        $set = static function (int $from, int $to, int $level) use (&$status, $total): void {
            for ($row = max(1, $from); $row < min($to, $total); ++$row) {
                $status[$row] = min($status[$row] ?? 2, $level);
            }
        };
        $inFlow = fn (AbstractWidget $widget): bool => true !== ($style = $this->resolveStyle($widget))->getHidden() && !\in_array($style->getPosition()?->mode, [PositionMode::Absolute, PositionMode::Fixed], true);

        // Set below: the blocks of a container, which visit() goes into
        $siblings = static function (array $children): void {};
        $visit = function (AbstractWidget $widget) use (&$siblings, &$widgets, &$clones, $set, $contentRow, $total, $width): void {
            if (null === $rect = $this->positionTracker->getWidgetRect($widget)) {
                return;
            }
            $start = $rect->row - $contentRow;
            $end = $start + $rect->rows;
            if ($rect->rows <= 0 || $start >= $total || $end <= 0) {
                return;
            }
            $widgets[] = [$widget, $start, $rect->col];
            $style = $this->resolveStyle($widget);
            // A float is beside the text: what is beside it is not cut
            if (null !== $style->getFloat()) {
                $set($start + 1, $end, 0);

                return;
            }

            $box = $style->withResolvedBox($width);
            $edgeTop = min($end, $start + ($box->getMargin()->top ?? 0));
            $top = min($end, $edgeTop + ($box->getBorder()->top ?? 0) + ($box->getPadding()->top ?? 0));
            $edgeBottom = max($top, $end - ($box->getMargin()->bottom ?? 0));
            $bottom = max($top, $edgeBottom - ($box->getBorder()->bottom ?? 0) - ($box->getPadding()->bottom ?? 0));
            // The edges of the box stay with its first and last lines
            $set($edgeTop + 1, $top + 1, 0);
            $set($bottom, $edgeBottom, 0);
            if (true === $style->getAvoidBreak()) {
                $set($start + 1, $end, 1);
            }
            if (true === $style->getCloneDecoration() && ($top > $edgeTop || $edgeBottom > $bottom)) {
                $clones[] = [$top, $bottom, range($edgeTop, $top - 1), range($bottom, $edgeBottom - 1)];
            }

            if ($widget instanceof RichTextWidget) {
                // Between its lines, as many before and after as orphans and widows ask
                [$orphans, $widows] = $widget->getOrphansAndWidows();
                $lineStarts = $widget->getLineStarts();
                $allowed = [];
                foreach ($lineStarts as $k => $row) {
                    if ($k >= $orphans && \count($lineStarts) - $k >= $widows) {
                        $allowed[$top + $row] = true;
                    }
                }
                for ($row = $top + 1; $row < $bottom; ++$row) {
                    if (!isset($allowed[$row])) {
                        $set($row, $row + 1, 0);
                    }
                }
            } elseif ($widget instanceof ContainerWidget && Direction::Horizontal !== $style->getDirection() && null === $style->getGridTemplate() && TableRole::Row !== $style->getTableRole() && null === $style->getColumns()) {
                // Between the blocks it holds, and inside them
                $siblings($widget->all());
            } else {
                $set($top + 1, $bottom, 0);
            }
        };
        $siblings = function (array $children) use ($visit, $inFlow, $set, &$forced, $contentRow, $total): void {
            $previousEnd = null;
            $avoidAfter = false;
            foreach ($children as $child) {
                if (!$inFlow($child) || null === $rect = $this->positionTracker->getWidgetRect($child)) {
                    continue;
                }
                $start = $rect->row - $contentRow;
                [$before, $after, $avoidBefore, $avoidNext] = ($this->resolveStyle($child)->getColumnBreaks() ?? []) + [false, false, false, false];
                if ($before && $start > 0) {
                    $forced[$start] = true;
                }
                if ($after && $start + $rect->rows < $total) {
                    $forced[$start + $rect->rows] = true;
                }
                if (null !== $previousEnd && ($avoidBefore || $avoidAfter)) {
                    $set(min($previousEnd, $start), $start + 1, 1);
                }
                $previousEnd = $start + $rect->rows;
                $avoidAfter = $avoidNext;
                $visit($child);
            }
        };
        $siblings($children);

        return ['status' => $status, 'forced' => $forced, 'clones' => $clones, 'widgets' => $widgets];
    }

    /**
     * Shows the window of a scroll container's content at its scroll position, the focused widget in it, with a scrollbar.
     *
     * @param AbstractWidget[] $children
     * @param int|null         $barColumn The column of the scrollbar, null without one
     */
    private function scrollContent(ContainerWidget $widget, array $children, LineBufferInterface $lines, int $viewport, ?int $barColumn, int $contentRow, int $contentCol, Style $style): LineBufferInterface
    {
        $total = \count($lines);
        $top = min($widget->getScrollTop(), max(0, $total - $viewport));
        if ($this->positionTracker->isActive()) {
            $this->forgetScrollMarks($widget, 'stuck');
            $this->forgetScrollMarks($widget, 'y');
        }

        // Focus moving into the container scrolls the focused widget into view, once: the user can scroll away from it
        // Measuring passes are not tracked: they leave it alone
        $focused = $this->positionTracker->isActive() ? $this->findFocused($children) : false;
        if (null === $focused) {
            unset($this->revealed[$widget]);
        } elseif (false !== $focused && ($this->revealed[$widget] ?? null)?->get() !== $focused && null !== $rect = $this->positionTracker->getWidgetRect($focused)) {
            $this->revealed[$widget] = \WeakReference::create($focused);
            // With the room scroll-margin keeps around it, within the room scroll-padding keeps clear
            [$above, $below] = $this->resolveStyle($focused)->getScrollMargin() ?? [0, 0];
            $scroll = $style->getScroll();
            $clearTop = min($viewport - 1, $scroll->paddingTop ?? 0);
            $clearBottom = min($viewport - 1 - $clearTop, $scroll->paddingBottom ?? 0);
            $first = $rect->row - $contentRow - $above;
            $last = $rect->row - $contentRow + $rect->rows + $below;
            if ($first < $top + $clearTop) {
                $top = max(0, $first - $clearTop);
            } elseif ($last > $top + $viewport - $clearBottom) {
                $top = max(0, min($first - $clearTop, $last - $viewport + $clearBottom));
            }
        }
        // A widget to reveal, inside: its top at the top, once
        if ($this->positionTracker->isActive() && null !== ($target = $this->revealing?->get()) && !isset($this->revealedTarget[$widget]) && null !== $rect = $this->positionTracker->getWidgetRect($target)) {
            for ($ancestor = $target->getParent(); null !== $ancestor && $ancestor !== $widget; $ancestor = $ancestor->getParent()) {
            }
            if (null !== $ancestor) {
                $this->revealedTarget[$widget] = true;
                $room = ($this->resolveStyle($target)->getScrollMargin()[0] ?? 0) + min($viewport - 1, $style->getScroll()->paddingTop ?? 0);
                $top = max(0, min($rect->row - $contentRow - $room, $total - $viewport));
            }
        }
        // scroll-snap-type: where it is scrolled to, it rests on a snap position of a child
        if (null !== ($scroll = $style->getScroll())?->snap && $this->positionTracker->isActive() && $total > $viewport) {
            $top = $this->snap($widget, $scroll, $children, $top, $total, $viewport, $contentRow);
        }
        $widget->setScrollMetrics($top, $total, $viewport, $style->getScroll()?->contained ?? false);
        if ($this->positionTracker->isActive()) {
            $this->recordScroll($widget, $top, $total, $viewport, false);
            $this->recordCurrentMarker($widget, $top, $total, $viewport, $contentRow, false);
            $this->recordOffsets($children, $contentRow, false);
        }

        // scroll-behavior: smooth, the rows shown glide from the last ones to where the content is scrolled
        if ($this->positionTracker->isActive()) {
            $top = $this->glide($this->glides, $this->shownTops, $widget, $top, true === $style->getScroll()?->smooth);
        }

        if ($total <= $viewport && (null === $barColumn || !$style->getScroll()?->always)) {
            return $lines;
        }

        $all = $lines->toArray();
        $visible = \array_slice($all, $top, $viewport);

        // Sticky widgets stay below the top while their parent is in view: their columns are drawn again there
        $pins = $this->positionTracker->isActive() ? $this->stickyPins($widget, $children, $top, $total, $contentRow, $viewport) : [];
        foreach ($pins as [$sticky, $rect, $shift]) {
            $this->scrollStates[$sticky] = ['stuck' => [$shift > 0 ? 'top' : 'bottom']] + ($this->scrollStates[$sticky] ?? []);
            $this->scrollMarks[$widget] = ['stuck' => [...$this->scrollMarks[$widget]['stuck'] ?? [], \WeakReference::create($sticky)]] + ($this->scrollMarks[$widget] ?? []);
            $this->positionTracker->shiftContentPositions([$sticky], 0, $shift);
            $from = $rect->row - $contentRow;
            $col = $rect->col - $contentCol;
            for ($i = 0; $i < $rect->rows; ++$i) {
                $target = $from + $shift + $i - $top;
                if ($target < 0 || $target >= \count($visible)) {
                    continue;
                }
                $line = $visible[$target];
                $width = max(AnsiUtils::visibleWidth($line), $col + $rect->columns);
                $visible[$target] = AnsiUtils::sliceToWidth($line, 0, $col)."\e[0m".AnsiUtils::sliceToWidth($all[$from + $i] ?? '', $col, $rect->columns)."\e[0m".AnsiUtils::sliceToWidth($line, $col + $rect->columns, $width - $col - $rect->columns);
            }
        }
        $this->positionTracker->scrollContentPositions($children, -$top, $contentRow, $contentRow + $viewport);
        if (null === $barColumn) {
            return new ArrayLineBuffer($visible);
        }

        $rows = max(\count($visible), min($viewport, $style->getMinRows() ?? 0));
        $thumbRows = $total > $rows ? max(1, intdiv($rows * $rows + ($total >> 1), $total)) : $rows;
        $thumbTop = $total > $rows ? intdiv($top * ($rows - $thumbRows) + (($total - $rows) >> 1), $total - $rows) : 0;
        $scroll = $style->getScroll();
        // Light lines for a thin one
        $thumb = new Style(color: $scroll?->thumb)->apply($scroll?->thin ? '│' : '┃');
        $track = new Style(color: $scroll?->track, dim: null === $scroll?->track)->apply($scroll?->thin ? '┊' : '│');
        $barred = [];
        for ($row = 0; $row < $rows; ++$row) {
            $line = $visible[$row] ?? '';
            $width = AnsiUtils::visibleWidth($line);
            $line = $width > $barColumn ? AnsiUtils::truncateToWidth($line, $barColumn, '') : $line.str_repeat(' ', $barColumn - $width);
            $barred[] = $line.($row >= $thumbTop && $row < $thumbTop + $thumbRows ? $thumb : $track);
        }

        return new ArrayLineBuffer($barred);
    }

    /**
     * Shows a window of content wider than a scroll container, like CSS overflow-x: auto: the columns from where it
     * is scrolled sideways, the widgets moved and cut along.
     *
     * @param list<AbstractWidget> $children
     */
    private function scrollSideways(ContainerWidget $widget, array $children, LineBufferInterface $lines, int $visible, int $total, int $contentCol, Style $style): LineBufferInterface
    {
        $total = max($total, $visible);
        $left = min($widget->getScrollLeft(), $total - $visible);
        if ($this->positionTracker->isActive()) {
            $this->forgetScrollMarks($widget, 'x');
        }
        $scroll = $style->getScroll();
        $clearLeft = min($visible - 1, $scroll->paddingLeft ?? 0);
        $clearRight = min($visible - 1 - $clearLeft, $scroll->paddingRight ?? 0);
        // The fewest columns that bring a widget into view, with its scroll-margin, within the scroll-padding
        $nearest = static function (AbstractWidget $target, WidgetRect $rect, Style $targetStyle) use (&$left, $visible, $total, $contentCol, $clearLeft, $clearRight): void {
            $margin = $targetStyle->getScrollMargin() ?? [];
            $first = $rect->col - $contentCol - ($margin[2] ?? 0);
            $last = $rect->col - $contentCol + $rect->columns + ($margin[3] ?? 0);
            if ($first < $left + $clearLeft) {
                $left = max(0, $first - $clearLeft);
            } elseif ($last > $left + $visible - $clearRight) {
                $left = max(0, min($first - $clearLeft, $last - $visible + $clearRight));
            }
            $left = min($left, $total - $visible);
        };
        // Focus moving into it, or a target inside, scrolls it into view sideways, once
        $focused = $this->positionTracker->isActive() ? $this->findFocused($children) : false;
        if (null === $focused) {
            unset($this->revealedSideways[$widget]);
        } elseif (false !== $focused && ($this->revealedSideways[$widget] ?? null)?->get() !== $focused && null !== $rect = $this->positionTracker->getWidgetRect($focused)) {
            $this->revealedSideways[$widget] = \WeakReference::create($focused);
            $nearest($focused, $rect, $this->resolveStyle($focused));
        }
        if ($this->positionTracker->isActive() && null !== ($target = $this->revealing?->get()) && !isset($this->revealedTargetSideways[$widget]) && null !== $rect = $this->positionTracker->getWidgetRect($target)) {
            for ($ancestor = $target->getParent(); null !== $ancestor && $ancestor !== $widget; $ancestor = $ancestor->getParent()) {
            }
            if (null !== $ancestor) {
                $this->revealedTargetSideways[$widget] = true;
                $nearest($target, $rect, $this->resolveStyle($target));
            }
        }
        // scroll-snap-type: x, where it is scrolled to sideways, it rests on a snap position of a child
        if (null !== $scroll?->snapSideways && $this->positionTracker->isActive() && $total > $visible) {
            $left = $this->snap($widget, $scroll, $children, $left, $total, $visible, $contentCol, true);
        }
        $widget->setHorizontalScrollMetrics($left, $total, $visible, true === $scroll?->containedSideways);
        if ($this->positionTracker->isActive()) {
            $this->recordScroll($widget, $left, $total, $visible, true);
            $this->recordOffsets($children, $contentCol, true);
            if (!$scroll?->vertical) {
                $this->recordCurrentMarker($widget, $left, $total, $visible, $contentCol, true);
            }
        }

        // scroll-behavior: smooth, the columns shown glide from the last ones to where the content is scrolled
        if ($this->positionTracker->isActive()) {
            $left = $this->glide($this->sidewaysGlides, $this->shownLefts, $widget, $left, true === $scroll?->smooth);
        }
        if ($total <= $visible) {
            return $lines;
        }

        $this->positionTracker->scrollContentColumns($children, -$left, $contentCol, $contentCol + $visible);

        // The lines it showed last are cut once, like those of a long page where one changed: kept by their text, for
        // the columns shown
        [$from, $columns, $cut] = $this->slicedSideways[$widget] ?? [null, null, []];
        if ($from !== $left || $columns !== $visible) {
            $cut = [];
        }
        $kept = [];
        $sliced = [];
        foreach ($lines->toArray() as $line) {
            $sliced[] = $kept[$line] = $cut[$line] ?? $kept[$line] ?? AnsiUtils::sliceToWidth($line, $left, $visible);
        }
        $this->slicedSideways[$widget] = [$left, $visible, $kept];

        return new ArrayLineBuffer($sliced);
    }

    /**
     * Where a smooth scroll container shows its content from, gliding from where it showed it last to where it is
     * scrolled to, in about 150 ms, easing out.
     *
     * @param \WeakMap<ContainerWidget, array{int, int, float}> $glides
     * @param \WeakMap<ContainerWidget, int>                    $shown
     */
    private function glide(\WeakMap $glides, \WeakMap $shown, ContainerWidget $widget, int $to, bool $smooth): int
    {
        $at = $to;
        $previous = $shown[$widget] ?? null;
        if (null !== $previous && $smooth) {
            $now = ($this->clock)();
            $glide = $glides[$widget] ?? null;
            if ($previous !== $to && $to !== ($glide[1] ?? null)) {
                $glide = [$previous, $to, $now];
            }
            $progress = null !== $glide ? ($now - $glide[2]) / 0.15 : 1.0;
            if (null !== $glide && $progress < 1.0) {
                $at = (int) round($glide[0] + ($glide[1] - $glide[0]) * (1 - (1 - max(0.0, $progress)) ** 2));
                $glides[$widget] = $glide;
            } else {
                unset($glides[$widget]);
            }
        } else {
            unset($glides[$widget]);
        }
        $shown[$widget] = $at;

        return $at;
    }

    /**
     * The horizontal scrollbar of a scroll container: a thumb as wide as the share of the content shown, where it is.
     */
    private function horizontalBar(ContainerWidget $widget, int $columns, Scroll $scroll): string
    {
        $total = max($columns, $widget->getScrollWidth());
        // Where the content shows from, while it glides too
        $left = $this->shownLefts[$widget] ?? $widget->getScrollLeft();
        $thumbColumns = $total > $columns ? max(1, intdiv($columns * $columns + ($total >> 1), $total)) : $columns;
        $thumbLeft = $total > $columns ? intdiv($left * ($columns - $thumbColumns) + (($total - $columns) >> 1), $total - $columns) : 0;
        $thumb = new Style(color: $scroll->thumb)->apply(str_repeat($scroll->thin ? '─' : '━', $thumbColumns));
        $track = new Style(color: $scroll->track, dim: null === $scroll->track);

        return ($thumbLeft > 0 ? $track->apply(str_repeat($scroll->thin ? '┈' : '─', $thumbLeft)) : '').$thumb
            .($columns - $thumbLeft - $thumbColumns > 0 ? $track->apply(str_repeat($scroll->thin ? '┈' : '─', $columns - $thumbLeft - $thumbColumns)) : '');
    }

    /**
     * The control of a scroll container drawn at a cell of the last frame, like a ::scroll-button(): the container and
     * what the control does, like "up", or null.
     *
     * @return array{ContainerWidget, string}|null
     */
    public function getScrollControlAt(int $row, int $col): ?array
    {
        foreach ($this->scrollControls as $container => $controls) {
            if (null === $rect = $this->positionTracker->getWidgetRect($container)) {
                continue;
            }
            foreach ($controls as [$controlRow, $from, $to, $action]) {
                if ($row === $rect->row + $controlRow && $col >= $rect->col + $from && $col < $rect->col + $to) {
                    return [$container, $action];
                }
            }
        }

        return null;
    }

    /**
     * Draws the ::scroll-button() of a scroll container on rows of their own: the up one above the content, the down
     * and left ones below at the start, the right one there at the end; dim when it cannot scroll that way.
     *
     * @param AbstractWidget[] $children
     */
    private function addScrollButtons(ContainerWidget $widget, array $children, LineBufferInterface $lines, int $columns, Style $style): LineBufferInterface
    {
        $scrollable = $this->scrollStates[$widget]['scrollable'] ?? [];
        $sides = ['up' => 'top', 'down' => 'bottom', 'left' => 'left', 'right' => 'right'];
        $buttons = [];
        foreach ($widget->getScrollButtons() as $direction => $text) {
            $buttonStyle = $this->styleSheet->resolveElement($widget, 'scroll-button('.$direction.')');
            if (true === $buttonStyle->getHidden() || '' === $text) {
                continue;
            }
            $text = AnsiUtils::truncateToWidth($text, max(1, $columns), '');
            $enabled = \in_array($sides[$direction], $scrollable, true);
            $buttons[$direction] = [($enabled ? $buttonStyle : $buttonStyle->withDim(true))->apply($text), AnsiUtils::visibleWidth($text)];
        }
        [$position, $markers] = $widget->getScrollMarkers();
        if (!$buttons && null === $position) {
            return $lines;
        }

        [$chromeTop, $chromeLeft] = $this->chromeApplier->computeChromeOffset($style);
        $controls = [];
        $before = [];
        // scroll-marker-group: the markers of the children, the current one matching :target-current
        $group = null;
        if (null !== $position) {
            $groupStyle = $this->styleSheet->resolveElement($widget, 'scroll-marker-group');
            $group = '';
            $at = 0;
            $markerControls = [];
            foreach ($markers as $index => [$child, $text]) {
                $width = AnsiUtils::visibleWidth($text);
                if ($at + $width > $columns) {
                    break;
                }
                $group .= ($at > 0 ? ' ' : '').$this->styleSheet->resolveElement($child, 'scroll-marker')->apply($text)."\x1b[0m".$groupStyle->getAnsiRestore();
                $at += ($at > 0 ? 1 : 0);
                $markerControls[] = [$at, $at + $width, 'marker:'.$index];
                $at += $width;
            }
            $group = $groupStyle->apply($group);
        }
        if ('before' === $position) {
            foreach ($markerControls as [$from, $to, $action]) {
                $controls[] = [$chromeTop, $chromeLeft + $from, $chromeLeft + $to, $action];
            }
            $before[] = $group;
            $this->positionTracker->shiftContentPositions($children, 0, 1);
        }
        if (isset($buttons['up'])) {
            $controls[] = [$chromeTop + \count($before), $chromeLeft, $chromeLeft + $buttons['up'][1], 'up'];
            $before[] = $buttons['up'][0];
            $this->positionTracker->shiftContentPositions($children, 0, 1);
        }
        $count = \count($before) + \count($lines);
        $start = [];
        $at = 0;
        foreach (['down', 'left'] as $direction) {
            if (isset($buttons[$direction])) {
                $start[] = $buttons[$direction][0];
                $controls[] = [$chromeTop + $count, $chromeLeft + $at, $chromeLeft + $at + $buttons[$direction][1], $direction];
                $at += $buttons[$direction][1] + 1;
            }
        }
        $after = [];
        if ($start || isset($buttons['right'])) {
            $row = implode(' ', $start);
            if (isset($buttons['right'])) {
                $from = max($at, $columns - $buttons['right'][1]);
                $row .= str_repeat(' ', max(0, $from - AnsiUtils::visibleWidth($row))).$buttons['right'][0];
                $controls[] = [$chromeTop + $count, $chromeLeft + $from, $chromeLeft + $from + $buttons['right'][1], 'right'];
            }
            $after[] = $row;
        }
        if ('after' === $position) {
            foreach ($markerControls as [$from, $to, $action]) {
                $controls[] = [$chromeTop + $count + \count($after), $chromeLeft + $from, $chromeLeft + $to, $action];
            }
            $after[] = $group;
        }
        if ($this->positionTracker->isActive()) {
            $this->scrollControls[$widget] = $controls;
        }

        return new ConcatenatedLineBuffer([new ArrayLineBuffer($before), $lines, new ArrayLineBuffer($after)]);
    }

    /**
     * The height of a widget as its content makes it where it was last drawn, its padding and border included, like
     * CSS height: auto; null when it was not drawn.
     */
    public function getIntrinsicRows(AbstractWidget $widget): ?int
    {
        if (null === $rows = $this->contentRows[$widget] ?? null) {
            return null;
        }
        $style = $this->resolveStyle($widget);

        return $rows + ($style->getPadding()?->top ?? 0) + ($style->getPadding()?->bottom ?? 0) + ($style->getBorder()?->top ?? 0) + ($style->getBorder()?->bottom ?? 0);
    }

    /**
     * The scroll state of a widget in the last frame, for CSS scroll-state() queries: the sides it is stuck to, the
     * axes it is snapped on, the sides it can be scrolled to and the sides it was last scrolled to.
     *
     * @return array{stuck: list<'top'|'bottom'>, snapped: list<'x'|'y'>, scrollable: list<'top'|'bottom'|'left'|'right'>, scrolled: list<'top'|'bottom'|'left'|'right'>, targetCurrent: bool, offset: array{array{}|array{int, int}, array{}|array{int, int}}}
     */
    public function getScrollState(AbstractWidget $widget): array
    {
        $state = $this->scrollStates[$widget] ?? [];

        return [
            'stuck' => $state['stuck'] ?? [],
            'snapped' => $state['snapped'] ?? [],
            'scrollable' => $state['scrollable'] ?? [],
            'scrolled' => $widget instanceof ContainerWidget ? $this->scrolled[$widget][2] ?? [] : [],
            'targetCurrent' => true === ($state['targetCurrent'] ?? false),
            'offset' => $state['offset'] ?? [[], []],
        ];
    }

    /**
     * Records where a scroll container can be scrolled to, and where it was scrolled to last.
     */
    private function recordScroll(ContainerWidget $widget, int $at, int $total, int $visible, bool $sideways): void
    {
        [$start, $end] = $sideways ? ['left', 'right'] : ['top', 'bottom'];
        $scrollable = array_values(array_diff($this->scrollStates[$widget]['scrollable'] ?? [], [$start, $end]));
        if ($at > 0) {
            $scrollable[] = $start;
        }
        if ($at + $visible < $total) {
            $scrollable[] = $end;
        }
        $this->scrollStates[$widget] = ['scrollable' => $scrollable] + ($this->scrollStates[$widget] ?? []);

        [$top, $left, $last] = $this->scrolled[$widget] ?? [null, null, []];
        $previous = $sideways ? $left : $top;
        if (null !== $previous && $at !== $previous) {
            // The last scroll on this axis replaces the one before on it, the other axis kept
            $last = array_values(array_diff($last, [$start, $end]));
            $last[] = $at > $previous ? $end : $start;
        }
        $this->scrolled[$widget] = $sideways ? [$top, $at, $last] : [$at, $left, $last];
    }

    /**
     * Records which child of a scroll container its current ::scroll-marker stands for: the last one that starts
     * where it is scrolled to or before, the last one shown when it is scrolled to the end.
     */
    private function recordCurrentMarker(ContainerWidget $widget, int $at, int $total, int $visible, int $contentStart, bool $sideways): void
    {
        $markers = $widget->getScrollMarkers()[1];
        if (!$markers) {
            return;
        }
        $current = $markers[0][0];
        $atEnd = $total > $visible && $at + $visible >= $total;
        foreach ($markers as [$child]) {
            if (null === $rect = $this->positionTracker->getWidgetRect($child)) {
                continue;
            }
            $start = ($sideways ? $rect->col : $rect->row) - $contentStart;
            if ($start <= $at || ($atEnd && $start < $at + $visible)) {
                $current = $child;
            }
        }
        foreach ($markers as [$child]) {
            $this->scrollStates[$child] = ['targetCurrent' => $child === $current] + ($this->scrollStates[$child] ?? []);
        }
    }

    /**
     * Records where the widgets in a scroll container start in its content and how long they are, on an axis, for
     * CSS view timelines; not inside a scroll container in it, which records its own.
     *
     * @param AbstractWidget[] $widgets
     */
    private function recordOffsets(array $widgets, int $contentStart, bool $sideways, int $depth = 0): void
    {
        foreach ($widgets as $widget) {
            if (null !== $rect = $this->positionTracker->getWidgetRect($widget)) {
                $offset = $this->scrollStates[$widget]['offset'] ?? [[], []];
                $offset[$sideways ? 1 : 0] = $sideways ? [$rect->col - $contentStart, $rect->columns] : [$rect->row - $contentStart, $rect->rows];
                $this->scrollStates[$widget] = ['offset' => $offset] + ($this->scrollStates[$widget] ?? []);
            }
            if ($depth < 32 && $widget instanceof ParentInterface && null === $this->resolveStyle($widget)->getScroll()) {
                $this->recordOffsets($widget->all(), $contentStart, $sideways, $depth + 1);
            }
        }
    }

    /**
     * Records the widgets a scroll container snapped to, on an axis.
     *
     * @param list<array{int, bool, AbstractWidget}> $points
     */
    private function recordSnapped(ContainerWidget $scroller, array $points, int $snapped, bool $sideways): void
    {
        $axis = $sideways ? 'x' : 'y';
        foreach ($points as [$position, , $child]) {
            if ($position === $snapped) {
                $axes = $this->scrollStates[$child]['snapped'] ?? [];
                $axes[] = $axis;
                $this->scrollStates[$child] = ['snapped' => array_values(array_unique($axes))] + ($this->scrollStates[$child] ?? []);
                $this->scrollMarks[$scroller] = [$axis => [...$this->scrollMarks[$scroller][$axis] ?? [], \WeakReference::create($child)]] + ($this->scrollMarks[$scroller] ?? []);
            }
        }
    }

    /**
     * Forgets the widgets a scroll container marked stuck, or snapped on an axis, before it marks them again.
     *
     * @param 'stuck'|'x'|'y' $kind
     */
    private function forgetScrollMarks(ContainerWidget $scroller, string $kind): void
    {
        foreach ($this->scrollMarks[$scroller][$kind] ?? [] as $reference) {
            if (null === $marked = $reference->get()) {
                continue;
            }
            $state = $this->scrollStates[$marked] ?? [];
            if ('stuck' === $kind) {
                unset($state['stuck']);
            } else {
                $state['snapped'] = array_values(array_diff($state['snapped'] ?? [], [$kind]));
            }
            $this->scrollStates[$marked] = $state;
        }
        if (isset($this->scrollMarks[$scroller])) {
            $marks = $this->scrollMarks[$scroller];
            unset($marks[$kind]);
            $this->scrollMarks[$scroller] = $marks;
        }
    }

    /**
     * The row, or the column, a snapping scroll container rests on: the snap position of a child nearest where it is
     * scrolled to, in the way it went, unless a child that stops scrolls is on the way; with proximity, only when one
     * is near.
     *
     * @param list<AbstractWidget> $children
     */
    private function snap(ContainerWidget $widget, Scroll $scroll, array $children, int $at, int $total, int $viewport, int $contentStart, bool $sideways = false): int
    {
        $points = [];
        $collect = function (array $widgets) use (&$collect, &$points, $scroll, $total, $viewport, $contentStart, $sideways): void {
            foreach ($widgets as $child) {
                $childStyle = $this->resolveStyle($child);
                $align = $childStyle->getSnapAlign();
                $side = $sideways ? $align[2] ?? null : $align[0] ?? null;
                if (null !== $side && null !== $rect = $this->positionTracker->getWidgetRect($child)) {
                    $margin = $childStyle->getScrollMargin() ?? [];
                    [$before, $after] = $sideways ? [$margin[2] ?? 0, $margin[3] ?? 0] : [$margin[0] ?? 0, $margin[1] ?? 0];
                    [$paddingBefore, $paddingAfter] = $sideways ? [$scroll->paddingLeft, $scroll->paddingRight] : [$scroll->paddingTop, $scroll->paddingBottom];
                    $start = ($sideways ? $rect->col : $rect->row) - $contentStart;
                    $size = $sideways ? $rect->columns : $rect->rows;
                    $position = match ($side) {
                        'start' => $start - $before - $paddingBefore,
                        'end' => $start + $size + $after + $paddingAfter - $viewport,
                        default => (int) round($start + ($size - $viewport) / 2),
                    };
                    $points[] = [max(0, min($total - $viewport, $position)), $align[1], $child];
                }
                // The children of a scroll container inside snap in it
                if ($child instanceof ParentInterface && null === $childStyle->getScroll()) {
                    $collect($child->all());
                }
            }
        };
        $collect($children);
        if (!$points) {
            return $at;
        }

        $snappedAt = $sideways ? $this->snappedLefts : $this->snappedTops;
        $previous = $snappedAt[$widget] ?? null;
        $direction = null === $previous ? 0 : $at <=> $previous;
        usort($points, static fn (array $a, array $b) => $direction < 0 ? $b[0] <=> $a[0] : $a[0] <=> $b[0]);
        if (0 !== $direction) {
            // scroll-snap-stop: always, a scroll does not go past it, however far
            foreach ($points as [$position, $always]) {
                if ($always && ($position - $previous) * $direction > 0 && ($at - $position) * $direction > 0) {
                    $this->recordSnapped($widget, $points, $position, $sideways);

                    return $snappedAt[$widget] = $position;
                }
            }
        }
        // The nearest one in the way it went, so that a small scroll goes to the next one; else the nearest one
        $ahead = 0 === $direction ? [] : array_filter($points, static fn (array $point) => $point[0] === $at || ($point[0] - $previous) * $direction > 0);
        $snapped = null;
        foreach ($ahead ?: $points as [$position]) {
            if (null === $snapped || abs($position - $at) < abs($snapped - $at)) {
                $snapped = $position;
            }
        }
        if ('proximity' === ($sideways ? $scroll->snapSideways : $scroll->snap) && abs($snapped - $at) > intdiv($viewport, 3)) {
            $snapped = $at;
        }
        $this->recordSnapped($widget, $points, $snapped, $sideways);

        return $snappedAt[$widget] = $snapped;
    }

    /**
     * The sticky widgets of a scroll container that its scroll position moves, with the rows they move down by.
     *
     * A sticky widget stays its top rows below the top of the window, but not past the end of its parent's content.
     *
     * @param AbstractWidget[] $widgets
     *
     * @return list<array{AbstractWidget, WidgetRect, int}>
     */
    private function stickyPins(ContainerWidget $scroller, array $widgets, int $top, int $total, int $contentRow, int $viewport): array
    {
        $pins = [];
        foreach ($widgets as $widget) {
            $style = $this->resolveStyle($widget);
            $stickyTop = $style->getStickyTop();
            $stickyBottom = $style->getStickyBottom();
            if ((null !== $stickyTop || null !== $stickyBottom) && null !== $rect = $this->positionTracker->getWidgetRect($widget)) {
                // It moves within the content of its parent; a part of a table, like a header cell, within the table
                $parent = $widget->getParent();
                if (\in_array($style->getTableRole(), [TableRole::Cell, TableRole::Row, TableRole::RowGroup], true)) {
                    for ($table = $parent; null !== $table && $table !== $scroller && TableRole::Table !== $this->resolveStyle($table)->getTableRole(); $table = $table->getParent()) {
                    }
                    $parent = $table ?? $parent;
                }
                $start = 0;
                $end = $total;
                if ($parent !== $scroller && null !== $parent && null !== $parentRect = $this->positionTracker->getWidgetRect($parent)) {
                    $parentStyle = $this->resolveStyle($parent);
                    $parentPadding = $parentStyle->withResolvedBox($parentRect->columns)->getPadding();
                    $start = $parentRect->row - $contentRow + ($parentStyle->getBorder()->top ?? 0) + ($parentPadding->top ?? 0);
                    $end = $parentRect->row - $contentRow + $parentRect->rows - ($parentStyle->getBorder()->bottom ?? 0) - ($parentPadding->bottom ?? 0);
                }
                $row = $rect->row - $contentRow;
                $shift = 0;
                // Below the top: down, not past the end of its parent
                // Only down: a widget past the end of its parent already is not pulled up
                if (null !== $stickyTop) {
                    $shift = max(0, min(max($row, $top + $stickyTop), $end - $rect->rows) - $row);
                }
                // Above the bottom: up, not before the start of its parent
                if (0 === $shift && null !== $stickyBottom) {
                    $shift = max(min($row, $top + $viewport - $stickyBottom - $rect->rows), $start) - $row;
                    $shift = min(0, $shift);
                }
                if (0 !== $shift) {
                    $pins[] = [$widget, $rect, $shift];
                }
            }
            // A scroll container inside pins its own
            if ($widget instanceof ParentInterface && null === $style->getScroll()) {
                array_push($pins, ...$this->stickyPins($scroller, $widget->all(), $top, $total, $contentRow, $viewport));
            }
        }

        return $pins;
    }

    /**
     * @param AbstractWidget[] $widgets
     */
    private function findFocused(array $widgets): ?AbstractWidget
    {
        foreach ($widgets as $widget) {
            if ($widget instanceof FocusableInterface && $widget->isFocused()) {
                return $widget;
            }
            if ($widget instanceof ParentInterface && null !== $focused = $this->findFocused($widget->all())) {
                return $focused;
            }
        }

        return null;
    }

    private function validateLines(AbstractWidget $widget, LineBufferInterface $lines, int $availableColumns): void
    {
        if ($lines->getMaxVisibleWidth() <= $availableColumns) {
            return;
        }

        foreach ($lines as $i => $line) {
            if ('' === $line || AnsiUtils::containsImage($line)) {
                continue;
            }

            $lineWidth = AnsiUtils::visibleWidth($line);
            if ($lineWidth > $availableColumns) {
                throw new RenderException(\sprintf("Widget \"%s\" rendered line %d with width %d, exceeding the available %d columns.\nLine preview: \"%s\".", $widget::class, $i, $lineWidth, $availableColumns, mb_substr(AnsiUtils::stripAnsiCodes($line), 0, 100)), $i, $lineWidth, $availableColumns);
            }
        }
    }

    private function drawFlowOverflow(LineBufferInterface $frame, ContainerWidget $root, int $columns, int $rows): LineBufferInterface
    {
        $items = [];
        foreach ($this->flowOverflow as $widget => [$revision, $lines, $offset, $viewport]) {
            $parent = $widget->getParent();
            if ($revision !== $widget->getRenderRevision() || null === $parent || null === $rect = $this->positionTracker->getWidgetRect($widget)) {
                unset($this->flowOverflow[$widget]);
                continue;
            }
            $style = $this->resolveStyle($widget);
            $parentStyle = $this->resolveStyle($parent);
            if (null !== $style->getPosition() || $style->getHidden() || $style->getInvisible() || $style->getTransparent()
                || Direction::Horizontal !== $parentStyle->getDirection() || null !== $parentStyle->getGridTemplate() || null !== $parentStyle->getTableRole() || null !== $parentStyle->getWritingMode()) {
                unset($this->flowOverflow[$widget]);
                continue;
            }
            $path = [];
            $following = [];
            $current = $widget;
            for (; $current !== $root && null !== $ancestor = $current->getParent(); $current = $ancestor) {
                if (null !== $this->resolveStyle($ancestor)->getWritingMode()) {
                    $path = null;
                    break;
                }
                $siblings = $ancestor instanceof ParentInterface ? $this->boxChildren($ancestor) : [];
                if (null !== $this->resolveStyle($ancestor)->getDirection()) {
                    usort($siblings, fn (AbstractWidget $a, AbstractWidget $b): int => ($this->resolveStyle($a)->getOrder() ?? 0) <=> ($this->resolveStyle($b)->getOrder() ?? 0));
                }
                $index = array_search($current, $siblings, true);
                array_unshift($path, false === $index ? 0 : $index);
                if (false !== $index) {
                    array_push($following, ...\array_slice($siblings, $index + 1));
                }
            }
            if (null !== $path && $current === $root) {
                $items[] = [$path, $widget, $this->flowEffects($widget, $lines), $rect, $offset, $viewport, $following];
            } else {
                unset($this->flowOverflow[$widget]);
            }
        }
        if (!$items) {
            $this->flowPaints = new \WeakMap();

            return $frame;
        }
        usort($items, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $out = $frame->toArray();
        $limit = min(self::MAX_LINES, max($rows, \count($out)));
        foreach ($items as [, $widget, $lines, $rect, $offset, $viewport, $following]) {
            $clip = $this->flowClip($widget, $columns, $limit);
            $from = max($clip[1], $rect->col);
            $to = min($clip[3], $rect->col + $rect->columns);
            if ($from >= $to) {
                continue;
            }
            $seeThrough = !$this->paintsBackground($widget) && null !== ($background = $this->getInheritedBackground($widget)) ? substr($background->toBackgroundCode(), 2, -1) : null;
            $start = $rect->row - $offset;
            $this->markFlowOverflow($widget, $rect, $clip, $start, $viewport);
            foreach ($lines as $i => $line) {
                $target = $rect->row + $i;
                if ($target < $clip[0] || $target >= $clip[2] || ($target >= $start && $target < $start + $viewport)) {
                    continue;
                }
                while (\count($out) <= $target) {
                    $out[] = '';
                }
                $layers = [new Layer([$out[$target]], 0, 0, false, $columns, 1), new Layer([AnsiUtils::sliceToWidth($line, $from - $rect->col, $to - $from)], 0, $from, true, null, null, $seeThrough)];
                foreach ($following as $later) {
                    $laterStyle = $this->resolveStyle($later);
                    $box = $this->positionTracker->getWidgetRect($later);
                    $paint = null !== $box ? ($this->flowPaints[$later][$box->columns.':'.$box->rows] ?? null) : null;
                    if (null === $paint || null === $box || $laterStyle->getPosition()?->isOutOfFlow() || ($laterStyle->getPosition()?->zIndex ?? 0) < 0
                        || $target < $box->row || $target >= $box->row + \count($paint)) {
                        continue;
                    }
                    $laterClip = $this->flowClip($later, $columns, $limit);
                    if ($target < $laterClip[0] || $target >= $laterClip[2]) {
                        continue;
                    }
                    $left = max($from, $box->col, $laterClip[1]);
                    $right = min($to, $box->col + $box->columns, $laterClip[3]);
                    if ($left >= $right) {
                        continue;
                    }
                    $paint = $this->flowEffects($later, $paint);
                    $background = !$this->paintsBackground($later) ? $this->getInheritedBackground($later) : null;
                    $layers[] = new Layer([AnsiUtils::sliceToWidth($paint->getLine($target - $box->row), $left - $box->col, $right - $left)], 0, $left, true, null, null, null !== $background ? substr($background->toBackgroundCode(), 2, -1) : null);
                }
                $out[$target] = Compositor::composite(...$layers)[0];
            }
        }

        return new ArrayLineBuffer($out);
    }

    /** @param array{int, int, int, int} $clip */
    private function markFlowOverflow(AbstractWidget $widget, WidgetRect $rect, array $clip, int $start, int $viewport): void
    {
        $this->flowClips[$widget] = $rect->clip;
        $this->positionTracker->setWidgetRect($widget, $rect->withClip($clip));
        $left = max($rect->col, $clip[1]);
        $width = min($rect->col + $rect->columns, $clip[3]) - $left;
        foreach ([[max($rect->row, $clip[0]), min($start, $clip[2])], [max($start + $viewport, $clip[0]), min($rect->row + $rect->rows, $clip[2])]] as [$top, $bottom]) {
            if ($bottom <= $top || $width <= 0) {
                continue;
            }
            for ($parent = $widget->getParent(); null !== $parent; $parent = $parent->getParent()) {
                if (null === $box = $this->positionTracker->getWidgetRect($parent)) {
                    continue;
                }
                if (!isset($this->flowFragments[$parent])) {
                    $this->flowFragments[$parent] = $box->fragments;
                }
                $fragment = [$top - $box->row, $left - $box->col, $width, $bottom - $top];
                $fragments = $box->fragments;
                if (!\in_array($fragment, $fragments, true)) {
                    $fragments[] = $fragment;
                    $this->positionTracker->setWidgetRect($parent, new WidgetRect($box->row, $box->col, $box->columns, $box->rows, $fragments, $box->clip));
                }
            }
        }
    }

    /** @return array{int, int, int, int} */
    private function flowClip(AbstractWidget $widget, int $columns, int $rows): array
    {
        $clip = [0, 0, $rows, $columns];
        for ($ancestor = $widget->getParent(); null !== $ancestor; $ancestor = $ancestor->getParent()) {
            $style = $this->resolveStyle($ancestor);
            if ($style->getInvisible() || $style->getTransparent()) {
                return [0, 0, 0, 0];
            }
            if (null === $box = $this->paddingBox($ancestor)) {
                continue;
            }
            if (null !== $style->getScroll() || $this->clipsOverflow($ancestor, true)) {
                $clip[0] = max($clip[0], $box[0]);
                $clip[2] = min($clip[2], $box[0] + $box[3]);
            }
            if (null !== $style->getScroll() || $this->clipsSideways($ancestor)) {
                $clip[1] = max($clip[1], $box[1]);
                $clip[3] = min($clip[3], $box[1] + $box[2]);
            }
        }

        return $clip;
    }

    private function flowEffects(AbstractWidget $widget, LineBufferInterface $lines): LineBufferInterface
    {
        for ($parent = $widget->getParent(); null !== $parent; $parent = $parent->getParent()) {
            $lines = $this->paintEffects($this->resolveStyle($parent), $lines);
        }

        return $lines;
    }
}
