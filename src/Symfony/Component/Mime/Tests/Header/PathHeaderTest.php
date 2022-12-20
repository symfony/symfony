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
use Symfony\Component\Mime\Header\PathHeader;

class PathHeaderTest extends TestCase
{
    public function testSingleAddressCanBeSetAndFetched()
    {
        $header = new PathHeader('Return-Path', $address = new Address('chris@swiftmailer.org'));
        self::assertEquals($address, $header->getAddress());
    }

    public function testAddressMustComplyWithRfc2822()
    {
        self::expectException(\Exception::class);
        new PathHeader('Return-Path', new Address('chr is@swiftmailer.org'));
    }

    public function testValueIsAngleAddrWithValidAddress()
    {
        /* -- RFC 2822, 3.6.7.

            return          =       "Return-Path:" path CRLF

            path            =       ([CFWS] "<" ([CFWS] / addr-spec) ">" [CFWS]) /
                                                            obs-path
         */

        $header = new PathHeader('Return-Path', new Address('chris@swiftmailer.org'));
        self::assertEquals('<chris@swiftmailer.org>', $header->getBodyAsString());
    }

    public function testAddressIsIdnEncoded()
    {
        $header = new PathHeader('Return-Path', new Address('chris@swïftmailer.org'));
        self::assertEquals('<chris@xn--swftmailer-78a.org>', $header->getBodyAsString());
    }

    public function testAddressMustBeEncodableWithUtf8CharsInLocalPart()
    {
        $header = new PathHeader('Return-Path', new Address('chrïs@swiftmailer.org'));
        self::assertSame('<chrïs@swiftmailer.org>', $header->getBodyAsString());
    }

    public function testSetBody()
    {
        $header = new PathHeader('Return-Path', new Address('foo@example.com'));
        $header->setBody($address = new Address('foo@bar.tld'));
        self::assertEquals($address, $header->getAddress());
    }

    public function testGetBody()
    {
        $header = new PathHeader('Return-Path', $address = new Address('foo@bar.tld'));
        self::assertEquals($address, $header->getBody());
    }

    public function testToString()
    {
        $header = new PathHeader('Return-Path', new Address('chris@swiftmailer.org'));
        self::assertEquals('Return-Path: <chris@swiftmailer.org>', $header->toString());
    }
}
