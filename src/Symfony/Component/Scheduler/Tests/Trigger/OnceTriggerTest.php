<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Trigger;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Trigger\OnceTrigger;

class OnceTriggerTest extends TestCase
{
    public function testNextTo()
    {
        $time = new \DateTimeImmutable('2020-02-20 20:00:00');
        $schedule = new OnceTrigger($time);

        $this->assertEquals($time, $schedule->nextTo(new \DateTimeImmutable('@0'), ''));
        $this->assertEquals($time, $schedule->nextTo($time->modify('-1 sec'), ''));
        $this->assertNull($schedule->nextTo($time, ''));
        $this->assertNull($schedule->nextTo($time->modify('+1 sec'), ''));
    }
}
