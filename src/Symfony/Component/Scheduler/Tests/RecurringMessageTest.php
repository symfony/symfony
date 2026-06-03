<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\RecurringMessage;

class RecurringMessageTest extends TestCase
{
    public function testCanCreateHashedCronMessage()
    {
        $object = new DummyStringableMessage();

        $this->assertSame('30 0 * * *', (string) RecurringMessage::cron('#midnight', $object)->getTrigger());
        $this->assertSame('30 0 * * 3', (string) RecurringMessage::cron('#weekly', $object)->getTrigger());
    }

    public function testHashedCronContextIsRequiredIfMessageIsNotStringable()
    {
        $this->expectException(InvalidArgumentException::class);

        RecurringMessage::cron('#midnight', new \stdClass());
    }

    public function testUniqueId()
    {
        $message1 = RecurringMessage::cron('* * * * *', new \stdClass());
        $message2 = RecurringMessage::cron('* 5 * * *', new \stdClass());

        $this->assertSame($message1->getId(), (clone $message1)->getId());
        $this->assertNotSame($message1->getId(), $message2->getId());
    }
}

class DummyStringableMessage implements \Stringable
{
    public function __toString(): string
    {
        return 'my task';
    }
}
