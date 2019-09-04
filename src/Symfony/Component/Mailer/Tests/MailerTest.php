<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\LogicException;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class MailerTest extends TestCase
{
    public function testSendingRawMessages()
    {
        $this->expectException(LogicException::class);

        $transport = new Mailer($this->createMock(TransportInterface::class), $this->createMock(MessageBusInterface::class), $this->createMock(EventDispatcherInterface::class));
        $transport->send(new RawMessage('Some raw email message'));
    }
}
