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
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\EventListener\PgpMimeEncryptedMessageListener;
use Symfony\Component\Mailer\EventListener\PgpMimeSignedMessageListener;
use Symfony\Component\Mailer\EventListener\PgpPublicKeyRepositoryInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Crypto\PgpEncrypter;
use Symfony\Component\Mime\Crypto\PgpSigner;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Header\MailboxListHeader;
use Symfony\Component\Mime\Header\UnstructuredHeader;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\Multipart\PgpEncryptedPart;
use Symfony\Component\Mime\Part\Multipart\PgpSignedPart;
use Symfony\Component\Mime\Part\TextPart;
use Symfony\Component\Process\ExecutableFinder;

class PgpMimeSignAndEncryptListenerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(PgpEncrypter::class)) {
            $this->markTestSkipped('PGP/MIME support requires symfony/mime 8.2 or higher.');
        }

        if (!(new ExecutableFinder())->find('gpg')) {
            $this->markTestSkipped('The "gpg" binary is not available.');
        }
    }

    #[\PHPUnit\Framework\Attributes\RequiresPhpExtension('openssl')]
    public function testMessageIsSignedBeforeBeingEncrypted()
    {
        $signer = new PgpSigner(
            \dirname(__DIR__).'/Fixtures/pgp_secret_key.asc',
            \dirname(__DIR__).'/Fixtures/pgp_public_key.asc',
            'test1234'
        );
        $repository = $this->createStub(PgpPublicKeyRepositoryInterface::class);
        $repository->method('findPublicKeyPathFor')->willReturn(\dirname(__DIR__).'/Fixtures/pgp_public_key.asc');

        $encrypter = new PgpEncrypter();

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new PgpMimeEncryptedMessageListener($repository, $encrypter->encrypt(...)));
        $dispatcher->addSubscriber(new PgpMimeSignedMessageListener($signer->sign(...)));

        $message = new Message(
            new Headers(
                new MailboxListHeader('From', [new Address('pgp@pulli.dev')]),
                new UnstructuredHeader('X-Pgp-Sign', 'true'),
                new UnstructuredHeader('X-Pgp-Encrypt', 'true'),
            ),
            new TextPart('hello')
        );
        $envelope = new Envelope(new Address('pgp@pulli.dev'), [new Address('pgp@pulli.dev')]);
        $event = new MessageEvent($message, $envelope, 'default');

        $dispatcher->dispatch($event);

        $body = $event->getMessage()->getBody();
        $this->assertInstanceOf(PgpEncryptedPart::class, $body);
        $this->assertNotInstanceOf(PgpSignedPart::class, $body);
        $this->assertFalse($event->getMessage()->getHeaders()->has('X-Pgp-Sign'));
        $this->assertFalse($event->getMessage()->getHeaders()->has('X-Pgp-Encrypt'));
    }
}
