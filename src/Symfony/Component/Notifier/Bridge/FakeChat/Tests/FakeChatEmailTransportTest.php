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
use Symfony\Component\Mime\Email;
use Symfony\Component\Notifier\Bridge\FakeChat\FakeChatEmailTransport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Fixtures\TestOptions;
use Symfony\Component\Notifier\Tests\Mailer\DummyMailer;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class FakeChatEmailTransportTest extends TransportTestCase
{
    public function createTransport(HttpClientInterface $client = null, string $transportName = null): FakeChatEmailTransport
    {
        $transport = (new FakeChatEmailTransport($this->createMock(MailerInterface::class), 'recipient@email.net', 'sender@email.net', $client ?? $this->createMock(HttpClientInterface::class)));

        if (null !== $transportName) {
            $transport->setHost($transportName);
        }

        return $transport;
    }

    public function toStringProvider(): iterable
    {
        yield ['fakechat+email://default?to=recipient@email.net&from=sender@email.net', $this->createTransport()];
        yield ['fakechat+email://mailchimp?to=recipient@email.net&from=sender@email.net', $this->createTransport(null, 'mailchimp')];
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

    public function testSendWithDefaultTransportAndWithRecipient()
    {
        $transportName = null;

        $message = new ChatMessage($subject = 'Hello!', new TestOptions(['recipient_id' => $recipient = 'Oskar']));

        $mailer = new DummyMailer();

        $transport = (new FakeChatEmailTransport($mailer, $to = 'recipient@email.net', $from = 'sender@email.net'));
        $transport->setHost($transportName);

        $transport->send($message);

        /** @var Email $sentEmail */
        $sentEmail = $mailer->getSentEmail();
        $this->assertInstanceOf(Email::class, $sentEmail);
        $this->assertSame($to, $sentEmail->getTo()[0]->getEncodedAddress());
        $this->assertSame($from, $sentEmail->getFrom()[0]->getEncodedAddress());
        $this->assertSame(sprintf('New Chat message for recipient: %s', $recipient), $sentEmail->getSubject());
        $this->assertSame($subject, $sentEmail->getTextBody());
        $this->assertFalse($sentEmail->getHeaders()->has('X-Transport'));
    }

    public function testSendWithDefaultTransportAndWithoutRecipient()
    {
        $transportName = null;

        $message = new ChatMessage($subject = 'Hello!');

        $mailer = new DummyMailer();

        $transport = (new FakeChatEmailTransport($mailer, $to = 'recipient@email.net', $from = 'sender@email.net'));
        $transport->setHost($transportName);

        $transport->send($message);

        /** @var Email $sentEmail */
        $sentEmail = $mailer->getSentEmail();
        $this->assertInstanceOf(Email::class, $sentEmail);
        $this->assertSame($to, $sentEmail->getTo()[0]->getEncodedAddress());
        $this->assertSame($from, $sentEmail->getFrom()[0]->getEncodedAddress());
        $this->assertSame('New Chat message without specified recipient!', $sentEmail->getSubject());
        $this->assertSame($subject, $sentEmail->getTextBody());
        $this->assertFalse($sentEmail->getHeaders()->has('X-Transport'));
    }

    public function testSendWithCustomTransportAndWithRecipient()
    {
        $transportName = 'mailchimp';

        $message = new ChatMessage($subject = 'Hello!', new TestOptions(['recipient_id' => $recipient = 'Oskar']));

        $mailer = new DummyMailer();

        $transport = (new FakeChatEmailTransport($mailer, $to = 'recipient@email.net', $from = 'sender@email.net'));
        $transport->setHost($transportName);

        $transport->send($message);

        /** @var Email $sentEmail */
        $sentEmail = $mailer->getSentEmail();
        $this->assertInstanceOf(Email::class, $sentEmail);
        $this->assertSame($to, $sentEmail->getTo()[0]->getEncodedAddress());
        $this->assertSame($from, $sentEmail->getFrom()[0]->getEncodedAddress());
        $this->assertSame(sprintf('New Chat message for recipient: %s', $recipient), $sentEmail->getSubject());
        $this->assertSame($subject, $sentEmail->getTextBody());
        $this->assertTrue($sentEmail->getHeaders()->has('X-Transport'));
        $this->assertSame($transportName, $sentEmail->getHeaders()->get('X-Transport')->getBody());
    }

    public function testSendWithCustomTransportAndWithoutRecipient()
    {
        $transportName = 'mailchimp';

        $message = new ChatMessage($subject = 'Hello!');

        $mailer = new DummyMailer();

        $transport = (new FakeChatEmailTransport($mailer, $to = 'recipient@email.net', $from = 'sender@email.net'));
        $transport->setHost($transportName);

        $transport->send($message);

        /** @var Email $sentEmail */
        $sentEmail = $mailer->getSentEmail();
        $this->assertInstanceOf(Email::class, $sentEmail);
        $this->assertSame($to, $sentEmail->getTo()[0]->getEncodedAddress());
        $this->assertSame($from, $sentEmail->getFrom()[0]->getEncodedAddress());
        $this->assertSame('New Chat message without specified recipient!', $sentEmail->getSubject());
        $this->assertSame($subject, $sentEmail->getTextBody());
        $this->assertTrue($sentEmail->getHeaders()->has('X-Transport'));
        $this->assertSame($transportName, $sentEmail->getHeaders()->get('X-Transport')->getBody());
    }
}
