<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Tests\Message;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Message\SmsMessage;

/**
 * @author Jan Schädlich <jan.schaedlich@sensiolabs.de>
 */
class SmsMessageTest extends TestCase
{
    public function testCanBeConstructed()
    {
        $message = new SmsMessage('+3312345678', 'subject');

        self::assertSame('subject', $message->getSubject());
        self::assertSame('+3312345678', $message->getPhone());
    }

    public function testEnsureNonEmptyPhoneOnConstruction()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('"Symfony\Component\Notifier\Message\SmsMessage" needs a phone number, it cannot be empty.');

        new SmsMessage('', 'subject');
    }

    public function testSetPhone()
    {
        $message = new SmsMessage('+3312345678', 'subject');

        self::assertSame('+3312345678', $message->getPhone());

        $message->phone('+4912345678');

        self::assertSame('+4912345678', $message->getPhone());
    }

    public function testEnsureNonEmptyPhoneOnSet()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('"Symfony\Component\Notifier\Message\SmsMessage" needs a phone number, it cannot be empty.');

        $message = new SmsMessage('+3312345678', 'subject');

        self::assertSame('+3312345678', $message->getPhone());

        $message->phone('');
    }
}
