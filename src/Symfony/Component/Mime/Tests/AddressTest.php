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

class AddressTest extends TestCase
{
    public function testConstructor()
    {
        $a = new Address('fabien@symfonï.com');
        self::assertEquals('fabien@symfonï.com', $a->getAddress());
        self::assertEquals('fabien@xn--symfon-nwa.com', $a->toString());
        self::assertEquals('fabien@xn--symfon-nwa.com', $a->getEncodedAddress());

        $a = new Address('fabien@symfonï.com', 'Fabien');
        self::assertEquals('Fabien', $a->getName());
        self::assertEquals('fabien@symfonï.com', $a->getAddress());
        self::assertEquals('"Fabien" <fabien@xn--symfon-nwa.com>', $a->toString());
        self::assertEquals('fabien@xn--symfon-nwa.com', $a->getEncodedAddress());
    }

    public function testConstructorWithInvalidAddress()
    {
        self::expectException(\InvalidArgumentException::class);
        new Address('fab   pot@symfony.com');
    }

    public function testCreate()
    {
        self::assertSame($a = new Address('fabien@symfony.com'), Address::create($a));
        self::assertSame($b = new Address('helene@symfony.com', 'Helene'), Address::create($b));
        self::assertEquals($a, Address::create('fabien@symfony.com'));
    }

    /**
     * @dataProvider fromStringProvider
     */
    public function testCreateWithString($string, $displayName, $addrSpec)
    {
        $address = Address::create($string);
        self::assertEquals($displayName, $address->getName());
        self::assertEquals($addrSpec, $address->getAddress());
        $fromToStringAddress = Address::create($address->toString());
        self::assertEquals($displayName, $fromToStringAddress->getName());
        self::assertEquals($addrSpec, $fromToStringAddress->getAddress());
    }

    public function testCreateWrongArg()
    {
        self::expectException(\InvalidArgumentException::class);
        Address::create(new \stdClass());
    }

    public function testCreateArray()
    {
        $fabien = new Address('fabien@symfony.com');
        $helene = new Address('helene@symfony.com', 'Helene');
        self::assertSame([$fabien, $helene], Address::createArray([$fabien, $helene]));

        self::assertEquals([$fabien], Address::createArray(['fabien@symfony.com']));
    }

    public function testCreateArrayWrongArg()
    {
        self::expectException(\InvalidArgumentException::class);
        Address::createArray([new \stdClass()]);
    }

    /**
     * @dataProvider nameEmptyDataProvider
     */
    public function testNameEmpty(string $name)
    {
        $mail = 'mail@example.org';
        self::assertSame($mail, (new Address($mail, $name))->toString());
    }

    public function nameEmptyDataProvider(): array
    {
        return [[''], [' '], [" \r\n "]];
    }

    /**
     * @dataProvider fromStringProvider
     * @group legacy
     */
    public function testFromString($string, $displayName, $addrSpec)
    {
        $address = Address::fromString($string);
        self::assertEquals($displayName, $address->getName());
        self::assertEquals($addrSpec, $address->getAddress());
        $fromToStringAddress = Address::fromString($address->toString());
        self::assertEquals($displayName, $fromToStringAddress->getName());
        self::assertEquals($addrSpec, $fromToStringAddress->getAddress());
    }

    /**
     * @group legacy
     */
    public function testFromStringFailure()
    {
        self::expectException(InvalidArgumentException::class);
        Address::fromString('Jane Doe <example@example.com');
    }

    public function fromStringProvider()
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
        self::assertSame('"Fabien, \"Potencier" <fabien@symfony.com>', $address->toString());
    }
}
