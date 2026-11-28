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

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

class SentMessageTest extends TestCase
{
    public function test()
    {
        $m = new SentMessage($r = new RawMessage('Email'), $e = new Envelope(new Address('fabien@example.com'), [new Address('helene@example.com')]));
        $this->assertSame($r, $m->getOriginalMessage());
        $this->assertSame($r, $m->getMessage());
        $this->assertSame($e, $m->getEnvelope());
        $this->assertEquals($r->toString(), $m->toString());
        $this->assertEquals($r->toIterable(), $m->toIterable());

        $m = new SentMessage($r = (new Email())->from('fabien@example.com')->to('helene@example.com')->text('text'), $e);
        $this->assertSame($r, $m->getOriginalMessage());
        $this->assertNotSame($r, $m->getMessage());
    }

    public function testTransportMessageIdAndMessageIdHeader()
    {
        $envelope = new Envelope(new Address('fabien@example.com'), [new Address('helene@example.com')]);

        $m = new SentMessage($email = (new Email())->from('fabien@example.com')->to('helene@example.com')->text('text'), $envelope);
        $this->assertFalse($email->getHeaders()->has('Message-ID'));
        $generated = $m->getMessageIdHeader();
        $this->assertStringContainsString('@example.com', $generated);
        $this->assertStringContainsString('Message-ID: <'.$generated.'>', $m->toString());
        $this->assertSame($generated, $m->getTransportMessageId());

        $m->setTransportMessageId('D191E40505');
        $this->assertSame('D191E40505', $m->getTransportMessageId());
        $this->assertSame($generated, $m->getMessageIdHeader());

        $email = (new Email())->from('fabien@example.com')->to('helene@example.com')->text('text');
        $email->getHeaders()->addIdHeader('Message-ID', 'mine@example.com');
        $m = new SentMessage($email, $envelope);
        $this->assertSame('mine@example.com', $m->getMessageIdHeader());

        $m = new SentMessage(new RawMessage('Email'), $envelope);
        $this->assertNull($m->getMessageIdHeader());
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testSetMessageIdIsDeprecated()
    {
        $m = new SentMessage((new Email())->from('fabien@example.com')->to('helene@example.com')->text('text'), new Envelope(new Address('fabien@example.com'), [new Address('helene@example.com')]));

        $this->expectUserDeprecationMessage('Since symfony/mailer 8.3: Method "Symfony\Component\Mailer\SentMessage::setMessageId()" is deprecated, use "setTransportMessageId()" instead.');

        $m->setMessageId('D191E40505');

        $this->assertSame('D191E40505', $m->getTransportMessageId());
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testGetMessageIdIsDeprecated()
    {
        $m = new SentMessage((new Email())->from('fabien@example.com')->to('helene@example.com')->text('text'), new Envelope(new Address('fabien@example.com'), [new Address('helene@example.com')]));
        $m->setTransportMessageId('D191E40505');

        $this->expectUserDeprecationMessage('Since symfony/mailer 8.3: Method "Symfony\Component\Mailer\SentMessage::getMessageId()" is deprecated, use "getTransportMessageId()" or "getMessageIdHeader()" instead.');

        $this->assertSame('D191E40505', $m->getMessageId());
    }
}
