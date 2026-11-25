<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Widget;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Widget\DocumentWidget;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Event\InputEvent;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

final class DocumentScrollingTest extends TestCase
{
    public function testNativePagingAndManualWheelUseDeviceCellsWithoutStealingFocus()
    {
        [$session, $widget, $tui] = $this->application();
        try {
            $box = $session->getDocument()->getElementById('box');
            $focused = $session->getFocusedElement();
            $tui->handleInput("\x1b[6~");
            self::assertSame([0.0, 32.0], $session->getScroll($box));
            $tui->handleInput("\x1b[5~");
            self::assertSame([0.0, 0.0], $session->getScroll($box));
            $tui->handleInput("\x1b[C");
            self::assertSame([16.0, 0.0], $session->getScroll($box));
            $tui->handleInput("\x1b[D");
            self::assertSame([0.0, 0.0], $session->getScroll($box));
            self::assertTrue($widget->scrollAt(0, 1, 1, 2));
            self::assertSame([16.0, 16.0], $session->getScroll($box));
            self::assertSame($focused, $session->getFocusedElement());
            self::assertFalse($widget->scrollAt(100, 1, 1));
            $before = $session->render();
            self::assertFalse($widget->scrollAt(0, 1, 0));
            self::assertSame($before, $session->render());
        } finally {
            $tui->stop();
        }
    }

    public function testApplicationMouseCaptureCanForwardNativeInputIntoTheSameWheelPath()
    {
        [$session, $widget, $tui] = $this->application();
        $capture = static function (InputEvent $event) use ($widget): void {
            if ("\x1b[<65;2;1M" === $event->getData()) {
                $widget->scrollAt(0, 1, 1);
                $event->stopPropagation();
            }
        };
        try {
            $box = $session->getDocument()->getElementById('box');
            $tui->handleInput("\x1b[<65;2;1M");
            self::assertSame([0.0, 0.0], $session->getScroll($box), 'The application owns physical mouse capture.');
            $tui->addListener($capture, 10);
            $tui->handleInput("\x1b[<65;2;1M");
            self::assertSame([0.0, 16.0], $session->getScroll($box));
            $session->on('wheel', static fn ($event) => $event->preventDefault());
            $tui->handleInput("\x1b[<65;2;1M");
            self::assertSame([0.0, 16.0], $session->getScroll($box));
        } finally {
            $tui->removeListener($capture);
            $tui->stop();
        }
    }

    public function testTheTerminalEndCaretReservesAWholeCellInsideItsInput()
    {
        $session = new HtmlRenderer()->createSession('<input style="width:4ch;border:0;padding:0">', new RenderingContext(320, 128));
        $widget = new DocumentWidget($session);
        $context = new RenderContext(40, 8);
        $widget->render($context);
        $widget->setFocused(true);
        $widget->handleInput('ABCDE');
        $lines = $widget->render($context);
        $input = $session->getDocument()->querySelector('input');
        $geometry = $session->render()->getScrollGeometry($input);
        $carets = array_values(array_filter($session->render()->getDisplayList()->getOperations(), static fn ($operation) => 'caret' === $operation['type']));
        $caret = $carets[array_key_last($carets)];
        self::assertSame(8.0, $caret['width']);
        self::assertGreaterThanOrEqual($geometry->port->x, $caret['x']);
        self::assertLessThanOrEqual($geometry->port->x + $geometry->port->width, $caret['x'] + $caret['width']);
        self::assertStringContainsString(AnsiUtils::cursorMarker(), implode('', $lines));
    }

    private function application(): array
    {
        $session = new HtmlRenderer()->createSession('<div id="box" style="width:10;height:4;overflow:auto"><button>Go</button><div style="width:30;height:12">Tail</div></div>', new RenderingContext(400, 200));
        $widget = new DocumentWidget($session);
        $tui = new Tui(null, new VirtualTerminal(50, 16));
        $tui->add($widget);
        $tui->start();
        $tui->setFocus($widget);
        $tui->processRender();

        return [$session, $widget, $tui];
    }
}
