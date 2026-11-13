<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Render;

use Symfony\Component\Tui\Render\LineBufferInterface;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ParentInterface;
use Symfony\Component\TuiHtml\Style\Color;
use Symfony\Component\TuiHtml\Style\Style;

/**
 * Interface for rendering individual widgets and resolving their styles.
 *
 * Used by LayoutEngine and ChromeApplier to call back into the Renderer
 * without creating a circular class dependency.
 *
 * @experimental
 *
 * @internal
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface WidgetRendererInterface
{
    /**
     * Render a widget without flattening cached child buffers.
     */
    public function renderLines(AbstractWidget $widget, RenderContext $context): LineBufferInterface;

    /**
     * Render a widget at least $rows rows tall, its box stretched like a CSS
     * flex item: the content rows are padded inside the border.
     */
    public function renderStretchedLines(AbstractWidget $widget, RenderContext $context, int $rows): LineBufferInterface;

    /**
     * Resolve the style for a widget by merging cascade layers.
     */
    public function resolveComputedStyle(AbstractWidget $widget): Style;

    /**
     * A number that changes whenever the style resolveStyle() gives a widget may have changed since it last gave it.
     */
    public function getStyleEpoch(): int;

    /**
     * The background a widget is drawn on, that of its closest ancestor with one: its spaces show it.
     */
    public function getInheritedBackground(AbstractWidget $widget): ?Color;

    /**
     * The rows the children of a container pull into its bottom border by their negative bottom margins.
     *
     * @param ParentInterface&AbstractWidget $container
     */
    public function getBottomPull(ParentInterface $container): int;

    /**
     * Whether the last render of a widget came from its cache, what it holds not walked: their rects are those of the
     * render cached.
     */
    public function wasRenderCached(AbstractWidget $widget): bool;

    /**
     * Measure the intrinsic width of a widget: content width + chrome (border/padding).
     *
     * Unlike renderWidgetLines(), this does not pad lines to the allocated width.
     * Used by the layout engine to measure flex: 0 children.
     */
    public function measureIntrinsicWidth(AbstractWidget $widget, int $maxColumns, int $rows): int;

    /**
     * Measure the least width a widget needs not to be cut, like the CSS min-content width: the longest word of text
     * that wraps, the widest line of text that does not, chrome included.
     */
    public function measureMinContentWidth(AbstractWidget $widget, int $columns, int $rows): int;

    /**
     * Measure the least width a widget needs not to break a word: its controls ellipsizing their labels, its images
     * scaled down, for a row that does not fit at the min-content widths of its items.
     */
    public function measureSoftMinContentWidth(AbstractWidget $widget, int $columns, int $rows): int;

    /**
     * Whether a widget is a formatting context of its own, like overflow: hidden or display: flow-root: the floats
     * around it do not reach into it.
     */
    public function establishesFormattingContext(AbstractWidget $widget): bool;

    /**
     * Whether a widget is the box of an element, like a <p>, not that of a run of text among blocks, like the text of
     * <li>Inbox <span class="float-end">, nor one of a tree built without elements.
     */
    public function isElementBox(AbstractWidget $widget): bool;
}
