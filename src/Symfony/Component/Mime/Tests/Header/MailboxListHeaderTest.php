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
use Symfony\Component\Mime\Header\MailboxListHeader;

class MailboxListHeaderTest extends TestCase
{
    // RFC 2822, 3.6.2 for all tests

    public function testMailboxIsSetForAddress()
    {
        $header = new MailboxListHeader('From', [new Address('chris@swiftmailer.org')]);
        $this->assertEquals(['chris@swiftmailer.org'], $header->getAddressStrings());
    }

    public function testMailboxIsRenderedForNameAddress()
    {
        $header = new MailboxListHeader('From', [new Address('chris@swiftmailer.org', 'Chris Corbyn')]);
        $this->assertEquals(['Chris Corbyn <chris@swiftmailer.org>'], $header->getAddressStrings());
    }

    public function testAddressCanBeReturnedForAddress()
    {
        $header = new MailboxListHeader('From', $addresses = [new Address('chris@swiftmailer.org')]);
        $this->assertEquals($addresses, $header->getAddresses());
    }

    public function testQuotesInNameAreQuoted()
    {
        $header = new MailboxListHeader('From', [new Address('chris@swiftmailer.org', 'Chris Corbyn, "DHE"')]);
        $this->assertEquals(['"Chris Corbyn, \"DHE\"" <chris@swiftmailer.org>'], $header->getAddressStrings());
    }

    public function testEscapeCharsInNameAreQuoted()
    {
        $header = new MailboxListHeader('From', [new Address('chris@swiftmailer.org', 'Chris Corbyn, \\escaped\\')]);
        $this->assertEquals(['"Chris Corbyn, \\\\escaped\\\\" <chris@swiftmailer.org>'], $header->getAddressStrings());
    }

    public function testParenthesesInNameAreQuoted()
    {
        $header = new MailboxListHeader('From', [new Address('j.doe@example.com', 'J Doe (ACME)')]);
        $this->assertEquals(['"J Doe (ACME)" <j.doe@example.com>'], $header->getAddressStrings());
    }

    public function testUtf8CharsInDomainAreIdnEncoded()
    {
        $header = new MailboxListHeader('From', [new Address('chris@swïftmailer.org', 'Chris Corbyn')]);
        $this->assertEquals(['Chris Corbyn <chris@xn--swftmailer-78a.org>'], $header->getAddressStrings());
    }

    public function testUtf8CharsInLocalPart()
    {
        $header = new MailboxListHeader('From', [new Address('chrïs@swiftmailer.org', 'Chris Corbyn')]);
        $this->assertSame(['Chris Corbyn <chrïs@swiftmailer.org>'], $header->getAddressStrings());
    }

    public function testGetMailboxesReturnsNameValuePairs()
    {
        $header = new MailboxListHeader('From', $addresses = [new Address('chris@swiftmailer.org', 'Chris Corbyn, DHE')]);
        $this->assertEquals($addresses, $header->getAddresses());
    }

    public function testMultipleAddressesAsMailboxStrings()
    {
        $header = new MailboxListHeader('From', [new Address('chris@swiftmailer.org'), new Address('mark@swiftmailer.org')]);
        $this->assertEquals(['chris@swiftmailer.org', 'mark@swiftmailer.org'], $header->getAddressStrings());
    }

    public function testNameIsEncodedIfNonAscii()
    {
        $name = 'C'.pack('C', 0x8F).'rbyn';
        $header = new MailboxListHeader('From', [new Address('chris@swiftmailer.org', 'Chris '.$name)]);
        $header->setCharset('iso-8859-1');
        $addresses = $header->getAddressStrings();
        $this->assertEquals('Chris =?'.$header->getCharset().'?Q?C=8Frbyn?= <chris@swiftmailer.org>', array_shift($addresses));
    }

    public function testEncodingLineLengthCalculations()
    {
        /* -- RFC 2047, 2.
        An 'encoded-word' may not be more than 75 characters long, including
        'charset', 'encoding', 'encoded-text', and delimiters.
        */

        $name = 'C'.pack('C', 0x8F).'rbyn';
        $header = new MailboxListHeader('From', [new Address('chris@swiftmailer.org', 'Chris '.$name)]);
        $header->setCharset('iso-8859-1');
        $addresses = $header->getAddressStrings();
        $this->assertEquals('Chris =?'.$header->getCharset().'?Q?C=8Frbyn?= <chris@swiftmailer.org>', array_shift($addresses));
    }

    public function testGetValueReturnsMailboxStringValue()
    {
        $header = new MailboxListHeader('From', [new Address('chris@swiftmailer.org', 'Chris Corbyn')]);
        $this->assertEquals('Chris Corbyn <chris@swiftmailer.org>', $header->getBodyAsString());
    }

    public function testGetValueReturnsMailboxStringValueForMultipleMailboxes()
    {
        $header = new MailboxListHeader('From', [new Address('chris@swiftmailer.org', 'Chris Corbyn'), new Address('mark@swiftmailer.org', 'Mark Corbyn')]);
        $this->assertEquals('Chris Corbyn <chris@swiftmailer.org>, Mark Corbyn <mark@swiftmailer.org>', $header->getBodyAsString());
    }

    public function testSetBody()
    {
        $header = new MailboxListHeader('From', []);
        $header->setBody($addresses = [new Address('chris@swiftmailer.org')]);
        $this->assertEquals($addresses, $header->getAddresses());
    }

    public function testGetBody()
    {
        $header = new MailboxListHeader('From', $addresses = [new Address('chris@swiftmailer.org')]);
        $this->assertEquals($addresses, $header->getBody());
    }

    public function testToString()
    {
        $header = new MailboxListHeader('From', [new Address('chris@example.org', 'Chris Corbyn'), new Address('mark@example.org', 'Mark Corbyn')]);
        $this->assertEquals('From: Chris Corbyn <chris@example.org>, Mark Corbyn <mark@example.org>', $header->toString());
    }
}
