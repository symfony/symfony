<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Schedule;

use Symfony\Component\Scheduler\Trigger\TriggerInterface;

final class ScheduleConfig
{
    /**
     * @var array<int, array{TriggerInterface, object}>
     */
    private array $schedule = [];

    /**
     * @param iterable<array{TriggerInterface, object}> $schedule
     */
    public function __construct(iterable $schedule = [])
    {
        foreach ($schedule as $args) {
            $this->add(...$args);
        }
    }

    public function add(TriggerInterface $trigger, object $message): self
    {
        $this->schedule[] = [$trigger, $message];

        return $this;
    }

    /**
     * @return array<int, array{TriggerInterface, object}>
     */
    public function getSchedule(): array
    {
        return $this->schedule;
    }
}
