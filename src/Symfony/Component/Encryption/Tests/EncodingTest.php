<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Encryption\Encoding;
use Symfony\Component\Encryption\Exception\InvalidArgumentException;

final class EncodingTest extends TestCase
{
    public function testHexRoundTrip()
    {
        $bytes = random_bytes(32);

        self::assertSame($bytes, Encoding::fromHex(Encoding::toHex($bytes)));
    }

    public function testKnownHexVector()
    {
        self::assertSame('616263', Encoding::toHex('abc'));
        self::assertSame('abc', Encoding::fromHex('616263'));
    }

    public function testBase64RoundTrip()
    {
        $bytes = random_bytes(32);

        self::assertSame($bytes, Encoding::fromBase64(Encoding::toBase64($bytes)));
    }

    public function testKnownBase64Vector()
    {
        self::assertSame('YWJj', Encoding::toBase64('abc'));
        self::assertSame('abc', Encoding::fromBase64('YWJj'));
    }

    public function testFromHexRejectsInvalidInput()
    {
        $this->expectException(InvalidArgumentException::class);

        Encoding::fromHex('not-hex');
    }

    public function testFromBase64RejectsInvalidInput()
    {
        $this->expectException(InvalidArgumentException::class);

        Encoding::fromBase64('!!!not base64!!!');
    }
}
