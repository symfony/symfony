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

namespace Symfony\Component\Encryption\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Encryption\Certificate\CertificateSigningRequest;
use Symfony\Component\Encryption\Certificate\DistinguishedName;
use Symfony\Component\Encryption\CertificateManager;
use Symfony\Component\Encryption\Exception\InvalidKeyException;
use Symfony\Component\Encryption\Key\KeyPair;
use Symfony\Component\Encryption\Signer;

final class CertificateManagerGenerationTest extends TestCase
{
    protected function setUp(): void
    {
        if (!\extension_loaded('openssl')) {
            self::markTestSkipped('ext-openssl is required.');
        }
    }

    public function testGenerateRsaKeyPair(): void
    {
        $pair = (new CertificateManager())->generateKeyPair('rsa', 2048);

        self::assertInstanceOf(KeyPair::class, $pair);
        self::assertSame('rsa', $pair->algorithm());
        self::assertStringContainsString('PRIVATE KEY', $pair->private()->bytes());
    }

    public function testGenerateEcKeyPair(): void
    {
        $pair = (new CertificateManager())->generateKeyPair('ecdsa-p256');

        self::assertSame('ecdsa-p256', $pair->algorithm());
    }

    public function testCreateCsr(): void
    {
        $manager = new CertificateManager();
        $key = $manager->generateKeyPair('rsa', 2048);

        $csr = $manager->createCsr(new DistinguishedName(['CN' => 'example.com', 'O' => 'Acme']), $key->private());

        self::assertInstanceOf(CertificateSigningRequest::class, $csr);
        self::assertSame('example.com', $csr->subject()->commonName());
        self::assertStringContainsString('CERTIFICATE REQUEST', $csr->pem());
    }

    public function testCreateSelfSignedIsLoadableAndSelfSigned(): void
    {
        $manager = new CertificateManager();
        $key = $manager->generateKeyPair('rsa', 2048);

        $cert = $manager->createSelfSigned(
            new DistinguishedName(['CN' => 'self.example', 'O' => 'Acme']),
            $key->private(),
            30,
            ['self.example', 'www.self.example'],
        );

        self::assertSame('self.example', $cert->subject()->commonName());
        self::assertTrue($manager->isSelfSigned($cert));
        self::assertFalse($cert->isExpired());
        self::assertContains('DNS:self.example', $cert->subjectAlternativeNames());
    }

    public function testEcSelfSignedRoundTrip(): void
    {
        $manager = new CertificateManager();
        $key = $manager->generateKeyPair('ecdsa-p256');

        $cert = $manager->createSelfSigned(new DistinguishedName(['CN' => 'ec.example']), $key->private());

        self::assertTrue($manager->isSelfSigned($cert));
    }

    public function testCreateCsrRejectsNonCertificateKey(): void
    {
        $manager = new CertificateManager();
        $ed25519 = (new Signer())->generateKeyPair(); // ed25519 signing key

        $this->expectException(InvalidKeyException::class);

        $manager->createCsr(new DistinguishedName(['CN' => 'x']), $ed25519->private());
    }

    public function testCreateSelfSignedRejectsNonCertificateKey(): void
    {
        $manager = new CertificateManager();
        $ed25519 = (new Signer())->generateKeyPair();

        $this->expectException(InvalidKeyException::class);

        $manager->createSelfSigned(new DistinguishedName(['CN' => 'x']), $ed25519->private());
    }
}
