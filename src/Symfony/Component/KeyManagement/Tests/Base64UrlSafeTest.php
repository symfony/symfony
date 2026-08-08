<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Base64UrlSafe;

class Base64UrlSafeTest extends TestCase
{
    public function testEncodingUsesTheUrlSafeAlphabetAndNoPadding()
    {
        $bytes = "\xFB\xFF\xFE\x00";

        $encoded = Base64UrlSafe::encode($bytes);

        $this->assertSame('+//+AA==', base64_encode($bytes), 'the standard alphabet is what we are moving away from.');
        $this->assertSame('-__-AA', $encoded);
    }

    public function testEmptyInputRoundTrips()
    {
        $this->assertSame('', Base64UrlSafe::encode(''));
        $this->assertSame('', Base64UrlSafe::decode(''));
    }

    #[DataProvider('provideByteLengths')]
    public function testBinaryRoundTripsWhateverThePaddingWouldHaveBeen(int $length)
    {
        $bytes = random_bytes($length);

        $encoded = Base64UrlSafe::encode($bytes);

        $this->assertStringNotContainsString('=', $encoded);
        $this->assertSame($bytes, Base64UrlSafe::decode($encoded));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideByteLengths(): iterable
    {
        yield 'one byte, two padding chars dropped' => [1];
        yield 'two bytes, one padding char dropped' => [2];
        yield 'three bytes, nothing to drop' => [3];
        yield 'a key' => [32];
        yield 'a wrapped key' => [184];
    }

    /**
     * Decoding is permissive on purpose: a value pasted from a tool that emits standard base64, with
     * or without padding, still decodes, and the two alphabets do not overlap so the translation is
     * idempotent for either.
     */
    #[DataProvider('provideEquivalentEncodings')]
    public function testDecodingAcceptsBothAlphabetsPaddedOrNot(string $encoded)
    {
        $this->assertSame("\xFB\xFF\xFE\x00", Base64UrlSafe::decode($encoded));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEquivalentEncodings(): iterable
    {
        yield 'url-safe, unpadded' => ['-__-AA'];
        yield 'url-safe, padded' => ['-__-AA=='];
        yield 'standard, padded' => ['+//+AA=='];
        yield 'standard, unpadded' => ['+//+AA'];
    }

    #[DataProvider('provideInvalidInput')]
    public function testDecodingRejectsWhatIsNotBase64(string $invalid)
    {
        $this->assertFalse(Base64UrlSafe::decode($invalid), 'a caller must be able to tell a bad value from an empty one.');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidInput(): iterable
    {
        yield 'a comma' => ['-__,AA'];
        yield 'a quote' => ["-__'AA"];
        yield 'a non-ascii byte' => ["-__\xC3\xA9AA"];
    }

    /**
     * Inherited from `base64_decode()` in strict mode, which lets whitespace through: a key pasted
     * across two lines still decodes.
     */
    public function testWhitespaceIsTolerated()
    {
        $this->assertSame("\xFB\xFF\xFE\x00", Base64UrlSafe::decode("-__-\n AA"));
    }
}
