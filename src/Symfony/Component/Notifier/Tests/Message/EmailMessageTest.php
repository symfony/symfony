<?php

namespace Symfony\Component\Notifier\Tests\Message;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Message\EmailMessage;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\Recipient;

/**
 * @author Jan Schädlich <jan.schaedlich@sensiolabs.de>
 */
class EmailMessageTest extends TestCase
{
    public function testEnsureNonEmptyEmailOnCreationFromNotification()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"Symfony\Component\Notifier\Message\EmailMessage" needs an email, it cannot be empty.');

        EmailMessage::fromNotification(new Notification(), new Recipient('', '+3312345678'));
    }
}
