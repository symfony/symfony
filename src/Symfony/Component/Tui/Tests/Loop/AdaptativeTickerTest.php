<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Loop;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Loop\AdaptativeTicker;
use Symfony\Component\Tui\Loop\TickRuntimeInterface;

class AdaptativeTickerTest extends TestCase
{
    public function testRefreshSchedulesIdlePollingWhenTickStateIsUnknown()
    {
        $runtime = new TestTickRuntime();
        $ticker = new AdaptativeTicker($runtime);

        try {
            $ticker->refresh(true, false, null, true, null);

            $this->assertSame(0.25, $this->getCurrentInterval($ticker));
            $this->assertNotNull($this->getCallbackId($ticker));
        } finally {
            $ticker->stop();
        }
    }

    public function testRefreshDisablesPollingWhenExplicitlyIdleWithoutOtherWork()
    {
        $runtime = new TestTickRuntime();
        $ticker = new AdaptativeTicker($runtime);

        try {
            $ticker->refresh(true, false, null, true, false);

            $this->assertNull($this->getCurrentInterval($ticker));
            $this->assertNull($this->getCallbackId($ticker));
        } finally {
            $ticker->stop();
        }
    }

    public function testRefreshUsesSmallestIntervalAcrossSignals()
    {
        $runtime = new TestTickRuntime();
        $ticker = new AdaptativeTicker($runtime);

        try {
            $ticker->refresh(true, true, 0.2, true, null);

            $this->assertSame(0.01, $this->getCurrentInterval($ticker));
        } finally {
            $ticker->stop();
        }
    }

    public function testRefreshUsesScheduledDelayWhenNoRenderOrTickPolling()
    {
        $runtime = new TestTickRuntime();
        $ticker = new AdaptativeTicker($runtime);

        try {
            $ticker->refresh(true, false, 0.05, false, null);

            $this->assertSame(0.05, $this->getCurrentInterval($ticker));
        } finally {
            $ticker->stop();
        }
    }

    public function testStopClearsCurrentSchedule()
    {
        $runtime = new TestTickRuntime();
        $ticker = new AdaptativeTicker($runtime);

        $ticker->refresh(true, true, null, false, null);
        $this->assertNotNull($this->getCallbackId($ticker));

        $ticker->stop();

        $this->assertNull($this->getCurrentInterval($ticker));
        $this->assertNull($this->getCallbackId($ticker));
    }

    private function getCurrentInterval(AdaptativeTicker $ticker): ?float
    {
        $property = new \ReflectionProperty($ticker, 'interval');

        return $property->getValue($ticker);
    }

    private function getCallbackId(AdaptativeTicker $ticker): ?string
    {
        $property = new \ReflectionProperty($ticker, 'callbackId');

        return $property->getValue($ticker);
    }
}

final class TestTickRuntime implements TickRuntimeInterface
{
    public function tick(): void
    {
    }

    public function isRunning(): bool
    {
        return true;
    }
}
