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

namespace Symfony\Component\Encryption;

use Symfony\Component\Encryption\Certificate\Certificate;
use Symfony\Component\Encryption\Certificate\CertificateSigningRequest;
use Symfony\Component\Encryption\Certificate\DistinguishedName;
use Symfony\Component\Encryption\Engine\EngineSelector;
use Symfony\Component\Encryption\Exception\InvalidKeyException;
use Symfony\Component\Encryption\Key\KeyPair;
use Symfony\Component\Encryption\Key\PrivateKey;
use Symfony\Component\Encryption\Key\PublicKey;

/**
 * X.509 certificate inspection and verification (OpenSSL-backed).
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @api
 */
final class CertificateManager implements CertificateManagerInterface
{
    public function __construct(
        private readonly EngineSelector $engines = new EngineSelector(),
    ) {
    }

    #[\Override]
    public function load(string $certificate): Certificate
    {
        $engine = $this->engines->certificateEngine();
        $pem = $engine->normalizePem($certificate);
        $parsed = $engine->parse($pem);

        return new Certificate(
            subject: new DistinguishedName($parsed['subject']),
            issuer: new DistinguishedName($parsed['issuer']),
            serialNumber: $parsed['serialNumber'],
            validFrom: (new \DateTimeImmutable())->setTimestamp($parsed['validFrom']),
            validTo: (new \DateTimeImmutable())->setTimestamp($parsed['validTo']),
            subjectAlternativeNames: $parsed['subjectAlternativeNames'],
            signatureAlgorithm: $parsed['signatureAlgorithm'],
            publicKeyPem: $engine->publicKeyPem($pem),
            der: $engine->derBytes($pem),
            pem: $pem,
        );
    }

    /**
     * Verify that the issuer's public key signed the subject certificate.
     *
     * This checks a single issuer/subject link only. It is NOT full path
     * validation: it does not build or validate a chain to a trust anchor, and
     * it does not check expiry, revocation (CRL/OCSP), name constraints, or key
     * usage. Combine it with {@see Certificate::isValidAt()} and your own trust
     * decisions, or use a dedicated PKI library, when those checks matter.
     */
    #[\Override]
    public function verify(Certificate $subject, Certificate $issuer): bool
    {
        return $this->engines->certificateEngine()->verify($subject->pem(), $issuer->publicKeyPem());
    }

    #[\Override]
    public function isSelfSigned(Certificate $certificate): bool
    {
        return $certificate->subject()->equals($certificate->issuer())
            && $this->engines->certificateEngine()->verify($certificate->pem(), $certificate->publicKeyPem());
    }

    #[\Override]
    public function generateKeyPair(string $algorithm = 'rsa', int $rsaKeyBits = 2048): KeyPair
    {
        [$public, $private] = $this->engines->certificateEngine()->generateKeyPair($algorithm, $rsaKeyBits);

        return new KeyPair(
            PublicKey::fromBytes($algorithm, 'signing', $public),
            PrivateKey::fromBytes($algorithm, 'signing', $private),
        );
    }

    #[\Override]
    public function createCsr(DistinguishedName $subject, PrivateKey $key, array $dnsNames = []): CertificateSigningRequest
    {
        $this->assertCertificateKey($key);
        $engine = $this->engines->certificateEngine();
        $csrPem = $engine->createCsr($subject->toArray(), $key->bytes(), $dnsNames);
        $parsed = $engine->parseCsr($csrPem);

        return new CertificateSigningRequest(new DistinguishedName($parsed['subject']), $parsed['publicKeyPem'], $csrPem);
    }

    #[\Override]
    public function createSelfSigned(DistinguishedName $subject, PrivateKey $key, int $days = 365, array $dnsNames = []): Certificate
    {
        $this->assertCertificateKey($key);
        $certPem = $this->engines->certificateEngine()->createSelfSigned(
            $subject->toArray(),
            $key->bytes(),
            $days,
            $dnsNames,
            random_int(1, \PHP_INT_MAX),
        );

        return $this->load($certPem);
    }

    private function assertCertificateKey(PrivateKey $key): void
    {
        if (!\in_array($key->algorithm(), ['rsa', 'ecdsa-p256'], true)) {
            throw new InvalidKeyException(\sprintf(
                'Certificate operations require an RSA or ECDSA P-256 key; got "%s".',
                $key->algorithm(),
            ));
        }
    }
}
