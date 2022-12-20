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
use Symfony\Component\Mime\Header\MailboxHeader;

class MailboxHeaderTest extends TestCase
{
    public function testConstructor()
    {
        $header = new MailboxHeader('Sender', $address = new Address('fabien@symfony.com'));
        self::assertEquals($address, $header->getAddress());
        self::assertEquals($address, $header->getBody());
    }

    public function testAddress()
    {
        $header = new MailboxHeader('Sender', new Address('fabien@symfony.com'));
        $header->setBody($address = new Address('helene@symfony.com'));
        self::assertEquals($address, $header->getAddress());
        self::assertEquals($address, $header->getBody());
        $header->setAddress($address = new Address('thomas@symfony.com'));
        self::assertEquals($address, $header->getAddress());
        self::assertEquals($address, $header->getBody());
    }

    public function testgetBodyAsString()
    {
        $header = new MailboxHeader('Sender', new Address('fabien@symfony.com'));
        self::assertEquals('fabien@symfony.com', $header->getBodyAsString());

        $header->setAddress(new Address('fabien@sïmfony.com'));
        self::assertEquals('fabien@xn--smfony-iwa.com', $header->getBodyAsString());

        $header = new MailboxHeader('Sender', new Address('fabien@symfony.com', 'Fabien Potencier'));
        self::assertEquals('Fabien Potencier <fabien@symfony.com>', $header->getBodyAsString());

        $header = new MailboxHeader('Sender', new Address('fabien@symfony.com', 'Fabien Potencier, "from Symfony"'));
        self::assertEquals('"Fabien Potencier, \"from Symfony\"" <fabien@symfony.com>', $header->getBodyAsString());

        $header = new MailboxHeader('From', new Address('fabien@symfony.com', 'Fabien Potencier, \\escaped\\'));
        self::assertEquals('"Fabien Potencier, \\\\escaped\\\\" <fabien@symfony.com>', $header->getBodyAsString());

        $name = 'P'.pack('C', 0x8F).'tencier';
        $header = new MailboxHeader('Sender', new Address('fabien@symfony.com', 'Fabien '.$name));
        $header->setCharset('iso-8859-1');
        self::assertEquals('Fabien =?'.$header->getCharset().'?Q?P=8Ftencier?= <fabien@symfony.com>', $header->getBodyAsString());
    }

    public function testUtf8CharsInLocalPart()
    {
        $header = new MailboxHeader('Sender', new Address('fabïen@symfony.com'));
        self::assertSame('fabïen@symfony.com', $header->getBodyAsString());
    }

    public function testToString()
    {
        $header = new MailboxHeader('Sender', new Address('fabien@symfony.com'));
        self::assertEquals('Sender: fabien@symfony.com', $header->toString());

        $header = new MailboxHeader('Sender', new Address('fabien@symfony.com', 'Fabien Potencier'));
        self::assertEquals('Sender: Fabien Potencier <fabien@symfony.com>', $header->toString());
    }
}
