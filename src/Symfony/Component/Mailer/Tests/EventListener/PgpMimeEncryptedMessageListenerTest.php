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
use Symfony\Component\Mailer\EventListener\PgpMimeEncryptedMessageListener;
use Symfony\Component\Mailer\EventListener\PgpPublicKeyRepositoryInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Header\MailboxListHeader;
use Symfony\Component\Mime\Header\UnstructuredHeader;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\TextPart;
use Symfony\Component\MimePgp\Exception\KeyNotFoundException;
use Symfony\Component\MimePgp\Mime\Part\Multipart\PgpEncryptedPart;

class PgpMimeEncryptedMessageListenerTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\RequiresPhpExtension('openssl')]
    public function testPgpMessageEncryptionProcess()
    {
        $repository = $this->createMock(PgpPublicKeyRepositoryInterface::class);
        $repository->method('findPublicKeyPathFor')->willReturnCallback(static fn (string $address) => 'pgp@pulli.dev' === $address ? \dirname(__DIR__).'/Fixtures/pgp_public_key.asc' : null);
        $listener = new PgpMimeEncryptedMessageListener($repository);
        $message = new Message(
            new Headers(
                new MailboxListHeader('From', [new Address('sender@example.com')]),
                new UnstructuredHeader('X-Pgp-Encrypt', 'true'),
            ),
            new TextPart('hello')
        );
        $envelope = new Envelope(new Address('sender@example.com'), [new Address('pgp@pulli.dev')]);
        $event = new MessageEvent($message, $envelope, 'default');

        $listener->onMessage($event);
        $this->assertNotSame($message, $event->getMessage());
        $this->assertInstanceOf(TextPart::class, $message->getBody());
        $this->assertInstanceOf(PgpEncryptedPart::class, $event->getMessage()->getBody());
        $this->assertFalse($event->getMessage()->getHeaders()->has('X-Pgp-Encrypt'));
    }

    #[\PHPUnit\Framework\Attributes\RequiresPhpExtension('openssl')]
    public function testPgpMessageEncryptionWithSenderKey()
    {
        $repository = $this->createMock(PgpPublicKeyRepositoryInterface::class);
        $repository->method('findPublicKeyPathFor')->willReturn(\dirname(__DIR__).'/Fixtures/pgp_public_key.asc');
        $listener = new PgpMimeEncryptedMessageListener($repository);
        $message = new Message(
            new Headers(
                new MailboxListHeader('From', [new Address('pgp@pulli.dev')]),
                new UnstructuredHeader('X-Pgp-Encrypt', 'true'),
            ),
            new TextPart('hello')
        );
        $envelope = new Envelope(new Address('pgp@pulli.dev'), [new Address('pgp@pulli.dev')]);
        $event = new MessageEvent($message, $envelope, 'default');

        $listener->onMessage($event);
        $this->assertNotSame($message, $event->getMessage());
        $this->assertInstanceOf(TextPart::class, $message->getBody());
        $this->assertInstanceOf(PgpEncryptedPart::class, $event->getMessage()->getBody());
        $this->assertFalse($event->getMessage()->getHeaders()->has('X-Pgp-Encrypt'));
    }

    #[\PHPUnit\Framework\Attributes\RequiresPhpExtension('openssl')]
    public function testExceptionThrownWhenRecipientKeyMissingByDefault()
    {
        $repository = $this->createMock(PgpPublicKeyRepositoryInterface::class);
        $repository->method('findPublicKeyPathFor')->willReturn(null);
        $listener = new PgpMimeEncryptedMessageListener($repository);
        $message = new Message(
            new Headers(
                new MailboxListHeader('From', [new Address('sender@example.com')]),
                new UnstructuredHeader('X-Pgp-Encrypt', 'true'),
            ),
            new TextPart('hello')
        );
        $envelope = new Envelope(new Address('sender@example.com'), [new Address('r1@example.com')]);
        $event = new MessageEvent($message, $envelope, 'default');

        $this->expectException(KeyNotFoundException::class);
        $this->expectExceptionMessage('No PGP public key found for recipient "r1@example.com".');

        $listener->onMessage($event);
    }

    #[\PHPUnit\Framework\Attributes\RequiresPhpExtension('openssl')]
    public function testMessageNotEncryptedWhenOneRecipientCertificateIsMissingAndFailOnMissingKeyFalse()
    {
        $repository = $this->createMock(PgpPublicKeyRepositoryInterface::class);
        $repository->method('findPublicKeyPathFor')->willReturnOnConsecutiveCalls(\dirname(__DIR__).'/Fixtures/pgp_public_key.asc', null);
        $listener = new PgpMimeEncryptedMessageListener($repository, 'gpg', 'AES256', 60, false);
        $message = new Message(
            new Headers(
                new MailboxListHeader('From', [new Address('sender@example.com')]),
                new UnstructuredHeader('X-Pgp-Encrypt', 'true'),
            ),
            new TextPart('hello')
        );
        $envelope = new Envelope(new Address('sender@example.com'), [
            new Address('pgp@pulli.dev'),
            new Address('r2@example.com'),
        ]);
        $event = new MessageEvent($message, $envelope, 'default');

        $listener->onMessage($event);
        $this->assertSame($message, $event->getMessage());
        $this->assertInstanceOf(TextPart::class, $message->getBody());
        $this->assertInstanceOf(TextPart::class, $event->getMessage()->getBody());
    }

    #[\PHPUnit\Framework\Attributes\RequiresPhpExtension('openssl')]
    public function testMessageNotExplicitlyAskedForNonEncryption()
    {
        $repository = $this->createMock(PgpPublicKeyRepositoryInterface::class);
        $repository->method('findPublicKeyPathFor')->willReturn(\dirname(__DIR__).'/Fixtures/pgp_public_key.asc');
        $listener = new PgpMimeEncryptedMessageListener($repository);
        $message = new Message(
            new Headers(
                new MailboxListHeader('From', [new Address('sender@example.com')]),
            ),
            new TextPart('hello')
        );
        $envelope = new Envelope(new Address('sender@example.com'), [
            new Address('pgp@pulli.dev'),
            new Address('r2@example.com'),
        ]);
        $event = new MessageEvent($message, $envelope, 'default');

        $listener->onMessage($event);
        $this->assertSame($message, $event->getMessage());
        $this->assertInstanceOf(TextPart::class, $message->getBody());
        $this->assertInstanceOf(TextPart::class, $event->getMessage()->getBody());
    }
}
