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
use Symfony\Component\Mailer\EventListener\PgpMimeSignedMessageListener;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Crypto\PgpSigner;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Header\MailboxListHeader;
use Symfony\Component\Mime\Header\UnstructuredHeader;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\Multipart\MixedPart;
use Symfony\Component\Mime\Part\Multipart\PgpSignedPart;
use Symfony\Component\Mime\Part\TextPart;
use Symfony\Component\Process\ExecutableFinder;

class PgpMimeSignedMessageListenerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(PgpSigner::class)) {
            $this->markTestSkipped('PGP/MIME support requires symfony/mime 8.2 or higher.');
        }

        if (!(new ExecutableFinder())->find('gpg')) {
            $this->markTestSkipped('The "gpg" binary is not available.');
        }
    }

    public function testPgpMimeMessageSigningProcess()
    {
        $signer = new PgpSigner(
            \dirname(__DIR__).'/Fixtures/pgp_secret_key.asc',
            \dirname(__DIR__).'/Fixtures/pgp_public_key.asc',
            'test1234'
        );
        $listener = new PgpMimeSignedMessageListener($signer->sign(...));
        $message = new Message(
            new Headers(
                new MailboxListHeader('From', [new Address('sender@example.com')]),
                new UnstructuredHeader('X-Pgp-Sign', 'true'),
            ),
            new TextPart('hello')
        );
        $envelope = new Envelope(new Address('sender@example.com'), [new Address('r1@example.com')]);
        $event = new MessageEvent($message, $envelope, 'default');

        $listener->onMessage($event);
        $this->assertNotSame($message, $event->getMessage());
        $this->assertInstanceOf(TextPart::class, $message->getBody());
        $this->assertInstanceOf(PgpSignedPart::class, $event->getMessage()->getBody());
        $this->assertFalse($event->getMessage()->getHeaders()->has('X-Pgp-Sign'));
    }

    public function testMessageNotSignedWithoutHeader()
    {
        $signer = new PgpSigner(
            \dirname(__DIR__).'/Fixtures/pgp_secret_key.asc',
            \dirname(__DIR__).'/Fixtures/pgp_public_key.asc',
            'test1234'
        );
        $listener = new PgpMimeSignedMessageListener($signer->sign(...));
        $message = new Message(
            new Headers(
                new MailboxListHeader('From', [new Address('sender@example.com')]),
            ),
            new TextPart('hello')
        );
        $envelope = new Envelope(new Address('sender@example.com'), [new Address('r1@example.com')]);
        $event = new MessageEvent($message, $envelope, 'default');

        $listener->onMessage($event);
        $this->assertSame($message, $event->getMessage());
        $this->assertInstanceOf(TextPart::class, $message->getBody());
    }

    public function testCustomClosureIsUsedForSigning()
    {
        $customBody = new MixedPart(new TextPart('custom-signed'));
        $listener = new PgpMimeSignedMessageListener(static fn (Message $message) => new Message($message->getHeaders(), $customBody));
        $message = new Message(
            new Headers(
                new MailboxListHeader('From', [new Address('sender@example.com')]),
                new UnstructuredHeader('X-Pgp-Sign', 'true'),
            ),
            new TextPart('hello')
        );
        $envelope = new Envelope(new Address('sender@example.com'), [new Address('r1@example.com')]);
        $event = new MessageEvent($message, $envelope, 'default');

        $listener->onMessage($event);
        $this->assertNotSame($message, $event->getMessage());
        $this->assertSame($customBody, $event->getMessage()->getBody());
    }
}
