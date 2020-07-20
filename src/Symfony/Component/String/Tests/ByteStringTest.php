<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\String\Tests;

use Symfony\Component\String\AbstractString;
use Symfony\Component\String\ByteString;
use Symfony\Component\String\Exception\InvalidArgumentException;

class ByteStringTest extends AbstractAsciiTestCase
{
    protected static function createFromString(string $string): AbstractString
    {
        return new ByteString($string);
    }

    public function testFromRandom()
    {
        $random = ByteString::fromRandom(32);

        self::assertSame(32, $random->length());
        foreach ($random->chunk() as $char) {
            self::assertNotNull((new ByteString('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'))->indexOf($char));
        }
    }

    public function testFromRandomWithSpecificChars()
    {
        $random = ByteString::fromRandom(32, 'abc');

        self::assertSame(32, $random->length());
        foreach ($random->chunk() as $char) {
            self::assertNotNull((new ByteString('abc'))->indexOf($char));
        }
    }

    public function testFromRandoWithZeroLength()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A strictly positive length is expected, "0" given.');

        self::assertSame('', ByteString::fromRandom(0));
    }

    public function testFromRandomThrowsForNegativeLength()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A strictly positive length is expected, "-1" given.');

        ByteString::fromRandom(-1);
    }

    public function testFromRandomAlphabetMin()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The length of the alphabet must in the [2^1, 2^56] range.');

        ByteString::fromRandom(32, 'a');
    }

    public static function provideBytesAt(): array
    {
        return array_merge(
            parent::provideBytesAt(),
            [
                [[0xC3], 'Späßchen', 2],
                [[0x61], "Spa\u{0308}ßchen", 2],
                [[0xCC], "Spa\u{0308}ßchen", 3],
                [[0xE0], 'नमस्ते', 6],
            ]
        );
    }

    public static function provideLength(): array
    {
        return array_merge(
            parent::provideLength(),
            [
                [2, 'ä'],
            ]
        );
    }

    public static function provideWidth(): array
    {
        return array_merge(
            parent::provideWidth(),
            [
                [10, "f\u{001b}[0moo\x80bar\xfe\xfe1"], // foo?bar??1
                [13, "f\u{001b}[0moo\x80bar\xfe\xfe1", false], // f[0moo?bar??1
            ]
        );
    }
}
