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
