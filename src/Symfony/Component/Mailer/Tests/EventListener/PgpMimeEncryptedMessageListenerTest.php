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
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\EventListener\PgpMimeEncryptedMessageListener;
use Symfony\Component\Mailer\EventListener\PgpPublicKeyRepositoryInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Crypto\PgpEncrypter;
use Symfony\Component\Mime\Exception\KeyNotFoundException;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Header\MailboxListHeader;
use Symfony\Component\Mime\Header\UnstructuredHeader;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\Multipart\MixedPart;
use Symfony\Component\Mime\Part\Multipart\PgpEncryptedPart;
use Symfony\Component\Mime\Part\TextPart;
use Symfony\Component\Process\ExecutableFinder;

class PgpMimeEncryptedMessageListenerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(PgpEncrypter::class)) {
            $this->markTestSkipped('PGP/MIME support requires symfony/mime 8.2 or higher.');
        }
    }

    public function testPgpMessageEncryptionProcess()
    {
        $repository = $this->createStub(PgpPublicKeyRepositoryInterface::class);
        $repository->method('findPublicKeyPathFor')->willReturnCallback(static fn (string $address) => 'pgp@pulli.dev' === $address ? \dirname(__DIR__).'/Fixtures/pgp_public_key.asc' : null);
        $encrypter = $this->createEncrypter();
        $listener = new PgpMimeEncryptedMessageListener($repository, $encrypter->encrypt(...));
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

    public function testPgpMessageEncryptionWithSenderKey()
    {
        $repository = $this->createStub(PgpPublicKeyRepositoryInterface::class);
        $repository->method('findPublicKeyPathFor')->willReturn(\dirname(__DIR__).'/Fixtures/pgp_public_key.asc');
        $usedKeys = [];
        $listener = new PgpMimeEncryptedMessageListener($repository, static function (Message $m, array $keys) use (&$usedKeys) {
            $usedKeys = $keys;

            return $m;
        }, PgpMimeEncryptedMessageListener::ON_MISSING_KEY_FAIL, true);
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
        $this->assertSame(['pgp@pulli.dev', 'sender@example.com'], array_keys($usedKeys));
        $this->assertFalse($event->getMessage()->getHeaders()->has('X-Pgp-Encrypt'));
    }

    public function testSenderKeyIsNotIncludedByDefault()
    {
        $repository = $this->createStub(PgpPublicKeyRepositoryInterface::class);
        $repository->method('findPublicKeyPathFor')->willReturn(\dirname(__DIR__).'/Fixtures/pgp_public_key.asc');
        $usedKeys = [];
        $listener = new PgpMimeEncryptedMessageListener($repository, static function (Message $m, array $keys) use (&$usedKeys) {
            $usedKeys = $keys;

            return $m;
        });
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
        $this->assertSame(['pgp@pulli.dev'], array_keys($usedKeys));
    }

    public function testExceptionThrownWhenRecipientKeyMissingByDefault()
    {
        $repository = $this->createStub(PgpPublicKeyRepositoryInterface::class);
        $repository->method('findPublicKeyPathFor')->willReturn(null);
        $encrypter = $this->createEncrypter();
        $listener = new PgpMimeEncryptedMessageListener($repository, $encrypter->encrypt(...));
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
        $this->expectExceptionMessage('No PGP public key found for recipient(s) "r1@example.com".');

        $listener->onMessage($event);
    }

    public function testMessageStillEncryptedForRecipientsWithKeyWhenOnMissingKeyEncrypt()
    {
        $repository = $this->createStub(PgpPublicKeyRepositoryInterface::class);
        $repository->method('findPublicKeyPathFor')->willReturnCallback(static fn (string $address) => 'pgp@pulli.dev' === $address ? \dirname(__DIR__).'/Fixtures/pgp_public_key.asc' : null);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with(
            $this->anything(),
            ['recipients' => ['r2@example.com'], 'on_missing_key' => PgpMimeEncryptedMessageListener::ON_MISSING_KEY_ENCRYPT],
        );
        $encrypter = $this->createEncrypter();
        $listener = new PgpMimeEncryptedMessageListener($repository, $encrypter->encrypt(...), PgpMimeEncryptedMessageListener::ON_MISSING_KEY_ENCRYPT, logger: $logger);
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
        $this->assertNotSame($message, $event->getMessage());
        $this->assertInstanceOf(TextPart::class, $message->getBody());
        $this->assertInstanceOf(PgpEncryptedPart::class, $event->getMessage()->getBody());
        $this->assertCount(2, $event->getEnvelope()->getRecipients());
    }

    public function testRecipientsWithoutKeyAreDroppedFromEnvelopeWhenOnMissingKeySkip()
    {
        $repository = $this->createStub(PgpPublicKeyRepositoryInterface::class);
        $repository->method('findPublicKeyPathFor')->willReturnCallback(static fn (string $address) => 'pgp@pulli.dev' === $address ? \dirname(__DIR__).'/Fixtures/pgp_public_key.asc' : null);
        $encrypter = $this->createEncrypter();
        $listener = new PgpMimeEncryptedMessageListener($repository, $encrypter->encrypt(...), PgpMimeEncryptedMessageListener::ON_MISSING_KEY_SKIP);
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
        $this->assertInstanceOf(PgpEncryptedPart::class, $event->getMessage()->getBody());
        $recipients = array_map(static fn (Address $a) => $a->getAddress(), $event->getEnvelope()->getRecipients());
        $this->assertSame(['pgp@pulli.dev'], $recipients);
    }

    public function testHeaderValueOverridesTheConfiguredOnMissingKey()
    {
        $repository = $this->createStub(PgpPublicKeyRepositoryInterface::class);
        $repository->method('findPublicKeyPathFor')->willReturnCallback(static fn (string $address) => 'pgp@pulli.dev' === $address ? \dirname(__DIR__).'/Fixtures/pgp_public_key.asc' : null);
        $encrypter = $this->createEncrypter();
        $listener = new PgpMimeEncryptedMessageListener($repository, $encrypter->encrypt(...), PgpMimeEncryptedMessageListener::ON_MISSING_KEY_FAIL);
        $message = new Message(
            new Headers(
                new MailboxListHeader('From', [new Address('sender@example.com')]),
                new UnstructuredHeader('X-Pgp-Encrypt', PgpMimeEncryptedMessageListener::ON_MISSING_KEY_SKIP),
            ),
            new TextPart('hello')
        );
        $envelope = new Envelope(new Address('sender@example.com'), [
            new Address('pgp@pulli.dev'),
            new Address('r2@example.com'),
        ]);
        $event = new MessageEvent($message, $envelope, 'default');

        $listener->onMessage($event);
        $this->assertInstanceOf(PgpEncryptedPart::class, $event->getMessage()->getBody());
        $recipients = array_map(static fn (Address $a) => $a->getAddress(), $event->getEnvelope()->getRecipients());
        $this->assertSame(['pgp@pulli.dev'], $recipients);
    }

    public function testExceptionWhenNoRecipientHasKeyAndOnMissingKeySkip()
    {
        $repository = $this->createStub(PgpPublicKeyRepositoryInterface::class);
        $repository->method('findPublicKeyPathFor')->willReturn(null);
        $listener = new PgpMimeEncryptedMessageListener($repository, static fn (Message $m, array $keys) => $m, PgpMimeEncryptedMessageListener::ON_MISSING_KEY_SKIP);
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

        $listener->onMessage($event);
    }

    public function testExceptionWhenNoRecipientHasKeyAndOnMissingKeyEncrypt()
    {
        $repository = $this->createStub(PgpPublicKeyRepositoryInterface::class);
        $repository->method('findPublicKeyPathFor')->willReturn(null);
        $listener = new PgpMimeEncryptedMessageListener($repository, static fn (Message $m, array $keys) => $m, PgpMimeEncryptedMessageListener::ON_MISSING_KEY_ENCRYPT);
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
        $this->expectExceptionMessage('No PGP public key found for any recipient.');

        $listener->onMessage($event);
    }

    public function testSenderKeyAloneDoesNotMakeTheMessageEncryptable()
    {
        $repository = $this->createStub(PgpPublicKeyRepositoryInterface::class);
        $repository->method('findPublicKeyPathFor')->willReturnCallback(static fn (string $address) => 'sender@example.com' === $address ? \dirname(__DIR__).'/Fixtures/pgp_public_key.asc' : null);
        $listener = new PgpMimeEncryptedMessageListener($repository, static fn (Message $m, array $keys) => $m, PgpMimeEncryptedMessageListener::ON_MISSING_KEY_ENCRYPT, true);
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

        $listener->onMessage($event);
    }

    public function testTheHeaderIsKeptWhenTheMessageIsNotSent()
    {
        $repository = $this->createStub(PgpPublicKeyRepositoryInterface::class);
        $repository->method('findPublicKeyPathFor')->willReturn(null);
        $listener = new PgpMimeEncryptedMessageListener($repository, static fn (Message $m, array $keys) => $m);
        $message = new Message(
            new Headers(
                new MailboxListHeader('From', [new Address('sender@example.com')]),
                new UnstructuredHeader('X-Pgp-Encrypt', 'true'),
            ),
            new TextPart('hello')
        );
        $envelope = new Envelope(new Address('sender@example.com'), [new Address('r1@example.com')]);
        $event = new MessageEvent($message, $envelope, 'default');

        try {
            $listener->onMessage($event);
            $this->fail('Expected a KeyNotFoundException.');
        } catch (KeyNotFoundException) {
        }

        $this->assertTrue($message->getHeaders()->has('X-Pgp-Encrypt'));
    }

    public function testMessageNotExplicitlyAskedForNonEncryption()
    {
        $repository = $this->createStub(PgpPublicKeyRepositoryInterface::class);
        $repository->method('findPublicKeyPathFor')->willReturn(\dirname(__DIR__).'/Fixtures/pgp_public_key.asc');
        $encrypter = $this->createEncrypter();
        $listener = new PgpMimeEncryptedMessageListener($repository, $encrypter->encrypt(...));
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

    public function testCustomClosureIsUsedForEncryption()
    {
        $repository = $this->createStub(PgpPublicKeyRepositoryInterface::class);
        $repository->method('findPublicKeyPathFor')->willReturn(\dirname(__DIR__).'/Fixtures/pgp_public_key.asc');
        $customBody = new MixedPart(new TextPart('custom-encrypted'));
        $listener = new PgpMimeEncryptedMessageListener(
            $repository,
            static fn (Message $message, array $publicKeys) => new Message($message->getHeaders(), $customBody),
        );
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
        $this->assertSame($customBody, $event->getMessage()->getBody());
    }

    private function createEncrypter(): PgpEncrypter
    {
        if (!(new ExecutableFinder())->find('gpg')) {
            $this->markTestSkipped('The "gpg" binary is not available.');
        }

        return new PgpEncrypter();
    }
}
