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

use Symfony\Component\Tui\Focus\FocusPolicyInterface;
use Symfony\Component\Tui\Render\FrameWriterInterface;
use Symfony\Component\Tui\Render\LineBufferInterface;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Render\RendererDecoratorInterface;
use Symfony\Component\Tui\Render\RendererInterface;
use Symfony\Component\Tui\Render\WidgetRect;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Terminal\TerminalInterface;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;

/**
 * Adds terminal protocol output to an existing Tui widget renderer.
 *
 * @experimental
 */
final class TerminalRenderer implements RendererDecoratorInterface, FocusPolicyInterface
{
    public function __construct(private readonly RendererInterface $renderer)
    {
    }

    public function getDecoratedRenderer(): RendererInterface
    {
        return $this->renderer;
    }

    public function addStyleSheet(StyleSheet $styleSheet): void
    {
        $this->renderer->addStyleSheet($styleSheet);
    }

    public function resolveElement(AbstractWidget $widget, string $element): Style
    {
        return $this->renderer->resolveElement($widget, $element);
    }

    public function invalidateStyles(): void
    {
        $this->renderer->invalidateStyles();
    }

    public function createFrameWriter(TerminalInterface $terminal): FrameWriterInterface
    {
        return new ScreenWriter($terminal);
    }

    public function getWidgetRect(AbstractWidget $widget): ?WidgetRect
    {
        return $this->renderer->getWidgetRect($widget);
    }

    public function renderFrame(ContainerWidget $root, int $columns, int $rows): LineBufferInterface
    {
        return $this->renderer->renderFrame($root, $columns, $rows);
    }

    public function renderWidgetLines(AbstractWidget $widget, RenderContext $context): LineBufferInterface
    {
        return $this->renderer->renderWidgetLines($widget, $context);
    }

    public function resolveStyle(AbstractWidget $widget): Style
    {
        return $this->renderer->resolveStyle($widget);
    }

    public function allowsFocus(AbstractWidget $widget): bool
    {
        return !$this->renderer instanceof FocusPolicyInterface || $this->renderer->allowsFocus($widget);
    }

    public function orderFocusCycle(array $widgets): array
    {
        return $this->renderer instanceof FocusPolicyInterface ? $this->renderer->orderFocusCycle($widgets) : $widgets;
    }
}
