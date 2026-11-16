<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Render;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Focus\FocusPolicyInterface;
use Symfony\Component\Tui\Render\FrameWriterInterface;
use Symfony\Component\Tui\Render\LineBufferInterface;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Render\RendererDecoratorInterface;
use Symfony\Component\Tui\Render\RendererInterface;
use Symfony\Component\Tui\Render\WidgetRect;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Terminal\TerminalInterface;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\InputWidget;

/**
 * The decorator of the README: styles come from the style sheet, the focus from the decorator.
 */
final class DecoratingRendererTest extends TestCase
{
    public function testTheReadmeDecoratorMutesWidgets()
    {
        $terminal = new VirtualTerminal(20, 4);
        $tui = new Tui(new StyleSheet([':muted' => new Style(dim: true)]), $terminal, renderer: new MutingRenderer());
        $tui->add($first = new InputWidget())->add($muted = (new InputWidget())->setState('muted'))->add($last = new InputWidget());
        $tui->add((new InputWidget())->setStyle(new Style(hidden: true)));

        $this->assertTrue($tui->getRenderer()->resolveStyle($muted)->getDim());
        $this->assertNull($tui->getRenderer()->resolveStyle($first)->getDim());

        $tui->start();
        $tui->processRender();
        $this->assertSame($first, $tui->getFocusManager()->getFocus());
        $tui->handleInput("\x1b[17~"); // F6
        $this->assertSame($last, $tui->getFocusManager()->getFocus(), 'F6 skips the muted widget.');
        $tui->handleInput("\x1b[17~"); // F6
        $this->assertSame($first, $tui->getFocusManager()->getFocus(), 'F6 skips the hidden widget too.');
        $tui->stop();
    }

    public function testTheDecoratedRendererIsFoundThroughTheDecorator()
    {
        $native = new Renderer();
        $tui = new Tui(renderer: new MutingRenderer(new MutingRenderer($native)));

        $renderer = $tui->getRenderer();
        while (!$renderer instanceof Renderer && $renderer instanceof RendererDecoratorInterface) {
            $renderer = $renderer->getDecoratedRenderer();
        }

        $this->assertSame($native, $renderer, 'Each decorator gives the renderer it decorates.');
    }
}

/**
 * Copied from the README.
 */
final class MutingRenderer implements RendererDecoratorInterface, FocusPolicyInterface
{
    public function __construct(private readonly RendererInterface $inner = new Renderer())
    {
    }

    public function getDecoratedRenderer(): RendererInterface
    {
        return $this->inner;
    }

    public function allowsFocus(AbstractWidget $widget): bool
    {
        if ($widget->hasState('muted')) {
            return false;
        }
        // What Tui checks without a policy: neither the widget nor a parent is hidden
        for ($current = $widget; null !== $current; $current = $current->getParent()) {
            if ($this->inner->resolveStyle($current)->getHidden()) {
                return false;
            }
        }

        return true;
    }

    public function orderFocusCycle(array $widgets): array
    {
        return $widgets;
    }

    public function resolveStyle(AbstractWidget $widget): Style
    {
        return $this->inner->resolveStyle($widget);
    }

    public function addStyleSheet(StyleSheet $styleSheet): void
    {
        $this->inner->addStyleSheet($styleSheet);
    }

    public function resolveElement(AbstractWidget $widget, string $element): Style
    {
        return $this->inner->resolveElement($widget, $element);
    }

    public function invalidateStyles(): void
    {
        $this->inner->invalidateStyles();
    }

    public function createFrameWriter(TerminalInterface $terminal): FrameWriterInterface
    {
        return $this->inner->createFrameWriter($terminal);
    }

    public function getWidgetRect(AbstractWidget $widget): ?WidgetRect
    {
        return $this->inner->getWidgetRect($widget);
    }

    public function renderFrame(ContainerWidget $root, int $columns, int $rows): LineBufferInterface
    {
        return $this->inner->renderFrame($root, $columns, $rows);
    }

    public function renderWidgetLines(AbstractWidget $widget, RenderContext $context): LineBufferInterface
    {
        return $this->inner->renderWidgetLines($widget, $context);
    }
}
