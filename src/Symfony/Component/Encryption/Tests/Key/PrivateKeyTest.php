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
use Symfony\Component\Encryption\Exception\InvalidKeyException;
use Symfony\Component\Encryption\Key\PrivateKey;
use Symfony\Component\Encryption\Key\PublicKey;

final class PrivateKeyTest extends TestCase
{
    public function testHoldsAlgorithmPurposeAndBytes()
    {
        $key = PrivateKey::fromBytes('x25519', 'encryption', str_repeat("\x02", 32));

        self::assertSame('x25519', $key->algorithm());
        self::assertSame('encryption', $key->purpose());
        self::assertSame(str_repeat("\x02", 32), $key->bytes());
    }

    public function testExportImportRoundTrip()
    {
        $key = PrivateKey::fromBytes('x25519', 'encryption', random_bytes(32));

        $imported = PrivateKey::import($key->export());

        self::assertSame($key->bytes(), $imported->bytes());
        self::assertSame('x25519', $imported->algorithm());
        self::assertSame('encryption', $imported->purpose());
    }

    public function testDerivePublicMatchesSodium()
    {
        $sk = random_bytes(32);
        $key = PrivateKey::fromBytes('x25519', 'encryption', $sk);

        $public = $key->derivePublic();

        self::assertInstanceOf(PublicKey::class, $public);
        self::assertSame(sodium_crypto_box_publickey_from_secretkey($sk), $public->bytes());
        self::assertSame('encryption', $public->purpose());
    }

    public function testImportRejectsPublicKeyMagic()
    {
        $public = PublicKey::fromBytes('x25519', 'encryption', random_bytes(32));

        $this->expectException(InvalidKeyException::class);

        PrivateKey::import($public->export());
    }
}
