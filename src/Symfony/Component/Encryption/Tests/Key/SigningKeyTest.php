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

namespace Symfony\Component\Encryption\Tests\Key;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Encryption\Exception\InvalidKeyException;
use Symfony\Component\Encryption\Key\PrivateKey;
use Symfony\Component\Encryption\Key\PublicKey;

final class SigningKeyTest extends TestCase
{
    protected function setUp(): void
    {
        if (!\function_exists('sodium_crypto_sign_keypair')) {
            self::markTestSkipped('ext-sodium is required.');
        }
    }

    public function testHoldsSigningPurposeAndAlgorithm(): void
    {
        $keypair = sodium_crypto_sign_keypair();
        $sk = sodium_crypto_sign_secretkey($keypair);

        $key = PrivateKey::fromBytes('ed25519', 'signing', $sk);

        self::assertSame('ed25519', $key->algorithm());
        self::assertSame('signing', $key->purpose());
    }

    public function testExportImportRoundTrip(): void
    {
        $sk = sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair());
        $key = PrivateKey::fromBytes('ed25519', 'signing', $sk);

        $imported = PrivateKey::import($key->export());

        self::assertSame($key->bytes(), $imported->bytes());
        self::assertSame('ed25519', $imported->algorithm());
        self::assertSame('signing', $imported->purpose());
    }

    public function testDerivePublicIsTrailing32BytesOfSecret(): void
    {
        $keypair = sodium_crypto_sign_keypair();
        $sk = sodium_crypto_sign_secretkey($keypair);
        $pk = sodium_crypto_sign_publickey($keypair);

        $public = PrivateKey::fromBytes('ed25519', 'signing', $sk)->derivePublic();

        self::assertInstanceOf(PublicKey::class, $public);
        self::assertSame($pk, $public->bytes());
        self::assertSame('signing', $public->purpose());
    }

    public function testDerivePublicRejectsWrongLengthEd25519Secret(): void
    {
        $key = PrivateKey::fromBytes('ed25519', 'signing', str_repeat("\x01", 32));

        $this->expectException(InvalidKeyException::class);

        $key->derivePublic();
    }
}
