<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Fixtures;

use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

class SomeScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(
        private readonly array $messages,
    ) {
    }

    public function getSchedule(): Schedule
    {
        return (new Schedule())->add(...$this->messages);
    }
}
