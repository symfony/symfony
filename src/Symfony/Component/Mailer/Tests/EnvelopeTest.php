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
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\LogicException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;

class EnvelopeTest extends TestCase
{
    public function testConstructorWithAddressSender()
    {
        $e = new Envelope(new Address('fabien@symfony.com'), [new Address('thomas@symfony.com')]);
        $this->assertEquals(new Address('fabien@symfony.com'), $e->getSender());
    }

    public function testConstructorWithNamedAddressSender()
    {
        $e = new Envelope(new Address('fabien@symfony.com', 'Fabien'), [new Address('thomas@symfony.com')]);
        $this->assertEquals(new Address('fabien@symfony.com'), $e->getSender());
    }

    public function testConstructorWithAddressRecipients()
    {
        $e = new Envelope(new Address('fabien@symfony.com'), [new Address('thomas@symfony.com'), new Address('lucas@symfony.com', 'Lucas')]);
        $this->assertEquals([new Address('thomas@symfony.com'), new Address('lucas@symfony.com')], $e->getRecipients());
    }

    public function testConstructorWithNoRecipients()
    {
        $this->expectException(\InvalidArgumentException::class);
        new Envelope(new Address('fabien@symfony.com'), []);
    }

    public function testConstructorWithWrongRecipients()
    {
        $this->expectException(\InvalidArgumentException::class);
        new Envelope(new Address('fabien@symfony.com'), ['lucas@symfony.com']);
    }

    public function testSenderFromHeaders()
    {
        $headers = new Headers();
        $headers->addPathHeader('Return-Path', new Address('return@symfony.com', 'return'));
        $headers->addMailboxListHeader('To', ['from@symfony.com']);
        $e = Envelope::create(new Message($headers));
        $this->assertEquals(new Address('return@symfony.com', 'return'), $e->getSender());

        $headers = new Headers();
        $headers->addMailboxHeader('Sender', new Address('sender@symfony.com', 'sender'));
        $headers->addMailboxListHeader('To', ['from@symfony.com']);
        $e = Envelope::create(new Message($headers));
        $this->assertEquals(new Address('sender@symfony.com', 'sender'), $e->getSender());

        $headers = new Headers();
        $headers->addMailboxListHeader('From', [new Address('from@symfony.com', 'from'), 'some@symfony.com']);
        $headers->addMailboxListHeader('To', ['from@symfony.com']);
        $e = Envelope::create(new Message($headers));
        $this->assertEquals(new Address('from@symfony.com', 'from'), $e->getSender());
    }

    public function testSenderFromHeadersWithoutFrom()
    {
        $headers = new Headers();
        $headers->addMailboxListHeader('To', ['from@symfony.com']);
        $e = Envelope::create($message = new Message($headers));
        $message->getHeaders()->addMailboxListHeader('From', [new Address('from@symfony.com', 'from')]);
        $this->assertEquals(new Address('from@symfony.com', 'from'), $e->getSender());
    }

    public function testRecipientsFromHeaders()
    {
        $headers = new Headers();
        $headers->addPathHeader('Return-Path', 'return@symfony.com');
        $headers->addMailboxListHeader('To', [new Address('to@symfony.com')]);
        $headers->addMailboxListHeader('Cc', [new Address('cc@symfony.com')]);
        $headers->addMailboxListHeader('Bcc', [new Address('bcc@symfony.com')]);
        $e = Envelope::create(new Message($headers));
        $this->assertEquals([new Address('to@symfony.com'), new Address('cc@symfony.com'), new Address('bcc@symfony.com')], $e->getRecipients());
    }

    public function testRecipientsFromHeadersWithNames()
    {
        $headers = new Headers();
        $headers->addPathHeader('Return-Path', 'return@symfony.com');
        $headers->addMailboxListHeader('To', [new Address('to@symfony.com', 'to')]);
        $headers->addMailboxListHeader('Cc', [new Address('cc@symfony.com', 'cc')]);
        $headers->addMailboxListHeader('Bcc', [new Address('bcc@symfony.com', 'bcc')]);
        $e = Envelope::create(new Message($headers));
        $this->assertEquals([new Address('to@symfony.com', 'to'), new Address('cc@symfony.com', 'cc'), new Address('bcc@symfony.com', 'bcc')], $e->getRecipients());
    }

    public function testFromRawMessages()
    {
        $this->expectException(LogicException::class);

        Envelope::create(new RawMessage('Some raw email message'));
    }
}
