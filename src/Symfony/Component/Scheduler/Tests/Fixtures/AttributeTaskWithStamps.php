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

use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Scheduler\Attribute\AsCronTask;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[AsPeriodicTask(frequency: '1 hour', schedule: 'stamped', stamps: [DispatchAfterCurrentBusStamp::class, DelayStamp::class => 5000])]
#[AsCronTask(expression: '0 2 * * *', schedule: 'stamped', transports: 'async', stamps: [DelayStamp::class => ['delay' => 7000]])]
class AttributeTaskWithStamps
{
    public function __invoke(): void
    {
    }
}
