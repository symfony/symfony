<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\SignalRegistry;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\SignalRegistry\SignalRegistry;

#[RequiresPhpExtension('pcntl')]
class SignalRegistryTest extends TestCase
{
    protected function tearDown(): void
    {
        pcntl_async_signals(false);
        // We reset all signals to their default value to avoid side effects
        pcntl_signal(\SIGINT, \SIG_DFL);
        pcntl_signal(\SIGTERM, \SIG_DFL);
        pcntl_signal(\SIGUSR1, \SIG_DFL);
        pcntl_signal(\SIGUSR2, \SIG_DFL);
        pcntl_signal(\SIGALRM, \SIG_DFL);
    }

    public function testOneCallbackForASignalSignalIsHandled()
    {
        $signalRegistry = new SignalRegistry();

        $isHandled = false;
        $signalRegistry->register(\SIGUSR1, function () use (&$isHandled) {
            $isHandled = true;
        });

        posix_kill(posix_getpid(), \SIGUSR1);

        $this->assertTrue($isHandled);
    }

    public function testTwoCallbacksForASignalBothCallbacksAreCalled()
    {
        $signalRegistry = new SignalRegistry();

        $isHandled1 = false;
        $signalRegistry->register(\SIGUSR1, function () use (&$isHandled1) {
            $isHandled1 = true;
        });

        $isHandled2 = false;
        $signalRegistry->register(\SIGUSR1, function () use (&$isHandled2) {
            $isHandled2 = true;
        });

        posix_kill(posix_getpid(), \SIGUSR1);

        $this->assertTrue($isHandled1);
        $this->assertTrue($isHandled2);
    }

    public function testTwoSignalsSignalsAreHandled()
    {
        $signalRegistry = new SignalRegistry();

        $isHandled1 = false;
        $isHandled2 = false;

        $signalRegistry->register(\SIGUSR1, function () use (&$isHandled1) {
            $isHandled1 = true;
        });

        posix_kill(posix_getpid(), \SIGUSR1);

        $this->assertTrue($isHandled1);
        $this->assertFalse($isHandled2);

        $signalRegistry->register(\SIGUSR2, function () use (&$isHandled2) {
            $isHandled2 = true;
        });

        posix_kill(posix_getpid(), \SIGUSR2);

        $this->assertTrue($isHandled2);
    }

    public function testTwoCallbacksForASignalPreviousAndRegisteredCallbacksWereCalled()
    {
        $signalRegistry = new SignalRegistry();

        $isHandled1 = false;
        pcntl_signal(\SIGUSR1, function () use (&$isHandled1) {
            $isHandled1 = true;
        });

        $isHandled2 = false;
        $signalRegistry->register(\SIGUSR1, function () use (&$isHandled2) {
            $isHandled2 = true;
        });

        posix_kill(posix_getpid(), \SIGUSR1);

        $this->assertTrue($isHandled1);
        $this->assertTrue($isHandled2);
    }

    public function testTwoCallbacksForASignalPreviousCallbackFromAnotherRegistry()
    {
        $signalRegistry1 = new SignalRegistry();

        $isHandled1 = false;
        $signalRegistry1->register(\SIGUSR1, function () use (&$isHandled1) {
            $isHandled1 = true;
        });

        $signalRegistry2 = new SignalRegistry();

        $isHandled2 = false;
        $signalRegistry2->register(\SIGUSR1, function () use (&$isHandled2) {
            $isHandled2 = true;
        });

        posix_kill(posix_getpid(), \SIGUSR1);

        $this->assertTrue($isHandled1);
        $this->assertTrue($isHandled2);
    }

    public function testPushPopIsolatesHandlers()
    {
        $registry = new SignalRegistry();

        $signal = \SIGUSR1;

        $handler1 = static function () {};
        $handler2 = static function () {};

        $registry->pushCurrentHandlers();
        $registry->register($signal, $handler1);

        $this->assertCount(1, $this->getHandlersForSignal($registry, $signal));

        $registry->pushCurrentHandlers();
        $registry->register($signal, $handler2);

        $this->assertCount(1, $this->getHandlersForSignal($registry, $signal));
        $this->assertSame([$handler2], $this->getHandlersForSignal($registry, $signal));

        $registry->popPreviousHandlers();

        $this->assertCount(1, $this->getHandlersForSignal($registry, $signal));
        $this->assertSame([$handler1], $this->getHandlersForSignal($registry, $signal));

        $registry->popPreviousHandlers();

        $this->assertCount(0, $this->getHandlersForSignal($registry, $signal));
    }

    public function testRestoreOriginalOnEmptyAfterPop()
    {
        if (!\extension_loaded('pcntl')) {
            $this->markTestSkipped('PCNTL extension required');
        }

        $registry = new SignalRegistry();

        $signal = \SIGUSR2;

        $original = pcntl_signal_get_handler($signal);

        $handler = static function () {};

        $registry->pushCurrentHandlers();
        $registry->register($signal, $handler);

        $this->assertNotEquals($original, pcntl_signal_get_handler($signal));

        $registry->popPreviousHandlers();

        $this->assertEquals($original, pcntl_signal_get_handler($signal));
    }

    private function getHandlersForSignal(SignalRegistry $registry, int $signal): array
    {
        $ref = new \ReflectionClass($registry);
        $prop = $ref->getProperty('signalHandlers');
        $handlers = $prop->getValue($registry);

        return $handlers[$signal] ?? [];
    }
}
