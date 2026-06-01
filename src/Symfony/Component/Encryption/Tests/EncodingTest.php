<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Encryption\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Encryption\Encoding;
use Symfony\Component\Encryption\Exception\InvalidArgumentException;

final class EncodingTest extends TestCase
{
    public function testHexRoundTrip(): void
    {
        $bytes = random_bytes(32);

        self::assertSame($bytes, Encoding::fromHex(Encoding::toHex($bytes)));
    }

    public function testKnownHexVector(): void
    {
        self::assertSame('616263', Encoding::toHex('abc'));
        self::assertSame('abc', Encoding::fromHex('616263'));
    }

    public function testBase64RoundTrip(): void
    {
        $bytes = random_bytes(32);

        self::assertSame($bytes, Encoding::fromBase64(Encoding::toBase64($bytes)));
    }

    public function testKnownBase64Vector(): void
    {
        self::assertSame('YWJj', Encoding::toBase64('abc'));
        self::assertSame('abc', Encoding::fromBase64('YWJj'));
    }

    public function testFromHexRejectsInvalidInput(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Encoding::fromHex('not-hex');
    }

    public function testFromBase64RejectsInvalidInput(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Encoding::fromBase64('!!!not base64!!!');
    }
}
