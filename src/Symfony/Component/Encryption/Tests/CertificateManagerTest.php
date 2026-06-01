<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Encryption\Certificate\Certificate;
use Symfony\Component\Encryption\CertificateManager;
use Symfony\Component\Encryption\Exception\CertificateException;

final class CertificateManagerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!\extension_loaded('openssl')) {
            self::markTestSkipped('ext-openssl is required.');
        }
    }

    /**
     * @return array{0: string, 1: string} [certPem, privateKeyPem]
     */
    private function selfSigned(string $cn = 'example.com', int $days = 365): array
    {
        $pk = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        \assert(false !== $pk);
        $privateKeyPem = '';
        openssl_pkey_export($pk, $privateKeyPem);
        $csr = openssl_csr_new(['commonName' => $cn, 'organizationName' => 'Acme'], $pk, ['digest_alg' => 'sha256']);
        \assert(false !== $csr);
        $cert = openssl_csr_sign($csr, null, $pk, $days, ['digest_alg' => 'sha256']);
        \assert(false !== $cert);
        $certPem = '';
        openssl_x509_export($cert, $certPem);

        return [$certPem, $privateKeyPem];
    }

    public function testLoadParsesCertificate()
    {
        [$pem] = $this->selfSigned();

        $cert = (new CertificateManager())->load($pem);

        self::assertInstanceOf(Certificate::class, $cert);
        self::assertSame('example.com', $cert->subject()->commonName());
        self::assertSame('Acme', $cert->subject()->organization());
        self::assertStringContainsString('PUBLIC KEY', $cert->publicKeyPem());
        self::assertFalse($cert->isExpired());
    }

    public function testLoadRejectsGarbage()
    {
        $this->expectException(CertificateException::class);

        (new CertificateManager())->load('not a certificate');
    }

    public function testIsSelfSigned()
    {
        $manager = new CertificateManager();
        [$pem] = $this->selfSigned();

        self::assertTrue($manager->isSelfSigned($manager->load($pem)));
    }

    public function testVerifyAgainstIssuer()
    {
        $manager = new CertificateManager();
        [$pem] = $this->selfSigned();
        $cert = $manager->load($pem);

        // A self-signed cert is its own issuer.
        self::assertTrue($manager->verify($cert, $cert));
    }

    public function testVerifyRejectsUnrelatedIssuer()
    {
        $manager = new CertificateManager();
        $a = $manager->load($this->selfSigned('a.example')[0]);
        $b = $manager->load($this->selfSigned('b.example')[0]);

        self::assertFalse($manager->verify($a, $b));
        self::assertFalse($manager->verify($b, $a));
    }
}
