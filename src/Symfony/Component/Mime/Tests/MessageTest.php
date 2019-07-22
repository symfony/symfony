<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Header\MailboxListHeader;
use Symfony\Component\Mime\Header\UnstructuredHeader;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\NamedAddress;
use Symfony\Component\Mime\Part\TextPart;

class MessageTest extends TestCase
{
    public function testConstruct()
    {
        $m = new Message();
        $this->assertNull($m->getBody());
        $this->assertEquals(new Headers(), $m->getHeaders());

        $m = new Message($h = (new Headers())->addDateHeader('Date', new \DateTime()), $b = new TextPart('content'));
        $this->assertSame($b, $m->getBody());
        $this->assertEquals($h, $m->getHeaders());

        $m = new Message();
        $m->setBody($b);
        $m->setHeaders($h);
        $this->assertSame($b, $m->getBody());
        $this->assertSame($h, $m->getHeaders());
    }

    public function testGetPreparedHeadersThrowsWhenNoFrom()
    {
        $this->expectException(\LogicException::class);
        (new Message())->getPreparedHeaders();
    }

    public function testGetPreparedHeadersCloneHeaders()
    {
        $message = new Message();
        $message->getHeaders()->addMailboxListHeader('From', ['fabien@symfony.com']);
        $this->assertNotSame($message->getPreparedHeaders(), $message->getHeaders());
    }

    public function testGetPreparedHeadersSetRequiredHeaders()
    {
        $message = new Message();
        $message->getHeaders()->addMailboxListHeader('From', ['fabien@symfony.com']);
        $headers = $message->getPreparedHeaders();
        $this->assertTrue($headers->has('MIME-Version'));
        $this->assertTrue($headers->has('Message-ID'));
        $this->assertTrue($headers->has('Date'));
        $this->assertFalse($headers->has('Bcc'));
    }

    public function testGetPreparedHeaders()
    {
        $message = new Message();
        $message->getHeaders()->addMailboxListHeader('From', ['fabien@symfony.com']);
        $h = $message->getPreparedHeaders();
        $this->assertCount(4, iterator_to_array($h->all()));
        $this->assertEquals(new MailboxListHeader('From', [new Address('fabien@symfony.com')]), $h->get('From'));
        $this->assertEquals(new UnstructuredHeader('MIME-Version', '1.0'), $h->get('mime-version'));
        $this->assertTrue($h->has('Message-Id'));
        $this->assertTrue($h->has('Date'));

        $message = new Message();
        $message->getHeaders()->addMailboxListHeader('From', ['fabien@symfony.com']);
        $message->getHeaders()->addDateHeader('Date', $n = new \DateTimeImmutable());
        $this->assertEquals($n, $message->getPreparedHeaders()->get('Date')->getDateTime());

        $message = new Message();
        $message->getHeaders()->addMailboxListHeader('From', ['fabien@symfony.com']);
        $message->getHeaders()->addMailboxListHeader('Bcc', ['fabien@symfony.com']);
        $this->assertNull($message->getPreparedHeaders()->get('Bcc'));
    }

    public function testGetPreparedHeadersWithNoFrom()
    {
        $this->expectException(\LogicException::class);
        (new Message())->getPreparedHeaders();
    }

    public function testGetPreparedHeadersWithNamedFrom()
    {
        $message = new Message();
        $message->getHeaders()->addMailboxListHeader('From', [new NamedAddress('fabien@symfony.com', 'Fabien')]);
        $h = $message->getPreparedHeaders();
        $this->assertEquals(new MailboxListHeader('From', [new NamedAddress('fabien@symfony.com', 'Fabien')]), $h->get('From'));
        $this->assertTrue($h->has('Message-Id'));
    }

    public function testGetPreparedHeadersHasSenderWhenNeeded()
    {
        $message = new Message();
        $message->getHeaders()->addMailboxListHeader('From', ['fabien@symfony.com']);
        $this->assertNull($message->getPreparedHeaders()->get('Sender'));

        $message = new Message();
        $message->getHeaders()->addMailboxListHeader('From', ['fabien@symfony.com', 'lucas@symfony.com']);
        $this->assertEquals('fabien@symfony.com', $message->getPreparedHeaders()->get('Sender')->getAddress()->getAddress());

        $message = new Message();
        $message->getHeaders()->addMailboxListHeader('From', ['fabien@symfony.com', 'lucas@symfony.com']);
        $message->getHeaders()->addMailboxHeader('Sender', 'thomas@symfony.com');
        $this->assertEquals('thomas@symfony.com', $message->getPreparedHeaders()->get('Sender')->getAddress()->getAddress());
    }

    public function testToString()
    {
        $message = new Message();
        $message->getHeaders()->addMailboxListHeader('From', ['fabien@symfony.com']);
        $expected = <<<EOF
From: fabien@symfony.com
MIME-Version: 1.0
Date: %s
Message-ID: <%s@symfony.com>
Content-Type: text/plain; charset=utf-8
Content-Transfer-Encoding: quoted-printable


EOF;
        $this->assertStringMatchesFormat($expected, str_replace("\r\n", "\n", $message->toString()));
        $this->assertStringMatchesFormat($expected, str_replace("\r\n", "\n", implode('', iterator_to_array($message->toIterable(), false))));

        $message = new Message(null, new TextPart('content'));
        $message->getHeaders()->addMailboxListHeader('From', ['fabien@symfony.com']);
        $expected = <<<EOF
From: fabien@symfony.com
MIME-Version: 1.0
Date: %s
Message-ID: <%s@symfony.com>
Content-Type: text/plain; charset=utf-8
Content-Transfer-Encoding: quoted-printable

content
EOF;
        $this->assertStringMatchesFormat($expected, str_replace("\r\n", "\n", $message->toString()));
        $this->assertStringMatchesFormat($expected, str_replace("\r\n", "\n", implode('', iterator_to_array($message->toIterable(), false))));
    }
}
