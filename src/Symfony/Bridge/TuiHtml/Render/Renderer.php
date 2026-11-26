<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Render;

use Symfony\Bridge\TuiHtml\Css\Value;
use Symfony\Bridge\TuiHtml\Exception\RenderException;
use Symfony\Bridge\TuiHtml\Internal\ContainerState;
use Symfony\Bridge\TuiHtml\Internal\ContextBridge;
use Symfony\Bridge\TuiHtml\Internal\FontFamilies;
use Symfony\Bridge\TuiHtml\Internal\NativeStyleProvider;
use Symfony\Bridge\TuiHtml\Internal\RenderCache;
use Symfony\Bridge\TuiHtml\Internal\ScrollbarPresentation;
use Symfony\Bridge\TuiHtml\Internal\StyleBridge;
use Symfony\Bridge\TuiHtml\Internal\TextMarkers;
use Symfony\Bridge\TuiHtml\Style\Align;
use Symfony\Bridge\TuiHtml\Style\AutoMargin;
use Symfony\Bridge\TuiHtml\Style\BackgroundLayout;
use Symfony\Bridge\TuiHtml\Style\Border;
use Symfony\Bridge\TuiHtml\Style\BorderPattern;
use Symfony\Bridge\TuiHtml\Style\Color;
use Symfony\Bridge\TuiHtml\Style\ColorScheme;
use Symfony\Bridge\TuiHtml\Style\Columns;
use Symfony\Bridge\TuiHtml\Style\DefaultStyleSheet;
use Symfony\Bridge\TuiHtml\Style\Direction;
use Symfony\Bridge\TuiHtml\Style\Filter;
use Symfony\Bridge\TuiHtml\Style\Gradient;
use Symfony\Bridge\TuiHtml\Style\IntrinsicSizeAwareInterface;
use Symfony\Bridge\TuiHtml\Style\Justify;
use Symfony\Bridge\TuiHtml\Style\LayoutAwareStyleProviderInterface;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Bridge\TuiHtml\Style\Outline;
use Symfony\Bridge\TuiHtml\Style\Padding;
use Symfony\Bridge\TuiHtml\Style\Position;
use Symfony\Bridge\TuiHtml\Style\PositionMode;
use Symfony\Bridge\TuiHtml\Style\PseudoElementProviderInterface;
use Symfony\Bridge\TuiHtml\Style\RasterImage;
use Symfony\Bridge\TuiHtml\Style\Scroll;
use Symfony\Bridge\TuiHtml\Style\ScrollStateAwareInterface;
use Symfony\Bridge\TuiHtml\Style\Style;
use Symfony\Bridge\TuiHtml\Style\StyleSheet;
use Symfony\Bridge\TuiHtml\Style\TableRole;
use Symfony\Bridge\TuiHtml\Style\TextAlign;
use Symfony\Bridge\TuiHtml\Style\VerticalAlign;
use Symfony\Bridge\TuiHtml\Widget\CheckboxWidget;
use Symfony\Bridge\TuiHtml\Widget\CollapsibleWidget;
use Symfony\Bridge\TuiHtml\Widget\ContainerWidget as HtmlContainerWidget;
use Symfony\Bridge\TuiHtml\Widget\FormWidget;
use Symfony\Bridge\TuiHtml\Widget\HorizontalRuleWidget;
use Symfony\Bridge\TuiHtml\Widget\ImageWidget;
use Symfony\Bridge\TuiHtml\Widget\InputWidget;
use Symfony\Bridge\TuiHtml\Widget\ReplacedElementInterface;
use Symfony\Bridge\TuiHtml\Widget\RichTextWidget;
use Symfony\Bridge\TuiHtml\Widget\SvgWidget;
use Symfony\Bridge\TuiHtml\Widget\Text\InlineImages;
use Symfony\Bridge\TuiHtml\Widget\Text\InlineNode;
use Symfony\Bridge\TuiHtml\Widget\TextWidget;
use Symfony\Component\HtmlRenderer\Exception\InvalidArgumentException;
use Symfony\Component\HtmlRenderer\Paint\BlendMode;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Ansi\TextWrapper;
use Symfony\Component\Tui\Focus\FocusPolicyInterface;
use Symfony\Component\Tui\Render\FrameWriterInterface;
use Symfony\Component\Tui\Render\LineBufferInterface;
use Symfony\Component\Tui\Render\RenderContext as NativeContext;
use Symfony\Component\Tui\Render\RendererInterface;
use Symfony\Component\Tui\Render\WidgetRect;
use Symfony\Component\Tui\Style\DefaultStyleSheet as NativeDefaultStyleSheet;
use Symfony\Component\Tui\Style\Style as NativeStyle;
use Symfony\Component\Tui\Style\StyleSheet as NativeStyleSheet;
use Symfony\Component\Tui\Terminal\TerminalInterface;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\Figlet\FontRegistry;
use Symfony\Component\Tui\Widget\FocusableInterface;
use Symfony\Component\Tui\Widget\ParentInterface;

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
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Renderer implements FocusPolicyInterface, WidgetRendererInterface, RendererInterface
{
    /** The most lines a box draws, past which they are cut */
    public const MAX_LINES = 100000;
    /**
     * The widest a child of a box that clips sideways is laid out, cut at the side of the box.
     */
    public const MAX_CLIPPED_COLUMNS = 2000;

    /** What an entry of the run of a multi-column container is, see spanningRun() */
    private const RUN_CONTENT = 0;
    private const RUN_SPANS = 1;
    private const RUN_OPEN = 2;
    private const RUN_CLOSE = 3;

    /**
     * The renders of a widget in other sizes kept with its last one: a flex or grid layout measures its items in a few
     * sizes before drawing them, like a vertical grid alternating two widths.
     */
    private const CACHED_OTHER_SIZES = 3;
    private StyleSheet $styleSheet;
    private ?ScreenWriter $frameWriter = null;
    private ?\WeakReference $runtime = null;
    private ?string $glideTimer = null;
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
     * Resolved styles, keyed on the widget's render revision so that a style changed from beforeRender() is picked up:
     * those of the current frame, and those of earlier ones that nothing else they were resolved from changed for.
     *
     * @var \WeakMap<AbstractWidget, array{int, Style, Style, int, ?string}> Each style, the one of the style sheets it was
     *                                                                       resolved from, the frame, and what it was resolved
     *                                                                       with when it can be kept for later frames
     */
    private \WeakMap $styleCache;
    /** Counts the frames, for the styles resolved in the current one */
    private int $styleFrame = 0;
    /** What the styles kept for later frames were resolved with: the columns, the rules and the media */
    private string $styleInputs = '';
    /**
     * Counts the changes that can give a widget another resolved style within a frame, for the caches built on them.
     */
    private int $styleEpoch = 0;
    /** How many times the styles resolved were all forgotten, see getLayoutKey() */
    private int $styleResets = 0;
    /** Whether what was asked of the floats read a style resolved again each frame, like that of a table cell, see floatsKey() */
    private bool $floatsVolatile = false;
    /** @var array<class-string, bool> Whether each class of widgets does something before it renders, see canReplayRender() */
    private array $beforeRenderOverridden = [];
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
    /** @var array{AbstractWidget, list<array{AbstractWidget, array<string, mixed>|null, WidgetRect}>, array<int, array<int, int|string>>}|array{} The widgets the last frame drew over the flow, null the stacking key of a stuck sticky one, see getRectsDrawnOver() */
    private array $drawnOver = [];
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
    /** @var \WeakMap<AbstractWidget, array{int, string, bool, int, int, bool}> Whether each widget lets its floats out, by revision and floatsKey(), its parent, and the depth its answer was cut short at, -1 for none, see letsFloatsOut() */
    private \WeakMap $floatsOut;
    /** @var \WeakMap<AbstractWidget, array{int, string, bool, array{int, int}, int, bool}> Whether the floats of each widget go on past it, by revision and floatsKey(), its parent and its revision, and the depth its answer was cut short at, -1 for none, see escapesFloats() */
    private \WeakMap $floatsEscape;
    /** @var \WeakMap<ContainerWidget, array{int, bool, string}> Whether each block holds items of a list, by revision and floatsKey(), see holdsMarkedItems() */
    private \WeakMap $markedItems;
    /**
     * The item of a list each block was last found to hold, null for none, see holdsMarkedItems(): with the change serial
     * and floatsKey() then, its children that changed since only looked at again.
     *
     * @var \WeakMap<ContainerWidget, array{int, string, AbstractWidget|null}>
     */
    private \WeakMap $markedWitnesses;
    /**
     * The child whose floats were last found to go on past each block, null for none, see escapesFloats(): with the
     * change serial and floatsKey() then, the depth it was asked at, and whether the block held items of a list; its
     * children that changed since only looked at again.
     *
     * @var \WeakMap<ContainerWidget, array{int, string, int, bool, AbstractWidget|null}>
     */
    private \WeakMap $escapeWitnesses;
    /**
     * What renderContainer() asks of all the children of each block, kept with the change serial and the layout key it
     * was known at, the children that changed since only looked at again, see plainChildren(): the children that are
     * not plain and those that are marked items, by object id, the widest width set of a child at most, and whether
     * the floats of the last layout were cleared from all of them, the revision of each child when looked at and that of
     * the block.
     *
     * @var \WeakMap<ContainerWidget, array{serial: int, key: string, other: array<int, true>, marked: array<int, true>, width: int, cleared: bool, revisions: array<int, int>, revision: int}>
     */
    private \WeakMap $childSummaries;
    /**
     * The boxes that clip what they hold the children of each widget, and what they hold, were cut by for the pointer,
     * see clipChildrenPointer(): by the object id of the box, the write serial after that, see
     * PositionTracker::getWriteSerial(), and the children by object id; cut by it again, only the children whose rects
     * were written since are.
     *
     * @var \WeakMap<AbstractWidget, array<int, array{int, array<int, AbstractWidget>}>>
     */
    private \WeakMap $pointerClips;
    /** @var \WeakMap<ContainerWidget, int> The row each snapping scroll container snapped to last */
    private \WeakMap $snappedTops;
    /**
     * The width of the content of each scroll container whose last layout drawn overflowed it, and so made room for
     * its scrollbar, see renderContainer(): its next layout drawn at that width measured first, untracked.
     *
     * @var \WeakMap<ContainerWidget, int>
     */
    private \WeakMap $barLayouts;
    /** @var \WeakMap<ContainerWidget, int> The column each container snapping sideways snapped to last */
    private \WeakMap $snappedLefts;
    /** @var \WeakMap<ContainerWidget, array{int, int, float}> The scroll containers gliding: from, to, since when */
    private \WeakMap $glides;
    /** @var \Closure(): float */
    private \Closure $clock;
    /** @var array<int, array{string, list<array{int, int, string}>, list<array{int, int, string}>, bool}> The rows of the last frame holding marks of links, see extractLineLinks(): the links to a fragment of the page and those of an id on each, first and last column */
    private array $linkRows = [];
    /** @var array<string, array{string, list<array{int, int, string}>, list<array{int, int, string}>, bool}> The lines of the last frame holding marks of links, by their text, see extractLineLinks(): a line the next frame draws again is not read again */
    private array $linkLines = [];
    /** @var array{LineBufferInterface, LineBufferInterface, list<string>, array<int, mixed>}|null The last frame whose links were taken out, what it gave, its lines, and the rows holding marks of the renderer: a frame drawn again whole, like one where nothing changed, is not read again, nor the lines of one that did not change */
    private ?array $linkFrame = null;
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
     * The widgets whose last render came from their cache, see wasRenderCached(): 'other' from a render kept in another
     * size, see wasRenderOfOtherSize().
     *
     * @var \WeakMap<AbstractWidget, true|'other'>
     */
    private \WeakMap $cachedRenders;
    /**
     * The widgets with an outline, found when they render: kept while their render is cached, with their top and bottom
     * margins, percentages taken of their containing block.
     *
     * @var \WeakMap<AbstractWidget, array{int, int}>
     */
    private \WeakMap $outlines;
    /** @var \WeakMap<AbstractWidget, \WeakReference<AbstractWidget>> The anchor of each widget its style names none for */
    private \WeakMap $implicitAnchors;
    /** @var \WeakMap<AbstractWidget, array{stuck?: list<'top'|'bottom'>, snapped?: list<'x'|'y'>, scrollable?: list<'top'|'bottom'|'left'|'right'>, scrolled?: list<'top'|'bottom'|'left'|'right'>, targetCurrent?: bool, offset?: array{array{}|array{int, int}, array{}|array{int, int}}}> The scroll state of the widgets where they were last drawn: stuck, snapped and scrollable */
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
    /** @var \WeakMap<AbstractWidget, array{0: int, 1: LineBufferInterface, 2: int, 3: int, 4?: bool, 5?: int}> The content of a widget drawn past its box: its revision, its lines, the row of its box in them, its rows, whether it is a box past a height it sets, and then the rows of that box */
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
        $this->childSummaries = new \WeakMap();
        $this->pointerClips = new \WeakMap();
        $this->definiteRows = new \WeakMap();
        $this->blockRows = new \WeakMap();
        $this->revealed = new \WeakMap();
        $this->revealedTarget = new \WeakMap();
        $this->shownTops = new \WeakMap();
        $this->snappedTops = new \WeakMap();
        $this->barLayouts = new \WeakMap();
        $this->snappedLefts = new \WeakMap();
        $this->sidewaysCaps = new \WeakMap();
        $this->unboundedRows = new \WeakMap();
        $this->fitsSetHeight = new \WeakMap();
        $this->floatsOut = new \WeakMap();
        $this->floatsEscape = new \WeakMap();
        $this->markedItems = new \WeakMap();
        $this->markedWitnesses = new \WeakMap();
        $this->escapeWitnesses = new \WeakMap();
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
        $this->styleSheet->addProvider(new NativeStyleProvider(NativeDefaultStyleSheet::create(), true));
    }

    /**
     * Orders the widgets like CSS reading-flow: those inside a reading-flow container are ordered again among the places
     * they take, by the reading key of their outermost one.
     */
    public function orderFocusCycle(array $widgets): array
    {
        $groups = [];
        foreach ($widgets as $index => $widget) {
            $container = null;
            for ($current = $widget->getParent(); null !== $current; $current = $current->getParent()) {
                if (null !== $this->resolveComputedStyle($current)->getReadingFlow()) {
                    $container = $current;
                }
            }
            if (null !== $container) {
                $groups[spl_object_id($container)][0] = $container;
                $groups[spl_object_id($container)][1][] = $index;
            }
        }

        foreach ($groups as [$container, $indexes]) {
            $keys = [];
            foreach ($indexes as $index) {
                $keys[$index] = $this->readingKey($container, $widgets[$index]);
            }
            $sorted = $indexes;
            usort($sorted, static fn (int $a, int $b) => self::compareReadingKeys($keys[$a], $keys[$b]) ?: $a <=> $b);
            $placed = $widgets;
            foreach ($indexes as $i => $index) {
                $placed[$index] = $widgets[$sorted[$i]];
            }
            $widgets = $placed;
        }

        return $widgets;
    }

    /**
     * Compares reading keys item by item, a shorter one first when it starts the other.
     *
     * @param list<int|float> $a
     * @param list<int|float> $b
     */
    private static function compareReadingKeys(array $a, array $b): int
    {
        foreach ($a as $i => $value) {
            if (!isset($b[$i])) {
                return 1;
            }
            if (0 !== $order = $value <=> $b[$i]) {
                return $order;
            }
        }

        return \count($a) <=> \count($b);
    }

    /**
     * The key a reading-flow container orders a widget inside it by: for each level down to it, the reading-order, the
     * place the flow gives the child, and its place among its siblings.
     *
     * @return list<int|float>
     */
    private function readingKey(AbstractWidget $container, AbstractWidget $widget): array
    {
        $path = [];
        for ($current = $widget; null !== $current && $current !== $container; $current = $current->getParent()) {
            $path[] = $current;
        }
        $key = [];
        $parent = $container;
        foreach (array_reverse($path) as $child) {
            $siblings = $parent instanceof ParentInterface ? $parent->all() : [];
            $position = (int) array_search($child, $siblings, true);
            $style = $this->resolveComputedStyle($parent);
            $flow = $style->getReadingFlow();
            if (null === $flow) {
                array_push($key, 0, 0, 0, $position);
                $parent = $child;
                continue;
            }
            $childStyle = $this->resolveComputedStyle($child);
            $rect = $this->getWidgetRect($child);
            // The visual flows by where the children were drawn, the others by order then as written
            [$first, $second] = match ($flow) {
                'flex-visual', 'grid-rows' => null !== $rect ? [$rect->row, $rect->col] : [0, $position],
                'grid-columns' => null !== $rect ? [$rect->col, $rect->row] : [0, $position],
                'flex-flow', 'grid-order' => [$childStyle->getOrder() ?? 0, $position],
                default => [0, $position],
            };
            array_push($key, $childStyle->getReadingOrder() ?? 0, $first, $second, $position);
            $parent = $child;
        }

        return $key;
    }

    public function allowsFocus(AbstractWidget $widget): bool
    {
        for ($current = $widget; null !== $current; $current = $current->getParent()) {
            $style = $this->resolveComputedStyle($current);
            if ($style->getHidden() || $style->getInvisible() || $current !== $widget && $style->getContentHidden()) {
                return false;
            }
            // Like the HTML inert attribute, nothing under it takes the focus; interactivity: inert is inherited, which
            // auto undoes
            if ($current->hasState('inert') || $current === $widget && true === $style->getInert()) {
                return false;
            }
        }

        return true;
    }

    /** @internal */
    public function bindRuntime(Tui $tui): void
    {
        if ($this->runtime?->get() === $tui) {
            return;
        }
        $this->runtime = \WeakReference::create($tui);
        // Like prefers-reduced-motion, for the widgets of Tui that animate: set, never unset over what the application
        // chose, see setMediaContext()
        if ($this->mediaContext->reducedMotion) {
            $tui->setReducedMotion();
        }
    }

    public function createFrameWriter(TerminalInterface $terminal): FrameWriterInterface
    {
        $writer = $this->frameWriter = new ScreenWriter($terminal);
        $writer->setColors($this->mediaContext->colors);

        return $writer;
    }

    public function resolveStyle(AbstractWidget $widget): NativeStyle
    {
        return StyleBridge::native($this->resolveComputedStyle($widget));
    }

    public function resolveElement(AbstractWidget $widget, string $element): NativeStyle
    {
        return StyleBridge::native(StyleBridge::element($this->styleSheet, $widget, $element));
    }

    public function renderWidgetLines(AbstractWidget $widget, NativeContext $context): LineBufferInterface
    {
        return $this->renderLines($widget, ContextBridge::computed($context));
    }

    public function addStyleSheet(StyleSheet|NativeStyleSheet $styleSheet): void
    {
        if ($styleSheet instanceof NativeStyleSheet) {
            $this->styleSheet->addProvider(new NativeStyleProvider(clone $styleSheet));
        } else {
            $this->styleSheet->merge($styleSheet);
        }
        $this->dropResolvedStyles();
    }

    /**
     * Forgets the styles resolved before a change of the rules, like a stylesheet or a style provider added or removed.
     *
     * @internal
     */
    public function dropResolvedStyles(): void
    {
        $this->styleCache = new \WeakMap();
        ++$this->styleEpoch;
        ++$this->styleResets;
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
     * The terminal size of the context is replaced on each frame. A change of
     * its reduced motion is given to the TUI of the views mounted, see
     * Tui::setReducedMotion().
     */
    public function setMediaContext(MediaContext $mediaContext): void
    {
        $previous = $this->mediaContext;
        $this->mediaContext = $mediaContext;
        $this->frameWriter?->setColors($mediaContext->colors);
        if (null !== $tui = $this->runtime?->get()) {
            if ($previous->reducedMotion !== $mediaContext->reducedMotion) {
                $tui->setReducedMotion($mediaContext->reducedMotion);
            }
            $tui->requestRender();
        }
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
            if ($this->resolveComputedStyle($current)->getHidden()) {
                return null;
            }
        }

        return $rect;
    }

    /**
     * The rects of what the last frame drew over a widget: the absolute and fixed widgets, the relative ones of a
     * nonzero z-index and the top layer (a modal dialog, an open popover) stacked above it, and the sticky widgets
     * stuck over the flow it is drawn with, neither around it nor in it, like the list of a dropdown open over a
     * field, a dialog over a form or a header over the rows scrolled under it; in the order of the document. A
     * backend drawing the widget itself, like a native control, hides it where they cover it.
     *
     * @return list<WidgetRect>
     */
    public function getRectsDrawnOver(AbstractWidget $widget): array
    {
        $root = $widget;
        while (null !== $parent = $root->getParent()) {
            $root = $parent;
        }
        $style = $this->resolveComputedStyle(...);
        $raised = static fn (AbstractWidget $current): bool => StackingOrder::isRaised($style($current)->getPosition()) || $current->hasState('modal') || $current->hasState('popover-open');
        // The widgets drawn over the flow, found once a frame for all the widgets asked about
        if (($this->drawnOver[0] ?? null) !== $root) {
            $found = [];
            $indexes = [];
            $collect = function (AbstractWidget $parent) use (&$collect, &$found, &$indexes, $root, $style, $raised): void {
                foreach ($parent instanceof ParentInterface ? $parent->all() : [] as $child) {
                    $childStyle = $style($child);
                    // Neither it nor what is in it is drawn
                    if ($childStyle->getHidden()) {
                        continue;
                    }
                    if ($raised($child) && !$childStyle->getInvisible() && !$childStyle->getTransparent() && null !== ($key = StackingOrder::key($child, $root, $style, $indexes))
                        && true !== $key['under'] && null !== $rect = $this->getWidgetRect($child)) {
                        $found[] = [$child, $key, $rect];
                    } elseif (null !== ($this->scrollStates[$child]['stuck'] ?? null) && !$childStyle->getInvisible() && null !== $rect = $this->getWidgetRect($child)) {
                        // A sticky widget stuck to a side of its scroll container, drawn again over what is scrolled under it
                        $found[] = [$child, null, $rect];
                    }
                    $collect($child);
                }
            };
            $collect($root);
            $this->drawnOver = [$root, $found, $indexes];
        }
        [, $found, $indexes] = $this->drawnOver;
        // It is drawn with the closest raised widget around it, else with the flow
        $own = null;
        $ownWidget = null;
        $around = [];
        for ($current = $widget; $current !== $root && null !== $current; $current = $current->getParent()) {
            $around[spl_object_id($current)] = true;
            if (null === $own && $raised($current)) {
                $own = StackingOrder::key($current, $root, $style, $indexes);
                $ownWidget = $current;
            }
        }
        $rects = [];
        foreach ($found as [$other, $key, $rect]) {
            if (isset($around[spl_object_id($other)])) {
                continue;
            }
            if (null === $key) {
                // A stuck widget is drawn over the flow it is in: over a widget drawn with the same raised one, or both with the flow
                for ($layer = $other->getParent(); null !== $layer && $layer !== $root && !$raised($layer); $layer = $layer->getParent()) {
                }
                if (($layer === $root ? null : $layer) !== $ownWidget) {
                    continue;
                }
            } elseif (null !== $own && StackingOrder::compare($key, $own) <= 0) {
                continue;
            }
            // What is in the widget is drawn with it
            for ($current = $other->getParent(); null !== $current && $current !== $widget; $current = $current->getParent()) {
            }
            if (null === $current) {
                $rects[] = $rect;
            }
        }

        return $rects;
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
     * Returns the id of the link drawn at a cell of the last frame, see RichTextWidget::linkId(), null when there is none.
     */
    public function getLinkAt(int $row, int $col): ?string
    {
        foreach ($this->linkRows[$row][2] ?? [] as [$first, $last, $id]) {
            if ($col >= $first && $col <= $last) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Returns the fragment (#id) a link drawn at a cell of the last frame goes to, null when there is none.
     */
    public function getFragmentLinkAt(int $row, int $col): ?string
    {
        foreach ($this->linkRows[$row][1] ?? [] as [$first, $last, $fragment]) {
            if ($col >= $first && $col <= $last) {
                return $fragment;
            }
        }

        return null;
    }

    /**
     * Render the widget tree starting from root.
     */
    public function renderFrame(ContainerWidget $root, int $columns, int $rows): LineBufferInterface
    {
        $this->invalidateGlides();
        $this->drawnOver = [];
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
        $context = new RenderContext($columns, $rows, null, $this->fontRegistry, true);
        $this->currentColumns = $columns;
        // The styles of earlier frames are kept for the widgets that did not change, while the rest is the same
        ++$this->styleFrame;
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
                } elseif ($widget instanceof CollapsibleWidget) {
                    // Its content, closed too, which its all() does not give then
                    $clear($widget->getContent());
                }
            };
            $clear($root);
        }
        $this->renderedMedia = $media;
        $this->styleInputs = $columns.'|'.$this->styleSheet->getRevision().'|'.$this->mediaContext->getKey();
        $result = $this->renderLines($root, $context);
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
                ++$this->styleResets;
                $this->anchorIndex = null;
                $this->absoluteKeys = ['keys' => [], 'indexes' => []];
                $this->positionTracker->reset();
                $result = $this->renderLines($root, $context);
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
        $result = $this->extractFragmentLinks($overlays || \count($this->liftedRelatives) > 0 ? $this->drawOverlays($result, $overlays, $columns, $rows, $root) : $result);
        if (null !== $tui = $this->runtime?->get()) {
            if (null !== $this->glideTimer) {
                $tui->cancelInterval($this->glideTimer);
                $this->glideTimer = null;
            }
            if ($this->isScrolling()) {
                $this->glideTimer = $tui->scheduleInterval($tui->requestRender(...), 1 / 60);
            }
        }

        return $result;
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

    public function renderLines(AbstractWidget $widget, RenderContext $context): LineBufferInterface
    {
        return $this->recordCacheHit($widget, $this->doRenderWidgetLines($widget, $context, null, $hit, $otherSize), $hit, $context, $otherSize);
    }

    public function renderStretchedLines(AbstractWidget $widget, RenderContext $context, int $rows): LineBufferInterface
    {
        return $this->recordCacheHit($widget, $this->doRenderWidgetLines($widget, $context, $rows, $hit, $otherSize), $hit, $context, $otherSize);
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
        return isset($this->cachedRenders[$widget]) && ('other' !== $this->cachedRenders[$widget] || !$widget instanceof ParentInterface);
    }

    /**
     * @internal
     */
    public function wasRenderOfOtherSize(AbstractWidget $widget): bool
    {
        return 'other' === ($this->cachedRenders[$widget] ?? null);
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

    /**
     * @internal
     */
    public function getLayoutKey(): ?string
    {
        // Not while a render depends on more than the widget and its room: a width capped for a box scrolled sideways,
        // a box measured past its height, the content of a box measured, the paints kept for an overflow drawn past a box
        if (0 < $this->contentDepth || \count($this->sidewaysCaps) > 0 || \count($this->unboundedRows) > 0 || \count($this->flowOverflow) > 0) {
            return null;
        }

        return $this->styleInputs.'|'.$this->styleResets;
    }

    /**
     * @internal
     */
    public function canReplayRender(AbstractWidget $widget): bool
    {
        // Its style kept across frames, see resolveComputedStyle(): not a cell nor one of a list of fonts, resolved again
        $cached = $this->styleCache[$widget] ?? null;
        if (null === $cached || null === $cached[4] || $cached[0] !== $widget->getRenderRevision() || $cached[4] !== $this->styleInputs) {
            return false;
        }
        // Not a render that depends on the height of its parent, see doRenderWidgetLines()
        $style = $cached[1];
        if (null !== $style->getMinRowsPercent() || null !== $style->getMaxRowsPercent() || null !== $style->getWritingMode()) {
            return false;
        }

        return !($this->beforeRenderOverridden[$widget::class] ??= AbstractWidget::class !== (new \ReflectionMethod($widget, 'beforeRender'))->getDeclaringClass()->name);
    }

    public function getStyleEpoch(): int
    {
        return $this->styleEpoch + $this->layoutEngine->getColumnBackgroundRevision();
    }

    public function resolveComputedStyle(AbstractWidget $widget): Style
    {
        $revision = $widget->getRenderRevision();
        $cached = $this->styleCache[$widget] ?? null;
        if (null !== $cached && $cached[0] === $revision && ($cached[3] === $this->styleFrame || $cached[4] === $this->styleInputs)) {
            return $this->layoutOverrides($widget, $cached[1]);
        }
        // Changed since it was resolved in this frame: what was built on its style is stale
        if (null !== $cached) {
            ++$this->styleEpoch;
        }
        $declared = $style = $this->styleSheet->resolve($widget, $this->currentColumns);
        // Kept for later frames but for what reads more than the widget: the registered fonts, the table of a cell
        $inputs = $this->styleInputs;
        // A list of fonts, like font-family: the first registered one, else the font of the terminal
        if (str_contains($style->getFont() ?? '', ',')) {
            $inputs = null;
            $style = $style->withFont(FontFamilies::first($this->fontRegistry, $style->getFont()));
        }
        // A block of rows, like table { display: block; overflow-x: auto } to scroll a wide table: they make an anonymous
        // table, like CSS, the block drawn as it
        // Not one without a box, like tbody { display: contents }: its rows are those of its table
        if (null === $style->getTableRole() && !$style->getContents() && $widget instanceof ContainerWidget && $this->holdsRows($widget)) {
            $style = $style->withTableRole(TableRole::Table);
        }
        if (TableRole::Cell === $style->getTableRole()) {
            $inputs = null;
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
        $this->styleCache[$widget] = [$revision, $style, $declared, $this->styleFrame, $inputs];

        return $this->layoutOverrides($widget, $style);
    }
    /** The depth of the containers whose content is being measured: the percentage widths inside them are auto */
    private int $contentDepth = 0;

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

    public function measureMaxContentWidth(AbstractWidget $widget, int $columns, int $rows): int
    {
        $saved = $this->positionTracker->suppressStack();
        try {
            return $widget instanceof TextWidget ? $widget->measureWithoutFloats(fn (): int => $this->computeIntrinsicWidth($widget, $columns, $rows, true)) : $this->computeIntrinsicWidth($widget, $columns, $rows, true);
        } finally {
            $this->positionTracker->restoreStack($saved);
        }
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
        // A float, an absolute box, a multi-column container and an item of a flex or grid container too, like CSS
        $style = $this->resolveComputedStyle($widget);
        if (null !== $style->getScroll() || null !== $style->getFloat() || $style->getPosition()?->isOutOfFlow() || null !== $style->getColumns()) {
            return true;
        }
        if (null !== ($parent = $widget->getParent()) && (null !== ($parentStyle = $this->resolveComputedStyle($parent))->getDirection() || null !== $parentStyle->getGridTemplate())) {
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
        if (!$widget instanceof ContainerWidget || null === $widget->getParent() || ContainerState::of($widget)->isInlineFlow() || null === $widget->getParent()->getParent()) {
            return true;
        }
        $style = $this->resolveComputedStyle($widget);
        $margin = $style->getMargin();
        $zero = static fn (?object $box): bool => null === $box || 0 === $box->top && 0 === $box->right && 0 === $box->bottom && 0 === $box->left;
        if (null !== $style->getFloat() || null !== $style->getPosition() || null !== $style->getScroll() || null !== $style->getDirection() || null !== $style->getGridTemplate() || null !== $style->getTableRole() || null !== $style->getBackground() || ($box ? !$zero($style->getBorder()) || !$zero($style->getPadding()) || !$zero($style->getMargin()) : true === $style->getMargin()?->hasPercents() || true === $style->getPadding()?->hasPercents() || !$zero($style->getMarginPull()) || null !== $margin && min($margin->top, $margin->right, $margin->bottom, $margin->left) < 0) || null !== $style->getAutoMargin() || null !== $style->getMaxColumns() || null !== $style->getWidthPercent() || 0 < ($style->getMinColumns() ?? 0) || null !== $style->getMinRows() || null !== $style->getMaxRows() || null !== $style->getContentWidth() || true === $style->getHidden()) {
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
     * The height of a widget as its content makes it where it was last drawn, its padding and border included, like
     * CSS height: auto; null when it was not drawn.
     */
    public function getIntrinsicRows(AbstractWidget $widget): ?int
    {
        if (null === $rows = $this->contentRows[$widget] ?? null) {
            return null;
        }
        $style = $this->resolveComputedStyle($widget);

        return $rows + ($style->getPadding()->top ?? 0) + ($style->getPadding()->bottom ?? 0) + ($style->getBorder()->top ?? 0) + ($style->getBorder()->bottom ?? 0);
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

        return ['stuck' => $state['stuck'] ?? [], 'snapped' => $state['snapped'] ?? [], 'scrollable' => $state['scrollable'] ?? [], 'scrolled' => $widget instanceof ContainerWidget ? $this->scrolled[$widget][2] ?? [] : [], 'targetCurrent' => true === ($state['targetCurrent'] ?? false), 'offset' => $state['offset'] ?? [[], []]];
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
        $sample = static fn (int $x, int $y): ?array => $x < $border->left || $x >= $columns - $border->right || $y < $border->top || $y >= $rows - $border->bottom ? [$x, $y, $columns, $rows] : null;

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
        $tile = $paint instanceof RasterImage ? self::imageTile($paint, $layout, $originColumns, $originRows) : [max(1, $layout->size[0]?->resolve($originColumns) ?? $originColumns), max(1, $layout->size[1]?->resolve($originRows) ?? $originRows)];
        $axes = [];
        foreach ([[0, $originLeft, $originColumns, $clipLeft], [1, $originTop, $originRows, $clipTop]] as [$axis, $originStart, $originLength, $clipStart]) {
            $size = $tile[$axis];
            $repeat = $layout->repeat[$axis];
            // Round stretches as many whole images as fit best across the area: from one to the next, a fraction of it
            $images = 1;
            if (BackgroundLayout::ROUND === $repeat && $originLength > 0) {
                $images = max(1, (int) round($originLength / $size));
                $size = $tile[$axis] = max(1, (int) round($originLength / $images));
            }
            $count = intdiv(max(0, $originLength), $size);
            if (BackgroundLayout::SPACE === $repeat && $count < 2) {
                $repeat = BackgroundLayout::NO_REPEAT;
            }
            $axes[$axis] = [$clipStart, $originStart, $originLength, $originStart + $layout->position[$axis]->resolve($originLength, $size), $size, $repeat, $count, $images];
        }
        // The column or row of the image a column or row of the clip box shows, null where none is
        $at = static function (int $axis, int $offset) use ($axes): ?int {
            [$clipStart, $originStart, $originLength, $start, $size, $repeat, $count, $images] = $axes[$axis];
            $at = $clipStart + $offset - $start;
            if (BackgroundLayout::ROUND === $repeat && $originLength > 0) {
                // Where in its image, in whole numbers: a fraction of a cell, like 20 cells in images of 60 / 9, is the
                // same on every platform; an area of no room tiled like repeat
                $at = min($size - 1, intdiv((($at * $images) % $originLength + $originLength) % $originLength * $size, $originLength));
            } elseif (BackgroundLayout::SPACE === $repeat) {
                // Whole images, the first and the last at the edges of the area, the room left between them
                $at = $clipStart + $offset - $originStart;
                $step = $size + ($originLength - $count * $size) / ($count - 1);
                $at = $at < 0 || $at >= $originLength ? -1 : (int) floor($at - floor($at / $step) * $step);
            } elseif (BackgroundLayout::NO_REPEAT !== $repeat) {
                $at = ($at % $size + $size) % $size;
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
                    $halves[$half] = $alpha >= 1.0 ? $color : [(int) round($pixel[0] + ($color[0] - $pixel[0]) * $alpha), (int) round($pixel[1] + ($color[1] - $pixel[1]) * $alpha), (int) round($pixel[2] + ($color[2] - $pixel[2]) * $alpha)];
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
        [$columns, $rows] = \Symfony\Component\HtmlRenderer\Paint\BackgroundGeometry::imageSize($columns, $rows, $areaColumns, $areaRows, $layout->size[0]?->resolve($areaColumns), $layout->size[1]?->resolve($areaRows), $layout->fit, $ratio);

        return [max(1, min(1000, (int) round($columns))), max(1, min(1000, (int) round($rows)))];
    }

    /**
     * Takes the marks of the links to fragments out of a frame, keeping where they are: they are not terminal links.
     */
    private function extractFragmentLinks(LineBufferInterface $frame): LineBufferInterface
    {
        if (null !== $this->linkFrame && $this->linkFrame[0] === $frame) {
            return $this->linkFrame[1];
        }
        $lines = $frame->toArray();
        // As many lines as the frame before, few changed, like a block changed among many: those only
        if (null !== $this->linkFrame && \count($this->linkFrame[2]) === \count($lines) && \count($changed = array_diff_assoc($lines, $this->linkFrame[2])) <= \count($lines) >> 3) {
            return $this->extractChangedLinks($frame, $lines, $changed);
        }
        $this->linkRows = [];
        $seen = $this->linkLines;
        $kept = [];
        $found = false;
        // Only the lines holding a mark, each read once: those of the frame before as they were
        foreach (preg_grep('/\x1b\]8;/', $lines) as $row => $line) {
            $entry = $kept[$line] ??= $seen[$line] ?? self::extractLineLinks($line);
            $this->linkRows[$row] = $entry;
            if ($entry[3]) {
                $found = true;
                $lines[$row] = $entry[0];
            }
        }
        $this->linkLines = $kept;
        $result = $found ? new ArrayLineBuffer($lines) : $frame;
        $this->linkFrame = [$frame, $result, $frame->toArray(), array_filter($this->linkRows, static fn (array $entry): bool => $entry[3])];

        return $result;
    }

    /**
     * extractFragmentLinks() of a frame where only some lines changed since the frame before: those read, the others as
     * they were.
     *
     * @param list<string>       $lines   The lines of the frame
     * @param array<int, string> $changed The lines that changed, by row
     */
    private function extractChangedLinks(LineBufferInterface $frame, array $lines, array $changed): LineBufferInterface
    {
        [, $before, , $ours] = $this->linkFrame;
        $out = $before->toArray();
        foreach ($changed as $row => $line) {
            unset($this->linkRows[$row], $ours[$row]);
            $out[$row] = $line;
            if (str_contains($line, "\x1b]8;")) {
                $entry = $this->linkLines[$line] ??= self::extractLineLinks($line);
                $this->linkRows[$row] = $entry;
                if ($entry[3]) {
                    $ours[$row] = true;
                    $out[$row] = $entry[0];
                }
            }
        }
        // The lines read kept a while, not those of all the frames
        if (\count($this->linkLines) > 2 * \count($this->linkRows) + 64) {
            $this->linkLines = array_intersect_key($this->linkLines, array_flip(array_intersect_key($lines, $this->linkRows)));
        }
        $result = [] !== $ours ? new ArrayLineBuffer($out) : $frame;
        $this->linkFrame = [$frame, $result, $lines, $ours];

        return $result;
    }

    /**
     * The marks of the links of a line taken out, those of the renderer, and where they are: the line without them, the
     * links to fragments and the links of an id, each its first and last column, and whether it held marks of the
     * renderer.
     *
     * @return array{string, list<array{int, int, string}>, list<array{int, int, string}>, bool}
     */
    private static function extractLineLinks(string $line): array
    {
        $marker = "\x1b]8;".RichTextWidget::FRAGMENT_LINK;
        $fragments = [];
        $spans = [];
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
                $fragments[] = [$open[0], $col - 1, $open[1]];
            }
            if (null !== $openId && $col > $openId[0]) {
                $spans[] = [$openId[0], $col - 1, $openId[1]];
            }
            $open = $fragment && '' !== $uri ? [$col, $uri] : null;
            $openId = '' !== $uri && preg_match('/^\x1b\]8;[^;]*\bid=([^:;]+)/', $part, $id) ? [$col, $id[1]] : null;
            if (!$ours) {
                $out .= $part;
            }
        }
        if (null !== $open && $col > $open[0]) {
            $fragments[] = [$open[0], $col - 1, $open[1]];
        }
        if (null !== $openId && $col > $openId[0]) {
            $spans[] = [$openId[0], $col - 1, $openId[1]];
        }

        return [$out, $fragments, $spans, str_contains($line, "\x1b]8;tui-")];
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
            if ($depth < 32 && $child instanceof ParentInterface && $this->resolveComputedStyle($child)->getContents() && !$this->resolveComputedStyle($child)->getHidden()) {
                // No box of its own drawn: not where it was drawn with one, like before a class made it display: contents
                if ($this->positionTracker->isActive()) {
                    $this->positionTracker->forgetBox($child);
                }
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
                if (null === $parent || $this->resolveComputedStyle($current)->getHidden() || !$this->resolveComputedStyle($overlay)->getPosition()?->isOutOfFlow()) {
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
                $style = $this->resolveComputedStyle($widget);
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
                // A height or a max-height it sets, like height: 3 between top: 1 and bottom: 0: its box no taller, at its
                // top inset, the bottom one ignored, like CSS; its rows are those of its content, a share counts its chrome
                if (null !== $height && !$centered && (null !== $style->getMaxRows() || null !== $style->getMaxRowsPercent())) {
                    $padding = $style->withResolvedBox($blockColumns)->getPadding();
                    $border = $style->getBorder();
                    $chrome = max(0, $padding->top ?? 0) + max(0, $padding->bottom ?? 0) + (($border->top ?? 0) > 0 ? 1 : 0) + (($border->bottom ?? 0) > 0 ? 1 : 0);
                    $max = null !== $style->getMaxRows() ? $style->getMaxRows() + $chrome : \PHP_INT_MAX;
                    if (null !== $style->getMaxRowsPercent()) {
                        $max = min($max, intdiv($blockRows * $style->getMaxRowsPercent(), 100) + $style->getRowsPercentOffsets()[1]);
                    }
                    $height = max(1, min($height, max($max, (int) $style->getMinRows() + $chrome)));
                }
                // A height its insets give, its content drawn past it; not one it sets
                $overflows = null !== $height;
                // Its anchor: the one its style names, else the implicit one, like the button that opened it; without one,
                // like CSS, position-area and anchor() do nothing
                $named = $position->anchor ?? array_values($position->anchorEdges)[0][0] ?? null;
                $implicit = null === $named ? ($this->implicitAnchors[$widget] ?? null)?->get() : null;
                $anchored = $position->isAnchored() || null !== $position->anchor;
                $anchor = !$anchored ? null : (null !== $implicit ? $this->anchorBox($implicit) : $this->anchorRect($root, $named, $widget));
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
                        $rect = null === $name && null !== $implicit ? $this->anchorBox($implicit) : $this->anchorRect($root, $name ?? $position->anchor, $widget);

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
                $context = (new RenderContext($width, $rows, null, $this->fontRegistry))->withContainingColumns($blockColumns);
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
                $static = null === $top && null === $bottom || null === $left && null === $right ? $this->staticPlace($widget, $width, $count) : null;
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
                            $rect = null === $name && null !== $implicit ? $this->anchorBox($implicit) : $this->anchorRect($root, $name ?? $try->anchor, $widget);

                            return null === $rect ? null : [$rect->columns, $rect->rows];
                        });
                        $tryWidth = max(1, min($columns, $sizes['width'] ?? $width));
                        if (isset($sizes['height'])) {
                            return [$tryWidth, max(1, min($rows, $sizes['height']))];
                        }
                        $saved = $this->positionTracker->suppressStack();
                        $tryCount = $tryWidth === $width ? $count : \count($this->renderLines($widget, $context->withColumns($tryWidth)));
                        $this->positionTracker->restoreStack($saved);

                        return [$tryWidth, $tryCount];
                    };
                    [$row, $col, $fits] = self::anchored($position, $anchor, $width, $count, $row, $col, $columns, $rows, $measure, $chosen);
                    // Placed by a try of its own size: drawn at it
                    if (null !== $chosen && [] !== $chosen->anchorSizes) {
                        [$width, $tryHeight] = $measure($chosen);
                        $context = $context->withColumns($width);
                        $sizes = $chosen->anchorSizes(fn (?string $name): ?array => null === ($rect = null === $name && null !== $implicit ? $this->anchorBox($implicit) : $this->anchorRect($root, $name ?? $chosen->anchor, $widget)) ? null : [$rect->columns, $rect->rows]);
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
                    $backdropStyle = StyleBridge::element($this->styleSheet, $widget, 'backdrop');
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
                // Past the left edge of the screen, which is not scrolled to: what is right of it drawn, like CSS
                if ($col < 0) {
                    [$lines, $row, $col, $width] = self::clipLines($lines instanceof LineBufferInterface ? $lines->toArray() : $lines, $row, $col, $width, [\PHP_INT_MIN, 0, \PHP_INT_MAX, \PHP_INT_MAX]);
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
                if (null === $current || $this->resolveComputedStyle($current)->getHidden()) {
                    continue 2;
                }
            }
            if (PositionMode::Relative !== $this->resolveComputedStyle($widget)->getPosition()?->mode || null === $rect = $this->boxRect($widget)) {
                continue;
            }
            if (null === $lines) {
                $inPlace[] = [$widget, $rect, $width, $height];
                continue;
            }
            // Its box below its top margin, the lines lifted; what is right of the left edge of the screen
            if (null !== $key = $this->stackingKey($widget, $root, $indexes)) {
                [$lines, $row, $col, $width] = self::clipLines($lines, $rect->row + max(0, $this->resolveComputedStyle($widget)->getMargin()->top ?? 0), $rect->col, $width, [\PHP_INT_MIN, 0, \PHP_INT_MAX, \PHP_INT_MAX]);
                $placed[] = [$key, $widget, $lines, $row, $col, $width, null, false];
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
            $row = $rect->row + max(0, $this->resolveComputedStyle($widget)->getMargin()->top ?? 0);
            $holder = null;
            for ($current = $widget->getParent(); null !== $current && null === $holder; $current = $current->getParent()) {
                $holder = $placed[$holders[spl_object_id($current)] ?? -1] ?? null;
            }
            $source = null !== $holder ? \is_array($holder[2]) ? $holder[2] : $holder[2]->toArray() : $frameLines;
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
            if (true === ($overlayStyle = $this->resolveComputedStyle($overlay))->getInvisible() || true === $overlayStyle->getTransparent()) {
                continue;
            }
            // Its blank cells, without a background of its own, show what is under it, like CSS, like the floating label
            // of a field of Bootstrap over the field; not those of the lists of controls, of dialogs nor of popovers,
            // on the background of the page in browsers
            // A positioned ::before or ::after, styled by the page, is clear too, like the labels of the cells of EasyAdmin
            $pseudo = null !== ($owner = $overlay->getParent()) && \in_array($overlay, $this->pseudoWidgets[$owner] ?? [], true);
            $raised = PositionMode::Relative === $this->resolveComputedStyle($overlay)->getPosition()?->mode;
            // Taken out of the flow with a background of its own, opaque, like a dropdown menu of Bootstrap of the color of
            // the page; one raised in the flow still joins its borders to those beside it
            $clear = ($pseudo || null === StyleBridge::get($overlay)?->getPosition()) && !$overlay->hasState('modal') && !$overlay->hasState('popover-open') && ($raised || !$this->paintsBackground($overlay));
            $seeThrough = $clear && null !== ($inherited = $this->getInheritedBackground($overlay)) ? substr($inherited->toBackgroundCode(), 2, -1) : null;
            // Under the flow inside a context in it: over that context's background, within its box
            $context = true === $beneath && null !== ($key['context'] ?? null) ? $key['context'] : null;
            $contextBox = null !== $context ? $this->boxRect($context) : null;
            $contextBackground = null !== $context ? $this->resolveComputedStyle($context)->getBackground() : null;
            if ($backdrop instanceof BackdropTint) {
                $out = $backdrop->apply($out, $columns, $rows);
            } elseif ($backdrop) {
                // Dim after every style change, so that no reset or intensity change undoes it
                $out = array_map(static fn (string $line) => "\x1b[2m".(preg_replace('/\e\[([0-9;]*)m/', "\x1b[\$1;2m", $line) ?? $line), $out);
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
            $limit = PositionMode::Fixed === $overlayStyle->getPosition()?->mode && null === $this->fixedContainer($overlay) ? $rows : max($rows, \count($frame));
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
                    $layers = [new Layer([$under], 0, 0, false, $lineWidth, 1), new Layer([$line], 0, $col)];
                    if (isset($contextLines[$target - $contextRow]) && $contextCol >= 0 && $contextCol < $columns) {
                        $contextLine = AnsiUtils::sliceToWidth($contextLines[$target - $contextRow], 0, min($contextWidth, $columns - $contextCol));
                        $background = $this->resolveComputedStyle($context)->getBackground();
                        $layers[] = new Layer([null !== $background ? self::withoutBackground($contextLine, substr($background->toBackgroundCode(), 2, -1)) : $contextLine], 0, $contextCol, true);
                    }
                    $out[$target] = Compositor::composite(...$layers)[0];
                    continue;
                }
                if ($beneath) {
                    // What is drawn shows over it: its blank cells let it through, their text over its background; the
                    // background of the page is under it
                    $lineWidth = max($columns, AnsiUtils::visibleWidth($under));
                    $layers = [new Layer([null !== $canvas ? "\x1b[".$canvas.'m'.str_repeat(' ', $lineWidth)."\x1b[0m" : ''], 0, 0, false, $lineWidth, 1)];
                    // Inside a context in the flow: its background under it, the one of the flow taken away
                    if (null !== $contextBox && null !== $contextBackground && $target >= $contextBox->row && $target < $contextBox->row + $contextBox->rows) {
                        $code = substr($contextBackground->toBackgroundCode(), 2, -1);
                        $layers[] = new Layer(["\x1b[".$code.'m'.str_repeat(' ', $contextBox->columns)."\x1b[0m"], 0, $contextBox->col);
                        $under = AnsiUtils::sliceToWidth($under, 0, $contextBox->col).self::withoutBackground(AnsiUtils::sliceToWidth($under, $contextBox->col, $contextBox->columns), $code).AnsiUtils::sliceToWidth($under, $contextBox->col + $contextBox->columns, max(0, AnsiUtils::visibleWidth($under) - $contextBox->col - $contextBox->columns));
                    }
                    $layers[] = new Layer([$line], 0, $col);
                    $layers[] = new Layer([null !== $canvas ? self::withoutBackground($under, $canvas) : $under], 0, 0, true);
                    $out[$target] = Compositor::composite(...$layers)[0];
                    continue;
                }
                if (!$clear) {
                    // What is under it before and after, cut in one pass
                    [$left, $right] = AnsiUtils::splitAround($under, $col, $visible);
                    $out[$target] = $left."\x1b[0m".$line."\x1b[0m".($col + $visible < $columns ? $right : '');
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
                    $out[$target] = $before."\x1b[0m".('' === $background ? $line : $background.AnsiUtils::reapplyBackgroundAfterResets($line, $background))."\x1b[0m".($col + $visible < $columns ? $after : '');
                    continue;
                }
                // Raised in the flow, like the active link of a pagination of Bootstrap: its lines join those under it
                if ($raised) {
                    $line = LayoutEngine::joinGlyphs($line, AnsiUtils::sliceToWidth($under, $col, $visible));
                }
                $lineWidth = max($columns, AnsiUtils::visibleWidth($under));
                $out[$target] = Compositor::composite(new Layer([$under], 0, 0, false, $lineWidth, 1), new Layer([$line], 0, $col, true, null, null, $seeThrough))[0];
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
        if (PositionMode::Absolute !== $position->mode && (PositionMode::Fixed !== $position->mode || null === $this->fixedContainer($widget)) || $widget->hasState('modal') || $widget->hasState('popover-open')) {
            return null;
        }
        $fixed = PositionMode::Fixed === $position->mode;
        $clip = null;
        $block = false;
        for ($ancestor = $widget->getParent(); null !== $ancestor; $ancestor = $ancestor->getParent()) {
            $style = $this->resolveComputedStyle($ancestor);
            // From its containing block up, a sticky one too: those between it and that block do not clip it
            $block = $block || ($fixed ? true === $style->getContainsFixed() : self::isContainingBlock($style));
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
        return null !== $style->getPosition() || null !== $style->getStickyTop() || null !== $style->getStickyBottom() || true === $style->getContainsFixed();
    }

    /**
     * The closest ancestor that is the containing block of the fixed widgets in it, like one of a transform; null when
     * the screen is, or for one in the top layer.
     */
    private function fixedContainer(AbstractWidget $widget): ?AbstractWidget
    {
        for ($ancestor = $widget->getParent(); null !== $ancestor && !$widget->hasState('modal') && !$widget->hasState('popover-open'); $ancestor = $ancestor->getParent()) {
            if (true === $this->resolveComputedStyle($ancestor)->getContainsFixed()) {
                return $ancestor;
            }
        }

        return null;
    }

    /**
     * Cuts the rects of a widget and of what it holds to a clip for the pointer, see WidgetRect::withClip(), like an
     * element positioned out of the flow cut by the boxes around, drawn again each frame.
     *
     * @param array{int, int, int, int} $clip
     */
    private function clipPointer(AbstractWidget $widget, array $clip): void
    {
        $this->positionTracker->clipWidgetRect($widget, $clip);
        foreach ($widget instanceof ParentInterface ? $widget->all() : [] as $child) {
            // Not a fixed one, of the screen, nor one in the top layer
            if (PositionMode::Fixed !== $this->resolveComputedStyle($child)->getPosition()?->mode && !$child->hasState('modal') && !$child->hasState('popover-open')) {
                $this->clipPointer($child, $clip);
            }
        }
    }

    /**
     * Cuts the rects of the children of a widget, and of what they hold, for the pointer by the content clip of a box
     * around them, see PositionTracker::clipBy(): those cut by it before, where it is when read, not cut again, only
     * those whose rects were written since; not while some are moved later, see PositionTracker::shiftChildrenLater().
     *
     * @param array<AbstractWidget> $children
     */
    private function clipChildrenPointer(AbstractWidget $widget, array $children, AbstractWidget $box): void
    {
        $key = spl_object_id($box);
        $known = $this->pointerClips[$widget][$key] ?? null;
        $written = null !== $known && !$this->positionTracker->hasPendingMoves($widget) ? $this->positionTracker->getChildrenWrittenSince($widget, $known[0]) : null;
        if (null !== $written) {
            foreach ($written as $id) {
                // A child it did not hold, or no more: all looked at again
                if (null === ($child = $known[1][$id] ?? null) || $child->getParent() !== $widget) {
                    $written = null;
                    break;
                }
                // Not a fixed one, of the screen, nor one in the top layer
                if (PositionMode::Fixed !== $this->resolveComputedStyle($child)->getPosition()?->mode && !$child->hasState('modal') && !$child->hasState('popover-open')) {
                    $this->clipByBox($child, $box);
                }
            }
        }
        if (null === $written) {
            $known = [0, []];
            foreach ($children as $child) {
                $known[1][spl_object_id($child)] = $child;
                if (PositionMode::Fixed !== $this->resolveComputedStyle($child)->getPosition()?->mode && !$child->hasState('modal') && !$child->hasState('popover-open')) {
                    $this->clipByBox($child, $box);
                }
            }
        }
        // What is written from now kept, see PositionTracker::getChildrenWrittenSince(); for a few boxes around
        $known[0] = $this->positionTracker->getWriteSerial();
        $this->positionTracker->getChildrenWrittenSince($widget, $known[0]);
        $boxes = $this->pointerClips[$widget] ?? [];
        unset($boxes[$key]);
        $boxes[$key] = $known;
        $this->pointerClips[$widget] = \count($boxes) > 4 ? \array_slice($boxes, -4, null, true) : $boxes;
    }

    private function clipByBox(AbstractWidget $widget, AbstractWidget $box): void
    {
        $this->positionTracker->clipBy($widget, $box);
        if ($widget instanceof ParentInterface) {
            $this->clipChildrenPointer($widget, $widget->all(), $box);
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
        $style = $this->resolveComputedStyle($overlay);
        $position = $style->getPosition();
        // Nor one that draws nothing, invisible or transparent
        if (true !== $position?->isOutOfFlow() || $position->zIndex < 0 || true === $style->getInvisible() || true === $style->getTransparent()) {
            return null;
        }
        for ($current = $overlay; $current !== $root; $current = $current->getParent()) {
            if (null === $current || $this->resolveComputedStyle($current)->getHidden()) {
                return null;
            }
        }

        return $this->stackingKey($overlay, $root, $this->absoluteKeys['indexes']);
    }

    /**
     * The key of a widget in the stacking order; an absolute ::before before the children of its owner, an ::after
     * after them, like CSS, not in its list of children.
     *
     * @param array<int, array<int, int|string>> $indexes
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

        return StackingOrder::key($widget, $root, $this->resolveComputedStyle(...), $indexes);
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
                if (\in_array($part, [4, 7, 9, 21, 53], true) || $part >= 40 && $part <= 49 || $part >= 100 && $part <= 107) {
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
                        $sequence = "\x1b[".implode(';', \array_slice($parts, $i, $length)).'m';
                        if ('' !== $background && $sequence !== $background) {
                            return null;
                        }
                        $background = $sequence;
                    }
                    $i += $length - 1;
                    continue;
                }
                if ($part >= 40 && $part <= 47 || $part >= 100 && $part <= 107) {
                    $sequence = "\x1b[".$part.'m';
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
            $style = $this->resolveComputedStyle($ancestor);
            // A height clips what is past it, overflow: hidden or not, when it is past it; with overflow: hidden, whatever
            // A percentage height only once it is a share of a definite one, as the layout resolves it; a box that clips
            // sideways keeps in what is moved sideways
            $height = null !== $style->getMaxRows() || null !== $style->getMaxRowsPercent() && null !== ($this->blockRows[$ancestor] ?? (null !== ($parent = $ancestor->getParent()) ? $this->definiteRows[$parent] ?? null : null));
            // Not moved past it, but laid out past it all the same, like items wrapped below a bar of a set height
            $pastHeight = $height && !$heightClips && null !== ($rect = $this->positionTracker->getWidgetRect($widget)) && null !== ($box = $this->paddingBox($ancestor)) && $rect->row + $rect->rows > $box[0] + $box[3];
            if (null !== $style->getScroll() || $height && ($heightClips || $pastHeight || $this->clipsOverflow($ancestor, true)) || $style->getFilter() instanceof Filter || null !== $style->getBlend() || true === $style->getDim() || true === $style->getTransparent() || $widthClips && $this->clipsSideways($ancestor)) {
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
            if (null !== $background = $this->resolveComputedStyle($widget)->getBackground()) {
                return substr($background->toBackgroundCode(), 2, -1);
            }
            // Down to <html> and <body>, which pass the height of the screen: not the first box of the page
            $child = $widget->all()[0] ?? null;
            $widget = $child instanceof ContainerWidget && ContainerState::of($child)->passesDefiniteHeight() ? $child : null;
        }

        return null;
    }

    /**
     * The line with the cells of that background as cells without one.
     */
    private static function withoutBackground(string $line, string $background): string
    {
        if (!str_contains($line, "\x1b[")) {
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

            return "\x1b[".implode(';', $out).'m';
        }, $line) ?? $line;
    }

    /**
     * Draws the outlines of the widgets in the flow over the frame, like CSS outline: around their boxes, over what is
     * next to them, cut by the screen.
     */
    private function drawOutlines(LineBufferInterface $frame, int $columns, int $rows, ContainerWidget $root): LineBufferInterface
    {
        $out = null;
        foreach ($this->outlines as $widget => [$marginTop, $marginBottom]) {
            // Shown, in the tree, and in the flow: out of it, an overlay is drawn over the outlines
            for ($current = $widget; $current !== $root; $current = $current->getParent()) {
                if (null === $current || $this->resolveComputedStyle($current)->getHidden() || $this->resolveComputedStyle($current)->getPosition()?->isOutOfFlow()) {
                    continue 2;
                }
            }
            $outline = $this->resolveComputedStyle($widget)->getOutline();
            if (!$outline instanceof Outline || null === $box = $this->boxRect($widget)) {
                continue;
            }
            $out ??= $frame->toArray();
            // The box without its margins above and below
            $boxTop = $box->row + $marginTop;
            $boxRows = $box->rows - $marginTop - $marginBottom;
            // Around the border box: not the room its shadow takes on the right and below
            $shadow = $this->resolveComputedStyle($widget)->getShadow();
            [$shadowColumns, $shadowRows] = null !== $shadow && !$shadow->inset ? $shadow->extents() : [0, 0];
            // A ring spread inside a box too short to hold it, like a field or a button of a row: around it
            $pattern = $outline->pattern;
            $offset = $outline->offset;
            if (BorderPattern::RING_INSET === $pattern && $boxRows < 3) {
                [$pattern, $offset] = [BorderPattern::RING, 0];
            }
            $ringBox = \Symfony\Component\HtmlRenderer\Paint\OutlineGeometry::outer(new \Symfony\Component\HtmlRenderer\Geometry\Rect($box->col, $boxTop, $box->columns - $shadowColumns, $boxRows - $shadowRows), 1, 1, $offset, $offset);
            if (null === $ringBox) {
                continue;
            }
            // The native inputs are integer cells; the shared geometry preserves these exact coordinates.
            [$left, $top, $width, $height] = array_map(intval(...), [$ringBox->x, $ringBox->y, $ringBox->width, $ringBox->height]);
            if ($width < 2 || $height < 2) {
                continue;
            }
            $chars = BorderPattern::fromName($pattern)->getChars();
            $style = new Style(null, null, null, $outline->color ?? $this->resolveComputedStyle($widget)->getColor());
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
                            if ($i >= $end || (0 === $i || $last === $i) && $char !== $chars[min($last, 1)]) {
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
                $out[$row] = $left."\x1b[0m".$style->apply($text)."\x1b[0m".$right;
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
            StyleBridge::set($pseudo, $style);
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
        $right = $this->resolveComputedStyle($widget)->getMargin()->right ?? 0;

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
        return null !== ($anchor = $this->findAnchor($widget, $name, $positioned, true)) ? $this->anchorBox($anchor) : null;
    }

    /**
     * The box of an anchor where it was drawn, inside its margins on every side, like its border box in CSS.
     */
    private function anchorBox(AbstractWidget $anchor): ?WidgetRect
    {
        if (null === $rect = $this->boxRect($anchor)) {
            return null;
        }
        $margin = $this->resolveComputedStyle($anchor)->getMargin();
        $top = min($rect->rows, max(0, $margin->top ?? 0));

        return new WidgetRect($rect->row + $top, $rect->col, $rect->columns, max(0, $rect->rows - $top - max(0, $margin->bottom ?? 0)));
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
                if (null !== $anchorName = $this->resolveComputedStyle($node)->getAnchorName()) {
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
                $scope = $this->resolveComputedStyle($node)->getAnchorScope();
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
        $rectangle = new \Symfony\Component\HtmlRenderer\Geometry\Rect($anchor->col, $anchor->row, $anchor->columns, $anchor->rows);
        $edges = [];
        foreach ($position->anchorEdges as $side => [, $edge]) {
            $edges[$side] = [$rectangle, $edge];
        }
        [$width, $height] = \Symfony\Component\HtmlRenderer\Layout\AnchorGeometry::room($rectangle, $columns, $rows, $position->area, $edges, static fn (float $value, bool $vertical): float => (float) (int) ($value / 2));

        return [(int) $width, (int) $height];
    }

    /**
     * The row and column of an element next to its anchor, by its position-area or the anchor() of its insets, flipped
     * to the other side when it would leave the screen and its flips say so; not kept on the screen.
     *
     * @return array{int, int}
     */
    private static function placeNextTo(Position $position, WidgetRect $anchor, int $width, int $height, int $row, int $col, int $columns, int $rows): array
    {
        $rectangle = new \Symfony\Component\HtmlRenderer\Geometry\Rect($anchor->col, $anchor->row, $anchor->columns, $anchor->rows);
        $edges = [];
        foreach ($position->anchorEdges as $side => [, $edge]) {
            $edges[$side] = [$rectangle, $edge];
        }
        [$x, $y] = \Symfony\Component\HtmlRenderer\Layout\AnchorGeometry::nextTo($rectangle, $width, $height, $columns, $rows, $position->area, $edges, $position->flips, $position->anchorCenter, [$col, $row], static fn (float $value, bool $vertical): float => (float) (int) ($value / 2));

        return [(int) $y, (int) $x];
    }

    /**
     * Where an absolute widget would be in the flow of its parent: under the box before it, at the start of the parent's
     * content, like the static position of CSS; none when its parent was not drawn.
     *
     * In a flex container, where it would be as its only item, placed by justify-content and align-items, or its
     * align-self, when its size is known.
     *
     * @return array{int, int}|null
     */
    private function staticPlace(AbstractWidget $widget, ?int $width = null, ?int $height = null): ?array
    {
        $parent = $widget->getParent();
        if (!$parent instanceof ParentInterface || null === $rect = $this->positionTracker->getWidgetRect($parent)) {
            return null;
        }
        $style = $this->resolveComputedStyle($parent);
        $row = $rect->row + ($style->getMargin()->top ?? 0) + ($style->getBorder()->top ?? 0) + ($style->getPadding()->top ?? 0);
        $col = $rect->col + ($style->getMargin()->left ?? 0) + ($style->getBorder()->left ?? 0) + ($style->getPadding()->left ?? 0);
        // In a flow of blocks, under the last one before it that was drawn; in a row or a grid, at the start, like CSS
        if (null === $style->getDirection() && null === $style->getGridTemplate()) {
            foreach ($parent->all() as $child) {
                if ($child === $widget) {
                    break;
                }
                $childStyle = $this->resolveComputedStyle($child);
                if (!$childStyle->getHidden() && !$childStyle->getPosition()?->isOutOfFlow() && null !== $childRect = $this->positionTracker->getWidgetRect($child)) {
                    $row = $childRect->row + $childRect->rows;
                }
            }
        } elseif (null !== $style->getDirection() && null === $style->getGridTemplate() && null !== $width && null !== $height && null !== $box = $this->paddingBox($parent)) {
            // Its content box: the start of the row or the column, and the room the alignments share out
            $padding = $style->withResolvedBox($rect->columns)->getPadding();
            $row = $box[0] + max(0, $padding->top ?? 0);
            $col = $box[1] + max(0, $padding->left ?? 0);
            $free = [max(0, $box[2] - max(0, $padding->left ?? 0) - max(0, $padding->right ?? 0) - $width), max(0, $box[3] - max(0, $padding->top ?? 0) - max(0, $padding->bottom ?? 0) - $height)];
            $horizontal = Direction::Horizontal === $style->getDirection();
            // Along the main axis, a single item: space-around and space-evenly center it, space-between does not
            $centered = \in_array($style->getJustify(), [Justify::SpaceAround, Justify::SpaceEvenly], true);
            $across = $this->resolveComputedStyle($widget)->getAlignSelf();
            $share = static fn (int $free, bool $center, bool $end): int => $center ? intdiv($free, 2) : ($end ? $free : 0);
            if ($horizontal) {
                $col += $share($free[0], Align::Center === $style->getAlign() || $centered, Align::Right === $style->getAlign() && !$centered);
                $cross = $across ?? $style->getVerticalAlign();
                $row += $share($free[1], VerticalAlign::Center === $cross, VerticalAlign::Bottom === $cross);
            } else {
                $row += $share($free[1], VerticalAlign::Center === $style->getVerticalAlign() || $centered, VerticalAlign::Bottom === $style->getVerticalAlign() && !$centered);
                $cross = null !== $across ? match ($across) {
                    VerticalAlign::Center => Align::Center,
                    VerticalAlign::Bottom => Align::Right,
                    default => Align::Left,
                } : $style->getAlign();
                $col += $share($free[0], Align::Center === $cross, Align::Right === $cross);
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
        $style = $this->resolveComputedStyle($widget);
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
        // A fixed one: the screen, unless inside a box that contains it, like one of a transform
        $fixed = PositionMode::Fixed === $position->mode;
        if (!$fixed || null !== $this->fixedContainer($widget)) {
            for ($ancestor = $widget->getParent(); null !== $ancestor; $ancestor = $ancestor->getParent()) {
                $style = $this->resolveComputedStyle($ancestor);
                if (($fixed ? true === $style->getContainsFixed() : self::isContainingBlock($style)) && null !== $box = $this->paddingBox($ancestor)) {
                    // A scroll container: its padding box where its content starts, which moves as it is scrolled, like CSS
                    if ($ancestor instanceof ContainerWidget && null !== $style->getScroll()) {
                        $box[0] -= $this->shownTops[$ancestor] ?? 0;
                        $box[1] -= $this->shownLefts[$ancestor] ?? 0;
                    }

                    return [$box[0], $box[1], max(1, min($columns, $box[2])), max(1, min($rows, $box[3]))];
                }
                // One that draws no rows, like an empty <div class="position-relative">: where it would be in the flow of its
                // parent, as wide as the content of that parent
                if ((!$fixed || true === $style->getContainsFixed()) && null !== $style->getPosition() && !$style->getPosition()->isOutOfFlow() && !$style->getHidden() && null !== ($place = $this->staticPlace($ancestor)) && null !== $parentRect = $this->positionTracker->getWidgetRect($ancestor->getParent())) {
                    $parentStyle = StyleBridge::resolve($this, $ancestor->getParent());
                    $end = $parentRect->col + $parentRect->columns - ($parentStyle->getMargin()->right ?? 0) - ($parentStyle->getBorder()->right ?? 0) - ($parentStyle->getPadding()->right ?? 0);

                    return [$place[0], $place[1], max(1, min($columns, $end - $place[1])), 1];
                }
            }
        }

        return [0, 0, $columns, $rows];
    }

    /**
     * Records whether the render just made of a widget came from its cache, once it is made, see wasRenderCached(): not
     * a render of it inside it, like a measure.
     */
    private function recordCacheHit(AbstractWidget $widget, LineBufferInterface $lines, bool $hit, RenderContext $context, bool $otherSize = false): LineBufferInterface
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
        if ($hit || $otherSize) {
            $this->cachedRenders[$widget] = $otherSize ? 'other' : true;
        } else {
            unset($this->cachedRenders[$widget]);
        }

        return $lines;
    }

    /**
     * @param-out bool $hit       Whether it was taken from its cache
     * @param-out bool $otherSize Whether it was taken from a render kept in another size, see RenderCache::get()
     */
    private function doRenderWidgetLines(AbstractWidget $widget, RenderContext $context, ?int $stretchRows, ?bool &$hit = null, ?bool &$otherSize = null): LineBufferInterface
    {
        $hit = false;
        $otherSize = false;
        $widget->beforeRender();
        // Beside the floats of a layout that is gone no more, see TextWidget::dropStaleFloats()
        if ($widget instanceof TextWidget || $widget instanceof ContainerWidget) {
            ContainerState::of($widget)->dropStaleFloats();
        }
        $resolvedStyle = $this->resolveComputedStyle($widget);
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
        $cacheKey = null !== $definite ? 'height:'.$definite : '';
        // In a row, two containing blocks can give the same share: the render of each is its own, as is that of a width
        // that is a share of its containing block, not of an item of a row, which the row gives its share, see below
        $sharedWidth = null !== $resolvedStyle->getWidthPercent() && !(($parent = $widget->getParent()) instanceof ContainerWidget && Direction::Horizontal === $this->resolveComputedStyle($parent)->getDirection());
        // The block the box is sized from: the one around, or the box that clips it, see above
        $sizedFrom = $boxPercents ? $containing : ($sharedWidth ? $context->getContainingColumns() : $cacheColumns);
        if ($sizedFrom !== $cacheColumns) {
            $cacheKey .= ':width:'.$sizedFrom;
        }
        // Drawn, it is not taken from a render kept in another size than its last one: those are for the layouts that
        // measure it, without tracking positions. What a render leaves besides its lines, like where the descendants of
        // the widget are or where the lines of a text start, is that of the last one, like after a terminal resized and
        // back: taken by a measure, it is no hit, see wasRenderCached(), and a layout drawing what it measured renders
        // it again, see wasRenderOfOtherSize()
        $cached = RenderCache::get($widget, $cacheColumns, $cacheRows, $cacheKey, !$this->positionTracker->isActive(), $otherSize);
        // Under its own key, which the sizes it is a share of change, not only that of its columns and rows; in another
        // size, a hit only for what holds no widget, whose rects follow it
        $hit = null !== $cached && (!$otherSize || !$widget instanceof ParentInterface);
        // The floats going on past it of the render cached, kept with its lines, or none until its render gives them,
        // see ContainerWidget::setEscapedFloats()
        if (null !== $cached) {
            if ($widget instanceof ContainerWidget) {
                ContainerState::of($widget)->setEscapedFloats(RenderCache::getExtra($widget, $cacheColumns, $cacheRows, $cacheKey));
            }

            return $cached;
        }
        if ($widget instanceof ContainerWidget) {
            ContainerState::of($widget)->setEscapedFloats([]);
        }
        if ($resolvedStyle->getHidden()) {
            $lines = new ArrayLineBuffer([]);
            RenderCache::set($widget, $lines, $cacheColumns, $cacheRows, $cacheKey, otherSizes: self::CACHED_OTHER_SIZES);

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
        if (null !== $maxColumns && $maxColumns !== $resolvedStyle->getRowMaxColumns() && ($row = $widget->getParent()) instanceof ContainerWidget && Direction::Horizontal === $this->resolveComputedStyle($row)->getDirection()) {
            $maxColumns = $resolvedStyle->getRowMaxColumns();
        }
        if (null !== $maxColumns) {
            $box = min($box ?? $maxColumns, $maxColumns);
        }
        // width: fit-content or min-content, the box as wide as its content, or as narrow as it can be
        // A text block written vertically is as wide as its columns already
        $contentWidth = $resolvedStyle->getContentWidth();
        // An image without a width: as wide as it is drawn, like the inline images of browsers, a border around it
        if ($widget instanceof ReplacedElementInterface && null === $contentWidth && null === $box && 0 >= ($resolvedStyle->getMinColumns() ?? 0)) {
            $contentWidth = 'fit-content';
        }
        if (null !== $contentWidth && $allotted > $marginColumns && !($widget instanceof TextWidget && null !== $resolvedStyle->getWritingMode())) {
            $room = $allotted - $marginColumns;
            $content = 'min-content' === $contentWidth ? $this->computeMinContentWidth($widget, $room, $context->getRows(), 0) - $marginColumns : min($room, $this->measureIntrinsicWidth($widget, 4096, $context->getRows()) - $marginColumns);
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
        // A table is never narrower than the longest words of its cells, like CSS: its width or max-width gives way to
        // them, like the portal box of Wikipedia of max-width: 175px
        if (null !== $box && TableRole::Table === $resolvedStyle->getTableRole() && $box + $marginColumns < $allotted) {
            $box = max($box, min($allotted, $this->measureMinContentWidth($widget, $allotted, $context->getRows())) - $marginColumns);
        }
        // min-width as a percentage, like 100%: a box sized by its content is at least that share of its containing
        // block, not of the room an item of a flex row or column is given, like CSS
        if (null !== $box && null !== $minPercent = $resolvedStyle->getMinWidthPercent()) {
            $box = max($box, min($allotted - $marginColumns, (int) floor($context->getContainingColumns() * $minPercent / 100)));
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
            $this->outlines[$widget] = [$boxMargin->top ?? 0, $boxMargin->bottom ?? 0];
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
            $resolvedStyle = $resolvedStyle->withRows(null === $min ? $resolvedStyle->getMinRows() : max($min, $resolvedStyle->getMinRows() ?? 0), null === $max ? $resolvedStyle->getMaxRows() : min($max, $resolvedStyle->getMaxRows() ?? \PHP_INT_MAX));
        }
        // aspect-ratio: without a height, the box is as tall as its width says, a row being two columns high
        $fixedRows = null !== $resolvedStyle->getMinRows() && $resolvedStyle->getMinRows() === $resolvedStyle->getMaxRows() ? $resolvedStyle->getMinRows() : null;
        if (null !== ($ratio = $resolvedStyle->getAspectRatio()) && null === $fixedRows) {
            [$contentColumns] = $this->chromeApplier->computeInnerDimensions($context->getColumns(), $context->getRows(), $resolvedStyle);
            $rows = max(1, min(1000, (int) round($contentColumns / $ratio / 2)));
            // The ratio of its border box, like box-sizing: border-box, the rows of its content inside its border and padding
            if (!$resolvedStyle->isContentBoxRatio()) {
                [$border, $padding] = [$resolvedStyle->getBorder(), $resolvedStyle->getPadding()];
                $borderColumns = $contentColumns + ($border->left ?? 0) + ($border->right ?? 0) + ($padding->left ?? 0) + ($padding->right ?? 0);
                $rows = max(0, max(1, min(1000, (int) round($borderColumns / $ratio / 2))) - ($border->top ?? 0) - ($border->bottom ?? 0) - ($padding->top ?? 0) - ($padding->bottom ?? 0));
            }
            // Within its max-height and its min-height, like CSS, the min-height winning
            $fixedRows = max(min($rows, $resolvedStyle->getMaxRows() ?? \PHP_INT_MAX), $resolvedStyle->getMinRows() ?? 0);
            $resolvedStyle = $resolvedStyle->withRows(max($fixedRows, $resolvedStyle->getMinRows() ?? 0), $resolvedStyle->getMaxRows());
        }
        // A checkbox or a radio CSS draws as a box too short for its border, like a 1em one: a mark in its colors, the
        // border and the background it has no room for left out
        if ($widget instanceof CheckboxWidget) {
            $border = $resolvedStyle->getBorder();
            $compact = $widget->isDrawnByCss() && null !== $border && $border->top + $border->bottom > 0 && ($resolvedStyle->getMaxRows() ?? \PHP_INT_MAX) <= $border->top + $border->bottom;
            $widget->setCompactMark($border->color ?? $resolvedStyle->getColor(), $resolvedStyle->getBackground(), $compact);
            if ($compact) {
                $resolvedStyle = $resolvedStyle->withBorder(new Border(0, 0, 0, 0))->withBackground(null)->withRows(1, 1);
            }
        }
        // An image is told the size its style sets: its width, its height, how it fills them
        if ($widget instanceof ReplacedElementInterface) {
            // The rows of a style are those of the content already, inside the border and the padding
            [$contentColumns] = $this->chromeApplier->computeInnerDimensions($context->getColumns(), $context->getRows(), $resolvedStyle);
            $widget->setBox(null !== $resolvedStyle->getMaxColumns() || null !== $resolvedStyle->getWidthPercent() ? $contentColumns : null, $fixedRows, $resolvedStyle->getObjectFit(), $resolvedStyle->getObjectPosition());
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
            // Not drawn: nowhere, as when drawn whole, not where an earlier frame drew them
            if ($this->positionTracker->isActive() && $widget instanceof ParentInterface) {
                foreach ($widget->all() as $child) {
                    $this->positionTracker->forgetUndrawn($child);
                }
            }
        } elseif ($widget instanceof ContainerWidget) {
            $lines = $this->renderContainer($widget, $styledContext, $resolvedStyle);
        } elseif ($widget instanceof HorizontalRuleWidget && ($resolvedStyle->getBorder()->top ?? 0) + ($resolvedStyle->getBorder()->bottom ?? 0) > 0) {
            // A rule given a border, like hr { border: 0; border-top: 1px solid }: the border is the rule, like CSS
            $lines = $this->chromeApplier->apply(new ArrayLineBuffer([]), $context->getColumns(), $resolvedStyle, $widget);
        } else {
            $innerContext = $this->chromeApplier->computeInnerContext($styledContext, $resolvedStyle);
            $vertical = null !== $resolvedStyle->getWritingMode() && $widget instanceof TextWidget;
            $scale = $this->mediaContext->textSizing && $widget instanceof TextWidget ? $resolvedStyle->getFontScale() : null;
            $content = match (true) {
                $vertical => $this->renderVertically($widget, $innerContext, $resolvedStyle, $context, $definite),
                null !== $scale && $widget instanceof TextWidget => $this->renderScaled($widget, $innerContext, $scale),
                // One drawing what it holds itself, like an open <details> its content under its summary
                $widget instanceof ParentInterface => $this->renderSelfDrawn($widget, $resolvedStyle, static fn (): array => $widget->render(ContextBridge::forWidget($widget, $innerContext, 'render'))),
                default => $widget->render(ContextBridge::forWidget($widget, $innerContext, 'render')),
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
            } elseif ($vertical && !\in_array($resolvedStyle->getWritingMode(), ['vertical-lr', 'sideways-lr'], true) && 0 < $free = $innerContext->getColumns() - max(0, ...array_map(AnsiUtils::visibleWidth(...), $content ?: ['']))) {
                // Wider than its columns, from the right: they start at its right side, like CSS
                $content = array_map(static fn (string $line): string => str_repeat(' ', $free).$line, $content);
            }
            // A ::before that clears, see TextWidget::setClearance(): its lines below the floats, its box beside them
            // Not text written vertically, whose rows are its lines
            if (!$vertical && 0 < $clearance = $this->clearance($widget, $resolvedStyle)) {
                $content = [...array_fill(0, $clearance, ''), ...$content];
            }
            $lines = $this->chromeApplier->apply(new ArrayLineBuffer($content), $boxColumns, $resolvedStyle, $widget);
            // An open <details> places its content itself, see renderSelfDrawn(): what its box does not draw of it, like
            // what a height with overflow: hidden cuts, takes no pointer
            if (self::placesItsContent($widget) && $widget->isExpanded() && $this->positionTracker->isActive()) {
                [$row] = $this->positionTracker->currentOffset();
                [$top] = $this->chromeApplier->computeChromeOffset($resolvedStyle);
                $bottom = ($resolvedStyle->getBorder()->bottom ?? 0) + ($resolvedStyle->getPadding()->bottom ?? 0) + ($resolvedStyle->getMargin()->bottom ?? 0);
                $content = $widget->getContent();
                $start = $this->positionTracker->getWidgetRect($content);
                $this->positionTracker->scrollContentPositions([$content], 0, $row + $top, $row + \count($lines) - $bottom);
                // All cut, it still tells where it starts, below the summary, like an empty one: no row, no pointer
                if (null !== $start && null === $this->positionTracker->getWidgetRect($content)) {
                    $this->positionTracker->setWidgetRect($content, new WidgetRect(min($start->row, $row + \count($lines) - $bottom), $start->col, $start->columns, 0));
                }
            }
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
        if ($this->postRenderOverridden[$widget::class] ??= AbstractWidget::class !== (new \ReflectionMethod($widget, 'postRender'))->getDeclaringClass()->name) {
            $lines = new ArrayLineBuffer($widget->postRender($lines->toArray(), ContextBridge::forWidget($widget, $context, 'postRender')));
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
        $lines = $this->overflowSetHeight($widget, $context, $resolvedStyle, $lines, null !== $stretchRows);
        $this->validateLines($widget, $lines, $context->getColumns());
        RenderCache::set($widget, $lines, $cacheColumns, $cacheRows, $cacheKey, $widget instanceof ContainerWidget ? ContainerState::of($widget)->getEscapedFloats() : [], self::CACHED_OTHER_SIZES);
        $this->positionTracker->markRendered($widget);

        return $lines;
    }

    /**
     * Renders a widget its parent draws itself, $row rows below the top of the content box of that parent, like the
     * content of an open <details> under its summary: tracked there, what it holds taking the pointer.
     *
     * @internal
     */
    public function renderLinesAt(AbstractWidget $widget, RenderContext $context, int $row): LineBufferInterface
    {
        if (!$this->positionTracker->isActive()) {
            return $this->renderLines($widget, $context);
        }
        [$parentRow, $parentCol] = $this->positionTracker->currentOffset();
        $this->positionTracker->push($parentRow + $row, $parentCol);
        try {
            $lines = $this->renderLines($widget, $context);
            $rect = new WidgetRect($parentRow + $row, $parentCol, $context->getColumns(), \count($lines));
            // Taken from its cache, what it holds is where it was drawn last: moved, or drawn again when not tracked then
            if ($widget instanceof ParentInterface && $this->wasRenderCached($widget) && !$this->positionTracker->moveSubtree($widget, $rect)) {
                $widget->clearRenderCache();
                $lines = $this->renderLines($widget, $context);
                $rect = new WidgetRect($parentRow + $row, $parentCol, $context->getColumns(), \count($lines));
            }
        } finally {
            $this->positionTracker->pop();
        }
        $this->positionTracker->setWidgetRect($widget, $rect);

        return $lines;
    }

    /**
     * Whether a widget drawing what it holds itself places it where it is tracked: the <details> whose own render() does,
     * see CollapsibleWidget::render(), not a subclass of it, drawn untracked.
     *
     * @phpstan-assert-if-true CollapsibleWidget $widget
     *
     * @psalm-assert-if-true CollapsibleWidget $widget
     */
    private static function placesItsContent(AbstractWidget $widget): bool
    {
        return $widget instanceof CollapsibleWidget && CollapsibleWidget::class === $widget::class;
    }

    /**
     * Renders a widget that draws what it holds itself: a <details> tracks its content where it draws it, from its
     * content box, see renderLinesAt(); where the others, like a TabsWidget of Tui, draw it is not known, it is not
     * tracked.
     *
     * @template T
     *
     * @param \Closure(): T $render
     *
     * @return T
     */
    private function renderSelfDrawn(AbstractWidget $widget, Style $style, \Closure $render): mixed
    {
        if (!self::placesItsContent($widget)) {
            return $this->renderUntracked($render);
        }
        if (!$this->positionTracker->isActive()) {
            return $render();
        }
        // Closed, its content is not drawn: it has no rect, like what display: none hides
        if (!$widget->isExpanded()) {
            $this->positionTracker->forget($widget->getContent());
        }
        [$row, $col] = $this->positionTracker->currentOffset();
        [$top, $left] = $this->chromeApplier->computeChromeOffset($style);
        $this->positionTracker->push($row + $top, $col + $left);
        try {
            return $render();
        } finally {
            $this->positionTracker->pop();
        }
    }

    /**
     * @template T
     *
     * @param \Closure(): T $render
     *
     * @return T
     */
    private function renderUntracked(\Closure $render): mixed
    {
        $saved = $this->positionTracker->suppressStack();
        try {
            return $render();
        } finally {
            $this->positionTracker->restoreStack($saved);
        }
    }

    /**
     * Drops the margin rows a parent and its first or last child share: the larger margin stays, outside the box of the
     * parent, like CSS, not its border and background beside the rows of the margin of its child.
     *
     * @param list<AbstractWidget> $children
     * @param bool                 $keepInside The margins of its children not collapsed with its own kept in its box
     *
     * @param-out int             $dropped
     * @param-out array{int, int} $outside The rows of the margins of its children its own margins take on top of theirs
     */
    private function collapseParentMargins(Style $style, array $children, LineBufferInterface $lines, int $columns, ?int &$dropped = null, bool $bottomOnly = false, ?array &$outside = null, bool $keepInside = false): LineBufferInterface
    {
        $dropped = 0;
        $outside = [0, 0];
        $margin = $style->getMargin();
        $border = $style->getBorder();
        $padding = $style->getPadding();
        if (!$children) {
            return $lines;
        }
        // The margins of its first and last children through its top and bottom
        $inTop = 0;
        // Not with a first child whose ::before cleared floats in the layout just made: that clearance counts its margin
        // rows, its content under the floats
        $first = $children[0];
        $cleared = LayoutEngine::hasClearance($first);
        // Past the floats it starts with, put under that margin, see LayoutEngine::layoutFloats(); not with a float it ends
        // with, whose margins do not collapse, drawn over those rows
        $start = $this->layoutEngine->flowStart($this->unwrapFloats($children));
        if (!$bottomOnly && !$cleared && 0 === ($border->top ?? 0) && 0 === ($padding->top ?? 0) && null !== $start) {
            $inTop = $this->layoutEngine->collapsedMargin($start, true, $columns);
        }
        $inBottom = 0;
        if (0 === ($border->bottom ?? 0) && 0 === ($padding->bottom ?? 0) && null === $this->resolveComputedStyle($children[array_key_last($children)])->getFloat()) {
            $inBottom = $this->layoutEngine->collapsedMargin($children[array_key_last($children)], false, $columns);
        }
        $top = min(max(0, $margin->top ?? 0), $inTop);
        $bottom = min(max(0, $margin->bottom ?? 0), $inBottom);
        if ($keepInside) {
            [$inTop, $inBottom] = [$top, $bottom];
        }
        // Nothing but margins, like those of an empty child collapsed through it, counted on both sides: all of them go,
        // collapsed with its own, like CSS
        if ($inTop + $inBottom >= \count($lines)) {
            if (0 >= $top + $bottom) {
                return $lines;
            }
            if ($top + $bottom >= \count($lines)) {
                $dropped = min($top, \count($lines));

                return new ArrayLineBuffer([]);
            }
            $dropped = $top;

            return ArrayLineBuffer::sliceOf($lines, $top, \count($lines) - $top - $bottom);
        }
        // Its bottom margin, of a box as tall as its content only, like CSS: one of a height keeps the rows in it
        if (null !== $style->getMaxRows() || null !== $style->getMinRows() || null !== $style->getMinRowsPercent() || null !== $style->getMaxRowsPercent()) {
            $inBottom = $bottom;
        }
        // Only rows of margins, blank, not those of a ::before or ::after of the child or of something it draws
        $blank = static fn (string $line): bool => '' === trim(AnsiUtils::stripAnsiCodes($line));
        for ($row = $top; $row < $inTop; ++$row) {
            if (!$blank($lines->getLine($row))) {
                $inTop = $row;
            }
        }
        for ($row = $bottom; $row < $inBottom; ++$row) {
            if (!$blank($lines->getLine(\count($lines) - 1 - $row))) {
                $inBottom = $row;
            }
        }
        if (0 >= $inTop + $inBottom) {
            return $lines;
        }
        // Its content moved up by the caller as much as the margins collapsed, the rest of those of its children outside
        $dropped = $top;
        $outside = [$inTop - $top, $inBottom - $bottom];

        // Sliced from the buffer, not from all its lines copied first: millions of rows of margins, too
        return ArrayLineBuffer::sliceOf($lines, $inTop, \count($lines) - $inTop - $inBottom);
    }

    /**
     * The ::before and ::after of a block drawn on lines of their own, before and after its children: their text and
     * style; null for none, or an empty or hidden one, like the boxes of a clearfix.
     *
     * @return array{0: array{string, Style}|null, 1: array{string, Style}|null}
     */
    private function generatedLines(AbstractWidget $widget, Style $style): array
    {
        $generated = [null, null];
        if ((ContainerWidget::class === $widget::class || HtmlContainerWidget::class === $widget::class || $widget instanceof FormWidget) && Direction::Horizontal !== $style->getDirection() && null === $style->getGridTemplate()) {
            foreach (ContainerState::of($widget)->getGeneratedContent() as $i => $text) {
                if (null === $text || self::isBlankContent($text)) {
                    continue;
                }
                $elementStyle = StyleBridge::element($this->styleSheet, $widget, 0 === $i ? 'before' : 'after');
                if (true !== $elementStyle->getHidden() && true !== $elementStyle->getInvisible() && 0 !== $elementStyle->getMaxRows()) {
                    $generated[$i] = [$text, $elementStyle];
                }
            }
        }

        return $generated;
    }

    /**
     * Whether a block draws its ::before or its ::after on lines of its own, between it and its first or last child.
     *
     * @internal
     */
    public function drawsGeneratedLines(AbstractWidget $widget, bool $before): bool
    {
        return null !== $this->generatedLines($widget, $this->resolveComputedStyle($widget))[$before ? 0 : 1];
    }

    /**
     * The style of a widget as the style sheets give it, before the layout changes it, like the border of a cell of a
     * collapsed table, which the table draws: resolved once a frame, with resolveStyle().
     */
    private function declaredStyle(AbstractWidget $widget): Style
    {
        $cached = $this->styleCache[$widget] ?? null;
        if (null === $cached || $cached[0] !== $widget->getRenderRevision() || $cached[3] !== $this->styleFrame && $cached[4] !== $this->styleInputs) {
            $resolved = $this->resolveComputedStyle($widget);

            return $this->styleCache[$widget][2] ?? $resolved;
        }

        return $cached[2];
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
                $style = $this->resolveComputedStyle($child);
                if (null !== $style->getTableRole() || $bottom !== $style->getCaptionBottom()) {
                    break;
                }
                if (true !== $style->getHidden()) {
                    $count += \count($this->renderLines($child, new RenderContext(max(1, $columns), $rows, null, $this->fontRegistry)));
                    $box = $this->renderLines($child, new RenderContext($boxColumns, $rows, null, $this->fontRegistry))->toArray();
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
        if (!(ContainerWidget::class === $widget::class || HtmlContainerWidget::class === $widget::class || $widget instanceof FormWidget) || Direction::Horizontal !== $resolvedStyle->getDirection() && null === $resolvedStyle->getGridTemplate()) {
            return [];
        }
        $found = [];
        foreach (ContainerState::of($widget)->getGeneratedContent() as $i => $text) {
            if (null === $text) {
                continue;
            }
            $elementStyle = StyleBridge::element($this->styleSheet, $widget, 0 === $i ? 'before' : 'after');
            if (true === $elementStyle->getHidden() || 0 === $elementStyle->getMaxRows()) {
                continue;
            }
            // A triangle its borders drew, like a caret after a menu: the glyph, its margins in it already, not boxed; not
            // one written as its content, which keeps its margins
            if (preg_match('/^ *[▾◂▴▸] *$/u', $text) && null !== ($border = $elementStyle->getBorder()) && $border->top + $border->right + $border->bottom + $border->left > 0) {
                $elementStyle = $elementStyle->withBorder([0, 0, 0, 0])->withMargin([0, 0, 0, 0])->withPadding([0, 0, 0, 0]);
                $text = str_replace(' ', "\u{a0}", $text);
            }
            // Empty but of a width, like the separator of a breadcrumb of MDN, a chevron its mask draws: its room
            if (self::isBlankContent($text)) {
                if (null === $width = $elementStyle->getMaxColumns()) {
                    continue;
                }
                $text = str_repeat("\u{a0}", max(1, min(1000, $width)));
            }
            // Its images, like content: url(icon.png), drawn in it
            $items = $this->pseudoItems[$widget] ?? [];
            if (($items[$i] ?? null)?->getSourceText() !== $text) {
                $items[$i] = new RichTextWidget([InlineNode::text($text)]);
            }
            $item = $items[$i];
            ContainerState::of($item)->setGeneratedImages(ContainerState::of($widget)->getGeneratedImages());
            StyleBridge::set($item, $elementStyle);
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
        if (null !== $this->resolveComputedStyle($widget)->getBackground()) {
            return true;
        }
        if ($depth >= 3 || !$widget instanceof ContainerWidget) {
            return false;
        }
        foreach ($widget->all() as $child) {
            // Not one out of the flow, drawn on its own, like the handle of the resizable example of Tailwind in a box
            // without a background over the example
            if ($child instanceof ContainerWidget && !($childStyle = $this->resolveComputedStyle($child))->getHidden() && !$childStyle->getPosition()?->isOutOfFlow() && $this->paintsBackground($child, $depth + 1)) {
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
        // Most widgets: none of what follows, read on every style asked for
        if (TableRole::Cell !== $style->getTableRole() && !isset($this->sidewaysCaps[$widget]) && !isset($this->unboundedRows[$widget])) {
            return $style;
        }
        if (isset($this->sidewaysCaps[$widget])) {
            $style = $style->withMaxColumns(min($style->getMaxColumns() ?? \PHP_INT_MAX, $this->sidewaysCaps[$widget]));
        }
        // The content of an absolute box past the height it sets, its least height kept
        if (isset($this->unboundedRows[$widget])) {
            $style = $style->withRows($style->getMinRows(), null)->withRowsPercent($style->getMinRowsPercent(), null, $style->getRowsPercentOffsets()[0]);
        }
        // A row of a height is a least one, its cells as tall as it, their content placed in it by their vertical-align
        if (TableRole::Cell === $style->getTableRole() && null !== ($row = $widget->getParent()) && ($rowRows = $this->resolveComputedStyle($row)->getMinRows() ?? 0) > ($style->getMinRows() ?? 0) && TableRole::Row === $this->resolveComputedStyle($row)->getTableRole()) {
            $style = $style->withRows($rowRows, null === $style->getMaxRows() ? null : max($rowRows, $style->getMaxRows()));
        }
        // The background of the column is under the cell, and under its row and row group too, like CSS; those of the
        // columns it spans, each under the part of it in its column
        $bands = TableRole::Cell === $style->getTableRole() && null === $style->getBackground() && null === $style->getGradient() ? $this->layoutEngine->getColumnBands($widget) : null;
        $background = null === $bands && TableRole::Cell === $style->getTableRole() && null === $style->getBackground() ? $this->layoutEngine->getColumnBackground($widget) : null;
        if (null !== $bands || null !== $background) {
            for ($ancestor = $widget->getParent(); null !== $ancestor; $ancestor = $ancestor->getParent()) {
                $ancestorStyle = $this->resolveComputedStyle($ancestor);
                if (TableRole::Table === $ancestorStyle->getTableRole()) {
                    break;
                }
                if (null !== $ancestorStyle->getBackground()) {
                    return $style;
                }
            }
            if (null !== $background) {
                return $style->withBackground($background);
            }
            // Hard stops at the edges of the columns, a column without a background transparent, of the color of another
            $any = array_values(array_filter(array_column($bands, 0)))[0];
            $stops = [];
            $at = 0;
            foreach ($bands as [$color, $columns]) {
                foreach ([$at, $at + $columns] as $cells) {
                    $stops[] = null === $color ? [$any, 0.0, (float) $cells, 0.0] : [$color, 0.0, (float) $cells];
                }
                $at += $columns;
            }
            $style = $style->withGradient(new Gradient('linear', 90.0, Gradient::resolve($stops, 0.0), rawStops: $stops));
        }

        return $style;
    }

    /**
     * The table a cell is in when its borders collapse, null otherwise.
     */
    private function collapsedTableOf(AbstractWidget $cell): ?AbstractWidget
    {
        for ($ancestor = $cell->getParent(), $depth = 0; null !== $ancestor && $depth < 3; $ancestor = $ancestor->getParent(), ++$depth) {
            $style = $this->resolveComputedStyle($ancestor);
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

        return min(ContainerState::of($widget)->getClearance(), $resolvedStyle->getMaxRows() ?? \PHP_INT_MAX);
    }

    /**
     * @param bool $unbound Not bound by its own width nor max-width, its max-content width, see measureMaxContentWidth()
     */
    private function computeIntrinsicWidth(AbstractWidget $widget, int $maxColumns, int $rows, bool $unbound = false): int
    {
        $resolvedStyle = $this->resolveComputedStyle($widget)->withResolvedBox($maxColumns);
        // Apply maxColumns from the widget's own style: that of its box, its margins around it, like CSS
        $margin = $resolvedStyle->getMargin();
        $marginColumns = ($margin->left ?? 0) + ($margin->right ?? 0);
        $styleMaxColumns = $resolvedStyle->getMaxColumns();
        // A width of its own: a flex-basis or a width set, see DeclarationMapper; of an item that grows, its width only,
        // its basis growing to its content
        $basis = $resolvedStyle->getFlexBasis();
        $grows = ($resolvedStyle->getFlex() ?? 0) > 0;
        $widthSet = null !== $basis && $basis > 0 && null === $resolvedStyle->getWidthPercent() && (!$grows || null !== $styleMaxColumns && $styleMaxColumns !== $resolvedStyle->getRowMaxColumns());
        // A max-width alone: its content unwrapped up to it, like the shrink-to-fit width of CSS, not the lines of its
        // content wrapped at it, narrower
        if (null !== $styleMaxColumns && !$unbound && !$widthSet && null === $resolvedStyle->getWidthPercent()) {
            return min($maxColumns, $styleMaxColumns + $marginColumns, $this->computeIntrinsicWidth($widget, $maxColumns, $rows, true));
        }
        if (null !== $styleMaxColumns && !$unbound) {
            $maxColumns = min($maxColumns, $styleMaxColumns + $marginColumns);
        }
        // A percentage of the width of what holds it: auto inside a container sized by its content, like CSS, like the
        // field of an input group of Bootstrap (width: 1%) in a toolbar
        if (null !== $resolvedStyle->getWidthPercent() && 0 === $this->contentDepth) {
            $maxColumns = min($maxColumns, (int) $resolvedStyle->resolveWidthPercent($maxColumns) + $marginColumns);
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
                $lines = $this->renderLines($widget, new RenderContext($maxColumns, $rows, null, $this->fontRegistry));
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
                    $childStyle = $this->resolveComputedStyle($child);
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
                    $childStyle = $this->resolveComputedStyle($child);
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
                fn (AbstractWidget $child) => !$this->resolveComputedStyle($child)->getHidden() && !$this->resolveComputedStyle($child)->getPosition()?->isOutOfFlow()
            ));
            $direction = $resolvedStyle->getDirection() ?? Direction::Vertical;
            $gap = $resolvedStyle->getGap() ?? 0;
            // Its height, when set, is that of the text its children write vertically: known before it is drawn
            if (null !== ($fixed = $resolvedStyle->getMaxRows()) && $fixed === $resolvedStyle->getMinRows()) {
                $this->definiteRows[$widget] = $fixed;
            }
            if (null !== $grid = $resolvedStyle->getGridTemplate()) {
                // A grid is as wide as its columns sized by their content, side by side, like the equal segments of
                // grid-auto-columns: 1fr of the tabs of the exception page of Symfony; one written vertically, as wide as
                // its rows: laid out to know them
                $saved = $this->positionTracker->suppressStack();
                try {
                    $lines = $this->layoutEngine->layoutGrid($children, $innerColumns, $rows, $grid, $gap, $resolvedStyle->getRowGap() ?? 0, null, false, null, $fixed === $resolvedStyle->getMinRows() ? $fixed : null, null, true);
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
                        // The items that grow from a basis narrower than their content, by index: that basis, their
                        // flex-grow and their content
                        $growing = [];
                        foreach ($children as $index => $child) {
                            $childStyle = $this->resolveComputedStyle($child);
                            $childWidth = $this->measureIntrinsicWidth($child, $innerColumns, $rows);
                            if ($childStyle->getOwnLine()) {
                                $ownLines = max($ownLines, $childWidth);
                                continue;
                            }
                            $contentWidth += $childWidth + ($index > 0 && $childStyle->getSpaceBefore() ? 1 : 0);
                            // Its basis with its margins, which its measured width counts too
                            if (($grow = $childStyle->getFlex() ?? 0) > 0 && null !== $basis = $childStyle->resolveFlexBasis($innerColumns)) {
                                $growing[$index] = [$basis + max(0, $childStyle->getMargin()->left ?? 0) + max(0, $childStyle->getMargin()->right ?? 0), (float) $grow, $childWidth];
                            }
                        }
                        // Wide enough for each of them to get its content from its share, like the flex fractions of CSS:
                        // two buttons of flex: 1 as wide as the wider, not one of them cut; each at least its basis
                        if ($growing) {
                            $fraction = max(0, ...array_map(static fn (array $item): float => ($item[2] - $item[0]) / $item[1], $growing));
                            foreach ($growing as [$basis, $grow, $childWidth]) {
                                $contentWidth += max(0, (int) ceil($basis + $fraction * $grow) - $childWidth);
                            }
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
                            $childStyle = $this->resolveComputedStyle($child);
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
                                'both' => ['left', 'right'],
                                'left' => ['left'],
                                'right' => ['right'],
                                default => [],
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
                $widget->setBox(null !== $resolvedStyle->getMaxColumns() || null !== $resolvedStyle->getWidthPercent() ? $innerColumns : null, null !== $resolvedStyle->getMinRows() && $resolvedStyle->getMinRows() === $resolvedStyle->getMaxRows() ? $resolvedStyle->getMinRows() : null, $resolvedStyle->getObjectFit(), $resolvedStyle->getObjectPosition());
                if ($widget instanceof ImageWidget) {
                    $widget->setRendering($resolvedStyle->getImageFromImage() ?? true, $resolvedStyle->getPixelated() ?? false);
                }
            }
            // An image: the columns it is drawn in, found without drawing it, which a large room would make long
            if (($widget instanceof ImageWidget || $widget instanceof SvgWidget) && null !== $drawn = $widget->drawnColumns($innerColumns)) {
                return min(max(1, $drawn + $maxColumns - $innerColumns), $maxColumns);
            }
            // For leaf widgets, render content at inner dimensions and measure widest line
            $innerContext = $this->chromeApplier->computeInnerContext((new RenderContext($maxColumns, $rows, null, $this->fontRegistry))->withStyle($resolvedStyle), $resolvedStyle);
            $widget->beforeRender();
            $contentLines = $widget->render(ContextBridge::forWidget($widget, $innerContext, 'render'));
            $widget->clearRenderCache();
            $contentWidth = 0;
            foreach ($contentLines as $line) {
                $contentWidth = max($contentWidth, AnsiUtils::visibleWidth($line));
            }
        }
        $chromeWidth = $maxColumns - $innerColumns;
        // A width set, like the width: 40px of the empty switch of VitePress: that width, its margins around it, whatever
        // its content; not the flex-basis alone of an item that grows, which grows to its content, like CSS, its width
        // when it has one
        if ($widthSet) {
            $margin = $resolvedStyle->getMargin();

            return min(max(1, ($grows ? (int) $styleMaxColumns : $basis) + max(0, $margin->left ?? 0) + max(0, $margin->right ?? 0)), $maxColumns);
        }
        $parent = $widget->getParent();
        // Nothing of an empty box without a border nor a padding, like the clearer of Sphinx in a flex row
        $minimum = null !== $parent && null !== $this->resolveComputedStyle($parent)->getGridTemplate() || $widget instanceof ContainerWidget && 0 === $chromeWidth && !$widget->all() ? 0 : 1;

        return min(max($minimum, $contentWidth + $chromeWidth), $maxColumns);
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
        $softens = $widget instanceof FocusableInterface && !$widget instanceof TextWidget || $widget instanceof ReplacedElementInterface || $widget instanceof ParentInterface && array_any($widget->all(), fn (AbstractWidget $child): bool => $this->softens($child, $depth + 1));
        $this->softens[$widget] = [$revision, $softens];

        return $softens;
    }

    /**
     * @param bool $placed Whether the widget is the positioned one being placed, measured though out of the flow
     * @param bool $soft   Its controls ellipsized and its images scaled down, see measureSoftMinContentWidth()
     */
    private function computeMinContentWidth(AbstractWidget $widget, int $columns, int $rows, int $depth, bool $placed = false, bool $soft = false): int
    {
        $style = $this->resolveComputedStyle($widget);
        if ($depth > 32 || $style->getHidden() || !$placed && $style->getPosition()?->isOutOfFlow()) {
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
                // Whose borders collapse: the lines between its columns, and on its sides those of its cells
                $collapsed = true === $resolved->getCollapse();
                $framed = false;
                foreach ($this->boxChildren($widget) as $child) {
                    $role = $this->resolveComputedStyle($child)->getTableRole();
                    $rowsOf = TableRole::Row === $role ? [$child] : (TableRole::RowGroup === $role && $child instanceof ParentInterface ? $child->all() : []);
                    foreach ($rowsOf as $row) {
                        $rowGap = max($rowGap, $collapsed ? 1 : $this->resolveComputedStyle($row)->getGap() ?? 0);
                        foreach ($row instanceof ParentInterface ? array_values($row->all()) : [] as $i => $cell) {
                            $columnsMin[$i] = max($columnsMin[$i] ?? 0, $soft ? $this->softMinContentWidth($cell, $innerColumns, $rows, $depth + 1) : $this->measureMinContentWidth($cell, $innerColumns, $rows, $depth + 1));
                            $framed = $framed || $collapsed && (($border = $this->declaredStyle($cell)->getBorder())->left ?? 0) + ($border->right ?? 0) > 0;
                        }
                    }
                }
                $border = $resolved->getBorder();

                return array_sum($columnsMin) + $rowGap * max(0, \count($columnsMin) - 1) + $chrome + ($framed ? (0 === ($border->left ?? 0) ? 1 : 0) + (0 === ($border->right ?? 0) ? 1 : 0) : 0);
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
            $context = $this->chromeApplier->computeInnerContext((new RenderContext($width, $rows, null, $this->fontRegistry))->withStyle($resolved), $resolved);
            $widget->beforeRender();
            // Its no-break spaces kept, which join words, like the 23&nbsp;/&nbsp;23 of a coverage report
            if ($widget instanceof RichTextWidget) {
                $widget->keepNoBreakSpaces(true);
            }
            try {
                // As if no float were beside it, see TextWidget::measureWithoutFloats()
                $lines = $widget instanceof TextWidget ? $widget->measureWithoutFloats(static fn (): array => $widget->render(ContextBridge::forWidget($widget, $context, 'render'))) : $widget->render(ContextBridge::forWidget($widget, $context, 'render'));
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
        if (false === $wraps || null === $wraps && ($width <= $innerColumns || \count($render($columns)) <= \count($widest))) {
            return $width + $chrome;
        }
        $word = 0;
        foreach ($widest as $line) {
            // At the spaces the lines wrap at: not a no-break space, like the 23&nbsp;/&nbsp;23 of a coverage report; at the
            // zero-width spaces between the letters of word-break: break-all too, like the code of the documentation of Django
            $line = AnsiUtils::stripAnsiCodes($line);
            // At the spaces alone in a line of bytes that are not UTF-8
            foreach ((preg_split('/[ \t\x{200B}]+/u', $line) ?: preg_split('/[ \t]+/', $line)) ?: [] as $part) {
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
        // A legend in the top border, out of the flow, like the one of a <fieldset>; not a floated or an absolute one, which
        // is laid out as any other child, like browsers
        $legend = ContainerState::of($widget)->getLegend();
        if (null !== $legend && ($legend->getParent() !== $widget || ($resolvedStyle->getBorder()->top ?? 0) < 1 || $this->resolveComputedStyle($legend)->getHidden() || null !== $this->resolveComputedStyle($legend)->getFloat() || true === $this->resolveComputedStyle($legend)->getPosition()?->isOutOfFlow())) {
            $legend = null;
        }
        // Plain children, all in the flow, only those that changed looked at again, see plainChildren()
        $changedChildren = null;
        $children = null === $legend ? $this->plainChildren($widget, $changedChildren) : null;
        if (null === $children) {
            $children = [];
            foreach ($this->boxChildren($widget) as $child) {
                if ($child === $legend) {
                    continue;
                }
                $style = $this->resolveComputedStyle($child);
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
        }
        $plain = null !== $changedChildren;
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
            // Scrolled sideways, like table { display: block; overflow-x: auto }: as wide as the longest words of its
            // rows, past its window, like CSS
            $tableColumns = $innerColumns;
            if (true === $resolvedStyle->getScroll()?->horizontal) {
                foreach ($children as $child) {
                    $tableColumns = max($tableColumns, min(self::MAX_CLIPPED_COLUMNS, $this->measureMinContentWidth($child, $innerColumns, $innerRows)));
                }
            }
            $this->layoutEngine->prepareTable($widget, $tableColumns, $innerRows, null !== $resolvedStyle->getWidthPercent() || null !== $resolvedStyle->getMinWidthPercent() || null !== $resolvedStyle->getMaxColumns() && null === $resolvedStyle->getContentWidth());
        }
        // Its children can have percentage heights when its own height is definite: the frame, a fixed height, or the
        // one its aspect-ratio gives, like CSS
        $fixedRows = $resolvedStyle->getMinRows();
        // Not lost to an absolute box measured past its height, see renderPositioned()
        if ($context->shouldFillRows() || null !== $fixedRows && ($fixedRows === $resolvedStyle->getMaxRows() || null !== $resolvedStyle->getAspectRatio() || true === ($this->unboundedRows[$widget] ?? null))) {
            $this->definiteRows[$widget] = $context->shouldFillRows() && null === $fixedRows ? $innerRows : (int) $fixedRows;
        } elseif ((null === $fixedRows || $resolvedStyle->getDocumentHeight()) && ContainerState::of($widget)->passesDefiniteHeight() && null !== ($parent = $widget->getParent()) && null !== $parentRows = $this->definiteRows[$parent] ?? null) {
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
            $bottom = array_filter($children, fn (AbstractWidget $child) => null === $this->resolveComputedStyle($child)->getTableRole() && $this->resolveComputedStyle($child)->getCaptionBottom());
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
            } elseif ($context->shouldFillRows()) {
                $columnRows = $innerRows;
            }
        }
        $inlineFlow = ContainerState::of($widget)->isInlineFlow();
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
        // Set below, once the ::before and ::after are known, before any layout runs
        /** @var array{0: array|null, 1: array|null} $generated */
        $generated = [null, null];
        // A ::before beside the floats of the block around: its lines wrapped in the room beside them and the columns of
        // the floats on their left, set by the layout, see $decorate
        /** @var array{list<string>, list<int>}|null $beforeBesideFloats */
        $beforeBesideFloats = null;
        $layout = function (int $innerColumns, ?array $children = null) use ($widget, $resolvedStyle, $all, $innerRows, $gap, $gapLine, $direction, $verticalAlign, $alignContent, $contentRows, $columnRows, $inlineFlow, $clearance, $plain, $changedChildren, &$generated, &$beforeBesideFloats, &$beforePull, &$contentRow, &$contentCol, &$escaped, &$origin): LineBufferInterface {
            // The layout of all of them kept for the next, see LayoutEngine::layoutVertical(): not of a part of them
            $owner = null === $children ? $widget : null;
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
                $exclusion = ContainerState::of($widget)->getFloatExclusion();
                // Plain children are no floats, nor let any out, see plainChildren()
                if (null === $grid && Direction::Horizontal !== $direction && ($exclusion || !$plain && array_any($children, fn (AbstractWidget $child) => null !== $this->resolveComputedStyle($child)->getFloat() || $this->letsFloatsOut($child) || $this->escapesFloats($child)))) {
                    if (isset($this->childSummaries[$widget])) {
                        $this->childSummaries[$widget]['cleared'] = false;
                    }
                    // Block layout, like CSS: the margins of a column without a direction collapse; the floats of a plain
                    // block holding nothing else laid out here, beside the floats after it, like the menu of jQuery; those
                    // of a plain block going on past its content, not making it taller, the blocks after it beside them
                    $escaped = ContainerState::of($widget)->hasFloatsPast() && $this->escapesFloats($widget) ? [] : null;

                    // Its top margin collapsing with that of its first block in the flow, see collapseParentMargins()
                    // A ::before beside the floats around: its lines in the room beside them, like those of text, its
                    // children after them among the floats left, moved up as much, like CSS
                    $beforeBesideFloats = null;
                    if ($exclusion && null !== $generated[0]) {
                        $insets = static fn (int $row): array => LayoutEngine::floatInsetsAt($exclusion, $row);
                        // No room between them: a blank row, the text below them
                        $text = TextWrapper::wrapTextWithAnsiWidths($generated[0][0], static fn (int $line): int => $innerColumns - array_sum($insets($line)));
                        $beforeBesideFloats = [array_map(static fn (string $line): string => $generated[0][1]->apply($line), $text), array_map(static fn (int $row): int => $insets($row)[0], array_keys($text))];
                        // Its first child pulled up over them, see $decorate
                        $exclusion = LayoutEngine::floatsMovedUp($exclusion, \count($text) - min($beforePull, \count($text)));
                    }
                    $adjoining = [null, null] === $generated && 0 === $clearance && 0 === ($resolvedStyle->getBorder()->top ?? 0) && 0 === ($resolvedStyle->getPadding()->top ?? 0) && !$this->establishesFormattingContext($widget) && (!isset($children[0]) || !LayoutEngine::hasClearance($children[0]));

                    return $this->layoutEngine->layoutFloats($this->unwrapFloats($children), $innerColumns, $innerRows, $gap, $exclusion, null === $resolvedStyle->getDirection() && 0 === $gap, $escaped, ContainerState::of($widget)->getMarginAbove(), $adjoining);
                }
                // No float around its children any more: those that changed only, when the others were cleared already
                $summary = $plain ? $this->childSummaries[$widget] ?? null : null;
                foreach (true === ($summary['cleared'] ?? false) ? $changedChildren : $children as $child) {
                    LayoutEngine::clearFloatsAround($child, false);
                }
                if (null !== $summary && $children === $all) {
                    $this->childSummaries[$widget]['cleared'] = true;
                }

                return null !== $grid ? $this->layoutEngine->layoutGrid($children, $innerColumns, $innerRows, $grid, $gap, $resolvedStyle->getRowGap() ?? 0, $verticalAlign, $resolvedStyle->getReverseOrder() ?? false, $resolvedStyle->getJustifyItems(), $contentRows, $alignContent, false, null !== $resolvedStyle->getScroll() ? null : self::MAX_LINES, $resolvedStyle->getJustify(), null === $resolvedStyle->getAlign() && null === $resolvedStyle->getJustify()) : $this->layoutEngine->layout(
                    $children,
                    $innerColumns,
                    $innerRows,
                    $gap,
                    $direction,
                    $gapLine,
                    // A flex column: its justify-content of center or end
                    Direction::Horizontal === $direction || Direction::Vertical === $resolvedStyle->getDirection() ? $verticalAlign : null,
                    $resolvedStyle->getJustify(),
                    $resolvedStyle->getWrap() ? $resolvedStyle->getRowGap() ?? 0 : null,
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
                    $owner
                );
            } finally {
                $this->positionTracker->pop();
            }
        };
        [$innerRows, $contentRows, $columnRows] = $boxRows;
        // A scroll container of bounded height shows a window of its content; the scrollbar takes the last column
        $scroll = $resolvedStyle->getScroll();
        $viewport = null === $scroll || !$scroll->vertical ? null : $resolvedStyle->getMaxRows() ?? ($context->shouldFillRows() ? $innerRows : null);
        // The rows of the ::scroll-button() are not in it: the up one above, the others below
        if (null !== $viewport && [] !== $scrollButtons = ContainerState::of($widget)->getScrollButtons()) {
            $viewport = max(1, $viewport - (isset($scrollButtons['up']) ? 1 : 0) - (array_intersect_key($scrollButtons, ['down' => 1, 'left' => 1, 'right' => 1]) ? 1 : 0));
        }
        // Nor is the row of the scroll-marker-group
        if (null !== $viewport && null !== ContainerState::of($widget)->getScrollMarkers()[0]) {
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
                $need = $this->measureMinContentWidth($child, $innerColumns, $innerRows);
                // A block of a width set as a length wider than its content, like width: 30ch: that width, cut sideways
                $childStyle = $this->resolveComputedStyle($child);
                if (null !== ($width = $childStyle->getMaxColumns()) && $width + ($childStyle->getMargin()->left ?? 0) + ($childStyle->getMargin()->right ?? 0) > $need && !$childStyle->getHidden() && !($childStyle->getPosition()?->isOutOfFlow() ?? false) && $this->hasLengthWidth($child)) {
                    $need = min($width + ($childStyle->getMargin()->left ?? 0) + ($childStyle->getMargin()->right ?? 0), self::MAX_CLIPPED_COLUMNS);
                }
                $needs[] = [$child, $need];
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
        // None wider than the box when the widest width set of plain children is not, see plainChildren()
        if (null === $scroll && 1 === $columnCount && (!$plain || $this->childSummaries[$widget]['width'] > $innerColumns)) {
            $wide = [];
            foreach ($children as $child) {
                $childStyle = $this->resolveComputedStyle($child);
                // Of a width set as a length, not a max-width nor a percentage, which are as wide as the box at most
                if (null !== ($width = $childStyle->getMaxColumns()) && $width > $innerColumns && !$childStyle->getHidden() && !($childStyle->getPosition()?->isOutOfFlow() ?? false)) {
                    $wide[] = [$child, min($width + ($childStyle->getMargin()->left ?? 0) + ($childStyle->getMargin()->right ?? 0), self::MAX_CLIPPED_COLUMNS)];
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
                // Their slots are as wide as the content: their boxes are not; drawn, not measured, see renderContainer()
                foreach ($this->positionTracker->isActive() ? $needs : [] as [$child]) {
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
        // or hidden one, like the boxes of a clearfix; in the columns of a multi-column one, the first and the last
        // content of its flow, but for one with children spanning its columns, laid out between them
        $spans = $columnCount > 1 && [] !== $this->spanningRun($children);
        $generated = $this->generatedLines($widget, $resolvedStyle);
        // Its first child pulled up over the lines of its ::before by a negative margin, as over those of a sibling, like
        // CSS, see $decorate: no further, out of its box
        $beforePull = null !== $generated[0] ? $this->layoutEngine->edgePull($widget, true) : 0;
        // And its last child, the first lines of its ::after over its last rows
        $afterPull = null !== $generated[1] ? $this->layoutEngine->edgePull($widget, false) : 0;
        // Its first or last row drawn over its top or bottom border, pulled there by a negative margin of its first or last
        // child, see ChromeApplier::apply(): only when that child draws that row, not a ::before, an ::after or a clearance,
        // nor in a box scrolled, the row being of what is scrolled to, in columns, a grid, whose rows are of its cells, or a
        // table, whose captions are drawn out of its box; the top one not in a row, each item of which draws its first row,
        // the bottom one in a row from its items, like the tabs of Bootstrap over the line under them
        $pullInto = 0;
        if (null === $scroll && 1 === $columnCount && null === $resolvedStyle->getGridTemplate() && TableRole::Table !== $resolvedStyle->getTableRole()) {
            if (1 === ($resolvedStyle->getBorder()->top ?? 0) && 0 === ($resolvedStyle->getPadding()->top ?? 0) && null === $generated[0] && 0 === $clearance && Direction::Horizontal !== $resolvedStyle->getDirection() && $this->layoutEngine->pullsIntoTopBorder($widget)) {
                $pullInto |= ChromeApplier::PULL_TOP;
            }
            if (0 < ($resolvedStyle->getBorder()->bottom ?? 0) && 0 === ($resolvedStyle->getPadding()->bottom ?? 0) && null === $generated[1] && 0 < (Direction::Horizontal === $resolvedStyle->getDirection() ? $this->layoutEngine->pullInto($widget, true) : $this->layoutEngine->edgePull($widget, false))) {
                $pullInto |= ChromeApplier::PULL_BOTTOM;
            }
        }
        // Their images, like content: url(icon.png), drawn in their lines
        $images = new InlineImages([], ContainerState::of($widget)->getGeneratedImages());
        $scheme = $this->mediaContext->colorScheme;
        // Every move of its laid out content in its box goes through here: what is tracked in it moves, and so do the
        // floats going on past it, placed once its content is where it is drawn, see ContainerWidget::setEscapedFloats()
        $shiftContent = function (int $columns, int $rows) use ($children, &$origin): void {
            $this->positionTracker->shiftContentPositions($children, $columns, $rows);
            $origin[0] += $columns;
            $origin[1] += $rows;
        };
        // The ::before first, the ::after last: around a part of the content only, like the first and the last run of
        // columns between children spanning them
        // The floats of the block around beside it, in its content box, see LayoutEngine::layoutFloats()
        $floatsAround = ContainerState::of($widget)->getFloatExclusion();
        // What its first child pulled over them lets show, the background it inherits, as over a sibling
        $seeThrough = $beforePull > 0 ? $this->getInheritedBackground($children[array_key_first($children)]) : null;
        // Rows drawn over others but for their spaces, their lines joined, like a sibling pulled up, see
        // LayoutEngine::layoutVertical()
        $drawOver = static function (array $under, array $over, int $width, ?Color $seeThrough): array {
            foreach ($over as $i => $line) {
                $over[$i] = LayoutEngine::joinGlyphs($line, $under[$i]);
            }

            return Compositor::composite(new Layer($under, 0, 0, false, $width, \count($under)), new Layer($over, 0, 0, true, null, null, null !== $seeThrough ? substr($seeThrough->toBackgroundCode(), 2, -1) : null));
        };
        // The ::after after its content, its first lines over the last rows of it by the negative margin of its last child
        $afterSeeThrough = $afterPull > 0 ? $this->getInheritedBackground($children[array_key_last($children)]) : null;
        // From the row it starts at, $afterPull rows above the end of the content at most, see $decorate; shorter than its
        // pull, the rows of the content under it after it as they were, like a sibling
        $withAfter = static function (LineBufferInterface $lines, array $after, int $width) use ($afterPull, $afterSeeThrough, $drawOver): LineBufferInterface {
            if (0 >= $pulled = min($afterPull, \count($lines))) {
                return new ConcatenatedLineBuffer([$lines, new ArrayLineBuffer($after)]);
            }
            $from = \count($lines) - $pulled;
            $over = min($pulled, \count($after));
            $under = ArrayLineBuffer::sliceOf($lines, $from, $over)->toArray();

            return new ConcatenatedLineBuffer([ArrayLineBuffer::sliceOf($lines, 0, $from), new ArrayLineBuffer([...$drawOver($under, \array_slice($after, 0, $over), $width, $afterSeeThrough), ...ArrayLineBuffer::sliceOf($lines, $from + $over, $pulled - $over)->toArray(), ...\array_slice($after, $over)])]);
        };
        $decorate = static function (LineBufferInterface $lines, int $width, bool $first = true, bool $last = true) use ($generated, &$beforeBesideFloats, $beforePull, $afterPull, $seeThrough, $drawOver, $withAfter, $floatsAround, $images, $scheme, $shiftContent, $clearance): LineBufferInterface {
            // Beside floats from a row, its lines in the room beside them, past those on its left, like those of text
            $wrap = static function (array $content, ?int $row = null) use ($floatsAround, $images, $scheme, $width): array {
                if (null === $row || !$floatsAround) {
                    return $images->draw(array_map(static fn (string $line) => $content[1]->apply($line), TextWrapper::wrapTextWithAnsi($content[0], max(1, $width))), $scheme);
                }
                $insets = static fn (int $line): array => LayoutEngine::floatInsetsAt($floatsAround, $row + $line);
                $text = TextWrapper::wrapTextWithAnsiWidths($content[0], static fn (int $line): int => $width - array_sum($insets($line)));

                return array_map(static fn (string $line, int $i): string => str_repeat(' ', $insets($i)[0]).$line, $images->draw(array_map(static fn (string $line) => $content[1]->apply($line), $text), $scheme), array_keys($text));
            };
            if (!$first) {
                // From the row it is drawn at, over the last rows of the content by the pull of the last child
                return null !== $generated[1] && $last ? $withAfter($lines, $wrap($generated[1], \count($lines) - min($afterPull, \count($lines))), $width) : $lines;
            }
            if (null !== $generated[0]) {
                // Beside floats, past those on its left
                $before = null !== $beforeBesideFloats ? array_map(static fn (string $line, int $left): string => str_repeat(' ', $left).$line, $images->draw($beforeBesideFloats[0], $scheme), $beforeBesideFloats[1]) : $wrap($generated[0]);
                // Its first child pulled up over its last lines, drawn over them but for its spaces, their lines joined,
                // like a sibling, see LayoutEngine::layoutVertical()
                $overlap = min($beforePull, \count($before), \count($lines));
                if ($overlap > 0) {
                    array_splice($before, -$overlap, $overlap, $drawOver(\array_slice($before, -$overlap), ArrayLineBuffer::sliceOf($lines, 0, $overlap)->toArray(), $width, $seeThrough));
                    $lines = ArrayLineBuffer::sliceOf($lines, $overlap, \count($lines) - $overlap);
                }
                $lines = new ConcatenatedLineBuffer([new ArrayLineBuffer($before), $lines]);
                $shiftContent(0, \count($before) - $overlap);
            }
            // The rows of the ::before that clears, above its text
            if ($clearance > 0) {
                $lines = new ConcatenatedLineBuffer([new ArrayLineBuffer(array_fill(0, $clearance, '')), $lines]);
                $shiftContent(0, $clearance);
            }
            if (null !== $generated[1] && $last) {
                // The floats around measured from below the rows of the ::before that clears
                $lines = $withAfter($lines, $wrap($generated[1], \count($lines) - $clearance - min($afterPull, \count($lines))), $width);
            }

            return $lines;
        };
        $visible = $innerColumns - ($scroll?->always || $scroll?->gutter ? $bar : 0);
        $layoutWidth = $columnCount > 1 ? $columnWidth : max($visible, $contentWidth);
        $barShifts = 1 === $columnCount && null !== $viewport && !$scroll->always && !$scroll->gutter && $bar;
        // Overflowing at its last layout drawn, like a long list scrolled: the layout without the scrollbar measured
        // untracked, the children keeping the renders drawn with it, and the one with it drawn, both as before (not
        // with a ::before or a clearance, which move what is drawn, see $decorate)
        if ($barShifts && $this->positionTracker->isActive() && ($this->barLayouts[$widget] ?? null) === $innerColumns && [null, null] === $generated && 0 === $clearance) {
            $saved = $this->positionTracker->suppressStack();
            try {
                $overflows = \count($layoutSideways($layoutWidth, $visible)) > $rowsShown;
            } finally {
                $this->positionTracker->restoreStack($saved);
            }
            if ($overflows) {
                $layoutWidth = max($innerColumns - $bar, $contentWidth);
            }
            $childLines = $layoutSideways($layoutWidth, $overflows ? $innerColumns - $bar : $visible);
        } else {
            $childLines = $columnCount > 1 ? ($spans ? new ArrayLineBuffer([]) : $decorate($layout($layoutWidth), $layoutWidth)) : $decorate($layoutSideways($layoutWidth, $visible), $layoutWidth);
            $overflows = $barShifts && \count($childLines) > $rowsShown;
            if ($overflows) {
                $layoutWidth = max($innerColumns - $bar, $contentWidth);
                $childLines = $decorate($layoutSideways($layoutWidth, $innerColumns - $bar), $layoutWidth);
            }
        }
        if ($barShifts && $this->positionTracker->isActive()) {
            if ($overflows) {
                $this->barLayouts[$widget] = $innerColumns;
            } else {
                unset($this->barLayouts[$widget]);
            }
        }
        // Block layout, like CSS: with nothing between them, the margins of a parent and of its first and last children collapse
        // Not its top margin past a ::before that clears, which separates them: its bottom one still
        // Not those of a formatting context of its own, like overflow: hidden or a float
        if (null === $resolvedStyle->getDirection() && \count($childLines) > 0 && [null, null] === $generated && !$this->establishesFormattingContext($widget)) {
            // Not outside <html> and <body>, whose background is that of the whole screen, like CSS paints the canvas with it
            $childLines = $this->collapseParentMargins($resolvedStyle, $children, $childLines, $innerColumns, $dropped, $clearance > 0, $outside, null === $widget->getParent()?->getParent()?->getParent());
            if ($dropped > 0) {
                $shiftContent(0, -$dropped);
            }
            if ([0, 0] !== $outside) {
                // Its content box under the margins of its child now outside it, see offsetRelative()
                $contentRow += $outside[0];
                $margin = $resolvedStyle->getMargin() ?? new Padding(0, 0, 0, 0);
                $resolvedStyle = $resolvedStyle->withMargin(new Padding($margin->top + $outside[0], $margin->right, $margin->bottom + $outside[1], $margin->left));
            }
        }
        if ($columnCount > 1 && null !== $multiColumn) {
            if ($spans) {
                $childLines = $this->flowAroundSpans($children, $layout, $multiColumn, $columnCount, $columnWidth, $innerColumns, $contentRow, $decorate, [null, null] !== $generated);
            } else {
                $childLines = $this->flowIntoColumns($children, $childLines, $multiColumn, $columnCount, $columnWidth, $contentRow, $multiColumn->balance ? null : $resolvedStyle->getMaxRows());
            }
        }
        if ($hasVerticalAlign && \count($childLines) < $innerRows && ($context->shouldFillRows() || $widget->isVerticallyExpanded())) {
            if (0 < $verticalOffset = $this->layoutEngine->computeVerticalAlignOffset(\count($childLines), $innerRows, $verticalAlign)) {
                $childLines = new ConcatenatedLineBuffer([new ArrayLineBuffer(array_fill(0, $verticalOffset, '')), $childLines]);
                $shiftContent(0, $verticalOffset);
            }
        }
        // A table cell stretched to the height of its row places its content in it, like CSS vertical-align
        $cellRows = TableRole::Cell === $resolvedStyle->getTableRole() ? $resolvedStyle->getMinRows() : null;
        if ($hasVerticalAlign && null !== $cellRows && \count($childLines) < $cellRows && !$context->shouldFillRows() && !$widget->isVerticallyExpanded() && 0 < $verticalOffset = $this->layoutEngine->computeVerticalAlignOffset(\count($childLines), $cellRows, $verticalAlign)) {
            $childLines = new ConcatenatedLineBuffer([new ArrayLineBuffer(array_fill(0, $verticalOffset, '')), $childLines]);
            $shiftContent(0, $verticalOffset);
        }
        $shouldPad = $widget->isVerticallyExpanded() && !$context->shouldFillRows() || $context->shouldFillRows() && $hasVerticalAlign;
        if ($shouldPad && \count($childLines) < $innerRows) {
            $childLines = new ConcatenatedLineBuffer([$childLines, new ArrayLineBuffer(array_fill(0, $innerRows - \count($childLines), ''))]);
        }
        // A wrapping row places each of its lines, see LayoutEngine::layoutWrapped()
        $wrapsRow = Direction::Horizontal === $resolvedStyle->getDirection() && $resolvedStyle->getWrap() && null === $resolvedStyle->getJustify() && null === $resolvedStyle->getGridTemplate();
        if ($hasAlign && !$wrapsRow && Direction::Vertical !== $resolvedStyle->getDirection() && 0 < $alignOffset = $this->layoutEngine->computeAlignOffset($childLines, $innerColumns, $align)) {
            $childLines = $this->layoutEngine->shiftLines($childLines, $alignOffset);
            $shiftContent($alignOffset, 0);
        }
        // position: relative with offsets: the child is drawn moved, its room in the flow kept empty
        // Plain children are not relative: none moved, see plainChildren()
        if ($this->positionTracker->isActive() && !$plain) {
            $childLines = $this->offsetRelative($children, $childLines, $innerColumns, $contentRow, $contentCol);
        }
        // Whether it clips what overflows it down, asked again for the pointer below
        if ($clipsDown = null === $scroll && $this->positionTracker->isActive() && $this->clipsOverflow($widget, true)) {
            $height = \count($childLines);
            $pending = $children;
            while ($pending) {
                $child = array_pop($pending);
                if (PositionMode::Fixed === $this->resolveComputedStyle($child)->getPosition()?->mode) {
                    continue;
                }
                if (null !== $rect = $this->positionTracker->getWidgetRect($child)) {
                    $height = max($height, $rect->row + $rect->rows - $contentRow);
                }
                if ($child instanceof ParentInterface && !$this->clipsOverflow($child, true) && null === $this->resolveComputedStyle($child)->getScroll()) {
                    array_push($pending, ...$child->all());
                }
            }
            $limit = $resolvedStyle->getMaxRows();
            $client = null === $limit ? $height : min($height, $this->chromeApplier->computeInnerDimensions($columns, (int) $limit, $resolvedStyle)[1]);
            ContainerState::of($widget)->setScrollMetrics(0, $height, $client);
            ContainerState::of($widget)->setHorizontalScrollMetrics(0, max($innerColumns, $childLines->getMaxVisibleWidth()), $innerColumns);
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
        if (null !== $scroll && ([] !== ContainerState::of($widget)->getScrollButtons() || null !== ContainerState::of($widget)->getScrollMarkers()[0])) {
            $childLines = $this->addScrollButtons($widget, $children, $childLines, $innerColumns, $resolvedStyle);
        }
        // What cannot shrink to the room, like the tracks and gaps of a grid of more columns than the box has: cut at
        // its edge, which a line of the terminal cannot go past, the overflow of CSS clipped
        if ($childLines->getMaxVisibleWidth() > $innerColumns) {
            $childLines = new ArrayLineBuffer(array_map(static fn (string $line): string => AnsiUtils::visibleWidth($line) > $innerColumns && !AnsiUtils::containsImage($line) ? AnsiUtils::sliceToWidth($line, 0, $innerColumns) : $line, $childLines->toArray()));
        }
        // The captions of a table drawn here, its borders separate or without rows, are outside its box, like CSS: above
        // and below its border and its background, like the caption under the image of a thumbnail of Wikipedia
        $captions = $bottomLines = [];
        $boxColumns = $columns;
        if (TableRole::Table === $resolvedStyle->getTableRole()) {
            $margin = $resolvedStyle->withResolvedBox($columns)->getMargin();
            [$top, $topLines, $bottom, $bottomLines, $captions] = $this->captionRows($children, $innerColumns, $innerRows, \count($childLines), $boxColumns = max(1, $columns - ($margin->left ?? 0) - ($margin->right ?? 0)));
            $this->chromeApplier->setOutsideRows($widget, $top, $topLines, $bottom, $bottomLines);
        }
        // The floats going on past it where its content is in its box, its first row and column 0
        if ($escaped) {
            [$right, $down] = $origin;
            // Drawn with its effects, like its filter, as the rest of its content
            ContainerState::of($widget)->setEscapedFloats(array_map(fn (array $float): array => [$float[0], $float[1] + $down, $float[2], $float[3], $float[4], $float[5] + ('left' === $float[0] ? $right : -$right), $float[6], $this->paintEffects($resolvedStyle, $float[7]), $float[8] + $down, $float[9]], $escaped));
        }
        $lines = $this->chromeApplier->apply($childLines, $columns, $resolvedStyle, $widget, $pullInto);
        // Its first row drawn over its top border: what it holds a row higher, where it is drawn
        if (0 !== ($pullInto & ChromeApplier::PULL_TOP) && 0 !== ($this->chromeApplier->pulledInto() & ChromeApplier::PULL_TOP) && $this->positionTracker->isActive()) {
            $this->positionTracker->shiftContentPositions($children, 0, -1);
            --$contentRow;
        }
        // A box of overflow: hidden or clip cuts what overflows it: what it holds keeps its whole box, like a native control
        // drawn at its size, what is past its padding box taking no pointer
        if (null === $scroll && $this->positionTracker->isActive() && (($down = $clipsDown) || $this->clipsSideways($widget))) {
            [$originRow, $originCol] = $this->positionTracker->currentOffset();
            $box = $resolvedStyle->withResolvedBox($columns);
            [$margin, $border] = [$box->getMargin(), $box->getBorder()];
            // Across only, like overflow-x: clip with overflow-y: visible
            $clip = [
                $down ? $originRow + max(0, $margin->top ?? 0) + ($border->top ?? 0) : \PHP_INT_MIN,
                $originCol + max(0, $margin->left ?? 0) + ($border->left ?? 0),
                $down ? $originRow + \count($lines) - max(0, $margin->bottom ?? 0) - ($border->bottom ?? 0) : \PHP_INT_MAX,
                $originCol + $columns - max(0, $margin->right ?? 0) - ($border->right ?? 0),
            ];
            $this->positionTracker->setContentClip($widget, $clip);
            $this->clipChildrenPointer($widget, $children, $widget);
        }
        // What its children are now that they are laid out, their renders kept: not looked at again for that next time
        if ($plain) {
            $this->plainChildren($widget, $changedChildren, false);
        }
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
     * Draws a legend over the top border of its container, inside its padding (a column at least after its corner),
     * placed like a block, as wide as its content within the border: by its auto margins, its justify-self, or at the
     * start of the direction of its container, like browsers; its margins over the border, which they let show.
     */
    private function drawLegend(AbstractWidget $legend, LineBufferInterface $lines, int $columns, Style $style): LineBufferInterface
    {
        $border = $style->getBorder();
        $margin = $style->getMargin();
        $padding = $style->getPadding();
        $row = $margin->top ?? 0;
        $start = ($margin->left ?? 0) + ($border->left ?? 0) + max(1, $padding->left ?? 0);
        $room = $columns - $start - ($border->right ?? 0) - ($margin->right ?? 0) - max(1, $padding->right ?? 0);
        $all = $lines->toArray();
        if ($room < 1 || !isset($all[$row])) {
            return $lines;
        }
        $width = max(1, min($room, $this->measureIntrinsicWidth($legend, $room, 1)));
        $legendStyle = $this->resolveComputedStyle($legend);
        $legendMargin = $legendStyle->withResolvedBox($room)->getMargin();
        $free = $room - $width;
        $rtl = $style->isRightToLeft();
        $autoMargin = $legendStyle->getAutoMargin();
        $column = $start + match (true) {
            null !== $autoMargin => match ($autoMargin) {
                AutoMargin::Left => $free,
                AutoMargin::Both => intdiv($free, 2),
                AutoMargin::Right => 0,
            },
            'center' === $legendStyle->getJustifySelf() => intdiv($free, 2),
            'end' === $legendStyle->getJustifySelf() => $rtl ? 0 : $free,
            'start' === $legendStyle->getJustifySelf() => $rtl ? $free : 0,
            'right' === $legendStyle->getJustifySelf() => $free,
            'left' === $legendStyle->getJustifySelf() => 0,
            default => $rtl ? $free : 0,
        };
        $tracked = $this->positionTracker->isActive();
        [$baseRow, $baseCol] = $tracked ? $this->positionTracker->currentOffset() : [0, 0];
        $tracked && $this->positionTracker->push($baseRow + $row, $baseCol + $column);
        $text = $this->renderLines($legend, new RenderContext($width, 1, null, $this->fontRegistry))->toArray()[0] ?? '';
        $tracked && $this->positionTracker->pop();
        $this->positionTracker->setWidgetRect($legend, new WidgetRect($baseRow + $row, $baseCol + $column, $width, 1));
        // Its border box over the border, not its margins
        $left = min($width, max(0, $legendMargin->left ?? 0));
        $box = max(0, $width - $left - max(0, $legendMargin->right ?? 0));
        $line = $all[$row];
        $all[$row] = AnsiUtils::sliceToWidth($line, 0, $column + $left).AnsiUtils::sliceToWidth($text, $left, $box).AnsiUtils::sliceToWidth($line, $column + $left + $box, max(0, AnsiUtils::visibleWidth($line) - $column - $left - $box));

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
     * @param bool             $widthOnly Whether only its width is wanted: one blank line as wide as it, its cells not drawn
     */
    private function renderCollapsedTable(ContainerWidget $table, array $children, RenderContext $context, Style $style, bool $widthOnly = false): ?LineBufferInterface
    {
        $chrome = $style->withBorder(new Border(0, 0, 0, 0));
        [$innerColumns, $innerRows] = $this->chromeApplier->computeInnerDimensions($context->getColumns(), $context->getRows(), $chrome);
        // The rows, through their groups, and the captions above and below
        $rows = [];
        $captions = [[], []];
        foreach ($this->layoutEngine->tableOrder($children) as $child) {
            $role = $this->resolveComputedStyle($child)->getTableRole();
            if (TableRole::Row === $role) {
                $rows[] = $child;
            } elseif (TableRole::RowGroup === $role && $child instanceof ParentInterface) {
                foreach ($child->all() as $row) {
                    if (TableRole::Row === $this->resolveComputedStyle($row)->getTableRole() && !$this->resolveComputedStyle($row)->getHidden()) {
                        $rows[] = $row;
                    }
                }
            } elseif (TableRole::Column !== $role) {
                $captions[$this->resolveComputedStyle($child)->getCaptionBottom() ? 1 : 0][] = $child;
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
        // The group of a row: its parent, or the table for one without a box, like tbody { display: contents }
        $groupOf = fn (?AbstractWidget $row): ?AbstractWidget => null === ($parent = $row?->getParent()) || !$this->resolveComputedStyle($parent)->getContents() ? $parent : $table;
        foreach ($rows as $r => $row) {
            $rowStyle = $this->declaredStyle($row);
            $rowSides[$r] = $sidesOf($rowStyle->getBorder());
            $group = $groupOf($row);
            $groupStyle = $group !== $table && null !== $group ? $this->declaredStyle($group) : null;
            // A group's top is of its first row, its bottom of its last
            $groupSides[$r] = [$sidesOf($groupStyle?->getBorder()), $group !== $groupOf($rows[$r - 1] ?? null), $group !== $groupOf($rows[$r + 1] ?? null)];
            foreach ([$rowStyle, $groupStyle] as $lineStyle) {
                if (null !== $lineStyle && \in_array(true, $sidesOf($lineStyle->getBorder()), true)) {
                    $firstLineStyle ??= $lineStyle;
                }
            }
        }
        // Like HTML, a cell spans no rows past the end of its row group: the index past the last row of each one's
        $groupEnds = [];
        for ($r = \count($rows) - 1; $r >= 0; --$r) {
            $groupEnds[$r] = $groupSides[$r][2] ? $r + 1 : $groupEnds[$r + 1];
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
                $rowSpan = max(1, min($cellStyle->getRowSpan() ?? 1, $groupEnds[$r] - $r));
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
        $resolvedRowSides = [];
        foreach ($rows as $row => $_) {
            $resolvedRowSides[] = [
                $rowSides[$row][0] || $groupSides[$row][0][0] && $groupSides[$row][1],
                $rowSides[$row][1] || $groupSides[$row][0][1],
                $rowSides[$row][2] || $groupSides[$row][0][2] && $groupSides[$row][2],
                $rowSides[$row][3] || $groupSides[$row][0][3],
            ];
        }
        $cellBoxes = [];
        foreach ($cells as [, $row, $column, $rowSpan, $columnSpan]) {
            $cellBoxes[] = [$row, $column, $rowSpan, $columnSpan];
        }
        $grid = new \Symfony\Component\HtmlRenderer\Layout\CollapsedBorderGrid($owners, $cellBoxes, $cellSides, $resolvedRowSides, $tableSides, $count);
        $vertical = $grid->vertical(...);
        $horizontal = $grid->horizontal(...);
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
        // The columns of a <col> or a cell of a set width, which keep it while the others take the room left, like CSS
        $set = [];
        if ($fixed) {
            $firstRow = array_values(array_map(static fn (array $c) => [$c[0], $c[2], $c[4]], array_filter($cells, static fn (array $c) => 0 === $c[1])));
            $widths = $this->layoutEngine->fixedTableWidths($this->layoutEngine->columnElementWidths($table, $innerColumns), $firstRow, $count, $innerColumns, max($count, $innerColumns - $frame - ($count - 1)), 1);
        } else {
            // The least widths the <col> elements give their columns, like <col style="width: 10ch">
            foreach (\array_slice($this->layoutEngine->columnElementWidths($table, $innerColumns), 0, $count) as $column => $least) {
                $widths[$column] = max(1, $least);
                if ($least > 0) {
                    $set[$column] = true;
                }
            }
            foreach ($cells as [$cell, , $column, , $span]) {
                if (1 === $span && (null !== ($cellStyle = $this->declaredStyle($cell))->getMaxColumns() || null !== $cellStyle->getWidthPercent())) {
                    $set[$column] = true;
                }
            }
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
            // Then the cells spanning columns: their longest words too, over the columns and the lines between them,
            // when they fit with those of the other cells, which are kept otherwise
            $spanned = $floors;
            foreach ($fixed || array_sum($floors) > $budget ? [] : $cells as $i => [$cell, , $column, , $span]) {
                if ($span > 1) {
                    $spanned = LayoutEngine::widenFloors($spanned, $widths, $column, $span, min($measured[$i], $this->measureMinContentWidth($cell, $innerColumns, $innerRows)) - ($span - 1));
                }
            }
            $floors = array_sum($spanned) <= $budget ? $spanned : $floors;
            $this->positionTracker->restoreStack($saved);
            // Past their longest words, the columns are cut as long as those words are, not as wide as the columns are
            $widths = array_sum($floors) <= $budget ? LayoutEngine::shrinkAboveFloors($widths, $floors, $budget) : array_values(LayoutEngine::shrinkToFit($floors, $budget));
        } elseif (!$fixed && (null !== $style->getMaxColumns() || null !== $style->getWidthPercent() || null !== $style->getMinWidthPercent()) && ($extra = min($budget, null === $style->getMaxColumns() && null === $style->getWidthPercent() ? (int) floor(($innerColumns - $frame) * $style->getMinWidthPercent() / 100) - ($count - 1) : $budget) - array_sum($widths)) > 0) {
            // A table of a set width, like width: 100%: the columns share the room left, as they are wide; those of no set
            // width when there are some
            $growing = array_diff_key($widths, \count($set) < $count ? $set : []);
            $sum = array_sum($growing);
            $lastGrowing = array_key_last($growing);
            $given = 0;
            foreach ($growing as $c => $width) {
                $share = $c === $lastGrowing ? $extra - $given : intdiv($extra * $width, $sum);
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
        $junctions = \Symfony\Bridge\TuiHtml\Internal\BorderJunctions::fromCorner($corner);
        $h = $junctions['0011'];
        $v = $junctions['1100'];
        $color = $tableBorder->color ?? $firstCellStyle?->getBorder()->color;
        $paint = static fn (string $text): string => null === $color || ' ' === $text ? $text : (new Style(null, null, null, $color))->apply($text);
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
            $rendered[$i] = [$this->renderLines($cell, new RenderContext($width, $innerRows, null, $this->fontRegistry))->toArray(), $width];
            if (1 === $rowSpan) {
                $heights[$r] = max($heights[$r], \count($rendered[$i][0]));
            }
        }
        $this->positionTracker->restoreStack($saved);
        // The cells spanning fewer rows first: what they add is there for those spanning more
        $bySpan = $cells;
        uasort($bySpan, static fn (array $a, array $b) => $a[3] <=> $b[3]);
        foreach ($bySpan as $i => [, $r, , $rowSpan]) {
            $room = array_sum(\array_slice($heights, $r, $rowSpan)) + $rulesIn($r + 1, $rowSpan);
            if ($rowSpan > 1 && \count($rendered[$i][0]) > $room) {
                $heights[$r + $rowSpan - 1] += \count($rendered[$i][0]) - $room;
            }
        }
        // Where the content of each cell starts in the rows it spans, like CSS vertical-align
        $offsets = [];
        foreach ($cells as $i => [$cell, $r, , $rowSpan]) {
            $room = array_sum(\array_slice($heights, $r, $rowSpan)) + $rulesIn($r + 1, $rowSpan);
            $offsets[$i] = $this->layoutEngine->computeVerticalAlignOffset(\count($rendered[$i][0]), $room, $this->resolveComputedStyle($cell)->getVerticalAlign() ?? VerticalAlign::Top);
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
                $lines = $this->renderLines($caption, new RenderContext($total, $innerRows, null, $this->fontRegistry));
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
                if (!$through && (0 === $c ? $left : $count !== $c || $right)) {
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
            $pieces = [$left ? $vertical($r, 0) ? $paint($v) : ' ' : ''];
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
            $pieces[] = $right ? $vertical($r, $count) ? $paint($v) : ' ' : '';
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
                $this->renderLines($cell, new RenderContext($rendered[$i][1], $innerRows, null, $this->fontRegistry));
                $this->positionTracker->pop();
            }
            $this->positionTracker->setWidgetRect($cell, new WidgetRect($baseRow + $top, $baseCol + $colX[$column], $rendered[$i][1], $bottom - $top));
        }
        // A row group spans its rows, like the <tbody> HTML adds
        $groups = [];
        foreach ($rows as $r => $row) {
            $group = $groupOf($row);
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
     * TextMarkers::scaled()), then the room it takes, that many rows tall.
     *
     * @return list<string>
     */
    private function renderScaled(TextWidget $widget, RenderContext $innerContext, int $scale): array
    {
        $out = [];
        foreach ($widget->render(ContextBridge::forWidget($widget, $innerContext->withColumns(max(1, intdiv($innerContext->getColumns(), $scale))), 'render')) as $line) {
            $width = AnsiUtils::visibleWidth($line);
            // Its room, with the marks of its links over the cells they are drawn in, so that a click on them follows them
            $room = self::scaledRoom($line, $width, $scale);
            $out[] = TextMarkers::scaled($line, $scale).$room;
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
        $lines = $widget->render(ContextBridge::forWidget($widget, $innerContext->withColumns($inline), 'render'));
        // Wide characters, like those of Japanese, take two columns: a line of them is as long in characters as the column
        $text = AnsiUtils::stripAnsiCodes(implode('', $lines));
        if ('' !== trim($text) && preg_match('/^(?:\s|[\x{1100}-\x{115F}\x{2E80}-\x{A4CF}\x{AC00}-\x{D7A3}\x{F900}-\x{FAFF}\x{FE30}-\x{FE4F}\x{FF00}-\x{FF60}\x{FFE0}-\x{FFE6}])+$/u', $text)) {
            $lines = $widget->render(ContextBridge::forWidget($widget, $innerContext->withColumns($inline * 2), 'render'));
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
            $anonymous = $anonymous || method_exists($provider, 'isAnonymous') && $provider->isAnonymous($widget);
        }

        // Not the anonymous row of a table around cells, a block
        return $widget instanceof TextWidget || $anonymous && null === $this->resolveComputedStyle($widget)->getTableRole();
    }

    /**
     * What the answers about the floats of a widget depend on besides it, its revision telling what changed in it: the
     * width of the frame, the rules and the styles forgotten; kept across frames. Not while a style is changed by the
     * layout, like a width capped for a box scrolled sideways, nor when the answer read a style resolved again each
     * frame, like that of a cell: for this frame only then.
     */
    private function floatsKey(bool $volatile = false): string
    {
        if ($volatile || \count($this->sidewaysCaps) > 0 || \count($this->unboundedRows) > 0) {
            return 'e'.$this->styleEpoch;
        }

        return $this->styleInputs.'|'.$this->styleResets;
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
        $parent = spl_object_id($widget->getParent() ?? $widget);
        // An answer cut short by the depth holds for a call as deep or deeper, which would be cut as short
        if (null !== ($known = $this->floatsOut[$widget] ?? null) && $known[0] === $revision && $known[1] === $this->floatsKey($known[5]) && $known[4] === $parent && $depth >= $known[3]) {
            $this->floatsVolatile = $this->floatsVolatile || $known[5];
            if ($known[3] >= 0) {
                $cut = true;
            }

            return $known[2];
        }
        $lets = false;
        $deep = false;
        $volatile = $this->floatsVolatile;
        $this->floatsVolatile = false;
        if (!$this->containsFloats($widget)) {
            foreach ($widget->all() as $child) {
                $style = $this->resolveComputedStyle($child);
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
        $read = $this->endFloatsAnswer($volatile);
        $this->floatsOut[$widget] = [$revision, $this->floatsKey($read), $lets, $deep ? $depth : -1, $parent, $read];

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
        if (!$widget instanceof ContainerWidget || !($parent = $widget->getParent()) instanceof ContainerWidget || ContainerState::of($parent)->isInlineFlow()) {
            return false;
        }
        // Too deep to tell: no, not kept, nor what it is part of; a leaf is no container, known at any depth
        if ($depth > 8) {
            $cut = true;

            return false;
        }
        $revision = $widget->getRenderRevision();
        // As it is and as its parent is, which a move or a change of the parent alone changes
        if (null !== ($known = $this->floatsEscape[$widget] ?? null) && $known[0] === $revision && $known[1] === $this->floatsKey($known[5]) && $known[3] === [spl_object_id($parent), $parent->getRenderRevision()] && $depth >= $known[4]) {
            $this->floatsVolatile = $this->floatsVolatile || $known[5];
            if ($known[4] >= 0) {
                $cut = true;
            }

            return $known[2];
        }
        $escapes = false;
        $deep = false;
        $record = null;
        $many = false;
        $volatile = $this->floatsVolatile;
        $this->floatsVolatile = false;
        $around = $this->resolveComputedStyle($parent);
        if (!$this->holdsMarkedItems($parent) && null === $around->getDirection() && null === $around->getGridTemplate() && 0 === ($around->getGap() ?? 0) && null === $this->resolveComputedStyle($widget)->getColumns() && !$this->letsFloatsOut($widget) && !$this->containsFloats($widget, false)) {
            // The children that changed since the answer before only, the others answering as they did, as long as what
            // they answer by did not change: its items of a list, see isMarkedItem(), the depth, the styles
            $marked = $this->holdsMarkedItems($widget);
            $key = $this->floatsKey();
            // Kept for a block of many children only: few cost less looked at again than their changes noted
            $many = \count($widget->all()) > LayoutEngine::MANY_CHILDREN;
            $known = $many ? $this->escapeWitnesses[$widget] ?? null : null;
            $children = null !== $known && $known[1] === $key && $depth <= $known[2] && $known[3] === $marked ? $widget->getChildrenChangedSince($known[0]) : null;
            $witness = $known[4] ?? null;
            if (null !== $children && null !== $witness) {
                // The one whose floats went on past it: as it was, else all asked again
                $children = \in_array($witness, $children, true) ? null : [];
                $escapes = null !== $children;
            }
            $witness = null;
            foreach ($children ?? $widget->all() as $child) {
                $style = $this->resolveComputedStyle($child);
                if ($child->getParent() !== $widget || $style->getHidden() || true === $style->getPosition()?->isOutOfFlow()) {
                    continue;
                }
                if (null !== $style->getFloat() || $this->letsFloatsOut($child) || $this->escapesFloats($child, $depth + 1, $deep)) {
                    $escapes = true;
                    $witness = $child;
                    break;
                }
            }
            $record = [AbstractWidget::getChangeSerial(), $key, null !== $children ? min($depth, $known[2] ?? $depth) : $depth, $marked, $escapes ? $witness ?? $known[4] ?? null : null];
        }
        // Yes is known whatever was too deep to tell
        // Yes is known whatever was too deep to tell; no, for a call as deep or deeper only
        $partial = $deep && !$escapes;
        if ($partial) {
            $cut = true;
        }
        $read = $this->endFloatsAnswer($volatile);
        $this->floatsEscape[$widget] = [$revision, $this->floatsKey($read), $escapes, [spl_object_id($parent), $parent->getRenderRevision()], $partial ? $depth : -1, $read];
        // Known for the changes after, when not cut short nor read from styles resolved each frame
        if (null === $record || $deep || $read || !$many) {
            unset($this->escapeWitnesses[$widget]);
        } else {
            $this->escapeWitnesses[$widget] = $record;
            $widget->getChildrenChangedSince($record[0]);
        }

        return $escapes;
    }

    /**
     * Ends an answer about floats, see letsFloatsOut(): whether it read a style resolved again each frame, which the
     * answers it is part of, begun before it, $volatile telling whether they read one already, read too.
     */
    private function endFloatsAnswer(bool $volatile): bool
    {
        $read = $this->floatsVolatile;
        $this->floatsVolatile = $volatile || $read;

        return $read;
    }

    /**
     * Whether a widget is an item of a list with its marker; an item holding blocks: its first text, after the floats it
     * starts with, carries the marker.
     */
    private function isMarkedItem(AbstractWidget $item): bool
    {
        return $item instanceof RichTextWidget && null !== $item->getMarker() || $item instanceof ContainerWidget && ($text = array_find($item->all(), fn (AbstractWidget $child): bool => null === $this->resolveComputedStyle($child)->getFloat())) instanceof RichTextWidget && null !== $text->getMarker();
    }

    /**
     * The children of a block when they are all plain, else null: in the flow, not hidden, no box whose children are its
     * own (display: contents), not relative, no float, none letting floats out nor going on past it, see
     * letsFloatsOut() and escapesFloats(); what renderContainer() asks of each child, the same for all of them then.
     * Known for all once, then only for those that changed since, see AbstractWidget::getChildrenChangedSince(), given
     * in $changed, for what is done to each of them: forgotten as absolute or lifted, their floats cleared.
     *
     * @param-out list<AbstractWidget>|null $changed
     *
     * @param bool $forget Whether the changed children are forgotten as absolute or lifted, like their parent renders
     *
     * @return list<AbstractWidget>|null
     */
    private function plainChildren(ContainerWidget $widget, ?array &$changed, bool $forget = true): ?array
    {
        $changed = null;
        // Not while the styles depend on more than the widgets, like a width capped for a box scrolled sideways; nor for
        // a few children, all looked at each time costing less than keeping what they are
        if (\count($widget->all()) <= LayoutEngine::MANY_CHILDREN || null === $key = $this->getLayoutKey()) {
            return null;
        }
        $summary = $this->childSummaries[$widget] ?? null;
        // None changed but for their renders kept when the block did not, which all their changes change
        if (null !== $summary && $summary['key'] === $key && $summary['revision'] === $widget->getRenderRevision()) {
            $since = [];
        } else {
            $since = null !== $summary && $summary['key'] === $key ? $widget->getChildrenChangedSince($summary['serial']) : null;
        }
        if (null === $since) {
            $summary = ['serial' => 0, 'key' => $key, 'other' => [], 'marked' => [], 'width' => 0, 'cleared' => false, 'revisions' => [], 'revision' => -1];
            $since = $widget->all();
        }
        // Those that changed, not only had their render kept
        $since = array_filter($since, static fn (AbstractWidget $child): bool => $child->getParent() === $widget && ($summary['revisions'][spl_object_id($child)] ?? null) !== $child->getRenderRevision());
        // The items of a list first, for the children going on past it, see escapesFloats(): all of them looked at again
        // when the block holds some no more, or now does
        $marked = [] !== $summary['marked'];
        foreach ($since as $child) {
            if ($this->isMarkedItem($child)) {
                $summary['marked'][spl_object_id($child)] = true;
            } else {
                unset($summary['marked'][spl_object_id($child)]);
            }
        }
        if ($marked !== ([] !== $summary['marked']) && 0 !== $summary['serial']) {
            $summary = ['serial' => 0, 'key' => $key, 'other' => [], 'marked' => $summary['marked'], 'width' => 0, 'cleared' => false, 'revisions' => [], 'revision' => -1];
            $since = array_filter($widget->all(), static fn (AbstractWidget $child): bool => $child->getParent() === $widget);
        }
        $this->markedItems[$widget] = [$widget->getRenderRevision(), [] !== $summary['marked'], $this->floatsKey()];
        foreach ($since as $child) {
            $style = $this->resolveComputedStyle($child);
            if ($child instanceof ParentInterface && $style->getContents() || $style->getHidden() || null !== $style->getPosition() && ($style->getPosition()->isOutOfFlow() || PositionMode::Relative === $style->getPosition()->mode) || null !== $style->getFloat() || $this->letsFloatsOut($child) || $this->escapesFloats($child)) {
                $summary['other'][spl_object_id($child)] = true;
            } else {
                unset($summary['other'][spl_object_id($child)]);
                // Not absolute, nor lifted, any more
                if ($forget) {
                    unset($this->overlays[$child], $this->liftedRelatives[$child]);
                }
            }
            $summary['width'] = max($summary['width'], $style->getMaxColumns() ?? 0);
            $summary['revisions'][spl_object_id($child)] = $child->getRenderRevision();
        }
        $summary['serial'] = AbstractWidget::getChangeSerial();
        $summary['revision'] = $widget->getRenderRevision();
        // What changed since kept from now
        $widget->getChildrenChangedSince($summary['serial']);
        $this->childSummaries[$widget] = $summary;
        if ([] !== $summary['other']) {
            return null;
        }
        $changed = array_values($since);

        return array_values($widget->all());
    }

    /**
     * Whether a block holds items of a list, whose markers a float going on past one would cover, like an image floated
     * in an item: kept in it; known once for each revision of the block, not for each of its children.
     */
    private function holdsMarkedItems(ContainerWidget $widget): bool
    {
        $revision = $widget->getRenderRevision();
        if (null !== ($known = $this->markedItems[$widget] ?? null) && $known[0] === $revision && $known[2] === $this->floatsKey()) {
            return $known[1];
        }
        $key = $this->floatsKey();
        // A few children: all looked at, which costs less than noting their changes
        if (\count($widget->all()) <= LayoutEngine::MANY_CHILDREN) {
            $marked = array_any($widget->all(), $this->isMarkedItem(...));
            $this->markedItems[$widget] = [$revision, $marked, $key];

            return $marked;
        }
        // The children that changed since the answer before only: the item found then as it was, or none and none of them
        $known = $this->markedWitnesses[$widget] ?? null;
        $changed = null !== $known && $known[1] === $key ? $widget->getChildrenChangedSince($known[0]) : null;
        $item = null;
        if (null !== $changed && null === $known[2]) {
            $item = array_find($changed, fn (AbstractWidget $child): bool => $child->getParent() === $widget && $this->isMarkedItem($child));
        } elseif (null !== $changed && (!\in_array($known[2], $changed, true) || $this->isMarkedItem($known[2]))) {
            $item = $known[2];
        } else {
            $item = array_find($widget->all(), $this->isMarkedItem(...));
        }
        $this->markedWitnesses[$widget] = [AbstractWidget::getChangeSerial(), $key, $item];
        $widget->getChildrenChangedSince(AbstractWidget::getChangeSerial());
        $this->markedItems[$widget] = [$revision, null !== $item, $key];

        return null !== $item;
    }

    /**
     * Whether a widget draws nothing: a box of nothing of its own holding only what is hidden, like the line of a hidden
     * button.
     */
    private function boxDrawsNothing(AbstractWidget $widget, int $depth = 0): bool
    {
        $style = $this->resolveComputedStyle($widget);
        // The border and the background of a cell are those of its table and of its column too, resolved each frame
        if (null !== $style->getTableRole()) {
            $this->floatsVolatile = true;
        }
        if ($style->getHidden()) {
            return true;
        }
        if (!$widget instanceof ContainerWidget || $depth > 8 || null !== $style->getFloat() || null !== $style->getBackground() || null !== $style->getBorder() || null !== $style->getMinRows() || null !== ($padding = $style->getPadding()) && 0 !== $padding->top + $padding->bottom || null !== ($margin = $style->getMargin()) && (0 !== $margin->top || 0 !== $margin->bottom)) {
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
        $lines = null === $height ? $this->renderLines($widget, $context) : $this->renderStretchedLines($widget, $context, $height);
        // A height it sets, like height: 100% of a box holding an image Tui draws nothing of, or 1em: its content drawn
        // past it too
        $style = $this->resolveComputedStyle($widget);
        // Not a height in pixels or ems, rounded to rows, which the content of a browser fits but not its rows, like the
        // bar of links of 22px of cppreference
        $sets = (null !== $style->getMaxRows() || null !== $style->getMaxRowsPercent()) && !$this->hasRoundedHeight($widget);
        if ((null === $height ? !$sets : !$overflows) || $this->clipsOverflow($widget, true)) {
            return [$lines, \count($lines)];
        }
        // Measured in an earlier frame with the same content and room: it fitted, kept like its render, see
        // RenderCache; and the same rows drawn at its height, not those of a render that changed
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
                return $this->renderLines($widget, $context);
            }
            $this->unboundedRows[$widget] = $definite;
            ++$this->styleEpoch;
            try {
                // Its render at its height kept for the frames after
                return RenderCache::without($widget, fn (): LineBufferInterface => $this->renderLines($widget, $context));
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
        if (null === $past = $this->pastHeight($style, $context, $lines, $measured)) {
            $this->fitsSetHeight[$widget] = [$key, $lines];

            return [$lines, \count($lines)];
        }
        unset($this->fitsSetHeight[$widget]);
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
     * How a box drawn at its height draws its content measured past it, like CSS overflow: visible: its rows above its
     * bottom padding as its content draws them, those after over its box, without its background nor its side borders,
     * and on past it; null when its content fits.
     *
     * @return (\Closure(LineBufferInterface): LineBufferInterface)|null Given its content laid out past its height
     */
    private function pastHeight(Style $style, RenderContext $context, LineBufferInterface $lines, LineBufferInterface $measured): ?\Closure
    {
        // Past its height, only the rows something is drawn on count, not the bottom margin of its last child: its
        // background and border stay at its size then
        // Its bottom border and margin kept under the rows drawn, its left margin and border not drawn on
        $sides = $style->getBorder();
        // Its margins in percentages too, shares of the width of its containing block
        $margin = $style->withResolvedBox($context->getContainingColumns())->getMargin();
        $border = (($sides->bottom ?? 0) > 0 ? 1 : 0) + max(0, $margin->bottom ?? 0);
        $lead = (($sides->left ?? 0) > 0 ? 1 : 0) + max(0, $margin->left ?? 0);
        $blank = static function (string $line) use ($sides, $lead): bool {
            $text = rtrim(AnsiUtils::stripAnsiCodes($line));
            if (($sides->right ?? 0) > 0) {
                $text = mb_substr($text, 0, -1);
            }

            return '' === trim(mb_substr($text, $lead));
        };
        // Its content box ends above its bottom padding, which its content drawn past it goes over
        $padding = $style->withResolvedBox($context->getContainingColumns())->getPadding();
        $inside = \count($lines) - $border - max(0, $padding->bottom ?? 0);
        for ($drawn = \count($measured) - $border; $drawn > $inside && $blank($measured->getLine($drawn - 1)); --$drawn) {
        }
        if ($drawn <= $inside) {
            return null;
        }
        // Its box at its height, like CSS, its content drawn on past it: over its bottom padding and border, then under
        // them, without its background nor its side borders
        $box = max(0, \count($lines) - max(0, $margin->bottom ?? 0));
        $from = max(0, $box - (($sides->bottom ?? 0) > 0 ? 1 : 0) - max(0, $padding->bottom ?? 0));
        $start = $lead + max(0, $padding->left ?? 0);
        // As wide as a row of its box, the room it was given when it draws none, like height: 0
        $width = max(0, ($box > 0 ? AnsiUtils::visibleWidth($lines->getLine($box - 1)) : $context->getColumns()) - $start - (($sides->right ?? 0) > 0 ? 1 : 0) - max(0, $padding->right ?? 0) - max(0, $margin->right ?? 0));
        $background = $style->getBackground()?->toBackgroundCode();

        // Its rows above its bottom padding as its content laid out past its height draws them, those after over its box
        // at its height, its margin under it kept
        return static function (LineBufferInterface $all) use ($lines, $box, $from, $drawn, $start, $width, $background): LineBufferInterface {
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
    }

    /**
     * Whether a widget has paint containment, contain: paint, content or strict, which clips its content to its box.
     */
    private function containsPaint(AbstractWidget $widget): bool
    {
        foreach ($this->styleSheet->getProviders() as $provider) {
            if (method_exists($provider, 'getComputedValue') && preg_match('/\b(?:paint|content|strict)\b/i', $provider->getComputedValue($widget, 'contain') ?? '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the content of a flex column or a grid taller than its height goes on below it, like CSS: not one whose
     * items or rows are placed at its center or its end, like justify-content: center, a column-reverse or
     * align-content: end, which CSS draws above its top.
     */
    private function overflowsDownward(Style $style): bool
    {
        if (null !== $style->getGridTemplate()) {
            return !\in_array($style->getAlignContent(), ['center', 'end'], true);
        }

        return Direction::Vertical !== $style->getDirection() || !\in_array($style->getVerticalAlign(), [VerticalAlign::Center, VerticalAlign::Bottom], true);
    }

    private function holdsPositioned(AbstractWidget $widget): bool
    {
        $pending = $widget instanceof ParentInterface ? $widget->all() : [];
        while (null !== $child = array_pop($pending)) {
            if (null !== $this->resolveComputedStyle($child)->getPosition()) {
                return true;
            }
            if ($child instanceof ParentInterface) {
                array_push($pending, ...$child->all());
            }
        }

        return false;
    }

    /**
     * Whether an item of a flex column grows, like flex: 1: CSS grows it past a height it sets, which the layout keeps
     * as its most, its content cut in it.
     */
    private function growsInColumn(AbstractWidget $widget): bool
    {
        $parent = $widget->getParent();

        return null !== $parent && Direction::Vertical === $this->resolveComputedStyle($parent)->getDirection() && ($this->resolveComputedStyle($widget)->getFlex() ?? 0) > 0;
    }

    private function isLayoutItem(AbstractWidget $widget): bool
    {
        $parent = $widget->getParent();
        if (null === $parent) {
            return false;
        }
        $parentStyle = $this->resolveComputedStyle($parent);

        return null !== $parentStyle->getDirection() || null !== $parentStyle->getGridTemplate();
    }

    /**
     * The lines of a block in the flow of a height it sets, like height: 3 of a <div>: its box at its height, and the
     * content past it, like CSS overflow: visible, painted over what comes after it at the end of the frame, see
     * drawFlowOverflow(). Not a height in pixels or ems, rounded to rows, which the content of a browser fits but not
     * its rows; not a box that clips, scrolls or is positioned, which renderPositioned() draws, nor a flex row, whose
     * items overflow its rows, see LayoutEngine::getOverflowingItems(), nor <html> and <body>, whose content goes on
     * past the screen. A flex column and a grid draw their items and rows past it like a block, and so does an item of
     * a flex or grid container, an inline-block too, from the top of a flex row that cuts it.
     */
    private function overflowSetHeight(AbstractWidget $widget, RenderContext $context, Style $style, LineBufferInterface $lines, bool $stretched): LineBufferInterface
    {
        // Measured past its height, below: what it drew past it kept until then
        if (isset($this->unboundedRows[$widget])) {
            return $lines;
        }
        if (!$widget instanceof ContainerWidget || null === $style->getMaxRows() && null === $style->getMaxRowsPercent()
            || null !== $style->getPosition() || null !== $style->getScroll() || null !== $style->getTableRole() || null !== $style->getWritingMode() || Direction::Horizontal === $style->getDirection() || null !== $style->getColumns()
            || !$this->overflowsDownward($style)
            || $style->getDocumentHeight() || ContainerState::of($widget)->passesDefiniteHeight()
            || $this->hasRoundedHeight($widget, true) || $this->clipsOverflow($widget, true) || $this->containsPaint($widget)
            // Nor an item stretched past its max-height, its box padded below its content cut at it, nor one a column grows
            || $this->isLayoutItem($widget) && ($stretched || $this->growsInColumn($widget))) {
            $this->forgetSetHeightOverflow($widget);

            return $lines;
        }
        $definite = null !== $style->getMaxRowsPercent() ? $style->getMinRowsPercent() === $style->getMaxRowsPercent() && $style->getRowsPercentOffsets()[0] === $style->getRowsPercentOffsets()[1] : $style->getMinRows() === $style->getMaxRows();
        // Measured only when its content was cut at its height, as tall as it was before, not a box its content does not
        // fill, like an empty bar of a set height
        if (($this->contentRows[$widget] ?? 0) <= ($style->getMaxRows() ?? \PHP_INT_MAX)) {
            $this->forgetSetHeightOverflow($widget);

            return $lines;
        }
        // Measured past its height, untracked: what it holds is tracked where it is drawn at its height
        $saved = $this->positionTracker->suppressStack();
        $this->unboundedRows[$widget] = $definite;
        ++$this->styleEpoch;
        try {
            $measured = RenderCache::without($widget, fn (): LineBufferInterface => $this->renderLines($widget, $context));
        } finally {
            unset($this->unboundedRows[$widget]);
            ++$this->styleEpoch;
            $this->positionTracker->restoreStack($saved);
        }
        // Not with a positioned element in it, which its stacking order would draw over what follows: cut at its box
        if (null === ($past = $this->pastHeight($style, $context, $lines, $measured)) || $this->holdsPositioned($widget)) {
            $this->forgetSetHeightOverflow($widget);

            return $lines;
        }
        $all = $past($measured);
        // In the flow, its rows at its height; those past it painted at the end of the frame, from its top
        $entry = [$widget->getRenderRevision(), $all, 0, \count($lines), true, \count($lines)];
        // An item of a flex row the row cuts, rendered again at the same height after it was laid out: drawn from the
        // top of the row still, see LayoutEngine::getOverflowingItems()
        $known = $this->flowOverflow[$widget] ?? null;
        if (null !== $known && $known[0] === $entry[0] && ($known[5] ?? null) === $entry[5]) {
            [$entry[2], $entry[3]] = [$known[2], $known[3]];
        }
        $this->flowOverflow[$widget] = $entry;

        return new ArrayLineBuffer($all->slice(0, \count($lines)));
    }

    /**
     * Forgets the content a widget drew past a height it sets, not the rows an item draws past the height of its flex
     * row, which LayoutEngine::getOverflowingItems() keeps.
     */
    private function forgetSetHeightOverflow(AbstractWidget $widget): void
    {
        if (!$this->isLayoutItem($widget) || true === ($this->flowOverflow[$widget][4] ?? null)) {
            unset($this->flowOverflow[$widget]);
        }
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
     * percentage; a percentage too with $ofParent, of a parent whose height is rounded, like height: 100% in a bar of
     * 3rem, which the content of a browser fits but not its rows.
     */
    private function hasRoundedHeight(AbstractWidget $widget, bool $ofParent = false): bool
    {
        foreach ($this->styleSheet->getProviders() as $provider) {
            if (!method_exists($provider, 'getComputedValue')) {
                continue;
            }
            // The height, else the max-height of a box of a height in a keyword, like max-height: 22px; a share of its
            // containing block is not rounded, even in a calc() with lengths
            foreach (['height', 'max-height'] as $property) {
                $height = $provider->getComputedValue($widget, $property);
                // Of a custom property, like var(--bar-height) of 3rem: its value
                if (null !== $height && str_contains($height, 'var(')) {
                    $height = $this->withCustomProperties($provider, $widget, $height);
                }
                // A keyword, like auto, fit-content or inherit: the next one
                if (null !== $height && preg_match('/\d/', $height)) {
                    if ($ofParent && str_contains($height, '%') && null !== $parent = $widget->getParent()) {
                        return $this->hasRoundedHeight($parent, true);
                    }

                    return !str_contains($height, '%') && preg_match('/\d(?:px|r?em|ex|ch|cap|ic|[sdl]?v(?:h|w|min|max|b|i)|cq(?:h|w|b|i|min|max)|cm|mm|q|in|pt|pc)\b/i', $height);
                }
            }
        }

        return false;
    }

    /**
     * A computed value with the custom properties it refers to substituted, as the widget inherits them; as it is when
     * one cannot be resolved.
     */
    private function withCustomProperties(object $provider, AbstractWidget $widget, string $value): string
    {
        if (!method_exists($provider, 'getComputedValue')) {
            return $value;
        }
        $properties = [];
        // Those the values of the others refer to too, a few levels deep
        for ($depth = 0, $pending = $value; $depth < 8 && preg_match_all('/var\(\s*(--[\w-]+)/', $pending, $matches); ++$depth) {
            $pending = '';
            foreach (array_diff(array_unique($matches[1]), array_keys($properties)) as $name) {
                if (null !== $properties[$name] = $provider->getComputedValue($widget, $name)) {
                    $pending .= ' '.$properties[$name];
                } else {
                    unset($properties[$name]);
                }
            }
        }
        try {
            return Value::substitute($value, $properties);
        } catch (InvalidArgumentException) {
            return $value;
        }
    }

    /**
     * Whether the width of a widget is set as a length, like width: 625rem, not auto, a percentage or a keyword.
     */
    private function hasLengthWidth(AbstractWidget $widget): bool
    {
        foreach ($this->styleSheet->getProviders() as $provider) {
            if (method_exists($provider, 'getComputedValue') && null !== $width = $provider->getComputedValue($widget, 'width')) {
                // In cells too, like width: 300, or calc() of lengths
                return (bool) preg_match('/^\s*(?:\d*\.?\d+(?:px|r?em|r?lh|ex|ch|cap|ic|[sdl]?v(?:h|w|min|max|b|i)|cq(?:h|w|b|i|min|max)|cm|mm|q|in|pt|pc)?|calc\([^%]*\))\s*$/i', $width);
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
                $both = preg_split('/\s+/', trim($provider->getComputedValue($widget, 'overflow') ?? '')) ?: [];
                $value = $provider->getComputedValue($widget, 'overflow-x') ?? $provider->getComputedValue($widget, 'overflow-inline') ?? $both[0] ?? '';
                if (preg_match('/^\s*(?:hidden|clip)\b/i', $value)) {
                    return true;
                }
                // Visible across, but hidden down, is clipped too, like CSS; not on <html> and <body>, whose overflow is
                // that of the screen
                $down = $provider->getComputedValue($widget, 'overflow-y') ?? $provider->getComputedValue($widget, 'overflow-block') ?? $both[1] ?? $both[0] ?? '';
                if (!preg_match('/^\s*(?:auto|scroll|overlay)\b/i', $value) && preg_match('/^\s*hidden\b/i', $down) && !($widget instanceof ContainerWidget && ContainerState::of($widget)->passesDefiniteHeight())) {
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
            if (null !== $this->resolveComputedStyle($widget)->getScroll()) {
                return true;
            }
            foreach ($this->styleSheet->getProviders() as $provider) {
                if (!method_exists($provider, 'getComputedValue')) {
                    continue;
                }
                $both = preg_split('/\s+/', trim($provider->getComputedValue($widget, 'overflow') ?? '')) ?: [];
                $x = $provider->getComputedValue($widget, 'overflow-x') ?? $provider->getComputedValue($widget, 'overflow-inline') ?? $both[0] ?? '';
                $y = $provider->getComputedValue($widget, 'overflow-y') ?? $provider->getComputedValue($widget, 'overflow-block') ?? $both[1] ?? $both[0] ?? '';
                if (preg_match('/^\s*(?:hidden|clip|auto|scroll|overlay)\b/i', $y)) {
                    return true;
                }
                // Visible down, but not across, is auto, like CSS: clip keeps it visible; not on <html> and <body>, whose
                // overflow is that of the screen
                if (preg_match('/^\s*(?:hidden|auto|scroll|overlay)\b/i', $x) && !($widget instanceof ContainerWidget && ContainerState::of($widget)->passesDefiniteHeight())) {
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
            $position = $this->resolveComputedStyle($child)->getPosition();
            if (PositionMode::Relative === $position?->mode && (0 !== $position->zIndex || $followsAbsolute($child))) {
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
            if (0 === $dx && 0 === $dy && isset($lifted[$child]) && $position->zIndex <= 0) {
                unset($lifted[$child]);
                if ($this->isInsideEffect($child, false, false)) {
                    unset($this->liftedRelatives[$child]);
                } else {
                    $margin = $this->resolveComputedStyle($child)->getMargin();
                    $this->liftedRelatives[$child] = [0, null, ($this->boxRect($child) ?? $rect)->columns, max(0, $rect->rows - max(0, $margin->top ?? 0) - max(0, $margin->bottom ?? 0))];
                }
                continue;
            }
            // Moved, or drawn over the absolute elements of a lower z-index
            if (0 !== $dx || 0 !== $dy || isset($lifted[$child])) {
                // Its box, inside its margins: what is beside it, above and below stays
                $box = $this->boxRect($child) ?? $rect;
                $margin = $this->resolveComputedStyle($child)->getMargin();
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
                // Moved sideways past them, like left: -1em into the padding of its container: drawn there too, unless its
                // container clips
                $fromCol = $box->col - $contentCol + $dx;
                if (0 !== $dx && ($fromCol < 0 || $fromCol + $box->columns > $columns)) {
                    if ($this->clipsOverflow($child->getParent()) || $this->clipsSideways($child->getParent())) {
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
        usort($moves, fn (array $a, array $b) => [StyleBridge::resolve($this, $a[0])?->getPosition()->zIndex ?? 0, $order[spl_object_id($a[0])]] <=> [StyleBridge::resolve($this, $b[0])?->getPosition()->zIndex ?? 0, $order[spl_object_id($b[0])]]);
        $out = $lines->toArray();
        $width = static fn (string $line) => max($columns, AnsiUtils::visibleWidth($line));
        $splice = static function (string $line, int $col, string $part, int $partWidth) use ($width): string {
            $total = $width($line);

            return AnsiUtils::sliceToWidth($line, 0, $col)."\x1b[0m".$part."\x1b[0m".AnsiUtils::sliceToWidth($line, $col + $partWidth, max(0, $total - $col - $partWidth));
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
            $zIndex = $this->resolveComputedStyle($child)->getPosition()->zIndex ?? 0;
            if (isset($lifted[$child]) && !$this->isInsideEffect($child, isset($past[$child]), 0 !== $dx)) {
                $this->liftedRelatives[$child] = [$zIndex, array_values($box), $boxColumns, $boxRows];
                $this->positionTracker->shiftContentPositions([$child], $dx, $dy);
                continue;
            }
            unset($this->liftedRelatives[$child]);
            $toCol = max(0, $col + $dx);
            $visible = min($boxColumns, $columns - $toCol);
            // A box without background or border is transparent: only up to its last character is drawn
            $style = $this->resolveComputedStyle($child);
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
     * columns of its own, each spanning child across them all, the ::before in the first run of columns and the ::after
     * in the last one. A spanning child in plain blocks, of no box of their own, spans the columns too, like CSS: the
     * blocks around it are laid out as the content before and after it.
     *
     * @param AbstractWidget[]                                                    $children
     * @param \Closure(int, list<AbstractWidget>|null): LineBufferInterface       $layout
     * @param \Closure(LineBufferInterface, int, bool, bool): LineBufferInterface $decorate
     */
    private function flowAroundSpans(array $children, \Closure $layout, Columns $columns, int $count, int $width, int $innerColumns, int $contentRow, \Closure $decorate, bool $generated): LineBufferInterface
    {
        // The runs between the spanning children: their content, and where the boxes they are in open and close, a box
        // open at a spanning child closed before it, without its bottom edge, and opened again after it, without its top
        $segments = [];
        $run = [];
        $open = [];
        $wrappers = [];
        foreach ($this->spanningRun($children, $wrappers) as [$child, $kind]) {
            if (self::RUN_SPANS === $kind) {
                if ($run) {
                    foreach (array_reverse($open) as $box) {
                        $run[] = [$box, self::RUN_CLOSE, false];
                    }
                    $segments[] = [false, $run];
                }
                $segments[] = [true, [[$child, self::RUN_CONTENT, true]]];
                $run = array_map(static fn (AbstractWidget $box): array => [$box, self::RUN_OPEN, false], $open);
                continue;
            }
            if (self::RUN_OPEN === $kind) {
                $open[] = $child;
            } elseif (self::RUN_CLOSE === $kind) {
                array_pop($open);
            }
            $run[] = [$child, $kind, true];
        }
        // A run left with only boxes opening and closing at once draws nothing, like the end of a box after its last child
        if ($run && [] !== array_filter($run, static fn (array $entry): bool => self::RUN_CONTENT === $entry[1])) {
            $segments[] = [false, $run];
        }
        // A run of columns for the ::before before a spanning first child, and for the ::after after a last one
        if ($generated && $segments[0][0]) {
            array_unshift($segments, [false, []]);
        }
        if ($generated && $segments[array_key_last($segments)][0]) {
            $segments[] = [false, []];
        }
        $runs = array_keys(array_filter($segments, static fn (array $segment): bool => !$segment[0]));
        $lines = [];
        foreach ($segments as $index => [$spans, $segment]) {
            $offset = \count($lines);
            $keep = [];
            [$laidOut, $widgets] = $this->layoutRun($segment, $spans ? $innerColumns : $width, $layout, $keep);
            if ($spans) {
                $part = $laidOut;
            } else {
                $first = $widgets ? $this->positionTracker->getWidgetRect($widgets[0])?->row : null;
                $decorated = $decorate($laidOut, $width, $index === $runs[0], $index === $runs[array_key_last($runs)]);
                // The rows of a ::before above, which move the widgets down, and the rows kept together with them
                $above = null !== $first ? ($this->positionTracker->getWidgetRect($widgets[0])->row ?? $first) - $first : 0;
                $keep = array_map(static fn (int $row): int => $row + $above, $keep);
                $part = $this->flowIntoColumns($widgets, $decorated, $columns, $count, $width, $contentRow, null, $keep);
            }
            // Laid out from the top of the content: moved below what comes before
            $this->positionTracker->shiftContentPositions($widgets, 0, $offset);
            array_push($lines, ...$part->toArray());
        }
        // The plain blocks around a spanning child where their content is drawn, in the runs before and after it
        foreach ($wrappers as [$wrapper, $descendants]) {
            $rects = array_filter(array_map($this->positionTracker->getWidgetRect(...), $descendants));
            if ($rects) {
                $top = min(array_map(static fn (WidgetRect $rect): int => $rect->row, $rects));
                $bottom = max(array_map(static fn (WidgetRect $rect): int => $rect->row + $rect->rows, $rects));
                $left = min(array_map(static fn (WidgetRect $rect): int => $rect->col, $rects));
                $this->positionTracker->setWidgetRect($wrapper, new WidgetRect($top, $left, $innerColumns, $bottom - $top));
            }
        }

        return new ArrayLineBuffer($lines);
    }

    /**
     * The children of a multi-column container in its flow, each with what it is: content, a child spanning the columns,
     * or where the box of a block holding a spanning child opens and closes. The blocks of no layout nor formatting
     * context of their own holding a spanning child are taken apart, like CSS: those of no box either leave their children
     * in their place, the others their children between where their box opens and closes, to be cut around the spanning
     * child; the blocks are remembered with what they hold. None when no child spans the columns.
     *
     * @param AbstractWidget[]                                  $children
     * @param list<array{AbstractWidget, list<AbstractWidget>}> $wrappers The blocks taken apart, with their children
     *
     * @return list<array{AbstractWidget, int}> The kinds are the RUN_* constants
     */
    private function spanningRun(array $children, array &$wrappers = [], int $depth = 0): array
    {
        $run = [];
        $found = false;
        foreach ($children as $child) {
            $style = $this->resolveComputedStyle($child);
            if (true === $style->getSpanAll()) {
                $run[] = [$child, self::RUN_SPANS];
                $found = true;
                continue;
            }
            if ($depth < 8 && (ContainerWidget::class === $child::class || HtmlContainerWidget::class === $child::class) && $this->isSplittableBlock($style) && !$this->establishesFormattingContext($child)) {
                $held = array_values(array_filter($child->all(), fn (AbstractWidget $held): bool => !$this->resolveComputedStyle($held)->getHidden() && !$this->resolveComputedStyle($held)->getPosition()?->isOutOfFlow()));
                if ([] !== $inner = $this->spanningRun($held, $wrappers, $depth + 1)) {
                    $wrappers[] = [$child, array_values(array_map(static fn (array $entry): AbstractWidget => $entry[0], array_filter($inner, static fn (array $entry): bool => self::RUN_CONTENT === $entry[1] || self::RUN_SPANS === $entry[1])))];
                    $boxed = !$this->isPlainBlock($style);
                    if ($boxed) {
                        $run[] = [$child, self::RUN_OPEN];
                    }
                    array_push($run, ...$inner);
                    if ($boxed) {
                        $run[] = [$child, self::RUN_CLOSE];
                    }
                    $found = true;
                    continue;
                }
            }
            $run[] = [$child, self::RUN_CONTENT];
        }

        return $found ? $run : [];
    }

    /**
     * Whether a block has no layout of its own, a block in the flow of its parent: no direction, grid, columns,
     * scrolling, positioning, float, size nor shadow; a box, a background and margins may be, cut around a child
     * spanning the columns of a multi-column container.
     */
    private function isSplittableBlock(Style $style): bool
    {
        return null === $style->getDirection() && null === $style->getGridTemplate() && null === $style->getColumns() && null === $style->getScroll() && null === $style->getPosition() && null === $style->getFloat()
            && null === $style->getMaxColumns() && null === $style->getWidthPercent() && null === $style->getMinRows() && null === $style->getMaxRows() && null === $style->getShadow();
    }

    /**
     * The style of a piece of a box cut across, without the edges where it is cut, like box-decoration-break: slice: its
     * top one when it continues a piece before, its bottom one when a piece after continues it.
     */
    private static function slicedBox(Style $style, int $width, bool $top, bool $bottom): Style
    {
        $style = $style->withResolvedBox($width);
        $side = static fn (?Padding $box): Padding => new Padding($top ? $box->top ?? 0 : 0, $box->right ?? 0, $bottom ? $box->bottom ?? 0 : 0, $box->left ?? 0);
        $style = $style->withPadding($side($style->getPadding()))->withMargin($side($style->getMargin()));
        if (null !== $border = $style->getBorder()) {
            $style = $style->withBorder($border->withWidths($top ? $border->top : 0, $border->right, $bottom ? $border->bottom : 0, $border->left));
        }

        return $style;
    }

    /**
     * Lays out a run of the content of a multi-column container: its widgets, and the pieces of the boxes they are in,
     * each piece drawn with the edges of its box it keeps, its content laid out inside it.
     *
     * @param list<array{AbstractWidget, int, bool}>                        $entries The widgets, and the boxes opening (with their top edge or not) and closing (with their bottom edge or not)
     * @param \Closure(int, list<AbstractWidget>|null): LineBufferInterface $layout
     * @param list<int>                                                     $keep    Set to the rows a column cannot end at, between the edges of a piece and its first or last line
     *
     * @return array{LineBufferInterface, list<AbstractWidget>} The lines, and the widgets laid out in them, outer ones first
     */
    private function layoutRun(array $entries, int $width, \Closure $layout, array &$keep): array
    {
        $lines = [];
        $widgets = [];
        $group = [];
        for ($i = 0, $n = \count($entries); $i < $n; ++$i) {
            [$widget, $kind, $edge] = $entries[$i];
            if (self::RUN_OPEN !== $kind) {
                $group[] = $widget;
                continue;
            }
            $this->layoutGroup($group, $width, $layout, $lines, $widgets);
            // The entries in the box, up to where it closes
            $depth = 0;
            for ($end = $i + 1; $end < $n; ++$end) {
                if ($entries[$end][0] === $widget && self::RUN_CLOSE === $entries[$end][1] && 0 === $depth) {
                    break;
                }
                $depth += match ($entries[$end][1]) {
                    self::RUN_OPEN => 1,
                    self::RUN_CLOSE => -1,
                    default => 0,
                };
            }
            $style = self::slicedBox($this->resolveComputedStyle($widget), $width, $edge, $entries[$end][2] ?? true);
            [$innerWidth] = $this->chromeApplier->computeInnerDimensions($width, 0, $style);
            $innerKeep = [];
            [$inner, $innerWidgets] = $this->layoutRun(\array_slice($entries, $i + 1, $end - $i - 1), $innerWidth, $layout, $innerKeep);
            $box = $this->chromeApplier->apply($inner, $width, $style, $widget)->toArray();
            [$top, $left] = $this->chromeApplier->computeChromeOffset($style);
            $this->positionTracker->shiftContentPositions($innerWidgets, $left, \count($lines) + $top);
            // Its top edge with its first line, its bottom edge with its last one
            $bottom = \count($box) - $top - \count($inner);
            foreach ([...range(1, $top), ...array_map(static fn (int $row): int => $top + $row, $innerKeep), ...($bottom > 0 && \count($inner) > 0 ? range($top + \count($inner), \count($box) - 1) : [])] as $row) {
                if ($row > 0 && $row < \count($box)) {
                    $keep[] = \count($lines) + $row;
                }
            }
            array_push($lines, ...$box);
            array_push($widgets, ...$innerWidgets);
            $i = $end;
        }
        $this->layoutGroup($group, $width, $layout, $lines, $widgets);

        return [new ArrayLineBuffer($lines), $widgets];
    }

    /**
     * Lays out the widgets of a run between the pieces of boxes, below the lines laid out before them, see layoutRun().
     *
     * @param list<AbstractWidget>                                          $group   Emptied once laid out
     * @param \Closure(int, list<AbstractWidget>|null): LineBufferInterface $layout
     * @param list<string>                                                  $lines
     * @param list<AbstractWidget>                                          $widgets
     */
    private function layoutGroup(array &$group, int $width, \Closure $layout, array &$lines, array &$widgets): void
    {
        if (!$group) {
            return;
        }
        $part = $layout($width, $group)->toArray();
        $this->positionTracker->shiftContentPositions($group, 0, \count($lines));
        array_push($lines, ...$part);
        array_push($widgets, ...$group);
        $group = [];
    }

    /**
     * Whether a block has no box nor layout of its own: no margins, border, padding, background, width, height, nor
     * positioning, a block in the flow of its parent.
     */
    private function isPlainBlock(Style $style): bool
    {
        $margin = $style->getMargin();
        $padding = $style->getPadding();
        $border = $style->getBorder();

        return null === $style->getDirection() && null === $style->getGridTemplate() && null === $style->getColumns() && null === $style->getScroll() && null === $style->getPosition() && null === $style->getFloat()
            && null === $style->getBackground() && null === $style->getGradient() && null === $style->getMaxColumns() && null === $style->getWidthPercent() && null === $style->getMinRows() && null === $style->getMaxRows()
            && 0 === ($margin->top ?? 0) + ($margin->right ?? 0) + ($margin->bottom ?? 0) + ($margin->left ?? 0) + ($padding?->getVertical() ?? 0) + ($padding?->getHorizontal() ?? 0)
            && 0 === ($border?->getVertical() ?? 0) + ($border?->getHorizontal() ?? 0);
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
     * @param list<int>        $keep     The rows a column cannot end at besides those the widgets say, like between the
     *                                   edges of a piece of a box laid out around them and its first or last line
     */
    private function flowIntoColumns(array $children, LineBufferInterface $lines, Columns $columns, int $count, int $width, int $contentRow, ?int $fill = null, array $keep = []): LineBufferInterface
    {
        $all = $lines->toArray();
        $total = \count($all);
        if (0 === $total) {
            return $lines;
        }
        // Without positions, every row can start a column
        ['status' => $status, 'forced' => $forced, 'clones' => $clones, 'widgets' => $widgets, 'margins' => $margins] = $this->positionTracker->isActive() ? $this->fragmentation($children, $contentRow, $total, $width) : ['status' => [], 'forced' => [], 'clones' => [], 'widgets' => [], 'margins' => []];
        foreach ($keep as $row) {
            $status[$row] = 0;
        }
        // Only the blank rows of the margins, not what is drawn in them, like a float beside them
        $margins = array_filter($margins, static fn (int $row): bool => '' === trim(AnsiUtils::stripAnsiCodes($all[$row])), \ARRAY_FILTER_USE_KEY);
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
        $place = static function (int $height) use ($all, $total, $count, $forced, $status, $edges, $closings, $margins): ?array {
            $placed = array_fill(0, $count, []);
            $at = [];
            $from = 0;
            $opening = [];
            for ($column = 0; $from < $total; ++$column) {
                if ($column >= $count) {
                    return null;
                }
                // The margins after a break that is not forced are truncated, like CSS: the column starts past them, the
                // boxes they are part of above its top
                if ($column > 0 && !isset($forced[$from])) {
                    for ($skip = 0; isset($margins[$from + $skip]); ++$skip) {
                    }
                    for ($row = 0; $row < $skip; ++$row) {
                        $at[$from + $row] = [$column, \count($opening) + $row - $skip];
                    }
                    $from += $skip;
                    if ($from >= $total) {
                        break;
                    }
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
        // by doubling the step past the first one tried, then halving the range, not by trying every row; from as many
        // rows as the content has each, past the margins the breaks may truncate
        $low = max(1, $fill ?? (int) ceil(($total - \count($margins)) / $count));
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
        $blank = $gap = str_repeat(' ', $columns->gap);
        if (null !== $columns->rule && $columns->gap > 0) {
            $half = intdiv($columns->gap - 1, 2);
            $gap = str_repeat(' ', $half).(new Style(null, null, null, $columns->color))->apply($columns->rule).str_repeat(' ', $columns->gap - 1 - $half);
        }
        // A rule only between two columns that both have content, like CSS: none next to the empty last ones
        $gaps = [];
        for ($column = 1; $column < $count; ++$column) {
            $gaps[$column] = $placed[$column - 1] && $placed[$column] ? $gap : $blank;
        }
        $out = [];
        $height = max(0, ...array_map(\count(...), $placed));
        for ($row = 0; $row < $height; ++$row) {
            $line = '';
            for ($column = 0; $column < $count; ++$column) {
                $line .= ($column > 0 ? $gaps[$column] : '').AnsiUtils::sliceToWidth($placed[$column][$row] ?? '', 0, $width);
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
     * @return array{status: array<int, int>, forced: array<int, true>, clones: list<array{int, int, list<int>, list<int>}>, widgets: list<array{AbstractWidget, int, int}>, margins: array<int, true>}
     */
    private function fragmentation(array $children, int $contentRow, int $total, int $width): array
    {
        $status = [];
        $forced = [];
        $clones = [];
        $widgets = [];
        $margins = [];
        $set = static function (int $from, int $to, int $level) use (&$status, $total): void {
            for ($row = max(1, $from); $row < min($to, $total); ++$row) {
                $status[$row] = min($status[$row] ?? 2, $level);
            }
        };
        $inFlow = fn (AbstractWidget $widget): bool => true !== ($style = $this->resolveComputedStyle($widget))->getHidden() && !\in_array($style->getPosition()?->mode, [PositionMode::Absolute, PositionMode::Fixed], true);
        // Set below: the blocks of a container, which visit() goes into
        $siblings = static function (array $children): void {
        };
        $visit = function (AbstractWidget $widget) use (&$siblings, &$widgets, &$clones, &$margins, $set, $contentRow, $total, $width): void {
            if (null === $rect = $this->positionTracker->getWidgetRect($widget)) {
                return;
            }
            $start = $rect->row - $contentRow;
            $end = $start + $rect->rows;
            if ($rect->rows <= 0 || $start >= $total || $end <= 0) {
                return;
            }
            $widgets[] = [$widget, $start, $rect->col];
            $style = $this->resolveComputedStyle($widget);
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
            // Its margins, which a break truncates
            foreach ([[max(0, $start), min($total, $edgeTop)], [max(0, $edgeBottom), min($total, $end)]] as [$from, $to]) {
                for ($row = $from; $row < $to; ++$row) {
                    $margins[$row] = true;
                }
            }
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
                [$before, $after, $avoidBefore, $avoidNext] = ($this->resolveComputedStyle($child)->getColumnBreaks() ?? []) + [false, false, false, false];
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

        return ['status' => $status, 'forced' => $forced, 'clones' => $clones, 'widgets' => $widgets, 'margins' => $margins];
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
        $top = min(ContainerState::of($widget)->getScrollTop(), max(0, $total - $viewport));
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
            [$above, $below] = $this->resolveComputedStyle($focused)->getScrollMargin() ?? [0, 0];
            $scroll = $style->getScroll();
            $clearTop = min($viewport - 1, $scroll->paddingTop ?? 0);
            $clearBottom = min($viewport - 1 - $clearTop, $scroll->paddingBottom ?? 0);
            // Its box, inside its margins
            $margin = $this->resolveComputedStyle($focused)->getMargin();
            $marginTop = min($rect->rows, max(0, $margin->top ?? 0));
            $first = $rect->row + $marginTop - $contentRow - $above;
            $last = $rect->row - $contentRow + $rect->rows - min($rect->rows - $marginTop, max(0, $margin->bottom ?? 0)) + $below;
            if ($first < $top + $clearTop) {
                $top = max(0, $first - $clearTop);
            } elseif ($last > $top + $viewport - $clearBottom) {
                $top = max(0, min($first - $clearTop, $last - $viewport + $clearBottom));
            }
            // Not past the end of its content, whatever the room kept under it
            $top = min($top, max(0, $total - $viewport));
        }
        // A widget to reveal, inside: its top at the top, once
        if ($this->positionTracker->isActive() && null !== ($target = $this->revealing?->get()) && !isset($this->revealedTarget[$widget]) && null !== $rect = $this->positionTracker->getWidgetRect($target)) {
            for ($ancestor = $target->getParent(); null !== $ancestor && $ancestor !== $widget; $ancestor = $ancestor->getParent()) {
            }
            if (null !== $ancestor) {
                $this->revealedTarget[$widget] = true;
                $room = ($this->resolveComputedStyle($target)->getScrollMargin()[0] ?? 0) + min($viewport - 1, $style->getScroll()->paddingTop ?? 0);
                // Its box at the top, not its margin
                $marginTop = min($rect->rows, max(0, $this->resolveComputedStyle($target)->getMargin()->top ?? 0));
                $top = max(0, min($rect->row + $marginTop - $contentRow - $room, $total - $viewport));
            }
        }
        // scroll-snap-type: where it is scrolled to, it rests on a snap position of a child
        if (null !== ($scroll = $style->getScroll())?->snap && $this->positionTracker->isActive() && $total > $viewport) {
            $top = $this->snap($widget, $scroll, $children, $top, $total, $viewport, $contentRow);
        }
        ContainerState::of($widget)->setScrollMetrics($top, $total, $viewport, $style->getScroll()->contained ?? false);
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
                $visible[$target] = AnsiUtils::sliceToWidth($line, 0, $col)."\x1b[0m".AnsiUtils::sliceToWidth($all[$from + $i] ?? '', $col, $rect->columns)."\x1b[0m".AnsiUtils::sliceToWidth($line, $col + $rect->columns, $width - $col - $rect->columns);
            }
        }
        $this->positionTracker->scrollContentPositions($children, -$top, $contentRow, $contentRow + $viewport);
        if (null === $barColumn) {
            return new ArrayLineBuffer($visible);
        }
        $rows = max(\count($visible), min($viewport, $style->getMinRows() ?? 0));
        [$thumbRows, $thumbTop] = ScrollbarPresentation::cells($rows, $total, $top);
        $scroll = $style->getScroll();
        // Light lines for a thin one
        $thumb = (new Style(null, null, null, $scroll?->thumb))->apply($scroll?->thin ? '│' : '┃');
        $track = (new Style(null, null, null, $scroll?->track, null, null === $scroll?->track))->apply($scroll?->thin ? '┊' : '│');
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
        $left = min(ContainerState::of($widget)->getScrollLeft(), $total - $visible);
        if ($this->positionTracker->isActive()) {
            $this->forgetScrollMarks($widget, 'x');
        }
        $scroll = $style->getScroll();
        $clearLeft = min($visible - 1, $scroll->paddingLeft ?? 0);
        $clearRight = min($visible - 1 - $clearLeft, $scroll->paddingRight ?? 0);
        // The fewest columns that bring the box of a widget into view, inside its margins, with its scroll-margin, within
        // the scroll-padding
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
            $nearest($focused, $this->boxRect($focused) ?? $rect, $this->resolveComputedStyle($focused));
        }
        if ($this->positionTracker->isActive() && null !== ($target = $this->revealing?->get()) && !isset($this->revealedTargetSideways[$widget]) && null !== $rect = $this->positionTracker->getWidgetRect($target)) {
            for ($ancestor = $target->getParent(); null !== $ancestor && $ancestor !== $widget; $ancestor = $ancestor->getParent()) {
            }
            if (null !== $ancestor) {
                $this->revealedTargetSideways[$widget] = true;
                $nearest($target, $this->boxRect($target) ?? $rect, $this->resolveComputedStyle($target));
            }
        }
        // scroll-snap-type: x, where it is scrolled to sideways, it rests on a snap position of a child
        if (null !== $scroll?->snapSideways && $this->positionTracker->isActive() && $total > $visible) {
            $left = $this->snap($widget, $scroll, $children, $left, $total, $visible, $contentCol, true);
        }
        ContainerState::of($widget)->setHorizontalScrollMetrics($left, $total, $visible, true === $scroll?->containedSideways);
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
        $total = max($columns, ContainerState::of($widget)->getScrollWidth());
        // Where the content shows from, while it glides too
        $left = $this->shownLefts[$widget] ?? ContainerState::of($widget)->getScrollLeft();
        [$thumbColumns, $thumbLeft] = ScrollbarPresentation::cells($columns, $total, $left);
        $thumb = (new Style(null, null, null, $scroll->thumb))->apply(str_repeat($scroll->thin ? '─' : '━', $thumbColumns));
        $track = new Style(null, null, null, $scroll->track, null, null === $scroll->track);

        return ($thumbLeft > 0 ? $track->apply(str_repeat($scroll->thin ? '┈' : '─', $thumbLeft)) : '').$thumb.($columns - $thumbLeft - $thumbColumns > 0 ? $track->apply(str_repeat($scroll->thin ? '┈' : '─', $columns - $thumbLeft - $thumbColumns)) : '');
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
        foreach (ContainerState::of($widget)->getScrollButtons() as $direction => $text) {
            $buttonStyle = StyleBridge::element($this->styleSheet, $widget, 'scroll-button('.$direction.')');
            if (true === $buttonStyle->getHidden() || '' === $text) {
                continue;
            }
            $text = AnsiUtils::truncateToWidth($text, max(1, $columns), '');
            $enabled = \in_array($sides[$direction], $scrollable, true);
            $buttons[$direction] = [($enabled ? $buttonStyle : $buttonStyle->withDim(true))->apply($text), AnsiUtils::visibleWidth($text)];
        }
        [$position, $markers] = ContainerState::of($widget)->getScrollMarkers();
        if (!$buttons && null === $position) {
            return $lines;
        }
        [$chromeTop, $chromeLeft] = $this->chromeApplier->computeChromeOffset($style);
        $controls = [];
        $before = [];
        // scroll-marker-group: the markers of the children, the current one matching :target-current
        $group = '';
        if (null !== $position) {
            $groupStyle = StyleBridge::element($this->styleSheet, $widget, 'scroll-marker-group');
            $group = '';
            $at = 0;
            $markerControls = [];
            foreach ($markers as $index => [$child, $text]) {
                $width = AnsiUtils::visibleWidth($text);
                if ($at + $width > $columns) {
                    break;
                }
                $group .= ($at > 0 ? ' ' : '').StyleBridge::element($this->styleSheet, $child, 'scroll-marker')->apply($text)."\x1b[0m".$groupStyle->getAnsiRestore();
                $at += $at > 0 ? 1 : 0;
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
        $markers = ContainerState::of($widget)->getScrollMarkers()[1];
        if (!$markers) {
            return;
        }
        $starts = [];
        foreach ($markers as [$child]) {
            $rect = $this->positionTracker->getWidgetRect($child);
            $starts[] = null === $rect ? null : ($sideways ? $rect->col : $rect->row) - $contentStart;
        }
        $current = $markers[\Symfony\Component\HtmlRenderer\Layout\ScrollMarkerGeometry::current($starts, $at, $total, $visible)][0];
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
            if ($depth < 32 && $widget instanceof ParentInterface && null === $this->resolveComputedStyle($widget)->getScroll()) {
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
                $childStyle = $this->resolveComputedStyle($child);
                $align = $childStyle->getSnapAlign();
                $side = $sideways ? $align[2] ?? null : $align[0] ?? null;
                if (null !== $side && null !== $rect = $this->positionTracker->getWidgetRect($child)) {
                    $margin = $childStyle->getScrollMargin() ?? [];
                    [$before, $after] = $sideways ? [$margin[2] ?? 0, $margin[3] ?? 0] : [$margin[0] ?? 0, $margin[1] ?? 0];
                    [$paddingBefore, $paddingAfter] = $sideways ? [$scroll->paddingLeft, $scroll->paddingRight] : [$scroll->paddingTop, $scroll->paddingBottom];
                    // Its box, inside its margins, like CSS: its scroll-margin is the room kept around it
                    if ($sideways) {
                        $box = $this->boxRect($child) ?? $rect;
                        [$start, $size] = [$box->col - $contentStart, $box->columns];
                    } else {
                        $boxMargin = $childStyle->getMargin();
                        $above = min($rect->rows, max(0, $boxMargin->top ?? 0));
                        [$start, $size] = [$rect->row + $above - $contentStart, max(0, $rect->rows - $above - max(0, $boxMargin->bottom ?? 0))];
                    }
                    $position = \Symfony\Component\HtmlRenderer\Layout\ScrollSnapGeometry::position($side, $start, $size, $before, $after, $paddingBefore, $paddingAfter, $viewport, $total - $viewport);
                    $position = (int) (\in_array($side, ['start', 'end'], true) ? $position : round($position));
                    $points[] = [$position, $align[1], $child];
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
        $snapped = (int) \Symfony\Component\HtmlRenderer\Layout\ScrollSnapGeometry::select(array_map(static fn (array $point): array => [$point[0], $point[1]], $points), $at, $previous, 'proximity' === ($sideways ? $scroll->snapSideways : $scroll->snap) ? intdiv($viewport, 3) : null);
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
            $style = $this->resolveComputedStyle($widget);
            $stickyTop = $style->getStickyTop();
            $stickyBottom = $style->getStickyBottom();
            if ((null !== $stickyTop || null !== $stickyBottom) && null !== $rect = $this->positionTracker->getWidgetRect($widget)) {
                // Its box sticks, inside its margins, which are not drawn over what it is stuck over, like CSS
                $box = $this->boxRect($widget) ?? $rect;
                $margin = $style->getMargin();
                $above = min($rect->rows, max(0, $margin->top ?? 0));
                $below = min($rect->rows - $above, max(0, $margin->bottom ?? 0));
                $rect = new WidgetRect($rect->row + $above, $box->col, $box->columns, $rect->rows - $above - $below);
                // It moves within the content of its parent; a part of a table, like a header cell, within the table
                $parent = $widget->getParent();
                if (\in_array($style->getTableRole(), [TableRole::Cell, TableRole::Row, TableRole::RowGroup], true)) {
                    for ($table = $parent; null !== $table && $table !== $scroller && TableRole::Table !== $this->resolveComputedStyle($table)->getTableRole(); $table = $table->getParent()) {
                    }
                    $parent = $table ?? $parent;
                }
                $start = 0;
                $end = $total;
                if ($parent !== $scroller && null !== $parent && null !== $parentRect = $this->positionTracker->getWidgetRect($parent)) {
                    $parentStyle = $this->resolveComputedStyle($parent);
                    $parentPadding = $parentStyle->withResolvedBox($parentRect->columns)->getPadding();
                    $start = $parentRect->row - $contentRow + ($parentStyle->getBorder()->top ?? 0) + ($parentPadding->top ?? 0);
                    $end = $parentRect->row - $contentRow + $parentRect->rows - ($parentStyle->getBorder()->bottom ?? 0) - ($parentPadding->bottom ?? 0);
                }
                $row = $rect->row - $contentRow;
                $shift = 0;
                // Below the top: down, not past the end of its parent
                // Only down: a widget past the end of its parent already is not pulled up
                if (null !== $stickyTop) {
                    $shift = max(0, min(max($row, $top + $stickyTop), $end - $rect->rows - $below) - $row);
                }
                // Above the bottom: up, not before the start of its parent
                if (0 === $shift && null !== $stickyBottom) {
                    $shift = max(min($row, $top + $viewport - $stickyBottom - $rect->rows), $start + $above) - $row;
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
        foreach ($this->flowOverflow as $widget => $entry) {
            [$revision, $lines, $offset, $viewport] = $entry;
            $parent = $widget->getParent();
            if ($revision !== $widget->getRenderRevision() || null === $parent || null === $rect = $this->positionTracker->getWidgetRect($widget)) {
                unset($this->flowOverflow[$widget]);
                continue;
            }
            $style = $this->resolveComputedStyle($widget);
            $parentStyle = $this->resolveComputedStyle($parent);
            // An item of a flex row taller than it, or a box past a height it sets (see overflowSetHeight()), an item of a
            // grid too
            if (null !== $style->getPosition() || $style->getHidden() || $style->getInvisible() || $style->getTransparent() || (!($entry[4] ?? false) && (Direction::Horizontal !== $parentStyle->getDirection() || null !== $parentStyle->getGridTemplate())) || null !== $parentStyle->getTableRole() || null !== $parentStyle->getWritingMode()) {
                unset($this->flowOverflow[$widget]);
                continue;
            }
            $path = [];
            $following = [];
            $current = $widget;
            for (; $current !== $root && null !== $ancestor = $current->getParent(); $current = $ancestor) {
                if (null !== $this->resolveComputedStyle($ancestor)->getWritingMode()) {
                    $path = null;
                    break;
                }
                $siblings = $ancestor instanceof ParentInterface ? $this->boxChildren($ancestor) : [];
                if (null !== $this->resolveComputedStyle($ancestor)->getDirection()) {
                    usort($siblings, fn (AbstractWidget $a, AbstractWidget $b): int => ($this->resolveComputedStyle($a)->getOrder() ?? 0) <=> ($this->resolveComputedStyle($b)->getOrder() ?? 0));
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
                if ($target < $clip[0] || $target >= $clip[2] || $target >= $start && $target < $start + $viewport) {
                    continue;
                }
                while (\count($out) <= $target) {
                    $out[] = '';
                }
                $layers = [new Layer([$out[$target]], 0, 0, false, $columns, 1), new Layer([AnsiUtils::sliceToWidth($line, $from - $rect->col, $to - $from)], 0, $from, true, null, null, $seeThrough)];
                foreach ($following as $later) {
                    $laterStyle = $this->resolveComputedStyle($later);
                    $box = $this->positionTracker->getWidgetRect($later);
                    $paint = null !== $box ? $this->flowPaints[$later][$box->columns.':'.$box->rows] ?? null : null;
                    if (null === $paint || null === $box || $laterStyle->getPosition()?->isOutOfFlow() || ($laterStyle->getPosition()->zIndex ?? 0) < 0 || $target < $box->row || $target >= $box->row + \count($paint)) {
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
            $style = $this->resolveComputedStyle($ancestor);
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
            $lines = $this->paintEffects($this->resolveComputedStyle($parent), $lines);
        }

        return $lines;
    }
}
