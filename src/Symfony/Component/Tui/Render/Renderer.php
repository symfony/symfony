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

    /** @var array<class-string, bool> */
    private array $postRenderOverridden = [];

    /** Current terminal columns, set during render() for breakpoint resolution */
    private ?int $currentColumns = null;

    /**
     * Resolved styles for the current frame, keyed on the widget's render
     * revision so that a style changed from beforeRender() is picked up.
     *
     * @var \WeakMap<AbstractWidget, array{int, Style}>
     */
    private \WeakMap $styleCache;

    public function __construct(?StyleSheet $styleSheet = null, ?FontRegistry $fontRegistry = null)
    {
        $this->fontRegistry = $fontRegistry ?? new FontRegistry();
        $this->styleCache = new \WeakMap();
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
     */
    public function renderFrame(ContainerWidget $root, int $columns, int $rows): LineBufferInterface
    {
        $context = new RenderContext($columns, $rows, null, $this->fontRegistry, fillRows: true);
        $this->currentColumns = $columns;
        $this->styleCache = new \WeakMap();
        $this->positionTracker->reset();

        $result = $this->renderWidgetLines($root, $context);

        $this->positionTracker->setWidgetRect($root, new WidgetRect(0, 0, $columns, \count($result)));

        return $result;
    }

    public function renderWidgetLines(AbstractWidget $widget, RenderContext $context): LineBufferInterface
    {
        $widget->beforeRender();

        $cacheColumns = $context->getColumns();
        $cacheRows = $context->getRows();
        $cached = $widget->getRenderCache($cacheColumns, $cacheRows);
        if (null !== $cached) {
            return $cached;
        }

        $resolvedStyle = $this->resolveStyle($widget);

        if ($resolvedStyle->getHidden()) {
            $lines = new ArrayLineBuffer([]);
            $widget->setRenderCache($lines, $cacheColumns, $cacheRows);

            return $lines;
        }

        $maxColumns = $resolvedStyle->getMaxColumns();
        if (null !== $maxColumns && $context->getColumns() > $maxColumns) {
            $context = $context->withColumns($maxColumns);
        }

        $styledContext = $context->withStyle($resolvedStyle);

        if ($widget instanceof ContainerWidget) {
            $lines = $this->renderContainer($widget, $styledContext, $resolvedStyle);
        } else {
            $innerContext = $this->chromeApplier->computeInnerContext($styledContext, $resolvedStyle);
            $lines = $this->chromeApplier->apply(new ArrayLineBuffer($widget->render($innerContext)), $context->getColumns(), $resolvedStyle, $widget);
        }

        // Reflection once per class: the hook costs nothing to widgets that
        // do not override it, since only then do the lines need materializing
        if ($this->postRenderOverridden[$widget::class] ??= AbstractWidget::class !== new \ReflectionMethod($widget, 'postRender')->getDeclaringClass()->name) {
            $lines = new ArrayLineBuffer($widget->postRender($lines->toArray(), $context));
        }

        $this->validateLines($widget, $lines, $context->getColumns());
        $widget->setRenderCache($lines, $cacheColumns, $cacheRows);

        return $lines;
    }

    public function resolveStyle(AbstractWidget $widget): Style
    {
        $revision = $widget->getRenderRevision();
        $cached = $this->styleCache[$widget] ?? null;

        if (null !== $cached && $cached[0] === $revision) {
            return $cached[1];
        }

        $this->styleCache[$widget] = [$revision, $style = $this->styleSheet->resolve($widget, $this->currentColumns)];

        return $style;
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
                fn (AbstractWidget $child) => !$this->resolveStyle($child)->getHidden(),
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
     */
    private function renderContainer(ContainerWidget $widget, RenderContext $context, Style $resolvedStyle): LineBufferInterface
    {
        $children = array_values(array_filter(
            $widget->all(),
            fn (AbstractWidget $child) => !$this->resolveStyle($child)->getHidden(),
        ));

        $columns = $context->getColumns();
        $rows = $context->getRows();

        if (!$children) {
            return $this->chromeApplier->apply(new ArrayLineBuffer([]), $columns, $resolvedStyle, $widget);
        }

        [$innerColumns, $innerRows] = $this->chromeApplier->computeInnerDimensions($columns, $rows, $resolvedStyle);

        $direction = $resolvedStyle->getDirection() ?? Direction::Vertical;
        $gap = $resolvedStyle->getGap() ?? 0;

        $gapLine = null;
        if ($gap > 0) {
            $gapContent = str_repeat(' ', max(1, $innerColumns));
            $gapLine = $resolvedStyle->isPlain() ? $gapContent : $resolvedStyle->apply($gapContent);
        }

        if ($this->positionTracker->isActive()) {
            [$parentRow, $parentCol] = $this->positionTracker->currentOffset();
            [$chromeTop, $chromeLeft] = $this->chromeApplier->computeChromeOffset($resolvedStyle);
            $this->positionTracker->push($parentRow + $chromeTop, $parentCol + $chromeLeft);
        }

        $align = $resolvedStyle->getAlign();
        $hasAlign = null !== $align && Align::Left !== $align;
        $verticalAlign = $resolvedStyle->getVerticalAlign();
        $hasVerticalAlign = null !== $verticalAlign;

        $childLines = $this->layoutEngine->layout(
            $children,
            $innerColumns,
            $innerRows,
            $gap,
            $direction,
            $gapLine,
            Direction::Horizontal === $direction ? $verticalAlign : null,
        );

        $this->positionTracker->pop();

        if ($hasVerticalAlign && \count($childLines) < $innerRows
            && ($context->shouldFillRows() || $widget->isVerticallyExpanded())
        ) {
            if (0 < $verticalOffset = $this->layoutEngine->computeVerticalAlignOffset(\count($childLines), $innerRows, $verticalAlign)) {
                $childLines = new ConcatenatedLineBuffer([
                    new ArrayLineBuffer(array_fill(0, $verticalOffset, '')),
                    $childLines,
                ]);
                $this->positionTracker->shiftContentPositions($children, 0, $verticalOffset);
            }
        }

        $shouldPad = ($widget->isVerticallyExpanded() && !$context->shouldFillRows())
            || ($context->shouldFillRows() && $hasVerticalAlign);
        if ($shouldPad && \count($childLines) < $innerRows) {
            $childLines = new ConcatenatedLineBuffer([
                $childLines,
                new ArrayLineBuffer(array_fill(0, $innerRows - \count($childLines), '')),
            ]);
        }

        if ($hasAlign && 0 < $alignOffset = $this->layoutEngine->computeAlignOffset($childLines, $innerColumns, $align)) {
            $childLines = $this->layoutEngine->shiftLines($childLines, $alignOffset);
            $this->positionTracker->shiftContentPositions($children, $alignOffset);
        }

        return $this->chromeApplier->apply($childLines, $columns, $resolvedStyle, $widget);
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
}
