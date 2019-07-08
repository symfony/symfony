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
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Header\IdentificationHeader;
use Symfony\Component\Mime\Header\MailboxListHeader;
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

    public function testGetAllReturnsAllHeadersMatchingName()
    {
        $header0 = new UnstructuredHeader('X-Test', 'some@id');
        $header1 = new UnstructuredHeader('X-Test', 'other@id');
        $header2 = new UnstructuredHeader('X-Test', 'more@id');
        $headers = new Headers();
        $headers->addTextHeader('X-Test', 'some@id');
        $headers->addTextHeader('X-Test', 'other@id');
        $headers->addTextHeader('X-Test', 'more@id');
        $this->assertEquals([$header0, $header1, $header2], iterator_to_array($headers->getAll('X-Test')));
    }

    public function testGetAllReturnsAllHeadersIfNoArguments()
    {
        $header0 = new IdentificationHeader('Message-ID', 'some@id');
        $header1 = new UnstructuredHeader('Subject', 'thing');
        $header2 = new MailboxListHeader('To', [new Address('person@example.org')]);
        $headers = new Headers();
        $headers->addIdHeader('Message-ID', 'some@id');
        $headers->addTextHeader('Subject', 'thing');
        $headers->addMailboxListHeader('To', [new Address('person@example.org')]);
        $this->assertEquals(['message-id' => $header0, 'subject' => $header1, 'to' => $header2], iterator_to_array($headers->getAll()));
    }

    public function testGetAllReturnsEmptyArrayIfNoneSet()
    {
        $headers = new Headers();
        $this->assertEquals([], iterator_to_array($headers->getAll('Received')));
    }

    public function testRemoveRemovesAllHeadersWithName()
    {
        $header0 = new UnstructuredHeader('X-Test', 'some@id');
        $header1 = new UnstructuredHeader('X-Test', 'other@id');
        $headers = new Headers();
        $headers->addIdHeader('X-Test', 'some@id');
        $headers->addIdHeader('X-Test', 'other@id');
        $headers->remove('X-Test');
        $this->assertFalse($headers->has('X-Test'));
        $this->assertFalse($headers->has('X-Test'));
    }

    public function testHasIsNotCaseSensitive()
    {
        $header = new IdentificationHeader('Message-ID', 'some@id');
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

    public function testGetAllIsNotCaseSensitive()
    {
        $header = new IdentificationHeader('Message-ID', 'some@id');
        $headers = new Headers();
        $headers->addIdHeader('Message-ID', 'some@id');
        $this->assertEquals([$header], iterator_to_array($headers->getAll('message-id')));
    }

    public function testRemoveIsNotCaseSensitive()
    {
        $header = new IdentificationHeader('Message-ID', 'some@id');
        $headers = new Headers();
        $headers->addIdHeader('Message-ID', 'some@id');
        $headers->remove('message-id');
        $this->assertFalse($headers->has('Message-ID'));
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
}
