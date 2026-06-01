<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Certificate;

use Symfony\Component\Encryption\Exception\UnsupportedAlgorithmException;

/**
 * A parsed, immutable X.509 certificate.
 *
 * Obtain instances from {@see \Symfony\Component\Encryption\CertificateManager::load()}.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @api
 */
final class Certificate
{
    private const FINGERPRINT_ALGORITHMS = ['sha1', 'sha256', 'sha384', 'sha512'];

    /**
     * @param list<string> $subjectAlternativeNames
     */
    public function __construct(
        private readonly DistinguishedName $subject,
        private readonly DistinguishedName $issuer,
        private readonly string $serialNumber,
        private readonly \DateTimeImmutable $validFrom,
        private readonly \DateTimeImmutable $validTo,
        private readonly array $subjectAlternativeNames,
        private readonly string $signatureAlgorithm,
        private readonly string $publicKeyPem,
        private readonly string $der,
        private readonly string $pem,
    ) {
    }

    public function subject(): DistinguishedName
    {
        return $this->subject;
    }

    public function issuer(): DistinguishedName
    {
        return $this->issuer;
    }

    public function serialNumber(): string
    {
        return $this->serialNumber;
    }

    public function validFrom(): \DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function validTo(): \DateTimeImmutable
    {
        return $this->validTo;
    }

    /**
     * @return list<string>
     */
    public function subjectAlternativeNames(): array
    {
        return $this->subjectAlternativeNames;
    }

    public function signatureAlgorithm(): string
    {
        return $this->signatureAlgorithm;
    }

    public function publicKeyPem(): string
    {
        return $this->publicKeyPem;
    }

    public function pem(): string
    {
        return $this->pem;
    }

    /**
     * Lowercase-hex fingerprint (hash of the DER encoding).
     */
    public function fingerprint(string $algorithm = 'sha256'): string
    {
        if (!\in_array($algorithm, self::FINGERPRINT_ALGORITHMS, true)) {
            throw new UnsupportedAlgorithmException(\sprintf('Unsupported fingerprint algorithm "%s". Supported: %s.', $algorithm, implode(', ', self::FINGERPRINT_ALGORITHMS)));
        }

        return hash($algorithm, $this->der);
    }

    /**
     * Whether the certificate is past its "not after" date. Only the upper
     * bound is checked — a not-yet-valid certificate is not "expired". Use
     * {@see self::isValidAt()} for a full validity-window check.
     */
    public function isExpired(?\DateTimeImmutable $at = null): bool
    {
        $at ??= new \DateTimeImmutable();

        return $at > $this->validTo;
    }

    public function isValidAt(?\DateTimeImmutable $at = null): bool
    {
        $at ??= new \DateTimeImmutable();

        return $at >= $this->validFrom && $at <= $this->validTo;
    }
}
