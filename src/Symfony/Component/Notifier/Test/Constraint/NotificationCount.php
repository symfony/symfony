<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Test\Constraint;

use PHPUnit\Framework\Constraint\Constraint;
use Symfony\Component\Notifier\Event\NotificationEvents;

/**
 * @author Smaïne Milianni <smaine.milianni@gmail.com>
 */
final class NotificationCount extends Constraint
{
    public function __construct(
        private int $expectedValue,
        private ?string $transport = null,
        private bool $queued = false,
    ) {
    }

    public function toString(): string
    {
        return \sprintf('%shas %s "%d" emails', $this->transport ? $this->transport.' ' : '', $this->queued ? 'queued' : 'sent', $this->expectedValue);
    }

    /**
     * @param NotificationEvents $events
     */
    protected function matches($events): bool
    {
        return $this->expectedValue === $this->countNotifications($events);
    }

    /**
     * @param NotificationEvents $events
     */
    protected function failureDescription($events): string
    {
        return \sprintf('the Transport %s (%d %s)', $this->toString(), $this->countNotifications($events), $this->queued ? 'queued' : 'sent');
    }

    private function countNotifications(NotificationEvents $events): int
    {
        $count = 0;
        foreach ($events->getEvents($this->transport) as $event) {
            if (
                ($this->queued && $event->isQueued())
                || (!$this->queued && !$event->isQueued())
            ) {
                ++$count;
            }
        }

        return $count;
    }
}
