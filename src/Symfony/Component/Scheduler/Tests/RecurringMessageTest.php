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
use Random\Randomizer;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\RecurringMessage;

class RecurringMessageTest extends TestCase
{
    public function testCanCreateHashedCronMessage()
    {
        $object = new class() {
            public function __toString(): string
            {
                return 'my task';
            }
        };

        if (class_exists(Randomizer::class)) {
            $this->assertSame('30 0 * * *', (string) RecurringMessage::cron('#midnight', $object)->getTrigger());
            $this->assertSame('30 0 * * 3', (string) RecurringMessage::cron('#weekly', $object)->getTrigger());
        } else {
            $this->assertSame('36 0 * * *', (string) RecurringMessage::cron('#midnight', $object)->getTrigger());
            $this->assertSame('36 0 * * 6', (string) RecurringMessage::cron('#weekly', $object)->getTrigger());
        }
    }

    public function testHashedCronContextIsRequiredIfMessageIsNotStringable()
    {
        $this->expectException(InvalidArgumentException::class);

        RecurringMessage::cron('#midnight', new \stdClass());
    }
}
