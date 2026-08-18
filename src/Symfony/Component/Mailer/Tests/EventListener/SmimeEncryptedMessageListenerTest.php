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

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\EventListener\DkimSignedMessageListener;
use Symfony\Component\Mailer\EventListener\SmimeCertificateRepositoryInterface;
use Symfony\Component\Mailer\EventListener\SmimeEncryptedMessageListener;
use Symfony\Component\Mailer\EventListener\SmimeSignedMessageListener;
use Symfony\Component\Mailer\Exception\RuntimeException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Header\MailboxListHeader;
use Symfony\Component\Mime\Header\UnstructuredHeader;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\SMimePart;
use Symfony\Component\Mime\Part\TextPart;

class SmimeEncryptedMessageListenerTest extends TestCase
{
    #[RequiresPhpExtension('openssl')]
    public function testSmimeMessageEncryptionProcess()
    {
        $repository = $this->createStub(SmimeCertificateRepositoryInterface::class);
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

    #[Group('legacy')]
    #[IgnoreDeprecations]
    #[RequiresPhpExtension('openssl')]
    public function testMessageNotEncryptedWhenOneRecipientCertificateIsMissing()
    {
        $this->expectUserDeprecationMessage('Since symfony/mailer 8.2: Sending an S/MIME message unencrypted because a recipient has no certificate is deprecated and will throw in 9.0; set the "on_missing_certificate" option (or the "X-SMime-Encrypt" header) to "fail", "encrypt" or "skip".');

        $repository = $this->createStub(SmimeCertificateRepositoryInterface::class);
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

    #[RequiresPhpExtension('openssl')]
    public function testMessageNotExplicitlyAskedForNonEncryption()
    {
        $repository = $this->createStub(SmimeCertificateRepositoryInterface::class);
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

    #[RequiresPhpExtension('openssl')]
    public function testFailModeThrowsWhenARecipientHasNoCertificate()
    {
        $repository = $this->createStub(SmimeCertificateRepositoryInterface::class);
        $repository->method('findCertificatePathFor')->willReturnCallback(
            static fn (string $email): ?string => 'r1@example.com' === $email ? \dirname(__DIR__).'/Fixtures/sign.crt' : null
        );
        $listener = new SmimeEncryptedMessageListener($repository, null, SmimeEncryptedMessageListener::ON_MISSING_CERTIFICATE_FAIL);
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

        $this->expectException(RuntimeException::class);
        $listener->onMessage($event);
    }

    #[RequiresPhpExtension('openssl')]
    public function testEncryptModeEncryptsForRecipientsWithCertificate()
    {
        $repository = $this->createStub(SmimeCertificateRepositoryInterface::class);
        $repository->method('findCertificatePathFor')->willReturnCallback(
            static fn (string $email): ?string => 'r1@example.com' === $email ? \dirname(__DIR__).'/Fixtures/sign.crt' : null
        );
        $listener = new SmimeEncryptedMessageListener($repository, null, SmimeEncryptedMessageListener::ON_MISSING_CERTIFICATE_ENCRYPT);
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
        $this->assertInstanceOf(SMimePart::class, $event->getMessage()->getBody());
        $this->assertSame(['r1@example.com', 'r2@example.com'], array_map(static fn (Address $r) => $r->getAddress(), $event->getEnvelope()->getRecipients()));
    }

    #[RequiresPhpExtension('openssl')]
    public function testSkipModeDropsRecipientsWithoutCertificate()
    {
        $repository = $this->createStub(SmimeCertificateRepositoryInterface::class);
        $repository->method('findCertificatePathFor')->willReturnCallback(
            static fn (string $email): ?string => 'r1@example.com' === $email ? \dirname(__DIR__).'/Fixtures/sign.crt' : null
        );
        $listener = new SmimeEncryptedMessageListener($repository, null, SmimeEncryptedMessageListener::ON_MISSING_CERTIFICATE_SKIP);
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
        $this->assertInstanceOf(SMimePart::class, $event->getMessage()->getBody());
        $this->assertSame(['r1@example.com'], array_map(static fn (Address $r) => $r->getAddress(), $event->getEnvelope()->getRecipients()));
    }

    #[RequiresPhpExtension('openssl')]
    public function testHeaderValueOverridesTheDefaultBehavior()
    {
        $repository = $this->createStub(SmimeCertificateRepositoryInterface::class);
        $repository->method('findCertificatePathFor')->willReturnCallback(
            static fn (string $email): ?string => 'r1@example.com' === $email ? \dirname(__DIR__).'/Fixtures/sign.crt' : null
        );
        $listener = new SmimeEncryptedMessageListener($repository);
        $message = new Message(
            new Headers(
                new MailboxListHeader('From', [new Address('sender@example.com')]),
                new UnstructuredHeader('X-SMime-Encrypt', SmimeEncryptedMessageListener::ON_MISSING_CERTIFICATE_FAIL),
            ),
            new TextPart('hello')
        );
        $envelope = new Envelope(new Address('sender@example.com'), [
            new Address('r1@example.com'),
            new Address('r2@example.com'),
        ]);
        $event = new MessageEvent($message, $envelope, 'default');

        $this->expectException(RuntimeException::class);
        $listener->onMessage($event);
    }

    #[RequiresPhpExtension('openssl')]
    public function testEncryptModeThrowsWhenNoRecipientHasCertificate()
    {
        $repository = $this->createStub(SmimeCertificateRepositoryInterface::class);
        $repository->method('findCertificatePathFor')->willReturn(null);
        $listener = new SmimeEncryptedMessageListener($repository, null, SmimeEncryptedMessageListener::ON_MISSING_CERTIFICATE_ENCRYPT);
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

        $this->expectException(RuntimeException::class);
        $listener->onMessage($event);
    }

    #[RequiresPhpExtension('openssl')]
    public function testSkipModeThrowsWhenNoRecipientHasCertificate()
    {
        $repository = $this->createStub(SmimeCertificateRepositoryInterface::class);
        $repository->method('findCertificatePathFor')->willReturn(null);
        $listener = new SmimeEncryptedMessageListener($repository, null, SmimeEncryptedMessageListener::ON_MISSING_CERTIFICATE_SKIP);
        $message = new Message(
            new Headers(
                new MailboxListHeader('From', [new Address('sender@example.com')]),
                new UnstructuredHeader('X-SMime-Encrypt', 'true'),
            ),
            new TextPart('hello')
        );
        $envelope = new Envelope(new Address('sender@example.com'), [new Address('r1@example.com')]);
        $event = new MessageEvent($message, $envelope, 'default');

        $this->expectException(RuntimeException::class);
        $listener->onMessage($event);
    }

    #[RequiresPhpExtension('openssl')]
    public function testSenderCertificateIsIncludedWhenAvailable()
    {
        $queried = [];
        $repository = new class($queried) implements SmimeCertificateRepositoryInterface {
            public function __construct(public array &$queried)
            {
            }

            public function findCertificatePathFor(string $email): ?string
            {
                $this->queried[] = $email;

                return \dirname(__DIR__).'/Fixtures/sign.crt';
            }
        };
        $listener = new SmimeEncryptedMessageListener($repository, encryptForSender: true);
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
        $this->assertInstanceOf(SMimePart::class, $event->getMessage()->getBody());
        $this->assertContains('sender@example.com', $queried);
    }

    #[RequiresPhpExtension('openssl')]
    public function testSenderCertificateIsNotIncludedByDefault()
    {
        $queried = [];
        $repository = new class($queried) implements SmimeCertificateRepositoryInterface {
            public function __construct(public array &$queried)
            {
            }

            public function findCertificatePathFor(string $email): ?string
            {
                $this->queried[] = $email;

                return \dirname(__DIR__).'/Fixtures/sign.crt';
            }
        };
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
        $this->assertInstanceOf(SMimePart::class, $event->getMessage()->getBody());
        $this->assertNotContains('sender@example.com', $queried);
    }

    #[RequiresPhpExtension('openssl')]
    public function testTheHeaderIsKeptWhenTheMessageIsNotSent()
    {
        $repository = $this->createStub(SmimeCertificateRepositoryInterface::class);
        $repository->method('findCertificatePathFor')->willReturn(null);
        $listener = new SmimeEncryptedMessageListener($repository, null, SmimeEncryptedMessageListener::ON_MISSING_CERTIFICATE_FAIL);
        $message = new Message(
            new Headers(
                new MailboxListHeader('From', [new Address('sender@example.com')]),
                new UnstructuredHeader('X-SMime-Encrypt', 'true'),
            ),
            new TextPart('hello')
        );
        $event = new MessageEvent($message, new Envelope(new Address('sender@example.com'), [new Address('r1@example.com')]), 'default');

        try {
            $listener->onMessage($event);
            $this->fail('An exception should have been thrown.');
        } catch (RuntimeException) {
        }

        $this->assertTrue($message->getHeaders()->has('X-SMime-Encrypt'), 'The message must not be mutated when it is not sent.');
    }

    #[RequiresPhpExtension('openssl')]
    public function testHeaderCannotDowngradeToSendUnencrypted()
    {
        $repository = $this->createStub(SmimeCertificateRepositoryInterface::class);
        $repository->method('findCertificatePathFor')->willReturnCallback(
            static fn (string $email): ?string => 'r1@example.com' === $email ? \dirname(__DIR__).'/Fixtures/sign.crt' : null
        );
        $listener = new SmimeEncryptedMessageListener($repository, null, SmimeEncryptedMessageListener::ON_MISSING_CERTIFICATE_FAIL);
        $message = new Message(
            new Headers(
                new MailboxListHeader('From', [new Address('sender@example.com')]),
                new UnstructuredHeader('X-SMime-Encrypt', 'send_unencrypted'),
            ),
            new TextPart('hello')
        );
        $envelope = new Envelope(new Address('sender@example.com'), [
            new Address('r1@example.com'),
            new Address('r2@example.com'),
        ]);
        $event = new MessageEvent($message, $envelope, 'default');

        $this->expectException(RuntimeException::class);
        $listener->onMessage($event);
    }

    #[RequiresPhpExtension('openssl')]
    public function testMissingRecipientsAreLogged()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with(
            'Some recipients have no S/MIME certificate.',
            ['recipients' => ['r2@example.com'], 'on_missing_certificate' => 'encrypt'],
        );
        $repository = $this->createStub(SmimeCertificateRepositoryInterface::class);
        $repository->method('findCertificatePathFor')->willReturnCallback(
            static fn (string $email): ?string => 'r1@example.com' === $email ? \dirname(__DIR__).'/Fixtures/sign.crt' : null
        );
        $listener = new SmimeEncryptedMessageListener($repository, null, SmimeEncryptedMessageListener::ON_MISSING_CERTIFICATE_ENCRYPT, logger: $logger);
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
        $this->assertInstanceOf(SMimePart::class, $event->getMessage()->getBody());
    }

    public function testEncryptionRunsAfterSigning()
    {
        $this->assertGreaterThan(SmimeEncryptedMessageListener::PRIORITY, SmimeSignedMessageListener::PRIORITY);
        $this->assertGreaterThan(DkimSignedMessageListener::PRIORITY, SmimeEncryptedMessageListener::PRIORITY);
    }
}
