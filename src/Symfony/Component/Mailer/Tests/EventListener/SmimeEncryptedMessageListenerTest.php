<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\EventListener\SmimeCertificateRepositoryInterface;
use Symfony\Component\Mailer\EventListener\SmimeEncryptedMessageListener;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Header\MailboxListHeader;
use Symfony\Component\Mime\Header\UnstructuredHeader;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\SMimePart;
use Symfony\Component\Mime\Part\TextPart;

class SmimeEncryptedMessageListenerTest extends TestCase
{
    /**
     * @requires extension openssl
     */
    public function testSmimeMessageEncryptionProcess()
    {
        $repository = $this->createMock(SmimeCertificateRepositoryInterface::class);
        $repository->method('findCertificatePathFor')->willReturn(\dirname(__DIR__).'/Fixtures/sign.crt');
        $listener = new SmimeEncryptedMessageListener($repository);
        $message = new Message(
            new Headers(
                new MailboxListHeader('From', [new Address('sender@example.com')]),
                new UnstructuredHeader('X-SMime-Encrypt', 'true'),
            ),
            new TextPart('hello')
        );
        $envelope = new Envelope(new Address('sender@example.com'), [new Address('r1@example.com')]);
        $event = new MessageEvent($message, $envelope, 'default');

        $listener->onMessage($event);
        $this->assertNotSame($message, $event->getMessage());
        $this->assertInstanceOf(TextPart::class, $message->getBody());
        $this->assertInstanceOf(SMimePart::class, $event->getMessage()->getBody());
        $this->assertFalse($event->getMessage()->getHeaders()->has('X-SMime-Encrypt'));
    }

    /**
     * @requires extension openssl
     */
    public function testMessageNotEncryptedWhenOneRecipientCertificateIsMissing()
    {
        $repository = $this->createMock(SmimeCertificateRepositoryInterface::class);
        $repository->method('findCertificatePathFor')->willReturnOnConsecutiveCalls(\dirname(__DIR__).'/Fixtures/sign.crt', null);
        $listener = new SmimeEncryptedMessageListener($repository);
        $message = new Message(
            new Headers(
                new MailboxListHeader('From', [new Address('sender@example.com')]),
                new UnstructuredHeader('X-SMime-Encrypt', 'true'),
            ),
            new TextPart('hello')
        );
        $envelope = new Envelope(new Address('sender@example.com'), [
            new Address('r1@example.com'),
            new Address('r2@example.com'),
        ]);
        $event = new MessageEvent($message, $envelope, 'default');

        $listener->onMessage($event);
        $this->assertSame($message, $event->getMessage());
        $this->assertInstanceOf(TextPart::class, $message->getBody());
        $this->assertInstanceOf(TextPart::class, $event->getMessage()->getBody());
    }

    /**
     * @requires extension openssl
     */
    public function testMessageNotExplicitlyAskedForNonEncryption()
    {
        $repository = $this->createMock(SmimeCertificateRepositoryInterface::class);
        $repository->method('findCertificatePathFor')->willReturn(\dirname(__DIR__).'/Fixtures/sign.crt');
        $listener = new SmimeEncryptedMessageListener($repository);
        $message = new Message(
            new Headers(
                new MailboxListHeader('From', [new Address('sender@example.com')]),
            ),
            new TextPart('hello')
        );
        $envelope = new Envelope(new Address('sender@example.com'), [
            new Address('r1@example.com'),
            new Address('r2@example.com'),
        ]);
        $event = new MessageEvent($message, $envelope, 'default');

        $listener->onMessage($event);
        $this->assertSame($message, $event->getMessage());
        $this->assertInstanceOf(TextPart::class, $message->getBody());
        $this->assertInstanceOf(TextPart::class, $event->getMessage()->getBody());
    }
}
