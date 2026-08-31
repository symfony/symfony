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

    public function testConstructorWithAtSignInDomainLiteral()
    {
        $a = new Address('user@[em@il]');
        $this->assertSame('user@[em@il]', $a->getAddress());
    }

    public function testConstructorWithUnquotedAtSignInLocalPartAndDomainLiteral()
    {
        $this->expectException(RfcComplianceException::class);
        new Address('em@il@[example]');
    }

    public function testConstructorWithQuotedAtSignInLocalPartAndDomainLiteral()
    {
        $a = new Address('"em@il"@[em@il]');
        $this->assertSame('"em@il"@[em@il]', $a->getAddress());
    }

    public function testConstructorWithACommentAfterADomainLiteral()
    {
        $a = new Address('user@[em@il] (comment)');
        $this->assertSame('user@[em@il] (comment)', $a->getAddress());
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

    /**
     * @dataProvider provideNamesWithControlCharacters
     */
    public function testConstructorStripsControlCharactersFromName(string $name)
    {
        $a = new Address('fabien@symfony.com', $name);
        $this->assertSame('ab', $a->getName());
        $this->assertSame('"ab" <fabien@symfony.com>', $a->toString());
    }

    public function testConstructorKeepsTheOnlyControlCharacterLegalInAPhrase()
    {
        $a = new Address('fabien@symfony.com', "Jean\tDupont");
        $this->assertSame("Jean\tDupont", $a->getName());
    }

    public static function provideNamesWithControlCharacters(): iterable
    {
        yield 'NUL byte' => ["a\x00b"];
        yield 'SOH (0x01)' => ["a\x01b"];
        yield 'LF' => ["a\nb"];
        yield 'CR' => ["a\rb"];
        yield 'VT (0x0B)' => ["a\x0Bb"];
        yield 'US (0x1F)' => ["a\x1Fb"];
        yield 'DEL (0x7F)' => ["a\x7Fb"];
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
        $this->expectException(\InvalidArgumentException::class);
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
        $this->expectException(\InvalidArgumentException::class);
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

    /**
     * @dataProvider fromStringProvider
     *
     * @group legacy
     */
    public function testFromString($string, $displayName, $addrSpec)
    {
        $address = Address::fromString($string);
        $this->assertEquals($displayName, $address->getName());
        $this->assertEquals($addrSpec, $address->getAddress());
        $fromToStringAddress = Address::fromString($address->toString());
        $this->assertEquals($displayName, $fromToStringAddress->getName());
        $this->assertEquals($addrSpec, $fromToStringAddress->getAddress());
    }

    /**
     * @group legacy
     */
    public function testFromStringFailure()
    {
        $this->expectException(InvalidArgumentException::class);
        Address::fromString('Jane Doe <example@example.com');
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
}
