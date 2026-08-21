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
use Symfony\Component\Mime\Exception\InvalidArgumentException;
use Symfony\Component\Mime\Exception\RfcComplianceException;

class AddressTest extends TestCase
{
    public function testConstructor()
    {
        $a = new Address('fabien@symfonï.com');
        $this->assertEquals('fabien@symfonï.com', $a->getAddress());
        $this->assertEquals('fabien@xn--symfon-nwa.com', $a->toString());
        $this->assertEquals('fabien@xn--symfon-nwa.com', $a->getEncodedAddress());

        $a = new Address('fabien@symfonï.com', 'Fabien');
        $this->assertEquals('Fabien', $a->getName());
        $this->assertEquals('fabien@symfonï.com', $a->getAddress());
        $this->assertEquals('"Fabien" <fabien@xn--symfon-nwa.com>', $a->toString());
        $this->assertEquals('fabien@xn--symfon-nwa.com', $a->getEncodedAddress());
    }

    public function testConstructorWithInvalidAddress()
    {
        $this->expectException(RfcComplianceException::class);
        new Address('fab   pot@symfony.com');
    }

    public function testConstructorWithUnquotedAtSignInLocalPart()
    {
        $this->expectException(RfcComplianceException::class);
        $this->expectExceptionMessage('Email "em@il@example.test" does not comply with addr-spec of RFC 2822.');
        new Address('em@il@example.test');
    }

    public function testConstructorWithQuotedAtSignInLocalPart()
    {
        $a = new Address('"em@il"@example.test');
        $this->assertSame('"em@il"@example.test', $a->getAddress());
    }

    /**
     * @dataProvider provideAddressesWithControlCharacters
     */
    public function testConstructorRejectsControlCharactersInAddress(string $address)
    {
        $this->expectException(InvalidArgumentException::class);
        new Address($address);
    }

    public static function provideAddressesWithControlCharacters(): iterable
    {
        yield 'CRLF in quoted-string' => ["\"x\r\nBcc: attacker@evil\"@example.com"];
        yield 'CR only' => ["foo\r@example.com"];
        yield 'LF only' => ["foo\n@example.com"];
        yield 'NUL byte' => ["foo\x00@example.com"];
        yield 'HTAB' => ["foo\t@example.com"];
        yield 'DEL (0x7F)' => ["foo\x7F@example.com"];
        yield 'control char in domain' => ["foo@example\x01.com"];
    }

    public function testCreate()
    {
        $this->assertSame($a = new Address('fabien@symfony.com'), Address::create($a));
        $this->assertSame($b = new Address('helene@symfony.com', 'Helene'), Address::create($b));
        $this->assertEquals($a, Address::create('fabien@symfony.com'));
    }

    public function testCreateWithInvalidFormat()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Could not parse "<fabien@symfony" to a "Symfony\Component\Mime\Address" instance.');

        Address::create('<fabien@symfony');
    }

    /**
     * @dataProvider fromStringProvider
     */
    public function testCreateWithString($string, $displayName, $addrSpec)
    {
        $address = Address::create($string);
        $this->assertEquals($displayName, $address->getName());
        $this->assertEquals($addrSpec, $address->getAddress());
        $fromToStringAddress = Address::create($address->toString());
        $this->assertEquals($displayName, $fromToStringAddress->getName());
        $this->assertEquals($addrSpec, $fromToStringAddress->getAddress());
    }

    public function testCreateWrongArg()
    {
        $this->expectException(\TypeError::class);
        Address::create(new \stdClass());
    }

    public function testCreateArray()
    {
        $fabien = new Address('fabien@symfony.com');
        $helene = new Address('helene@symfony.com', 'Helene');
        $this->assertSame([$fabien, $helene], Address::createArray([$fabien, $helene]));

        $this->assertEquals([$fabien], Address::createArray(['fabien@symfony.com']));
    }

    public function testCreateArrayWrongArg()
    {
        $this->expectException(\TypeError::class);
        Address::createArray([new \stdClass()]);
    }

    /**
     * @dataProvider nameEmptyDataProvider
     */
    public function testNameEmpty(string $name)
    {
        $mail = 'mail@example.org';
        $this->assertSame($mail, (new Address($mail, $name))->toString());
    }

    public static function nameEmptyDataProvider(): array
    {
        return [[''], [' '], [" \r\n "]];
    }

    public static function fromStringProvider()
    {
        return [
            [
                'example@example.com',
                '',
                'example@example.com',
            ],
            [
                '<example@example.com>',
                '',
                'example@example.com',
            ],
            [
                'Jane Doe <example@example.com>',
                'Jane Doe',
                'example@example.com',
            ],
            [
                'Jane Doe<example@example.com>',
                'Jane Doe',
                'example@example.com',
            ],
            [
                '\'Jane Doe\' <example@example.com>',
                'Jane Doe',
                'example@example.com',
            ],
            [
                '"Jane Doe" <example@example.com>',
                'Jane Doe',
                'example@example.com',
            ],
            [
                'Jane Doe <"ex<ample"@example.com>',
                'Jane Doe',
                '"ex<ample"@example.com',
            ],
            [
                'Jane Doe <"ex<amp>le"@example.com>',
                'Jane Doe',
                '"ex<amp>le"@example.com',
            ],
            [
                'Jane Doe > <"ex<am  p>le"@example.com>',
                'Jane Doe >',
                '"ex<am  p>le"@example.com',
            ],
            [
                'Jane Doe <example@example.com>discarded',
                'Jane Doe',
                'example@example.com',
            ],
        ];
    }

    public function testEncodeNameIfNameContainsCommas()
    {
        $address = new Address('fabien@symfony.com', 'Fabien, "Potencier');
        $this->assertSame('"Fabien, \"Potencier" <fabien@symfony.com>', $address->toString());
    }

    public function testEncodeNameIfNameContainsBackslashes()
    {
        $address = new Address('fabien@symfony.com', 'Fabien \ "Potencier');
        $this->assertSame('"Fabien \\\\ \"Potencier" <fabien@symfony.com>', $address->toString());

        $address = new Address('fabien@symfony.com', 'Fabien\\');
        $this->assertSame('"Fabien\\\\" <fabien@symfony.com>', $address->toString());
    }

    public function testEncodeNameIfNameIsNotValidUtf8()
    {
        $address = new Address('fabien@symfony.com', "Fabien \xB1 \\ Potencier");
        $this->assertSame("\"Fabien \xB1 \\\\ Potencier\" <fabien@symfony.com>", $address->toString());
    }
}
