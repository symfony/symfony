<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Widget;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Event\CancelEvent;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\CancellableLoaderWidget;

class CancellableLoaderWidgetTest extends TestCase
{
    public function testCancelViaEscapeKey()
    {
        [$loader, $tui] = $this->createLoaderWithTui();
        $cancelCalled = false;

        $tui->addListener(function (CancelEvent $event) use (&$cancelCalled, $loader): void {
            $cancelCalled = true;
            $this->assertSame($loader, $event->getTarget());
        });

        // Escape key = \x1b
        $loader->handleInput("\x1b");

        $this->assertTrue($loader->isCancelled());
        $this->assertTrue($cancelCalled);
    }

    public function testCancelViaCtrlC()
    {
        [$loader, $tui] = $this->createLoaderWithTui();

        $cancelCalled = false;
        $tui->addListener(static function (CancelEvent $e) use (&$cancelCalled): void {
            $cancelCalled = true;
        });

        // Ctrl+C = \x03
        $loader->handleInput("\x03");

        $this->assertTrue($loader->isCancelled());
        $this->assertTrue($cancelCalled);
    }

    public function testCancelWithoutListener()
    {
        [$loader, $tui] = $this->createLoaderWithTui();

        // Should not throw even without any listener
        $loader->handleInput("\x1b");

        $this->assertTrue($loader->isCancelled());
    }

    public function testNonCancelInputDoesNotCancel()
    {
        $loader = new CancellableLoaderWidget();

        $loader->handleInput('a');

        $this->assertFalse($loader->isCancelled());
    }

    public function testReset()
    {
        [$loader, $tui] = $this->createLoaderWithTui();

        $loader->handleInput("\x1b");
        $this->assertTrue($loader->isCancelled());

        $loader->reset();
        $this->assertFalse($loader->isCancelled());
    }

    public function testStartResetsCancelledState()
    {
        [$loader, $tui] = $this->createLoaderWithTui();

        $loader->handleInput("\x1b");
        $this->assertTrue($loader->isCancelled());

        $loader->stop();
        $loader->start();

        $this->assertFalse($loader->isCancelled());
        $this->assertTrue($loader->isRunning());
    }

    public function testMultipleListenersAllCalled()
    {
        [$loader, $tui] = $this->createLoaderWithTui();
        $firstCalled = false;
        $secondCalled = false;

        $tui->addListener(static function (CancelEvent $e) use (&$firstCalled): void {
            $firstCalled = true;
        });

        $tui->addListener(static function (CancelEvent $e) use (&$secondCalled): void {
            $secondCalled = true;
        });

        $loader->handleInput("\x1b");

        $this->assertTrue($firstCalled);
        $this->assertTrue($secondCalled);
    }

    public function testMultipleCancellationsCallListenerEachTime()
    {
        [$loader, $tui] = $this->createLoaderWithTui();
        $callCount = 0;

        $tui->addListener(static function (CancelEvent $e) use (&$callCount): void {
            ++$callCount;
        });

        $loader->handleInput("\x1b");
        $loader->handleInput("\x1b");

        $this->assertSame(2, $callCount);
        $this->assertTrue($loader->isCancelled());
    }

    public function testListenersRemovedOnDetach()
    {
        [$loader, $tui] = $this->createLoaderWithTui();
        $callCount = 0;

        $loader->onCancel(static function () use (&$callCount): void {
            ++$callCount;
        });

        $loader->handleInput("\x1b");
        $this->assertSame(1, $callCount);

        // Remove and re-add the widget (simulates screen transition)
        $tui->remove($loader);

        $loader2 = new CancellableLoaderWidget();
        $tui->add($loader2);

        // The old listener should NOT fire for the new widget
        $loader2->handleInput("\x1b");
        $this->assertSame(1, $callCount, 'Detached widget listener should not fire');
    }

    /**
     * @return array{CancellableLoaderWidget, Tui}
     */
    private function createLoaderWithTui(): array
    {
        $terminal = new VirtualTerminal(80, 24);
        $tui = new Tui(terminal: $terminal);
        $loader = new CancellableLoaderWidget();
        $tui->add($loader);

        return [$loader, $tui];
    }
}
