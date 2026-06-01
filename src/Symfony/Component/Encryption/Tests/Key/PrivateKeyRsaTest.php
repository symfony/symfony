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

final class PrivateKeyRsaTest extends TestCase
{
    protected function setUp(): void
    {
        if (!\extension_loaded('openssl')) {
            self::markTestSkipped('openssl required to mint an RSA key for this test.');
        }
    }

    private function rsaPrivatePem(): string
    {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($resource);
        self::assertTrue(openssl_pkey_export($resource, $pem));

        return $pem;
    }

    public function testHoldsRsaPemMaterial()
    {
        $pem = $this->rsaPrivatePem();

        $key = PrivateKey::fromBytes('rsa', 'encryption', $pem);

        self::assertSame('rsa', $key->algorithm());
        self::assertSame($pem, $key->bytes());
    }

    public function testRsaExportImportRoundTrip()
    {
        $key = PrivateKey::fromBytes('rsa', 'encryption', $this->rsaPrivatePem());

        $imported = PrivateKey::import($key->export());

        self::assertSame($key->bytes(), $imported->bytes());
        self::assertSame('rsa', $imported->algorithm());
    }

    public function testDerivePublicReturnsSpkiPublicKey()
    {
        $key = PrivateKey::fromBytes('rsa', 'encryption', $this->rsaPrivatePem());

        $public = $key->derivePublic();

        self::assertInstanceOf(PublicKey::class, $public);
        self::assertSame('rsa', $public->algorithm());
        self::assertStringContainsString('PUBLIC KEY', $public->bytes());
    }

    public function testDerivePublicRejectsGarbagePem()
    {
        $key = PrivateKey::fromBytes('rsa', 'encryption', "-----BEGIN RSA PRIVATE KEY-----\nnotvalidbase64!!!\n-----END RSA PRIVATE KEY-----\n");

        $this->expectException(InvalidKeyException::class);

        $key->derivePublic();
    }

    public function testDerivePublicRejectsPublicKeyPem()
    {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($resource);
        $details = openssl_pkey_get_details($resource);
        self::assertIsArray($details);
        $publicPem = $details['key'];

        // Store the public PEM as if it were a private key — derivePublic() must reject it.
        $key = PrivateKey::fromBytes('rsa', 'encryption', $publicPem);

        $this->expectException(InvalidKeyException::class);

        $key->derivePublic();
    }
}
