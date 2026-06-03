<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Event;

/**
 * Event dispatched on each tick of the main loop.
 *
 * Unlike widget events, tick is a global application event
 * with no associated widget target.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TickEvent
{
    private bool $hasBusyHint = false;
    private bool $busy = false;

    public function __construct(
        private readonly float $deltaTime = 0.0,
    ) {
    }

    /**
     * Time elapsed (in seconds) since the previous tick callback.
     */
    public function getDeltaTime(): float
    {
        return $this->deltaTime;
    }

    public function setBusy(bool $busy = true): void
    {
        $this->hasBusyHint = true;
        $this->busy = $busy;
    }

    public function hasBusyHint(): bool
    {
        return $this->hasBusyHint;
    }

    public function isBusy(): bool
    {
        return $this->busy;
    }
}
