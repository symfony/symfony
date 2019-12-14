<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Event;

use Symfony\Component\Scheduler\SchedulerInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerRebootedEvent extends Event
{
    private $scheduler;

    public function __construct(SchedulerInterface $scheduler)
    {
        $this->scheduler = $scheduler;
    }

    public function getScheduler(): SchedulerInterface
    {
        return $this->scheduler;
    }
}
