<?php

namespace Symfony\Component\Notifier\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Channel\SmsChannel;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Notifier;
use Symfony\Component\Notifier\Transport\NullTransport;

/**
 * @author Smaïne Milianni <smaine.milianni@gmail.com>
 */
final class NotifierTest extends TestCase
{
    public function testItThrowAnExplicitErrorIfAnSmsChannelDoesNotHaveRecipient()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "sms" channel needs a Recipient.');

        $notifier = new Notifier(['sms' => new SmsChannel(new NullTransport())]);
        $notifier->send(new Notification('Hello World!', ['sms/twilio']));
    }
}
