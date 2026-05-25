<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Widget;

use Symfony\Component\Tui\Exception\InvalidArgumentException;

/**
 * Shared scheduling lifecycle for runtime objects driven by WidgetContext ticks.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
trait ScheduledTickTrait
{
    private ?string $scheduledTickId = null;
    private ?float $scheduledTickInterval = null;

    abstract protected function resolveScheduledTickContext(): ?WidgetContext;

    abstract protected function onScheduledTick(): void;

    private function startScheduledTick(float $intervalSeconds): void
    {
        if ($intervalSeconds <= 0.0) {
            throw new InvalidArgumentException(\sprintf('Interval must be greater than 0, got %d.', $intervalSeconds));
        }

        if (null !== $this->scheduledTickId && null !== $this->scheduledTickInterval && abs($this->scheduledTickInterval - $intervalSeconds) < 0.000001) {
            return;
        }

        $this->stopScheduledTick();
        $this->scheduledTickInterval = $intervalSeconds;

        $context = $this->resolveScheduledTickContext();
        if (null === $context) {
            return;
        }

        $this->scheduledTickId = $context->scheduleTick(
            function (): void {
                $this->onScheduledTick();
            },
            $intervalSeconds,
        );
    }

    private function resumeScheduledTick(): void
    {
        if (null === $this->scheduledTickInterval) {
            return;
        }

        $this->startScheduledTick($this->scheduledTickInterval);
    }

    private function stopScheduledTick(): void
    {
        if (null === $this->scheduledTickId) {
            return;
        }

        $this->resolveScheduledTickContext()?->cancelTick($this->scheduledTickId);
        $this->scheduledTickId = null;
    }

    private function clearScheduledTick(): void
    {
        $this->stopScheduledTick();
        $this->scheduledTickInterval = null;
    }
}
