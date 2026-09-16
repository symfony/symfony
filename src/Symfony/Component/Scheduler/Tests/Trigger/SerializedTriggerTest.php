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
use Symfony\Component\Scheduler\Exception\LogicException;
use Symfony\Component\Scheduler\Trigger\SerializedTrigger;

class SerializedTriggerTest extends TestCase
{
    public function testToString()
    {
        $this->assertSame('every 5 seconds', (string) new SerializedTrigger('every 5 seconds'));
    }

    public function testGetNextRunDate()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Not possible to get next run date from a deserialized trigger.');

        new SerializedTrigger('every 5 seconds')->getNextRunDate(new \DateTimeImmutable());
    }
}
