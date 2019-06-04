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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\SmtpEnvelope;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\NamedAddress;
use Symfony\Component\Mime\RawMessage;

class SmtpEnvelopeTest extends TestCase
{
    public function testConstructorWithAddressSender()
    {
        $e = new SmtpEnvelope(new Address('fabien@symfony.com'), [new Address('thomas@symfony.com')]);
        $this->assertEquals(new Address('fabien@symfony.com'), $e->getSender());
    }

    public function testConstructorWithNamedAddressSender()
    {
        $e = new SmtpEnvelope(new NamedAddress('fabien@symfony.com', 'Fabien'), [new Address('thomas@symfony.com')]);
        $this->assertEquals(new Address('fabien@symfony.com'), $e->getSender());
    }

    public function testConstructorWithAddressRecipients()
    {
        $e = new SmtpEnvelope(new Address('fabien@symfony.com'), [new Address('thomas@symfony.com'), new NamedAddress('lucas@symfony.com', 'Lucas')]);
        $this->assertEquals([new Address('thomas@symfony.com'), new Address('lucas@symfony.com')], $e->getRecipients());
    }

    public function testConstructorWithNoRecipients()
    {
        $this->expectException(\InvalidArgumentException::class);
        $e = new SmtpEnvelope(new Address('fabien@symfony.com'), []);
    }

    public function testConstructorWithWrongRecipients()
    {
        $this->expectException(\InvalidArgumentException::class);
        $e = new SmtpEnvelope(new Address('fabien@symfony.com'), ['lucas@symfony.com']);
    }

    public function testSenderFromHeaders()
    {
        $headers = new Headers();
        $headers->addPathHeader('Return-Path', 'return@symfony.com');
        $headers->addMailboxListHeader('To', ['from@symfony.com']);
        $e = SmtpEnvelope::create(new Message($headers));
        $this->assertEquals('return@symfony.com', $e->getSender()->getAddress());

        $headers = new Headers();
        $headers->addMailboxHeader('Sender', 'sender@symfony.com');
        $headers->addMailboxListHeader('To', ['from@symfony.com']);
        $e = SmtpEnvelope::create(new Message($headers));
        $this->assertEquals('sender@symfony.com', $e->getSender()->getAddress());

        $headers = new Headers();
        $headers->addMailboxListHeader('From', ['from@symfony.com', 'some@symfony.com']);
        $headers->addMailboxListHeader('To', ['from@symfony.com']);
        $e = SmtpEnvelope::create(new Message($headers));
        $this->assertEquals('from@symfony.com', $e->getSender()->getAddress());
    }

    public function testSenderFromHeadersWithoutFrom()
    {
        $headers = new Headers();
        $headers->addMailboxListHeader('To', ['from@symfony.com']);
        $e = SmtpEnvelope::create($message = new Message($headers));
        $message->getHeaders()->addMailboxListHeader('From', ['from@symfony.com']);
        $this->assertEquals('from@symfony.com', $e->getSender()->getAddress());
    }

    public function testRecipientsFromHeaders()
    {
        $headers = new Headers();
        $headers->addPathHeader('Return-Path', 'return@symfony.com');
        $headers->addMailboxListHeader('To', ['to@symfony.com']);
        $headers->addMailboxListHeader('Cc', ['cc@symfony.com']);
        $headers->addMailboxListHeader('Bcc', ['bcc@symfony.com']);
        $e = SmtpEnvelope::create(new Message($headers));
        $this->assertEquals([new Address('to@symfony.com'), new Address('cc@symfony.com'), new Address('bcc@symfony.com')], $e->getRecipients());
    }
}
