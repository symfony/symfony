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
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\AesGcmEncryptionScheme;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;

final class AesGcmEncryptionSchemeTest extends TestCase
{
    public function testNameAndCryptographicParameters()
    {
        $scheme = new AesGcmEncryptionScheme();

        $this->assertSame('aes-gcm', $scheme->name());
        $this->assertSame('42002b0100000040'
            .'42001105000000040000000900000000'
            .'42002805000000040000000300000000'
            .'4200cd02000000040000008000000000'
            .'4200ce02000000040000001000000000',
            bin2hex((new \ReflectionMethod($scheme, 'cryptographicParameters'))->invoke($scheme, 16)));
    }

    public function testTwelveByteIvKeepsTheSameSchemeName()
    {
        $scheme = new AesGcmEncryptionScheme(12);

        $this->assertSame('aes-gcm', $scheme->name());
        $this->assertSame('42002b0100000040'
            .'42001105000000040000000900000000'
            .'42002805000000040000000300000000'
            .'4200cd02000000040000006000000000'
            .'4200ce02000000040000001000000000',
            bin2hex((new \ReflectionMethod($scheme, 'cryptographicParameters'))->invoke($scheme, 12)));
    }

    #[DataProvider('invalidIvLengths')]
    public function testInvalidIvLengthIsRejected(int $length)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The KMIP AES-GCM IV length must be between 12 and 255 bytes.');

        new AesGcmEncryptionScheme($length);
    }

    public static function invalidIvLengths(): iterable
    {
        yield 'too short' => [11];
        yield 'too long' => [256];
    }
}
