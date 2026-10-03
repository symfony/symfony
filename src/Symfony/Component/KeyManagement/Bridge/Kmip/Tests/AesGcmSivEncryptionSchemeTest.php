<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\Kmip\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\AesGcmSivEncryptionScheme;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;

final class AesGcmSivEncryptionSchemeTest extends TestCase
{
    private const string EVIDEN_BLOCK_CIPHER_MODE = '0x80000002';
    private const string OTHER_VENDOR_BLOCK_CIPHER_MODE = '0x80001234';

    public function testBlockCipherModeMustBeProvided()
    {
        $parameter = (new \ReflectionMethod(AesGcmSivEncryptionScheme::class, '__construct'))->getParameters()[0];

        $this->assertFalse($parameter->isOptional());
    }

    public function testConfiguredEvidenModeIsEncodedInCryptographicParameters()
    {
        $scheme = new AesGcmSivEncryptionScheme(self::EVIDEN_BLOCK_CIPHER_MODE);

        $this->assertSame('aes-gcm-siv', $scheme->name());
        $this->assertSame('42002b0100000020'
            .'42001105000000048000000200000000'
            .'42002805000000040000000300000000',
            bin2hex((new \ReflectionMethod($scheme, 'cryptographicParameters'))->invoke($scheme, 12)));
    }

    public function testAnotherVendorModeIsEncodedInCryptographicParameters()
    {
        $scheme = new AesGcmSivEncryptionScheme(self::OTHER_VENDOR_BLOCK_CIPHER_MODE, 'aes-gcm-siv/another');

        $this->assertSame('aes-gcm-siv/another', $scheme->name());
        $this->assertSame('42002b0100000020'
            .'42001105000000048000123400000000'
            .'42002805000000040000000300000000',
            bin2hex((new \ReflectionMethod($scheme, 'cryptographicParameters'))->invoke($scheme, 12)));
    }

    #[DataProvider('validBlockCipherModes')]
    public function testVendorRangeBoundariesAndNativeIntegerModeAreEncoded(int|string $mode, string $expectedValue)
    {
        $scheme = new AesGcmSivEncryptionScheme($mode);

        $this->assertStringContainsString('4200110500000004'.$expectedValue.'00000000', bin2hex((new \ReflectionMethod($scheme, 'cryptographicParameters'))->invoke($scheme, 12)));
    }

    public static function validBlockCipherModes(): iterable
    {
        yield 'first vendor mode' => ['0x80000000', '80000000'];
        yield 'last vendor mode' => ['0x8FFFFFFF', '8fffffff'];
        if (\PHP_INT_SIZE > 4) {
            yield 'native integer mode' => [0x80000002, '80000002'];
        }
    }

    #[DataProvider('invalidBlockCipherModes')]
    public function testRejectsNonExtensionBlockCipherModes(int|string $mode)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The KMIP AES-GCM-SIV block cipher mode must be in the vendor extension range 0x80000000 to 0x8FFFFFFF.');

        new AesGcmSivEncryptionScheme($mode);
    }

    public static function invalidBlockCipherModes(): iterable
    {
        yield 'negative' => [-1];
        yield 'standard GCM' => [9];
        yield 'below extension range' => ['0x7FFFFFFF'];
        yield 'above extension range' => ['0x90000000'];
        yield 'too many hex digits' => ['0x800000000'];
        yield 'not hexadecimal' => ['0x8000000G'];
    }
}
