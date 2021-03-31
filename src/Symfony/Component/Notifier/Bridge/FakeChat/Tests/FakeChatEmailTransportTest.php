<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\FakeChat\Tests;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Notifier\Bridge\FakeChat\FakeChatEmailTransport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class FakeChatEmailTransportTest extends TransportTestCase
{
    public function createTransport(?HttpClientInterface $client = null): TransportInterface
    {
        return (new FakeChatEmailTransport($this->createMock(MailerInterface::class), 'recipient@email.net', 'from@email.net'))->setHost('mailer');
    }

    public function toStringProvider(): iterable
    {
        yield ['fakechat+email://mailer?to=recipient@email.net&from=from@email.net', $this->createTransport()];
    }

    public function supportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
    }

    public function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
        yield [$this->createMock(MessageInterface::class)];
    }
}
