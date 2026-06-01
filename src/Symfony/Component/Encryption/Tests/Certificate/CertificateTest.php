<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Tests\Certificate;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Encryption\Certificate\Certificate;
use Symfony\Component\Encryption\Certificate\DistinguishedName;
use Symfony\Component\Encryption\Exception\UnsupportedAlgorithmException;

final class CertificateTest extends TestCase
{
    private function certificate(): Certificate
    {
        return new Certificate(
            subject: new DistinguishedName(['CN' => 'example.com']),
            issuer: new DistinguishedName(['CN' => 'Issuer CA']),
            serialNumber: '0a1b2c',
            validFrom: new \DateTimeImmutable('2024-01-01T00:00:00+00:00'),
            validTo: new \DateTimeImmutable('2025-01-01T00:00:00+00:00'),
            subjectAlternativeNames: ['DNS:example.com', 'DNS:www.example.com'],
            signatureAlgorithm: 'RSA-SHA256',
            publicKeyPem: "-----BEGIN PUBLIC KEY-----\nMII...\n-----END PUBLIC KEY-----\n",
            der: 'binary-der-bytes',
            pem: "-----BEGIN CERTIFICATE-----\nMII...\n-----END CERTIFICATE-----\n",
        );
    }

    public function testAccessors()
    {
        $cert = $this->certificate();

        self::assertSame('example.com', $cert->subject()->commonName());
        self::assertSame('Issuer CA', $cert->issuer()->commonName());
        self::assertSame('0a1b2c', $cert->serialNumber());
        self::assertSame('RSA-SHA256', $cert->signatureAlgorithm());
        self::assertSame(['DNS:example.com', 'DNS:www.example.com'], $cert->subjectAlternativeNames());
        self::assertStringContainsString('PUBLIC KEY', $cert->publicKeyPem());
        self::assertStringContainsString('CERTIFICATE', $cert->pem());
    }

    public function testFingerprintIsHashOfDer()
    {
        $cert = $this->certificate();

        self::assertSame(hash('sha256', 'binary-der-bytes'), $cert->fingerprint());
        self::assertSame(hash('sha1', 'binary-der-bytes'), $cert->fingerprint('sha1'));
    }

    public function testFingerprintRejectsUnsupportedAlgorithm()
    {
        $this->expectException(UnsupportedAlgorithmException::class);

        $this->certificate()->fingerprint('md5');
    }

    public function testExpiryChecks()
    {
        $cert = $this->certificate();

        self::assertFalse($cert->isExpired(new \DateTimeImmutable('2024-06-01T00:00:00+00:00')));
        self::assertTrue($cert->isExpired(new \DateTimeImmutable('2025-06-01T00:00:00+00:00')));

        self::assertTrue($cert->isValidAt(new \DateTimeImmutable('2024-06-01T00:00:00+00:00')));
        self::assertFalse($cert->isValidAt(new \DateTimeImmutable('2023-06-01T00:00:00+00:00')));
        self::assertFalse($cert->isValidAt(new \DateTimeImmutable('2025-06-01T00:00:00+00:00')));
    }
}
