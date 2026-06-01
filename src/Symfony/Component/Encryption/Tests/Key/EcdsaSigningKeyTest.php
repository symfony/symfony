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
use Symfony\Component\Encryption\Key\PrivateKey;
use Symfony\Component\Encryption\Key\PublicKey;

final class EcdsaSigningKeyTest extends TestCase
{
    protected function setUp(): void
    {
        if (!\extension_loaded('openssl')) {
            self::markTestSkipped('ext-openssl is required to mint an EC key for this test.');
        }
    }

    private function ecPrivatePem(): string
    {
        $resource = openssl_pkey_new(['private_key_type' => \OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        self::assertNotFalse($resource);
        self::assertTrue(openssl_pkey_export($resource, $pem));

        return $pem;
    }

    public function testHoldsEcdsaPemMaterial(): void
    {
        $pem = $this->ecPrivatePem();

        $key = PrivateKey::fromBytes('ecdsa-p256', 'signing', $pem);

        self::assertSame('ecdsa-p256', $key->algorithm());
        self::assertSame('signing', $key->purpose());
        self::assertSame($pem, $key->bytes());
    }

    public function testExportImportRoundTrip(): void
    {
        $key = PrivateKey::fromBytes('ecdsa-p256', 'signing', $this->ecPrivatePem());

        $imported = PrivateKey::import($key->export());

        self::assertSame($key->bytes(), $imported->bytes());
        self::assertSame('ecdsa-p256', $imported->algorithm());
    }

    public function testDerivePublicReturnsSpkiPublicKey(): void
    {
        $public = PrivateKey::fromBytes('ecdsa-p256', 'signing', $this->ecPrivatePem())->derivePublic();

        self::assertInstanceOf(PublicKey::class, $public);
        self::assertSame('ecdsa-p256', $public->algorithm());
        self::assertStringContainsString('PUBLIC KEY', $public->bytes());
    }

    public function testDerivePublicRejectsGarbagePem(): void
    {
        $key = PrivateKey::fromBytes('ecdsa-p256', 'signing', 'not a pem');

        $this->expectException(\Symfony\Component\Encryption\Exception\InvalidKeyException::class);

        $key->derivePublic();
    }
}
