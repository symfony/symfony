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
use Symfony\Component\Mailer\EventListener\SmimeSignedMessageListener;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Crypto\SMimeSigner;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Header\MailboxListHeader;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\SMimePart;
use Symfony\Component\Mime\Part\TextPart;

class SmimeSignedMessageListenerTest extends TestCase
{
    /**
     * @requires extension openssl
     */
    public function testSmimeMessageSigningProcess()
    {
        $signer = new SMimeSigner(\dirname(__DIR__).'/Fixtures/sign.crt', \dirname(__DIR__).'/Fixtures/sign.key');
        $listener = new SmimeSignedMessageListener($signer);
        $message = new Message(
            new Headers(
                new MailboxListHeader('From', [new Address('sender@example.com')])
            ),
            new TextPart('hello')
        );
        $envelope = new Envelope(new Address('sender@example.com'), [new Address('r1@example.com')]);
        $event = new MessageEvent($message, $envelope, 'default');

        $listener->onMessage($event);
        $this->assertNotSame($message, $event->getMessage());
        $this->assertInstanceOf(TextPart::class, $message->getBody());
        $this->assertInstanceOf(SMimePart::class, $event->getMessage()->getBody());
    }
}
