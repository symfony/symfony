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

use Symfony\Component\Tui\Focus\FocusPolicyInterface;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Terminal\TerminalInterface;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;

/**
 * A rendering backend of the TUI, selected with Tui::setRenderer().
 *
 * It resolves the styles of the widgets, renders the widget tree into
 * frames, and creates the frame writer that writes them to the terminal.
 * A renderer that also implements {@see FocusPolicyInterface} decides which
 * widgets take the focus and in what order.
 *
 * @experimental
 */
interface RendererInterface
{
    /**
     * Adds a stylesheet to those the styles of the widgets are resolved from.
     */
    public function addStyleSheet(StyleSheet $styleSheet): void;

    /**
     * Resolves the style of a named sub-element of a widget, like the cursor
     * of an input.
     */
    public function resolveElement(AbstractWidget $widget, string $element): Style;

    /**
     * Drops what the renderer derived from other widgets than the one it is
     * for, like styles matched through parents or siblings: called once for
     * each change of the widget tree (AbstractWidget::invalidate()), at the
     * root. A renderer whose styles only depend on the widget itself does
     * nothing.
     */
    public function invalidateStyles(): void;

    /**
     * Creates the writer of the frames this renderer produces.
     */
    public function createFrameWriter(TerminalInterface $terminal): FrameWriterInterface;

    /**
     * Returns where a widget was drawn in the last frame, in terminal
     * coordinates, or null when it was not drawn.
     */
    public function getWidgetRect(AbstractWidget $widget): ?WidgetRect;

    /**
     * Renders the widget tree from its root into a frame of the size of the
     * terminal.
     */
    public function renderFrame(ContainerWidget $root, int $columns, int $rows): LineBufferInterface;

    /**
     * Renders one widget in the given context, like a widget rendering one
     * of its children.
     */
    public function renderWidgetLines(AbstractWidget $widget, RenderContext $context): LineBufferInterface;

    /**
     * Returns the resolved style of a widget.
     */
    public function resolveStyle(AbstractWidget $widget): Style;
}
