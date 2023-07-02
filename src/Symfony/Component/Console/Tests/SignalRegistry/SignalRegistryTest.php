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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\SignalRegistry\SignalRegistry;

/**
 * @requires extension pcntl
 */
class SignalRegistryTest extends TestCase
{
    protected function tearDown(): void
    {
        pcntl_async_signals(false);
        // We reset all signals to their default value to avoid side effects
        for ($i = 1; $i <= 15; ++$i) {
            if (9 === $i) {
                continue;
            }
            pcntl_signal($i, \SIG_DFL);
        }
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
}
