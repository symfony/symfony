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
use Symfony\Component\Scheduler\Trigger\ExcludeTimeTrigger;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

class ExcludeTimeTriggerTest extends TestCase
{
    public function testGetNextRun()
    {
        $inner = $this->createMock(TriggerInterface::class);
        $inner
            ->method('getNextRunDate')
            ->willReturnCallback(static fn (\DateTimeImmutable $d) => $d->modify('+1 sec'));

        $scheduled = new ExcludeTimeTrigger(
            $inner,
            new \DateTimeImmutable('2020-02-20T02:02:02Z'),
            new \DateTimeImmutable('2020-02-20T20:20:20Z')
        );

        $this->assertEquals(new \DateTimeImmutable('2020-02-20T02:02:01Z'), $scheduled->getNextRunDate(new \DateTimeImmutable('2020-02-20T02:02:00Z')));
        $this->assertEquals(new \DateTimeImmutable('2020-02-20T20:20:21Z'), $scheduled->getNextRunDate(new \DateTimeImmutable('2020-02-20T02:02:02Z')));
        $this->assertEquals(new \DateTimeImmutable('2020-02-20T20:20:21Z'), $scheduled->getNextRunDate(new \DateTimeImmutable('2020-02-20T20:20:20Z')));
        $this->assertEquals(new \DateTimeImmutable('2020-02-20T22:22:23Z'), $scheduled->getNextRunDate(new \DateTimeImmutable('2020-02-20T22:22:22Z')));
    }
}
