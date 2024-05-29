<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests\Header;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Header\DateHeader;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Header\IdentificationHeader;
use Symfony\Component\Mime\Header\MailboxListHeader;
use Symfony\Component\Mime\Header\PathHeader;
use Symfony\Component\Mime\Header\UnstructuredHeader;

class HeadersTest extends TestCase
{
    public function testAddMailboxListHeaderDelegatesToFactory()
    {
        $headers = new Headers();
        $headers->addMailboxListHeader('From', ['person@domain']);
        $this->assertNotNull($headers->get('From'));
    }

    public function testAddDateHeaderDelegatesToFactory()
    {
        $dateTime = new \DateTimeImmutable();
        $headers = new Headers();
        $headers->addDateHeader('Date', $dateTime);
        $this->assertNotNull($headers->get('Date'));
    }

    public function testAddTextHeaderDelegatesToFactory()
    {
        $headers = new Headers();
        $headers->addTextHeader('Subject', 'some text');
        $this->assertNotNull($headers->get('Subject'));
    }

    public function testAddParameterizedHeaderDelegatesToFactory()
    {
        $headers = new Headers();
        $headers->addParameterizedHeader('Content-Type', 'text/plain', ['charset' => 'utf-8']);
        $this->assertNotNull($headers->get('Content-Type'));
    }

    public function testAddIdHeaderDelegatesToFactory()
    {
        $headers = new Headers();
        $headers->addIdHeader('Message-ID', 'some@id');
        $this->assertNotNull($headers->get('Message-ID'));
    }

    public function testAddPathHeaderDelegatesToFactory()
    {
        $headers = new Headers();
        $headers->addPathHeader('Return-Path', 'some@path');
        $this->assertNotNull($headers->get('Return-Path'));
    }

    public function testAddHeader()
    {
        $headers = new Headers();
        $headers->addHeader('from', ['from@example.com']);
        $headers->addHeader('reply-to', 'reply@example.com');
        $headers->addHeader('return-path', 'return@example.com');
        $headers->addHeader('foo', 'bar');
        $headers->addHeader('date', $now = new \DateTimeImmutable());
        $headers->addHeader('message-id', 'id@id');

        $this->assertInstanceOf(MailboxListHeader::class, $headers->get('from'));
        $this->assertEquals([new Address('from@example.com')], $headers->get('from')->getBody());

        $this->assertInstanceOf(MailboxListHeader::class, $headers->get('reply-to'));
        $this->assertEquals([new Address('reply@example.com')], $headers->get('reply-to')->getBody());

        $this->assertInstanceOf(PathHeader::class, $headers->get('return-path'));
        $this->assertEquals(new Address('return@example.com'), $headers->get('return-path')->getBody());

        $this->assertInstanceOf(UnstructuredHeader::class, $headers->get('foo'));
        $this->assertSame('bar', $headers->get('foo')->getBody());

        $this->assertInstanceOf(DateHeader::class, $headers->get('date'));
        $this->assertEquals($now, $headers->get('date')->getBody());

        $this->assertInstanceOf(IdentificationHeader::class, $headers->get('message-id'));
        $this->assertSame(['id@id'], $headers->get('message-id')->getBody());
    }

    public function testHasReturnsFalseWhenNoHeaders()
    {
        $headers = new Headers();
        $this->assertFalse($headers->has('Some-Header'));
    }

    public function testAddedMailboxListHeaderIsSeenByHas()
    {
        $headers = new Headers();
        $headers->addMailboxListHeader('From', ['person@domain']);
        $this->assertTrue($headers->has('From'));
    }

    public function testAddedDateHeaderIsSeenByHas()
    {
        $dateTime = new \DateTimeImmutable();
        $headers = new Headers();
        $headers->addDateHeader('Date', $dateTime);
        $this->assertTrue($headers->has('Date'));
    }

    public function testAddedTextHeaderIsSeenByHas()
    {
        $headers = new Headers();
        $headers->addTextHeader('Subject', 'some text');
        $this->assertTrue($headers->has('Subject'));
    }

    public function testAddedParameterizedHeaderIsSeenByHas()
    {
        $headers = new Headers();
        $headers->addParameterizedHeader('Content-Type', 'text/plain', ['charset' => 'utf-8']);
        $this->assertTrue($headers->has('Content-Type'));
    }

    public function testAddedIdHeaderIsSeenByHas()
    {
        $headers = new Headers();
        $headers->addIdHeader('Message-ID', 'some@id');
        $this->assertTrue($headers->has('Message-ID'));
    }

    public function testAddedPathHeaderIsSeenByHas()
    {
        $headers = new Headers();
        $headers->addPathHeader('Return-Path', 'some@path');
        $this->assertTrue($headers->has('Return-Path'));
    }

    public function testNewlySetHeaderIsSeenByHas()
    {
        $headers = new Headers();
        $headers->add(new UnstructuredHeader('X-Foo', 'bar'));
        $this->assertTrue($headers->has('X-Foo'));
    }

    public function testHasCanDistinguishMultipleHeaders()
    {
        $headers = new Headers();
        $headers->addTextHeader('X-Test', 'some@id');
        $headers->addTextHeader('X-Test', 'other@id');
        $this->assertTrue($headers->has('X-Test'));
    }

    public function testGet()
    {
        $header = new IdentificationHeader('Message-ID', 'some@id');
        $headers = new Headers();
        $headers->addIdHeader('Message-ID', 'some@id');
        $this->assertEquals($header->toString(), $headers->get('Message-ID')->toString());
    }

    public function testGetReturnsNullIfHeaderNotSet()
    {
        $headers = new Headers();
        $this->assertNull($headers->get('Message-ID'));
    }

    public function testAllReturnsAllHeadersMatchingName()
    {
        $header0 = new UnstructuredHeader('X-Test', 'some@id');
        $header1 = new UnstructuredHeader('X-Test', 'other@id');
        $header2 = new UnstructuredHeader('X-Test', 'more@id');
        $headers = new Headers();
        $headers->addTextHeader('X-Test', 'some@id');
        $headers->addTextHeader('X-Test', 'other@id');
        $headers->addTextHeader('X-Test', 'more@id');
        $this->assertEquals([$header0, $header1, $header2], iterator_to_array($headers->all('X-Test')));
    }

    public function testAllReturnsAllHeadersIfNoArguments()
    {
        $header0 = new IdentificationHeader('Message-ID', 'some@id');
        $header1 = new UnstructuredHeader('Subject', 'thing');
        $header2 = new MailboxListHeader('To', [new Address('person@example.org')]);
        $headers = new Headers();
        $headers->addIdHeader('Message-ID', 'some@id');
        $headers->addTextHeader('Subject', 'thing');
        $headers->addMailboxListHeader('To', [new Address('person@example.org')]);
        $this->assertEquals(['message-id' => $header0, 'subject' => $header1, 'to' => $header2], iterator_to_array($headers->all()));
    }

    public function testAllReturnsEmptyArrayIfNoneSet()
    {
        $headers = new Headers();
        $this->assertEquals([], iterator_to_array($headers->all('Received')));
    }

    public function testRemoveRemovesAllHeadersWithName()
    {
        $headers = new Headers();
        $headers->addIdHeader('X-Test', 'some@id');
        $headers->addIdHeader('X-Test', 'other@id');
        $headers->remove('X-Test');
        $this->assertFalse($headers->has('X-Test'));
        $this->assertFalse($headers->has('X-Test'));
    }

    public function testHasIsNotCaseSensitive()
    {
        $headers = new Headers();
        $headers->addIdHeader('Message-ID', 'some@id');
        $this->assertTrue($headers->has('message-id'));
    }

    public function testGetIsNotCaseSensitive()
    {
        $header = new IdentificationHeader('Message-ID', 'some@id');
        $headers = new Headers();
        $headers->addIdHeader('Message-ID', 'some@id');
        $this->assertEquals($header, $headers->get('message-id'));
    }

    public function testAllIsNotCaseSensitive()
    {
        $header = new IdentificationHeader('Message-ID', 'some@id');
        $headers = new Headers();
        $headers->addIdHeader('Message-ID', 'some@id');
        $this->assertEquals([$header], iterator_to_array($headers->all('message-id')));
    }

    public function testRemoveIsNotCaseSensitive()
    {
        $headers = new Headers();
        $headers->addIdHeader('Message-ID', 'some@id');
        $headers->remove('message-id');
        $this->assertFalse($headers->has('Message-ID'));
    }

    public function testAddHeaderIsNotCaseSensitive()
    {
        $headers = new Headers();
        $headers->addHeader('From', ['from@example.com']);

        $this->assertInstanceOf(MailboxListHeader::class, $headers->get('from'));
        $this->assertEquals([new Address('from@example.com')], $headers->get('from')->getBody());
    }

    public function testIsUniqueHeaderIsNotCaseSensitive()
    {
        $this->assertTrue(Headers::isUniqueHeader('From'));
    }

    public function testToStringJoinsHeadersTogether()
    {
        $headers = new Headers();
        $headers->addTextHeader('Foo', 'bar');
        $headers->addTextHeader('Zip', 'buttons');
        $this->assertEquals("Foo: bar\r\nZip: buttons\r\n", $headers->toString());
    }

    public function testHeadersWithoutBodiesAreNotDisplayed()
    {
        $headers = new Headers();
        $headers->addTextHeader('Foo', 'bar');
        $headers->addTextHeader('Zip', '');
        $this->assertEquals("Foo: bar\r\n", $headers->toString());
    }

    public function testToArray()
    {
        $headers = new Headers();
        $headers->addIdHeader('Message-ID', 'some@id');
        $headers->addTextHeader('Foo', str_repeat('a', 60).pack('C', 0x8F));
        $this->assertEquals([
            'Message-ID: <some@id>',
            "Foo: =?utf-8?Q?aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa?=\r\n =?utf-8?Q?aaaa?=",
        ], $headers->toArray());
    }

    public function testInReplyToAcceptsNonIdentifierValues()
    {
        $headers = new Headers();
        $headers->addTextHeader('In-Reply-To', 'foobar');
        $this->assertEquals('foobar', $headers->get('In-Reply-To')->getBody());
    }

    public function testInReplyToAcceptsIdentifierValues()
    {
        $headers = new Headers();
        $headers->addIdHeader('In-Reply-To', 'foo@bar.com');
        $this->assertEquals('<foo@bar.com>', $headers->get('In-Reply-To')->getBodyAsString());
    }

    public function testReferencesAcceptsNonIdentifierValues()
    {
        $headers = new Headers();
        $headers->addTextHeader('References', 'foobar');
        $this->assertEquals('foobar', $headers->get('References')->getBody());
    }

    public function testReferencesAcceptsIdentifierValues()
    {
        $headers = new Headers();
        $headers->addIdHeader('References', 'foo@bar.com');
        $this->assertEquals('<foo@bar.com>', $headers->get('References')->getBodyAsString());
    }

    public function testHeaderBody()
    {
        $headers = new Headers();
        $this->assertNull($headers->getHeaderBody('Content-Type'));
        $headers->setHeaderBody('Text', 'Content-Type', 'type');
        $this->assertSame('type', $headers->getHeaderBody('Content-Type'));
    }

    public function testHeaderParameter()
    {
        $headers = new Headers();
        $this->assertNull($headers->getHeaderParameter('Content-Disposition', 'name'));

        $headers->addParameterizedHeader('Content-Disposition', 'name');
        $headers->setHeaderParameter('Content-Disposition', 'name', 'foo');
        $this->assertSame('foo', $headers->getHeaderParameter('Content-Disposition', 'name'));
    }

    public function testHeaderParameterNotDefined()
    {
        $headers = new Headers();

        $this->expectException(\LogicException::class);
        $headers->setHeaderParameter('Content-Disposition', 'name', 'foo');
    }

    public function testSetHeaderParameterNotParameterized()
    {
        $headers = new Headers();
        $headers->addTextHeader('Content-Disposition', 'name');

        $this->expectException(\LogicException::class);
        $headers->setHeaderParameter('Content-Disposition', 'name', 'foo');
    }
}
