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
use Symfony\Component\Tui\Exception\RenderException;
use Symfony\Component\Tui\Style\Align;
use Symfony\Component\Tui\Style\DefaultStyleSheet;
use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\Figlet\FontRegistry;

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
    private StyleSheet $styleSheet;
    private FontRegistry $fontRegistry;
    private PositionTracker $positionTracker;
    private LayoutEngine $layoutEngine;
    private ChromeApplier $chromeApplier;

    /** Current terminal columns, set during render() for breakpoint resolution */
    private ?int $currentColumns = null;

    public function __construct(?StyleSheet $styleSheet = null, ?FontRegistry $fontRegistry = null)
    {
        $this->fontRegistry = $fontRegistry ?? new FontRegistry();
        $this->positionTracker = new PositionTracker();
        $this->layoutEngine = new LayoutEngine($this, $this->positionTracker, $this->fontRegistry);
        $this->chromeApplier = new ChromeApplier($this);

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

    /**
     * Get the tracked position of a widget from the last render pass.
     *
     * Returns null if the widget was not rendered or is not being tracked.
     */
    public function getWidgetRect(AbstractWidget $widget): ?WidgetRect
    {
        return $this->positionTracker->getWidgetRect($widget);
    }

    /**
     * Render the widget tree starting from root.
     *
     * @return string[] Array of rendered lines
     */
    public function render(ContainerWidget $root, int $columns, int $rows): array
    {
        $context = new RenderContext($columns, $rows, null, $this->fontRegistry, fillRows: true);
        $this->currentColumns = $columns;
        $this->positionTracker->reset();

        $result = $this->renderWidget($root, $context);

        // Track root widget position
        $this->positionTracker->setWidgetRect($root, new WidgetRect(0, 0, $columns, \count($result)));

        return $result;
    }

    public function renderWidget(AbstractWidget $widget, RenderContext $context): array
    {
        // Allow widget to sync state before rendering
        $widget->beforeRender();

        // Check render cache: if the widget hasn't been invalidated and
        // the available dimensions are unchanged, reuse the previous output.
        // This skips style resolution, layout, chrome, and content rendering.
        $cacheColumns = $context->getColumns();
        $cacheRows = $context->getRows();
        $cached = $widget->getRenderCache($cacheColumns, $cacheRows);
        if (null !== $cached) {
            return $cached;
        }

        // 1. Resolve style by merging: global → FQCN → state → instance
        $resolvedStyle = $this->resolveStyle($widget);

        // Hidden widgets produce no output and take no space
        if (true === $resolvedStyle->getHidden()) {
            $widget->setRenderCache([], $cacheColumns, $cacheRows);

            return [];
        }

        // 2. Apply maxColumns constraint if set
        $maxColumns = $resolvedStyle->getMaxColumns();
        if (null !== $maxColumns && $context->getColumns() > $maxColumns) {
            $context = $context->withColumns($maxColumns);
        }

        // 3. Create enriched context with resolved style
        $styledContext = $context->withStyle($resolvedStyle);

        // 4. For ContainerWidget, use the layout engine
        if ($widget instanceof ContainerWidget) {
            $lines = $this->renderContainer($widget, $styledContext, $resolvedStyle);
        } else {
            // 5. For all other widgets (leaf widgets + ParentInterface),
            // render content with inner dimensions, then apply chrome
            $innerContext = $this->chromeApplier->computeInnerContext($styledContext, $resolvedStyle);
            $lines = $widget->render($innerContext);
            $lines = $this->chromeApplier->apply($lines, $context->getColumns(), $resolvedStyle, $widget);
        }

        // Validate that no line exceeds the available width.
        // This catches widget bugs early, at the source, rather than
        // letting over-wide lines flow to ScreenWriter where the widget
        // context is lost. Image lines (Kitty/iTerm2 protocol) are
        // excluded because their visible width is not meaningful.
        $availableColumns = $context->getColumns();
        foreach ($lines as $i => $line) {
            if ('' === $line || AnsiUtils::containsImage($line)) {
                continue;
            }

            $lineWidth = AnsiUtils::visibleWidth($line);
            if ($lineWidth > $availableColumns) {
                throw new RenderException(\sprintf("Widget \"%s\" rendered line %d with width %d, exceeding the available %d columns.\nLine preview: %s.", $widget::class, $i, $lineWidth, $availableColumns, mb_substr(AnsiUtils::stripAnsiCodes($line), 0, 100)), $i, $lineWidth, $availableColumns);
            }
        }

        $widget->setRenderCache($lines, $cacheColumns, $cacheRows);

        return $lines;
    }

    public function resolveStyle(AbstractWidget $widget): Style
    {
        return $this->styleSheet->resolve($widget, $this->currentColumns);
    }

    public function measureIntrinsicWidth(AbstractWidget $widget, int $maxColumns, int $rows): int
    {
        $resolvedStyle = $this->resolveStyle($widget);

        // Apply maxColumns from the widget's own style
        $styleMaxColumns = $resolvedStyle->getMaxColumns();
        if (null !== $styleMaxColumns) {
            $maxColumns = min($maxColumns, $styleMaxColumns);
        }

        // Compute chrome (border + padding)
        [$innerColumns] = $this->chromeApplier->computeInnerDimensions($maxColumns, $rows, $resolvedStyle);

        if ($widget instanceof ContainerWidget) {
            // For containers, render children within inner dimensions and measure
            $children = array_values(array_filter(
                $widget->all(),
                fn (AbstractWidget $child) => true !== $this->resolveStyle($child)->getHidden(),
            ));

            $direction = $resolvedStyle->getDirection() ?? Direction::Vertical;
            $gap = $resolvedStyle->getGap() ?? 0;

            if (Direction::Horizontal === $direction) {
                // Horizontal container: sum of children's intrinsic widths + gaps
                $totalWidth = $gap * max(0, \count($children) - 1);
                foreach ($children as $child) {
                    $totalWidth += $this->measureIntrinsicWidth($child, $innerColumns, $rows);
                }
                $contentWidth = $totalWidth;
            } else {
                // Vertical container: widest child
                $contentWidth = 0;
                foreach ($children as $child) {
                    $contentWidth = max($contentWidth, $this->measureIntrinsicWidth($child, $innerColumns, $rows));
                }
            }
        } else {
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

        return min(max(1, $contentWidth + $chromeWidth), $maxColumns);
    }

    /**
     * Render a container widget with its children.
     *
     * @return string[]
     */
    private function renderContainer(ContainerWidget $widget, RenderContext $context, Style $resolvedStyle): array
    {
        // Filter out hidden children so they don't take up layout space
        $children = array_values(array_filter(
            $widget->all(),
            fn (AbstractWidget $child) => true !== $this->resolveStyle($child)->getHidden(),
        ));

        $columns = $context->getColumns();
        $rows = $context->getRows();

        if (!$children) {
            return $this->chromeApplier->apply([], $columns, $resolvedStyle, $widget);
        }

        // Calculate inner dimensions (content area after border/padding)
        [$innerColumns, $innerRows] = $this->chromeApplier->computeInnerDimensions($columns, $rows, $resolvedStyle);

        // Get direction and gap from resolved style
        $direction = $resolvedStyle->getDirection() ?? Direction::Vertical;
        $gap = $resolvedStyle->getGap() ?? 0;

        // Compute styled gap line matching what a child widget would render as blank
        // This ensures gap lines inherit the container's resolved style (e.g. bold from * rule)
        $gapLine = null;
        if ($gap > 0) {
            $gapContent = str_repeat(' ', max(1, $innerColumns));
            $gapLine = $resolvedStyle->isPlain() ? $gapContent : $resolvedStyle->apply($gapContent);
        }

        // Push the content area's absolute offset onto the position stack
        if ($this->positionTracker->isActive()) {
            [$parentRow, $parentCol] = $this->positionTracker->currentOffset();
            [$chromeTop, $chromeLeft] = $this->chromeApplier->computeChromeOffset($resolvedStyle);
            $this->positionTracker->push($parentRow + $chromeTop, $parentCol + $chromeLeft);
        }

        // Snapshot positions before layout so we can adjust them if alignment shifts content
        $align = $resolvedStyle->getAlign();
        $hasAlign = null !== $align && Align::Left !== $align;
        $verticalAlign = $resolvedStyle->getVerticalAlign();
        $hasVerticalAlign = null !== $verticalAlign;
        $positionsBeforeLayout = ($hasAlign || $hasVerticalAlign) ? $this->positionTracker->snapshotKeys() : null;

        // Render children using layout engine.
        // For horizontal containers, pass verticalAlign so layoutHorizontal can
        // offset shorter children (align-items). For vertical containers it is unused.
        $childLines = $this->layoutEngine->layout(
            $children,
            $innerColumns,
            $innerRows,
            $gap,
            $direction,
            $gapLine,
            Direction::Horizontal === $direction ? $verticalAlign : null,
        );

        // Pop position stack
        $this->positionTracker->pop();

        // Apply vertical alignment offset when VerticalAlign is set and the widget owns its rows.
        if ($hasVerticalAlign && \count($childLines) < $innerRows
            && ($context->shouldFillRows() || $widget->isVerticallyExpanded())
        ) {
            if (0 < $verticalOffset = $this->layoutEngine->computeVerticalAlignOffset(\count($childLines), $innerRows, $verticalAlign)) {
                $topPad = array_fill(0, $verticalOffset, '');
                array_unshift($childLines, ...$topPad);
                $this->positionTracker->shiftDescendantPositions($positionsBeforeLayout, 0, $verticalOffset);
            }
        }

        // Pad to fill allocated rows so that chrome (e.g. background-color) covers the full height.
        //
        // A fill child is detected by isVerticallyExpanded()=true AND shouldFillRows()=false:
        // layoutVertical creates a fresh RenderContext (fillRows=false) with exactly $childFillRows,
        // so the child must fill those rows itself before chromeApplier runs — otherwise layoutVertical
        // pads with bare empty strings that bypass chromeApplier and leave background incomplete.
        //
        // Root renders (shouldFillRows=true) pad only when VerticalAlign is explicitly set;
        // without it the root returns its natural content height.
        $shouldPad = ($widget->isVerticallyExpanded() && !$context->shouldFillRows())
            || ($context->shouldFillRows() && $hasVerticalAlign);
        if ($shouldPad && \count($childLines) < $innerRows) {
            while (\count($childLines) < $innerRows) {
                $childLines[] = '';
            }
        }

        // Apply horizontal alignment for child widgets and adjust tracked positions
        if ($hasAlign && 0 < $alignOffset = $this->layoutEngine->computeAlignOffset($childLines, $innerColumns, $align)) {
            $childLines = $this->layoutEngine->shiftLines($childLines, $alignOffset);
            $this->positionTracker->shiftDescendantPositions($positionsBeforeLayout, $alignOffset);
        }

        // Apply chrome (padding, border, background)
        return $this->chromeApplier->apply($childLines, $columns, $resolvedStyle, $widget);
    }
}
