<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Tests\Key;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Encryption\Encoding;
use Symfony\Component\Encryption\Engine\SymmetricEngineInterface;
use Symfony\Component\Encryption\Exception\InvalidKeyException;
use Symfony\Component\Encryption\Key\KeyInterface;
use Symfony\Component\Encryption\Key\SecretKey;

final class SecretKeyTest extends TestCase
{
    public function testGenerateProduces32ByteKey()
    {
        $key = SecretKey::generate();

        self::assertInstanceOf(KeyInterface::class, $key);
        self::assertSame(SymmetricEngineInterface::KEY_BYTES, \strlen($key->bytes()));
        self::assertSame('chacha20-poly1305-ietf', $key->algorithm());
    }

    public function testFromBytesRejectsWrongLength()
    {
        $this->expectException(InvalidKeyException::class);

        SecretKey::fromBytes(random_bytes(16));
    }

    public function testExportImportRoundTrip()
    {
        $key = SecretKey::generate();

        $imported = SecretKey::import($key->export());

        self::assertSame($key->bytes(), $imported->bytes());
    }

    public function testImportRejectsGarbage()
    {
        $this->expectException(InvalidKeyException::class);

        SecretKey::import('this is not a key');
    }

    public function testImportRejectsWrongMagic()
    {
        $bytes = 'XYZ'."\x01\x01".random_bytes(SymmetricEngineInterface::KEY_BYTES);
        $exported = Encoding::toBase64($bytes);

        $this->expectException(InvalidKeyException::class);

        SecretKey::import($exported);
    }

    public function testImportRejectsUnsupportedVersion()
    {
        $bytes = 'SYK'."\x02\x01".random_bytes(SymmetricEngineInterface::KEY_BYTES);
        $exported = Encoding::toBase64($bytes);

        $this->expectException(InvalidKeyException::class);

        SecretKey::import($exported);
    }
}
