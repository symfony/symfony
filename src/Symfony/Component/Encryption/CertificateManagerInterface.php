<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption;

use Symfony\Component\Encryption\Certificate\Certificate;
use Symfony\Component\Encryption\Certificate\CertificateSigningRequest;
use Symfony\Component\Encryption\Certificate\DistinguishedName;
use Symfony\Component\Encryption\Key\KeyPair;
use Symfony\Component\Encryption\Key\PrivateKey;

/**
 * X.509 certificate inspection and verification.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @api
 */
interface CertificateManagerInterface
{
    /**
     * Parse a PEM or DER certificate into a {@see Certificate}.
     */
    public function load(string $certificate): Certificate;

    /**
     * Verify that the issuer's public key signed the subject certificate.
     *
     * This checks a single issuer/subject link only. It is NOT full path
     * validation: it does not build or validate a chain to a trust anchor, and
     * it does not check expiry, revocation (CRL/OCSP), name constraints, or key
     * usage. Combine it with {@see Certificate::isValidAt()} and your own trust
     * decisions, or use a dedicated PKI library, when those checks matter.
     */
    public function verify(Certificate $subject, Certificate $issuer): bool;

    /**
     * True when the certificate is self-signed (issuer == subject and the
     * signature verifies against its own public key).
     */
    public function isSelfSigned(Certificate $certificate): bool;

    /**
     * Generate an RSA or ECDSA P-256 key pair for certificate use.
     */
    public function generateKeyPair(string $algorithm = 'rsa', int $rsaKeyBits = 2048): KeyPair;

    /**
     * Build a Certificate Signing Request for the subject, signed by the key.
     *
     * @param list<string> $dnsNames Subject Alternative DNS names
     */
    public function createCsr(DistinguishedName $subject, PrivateKey $key, array $dnsNames = []): CertificateSigningRequest;

    /**
     * Create a self-signed certificate for the subject.
     *
     * @param list<string> $dnsNames Subject Alternative DNS names
     */
    public function createSelfSigned(DistinguishedName $subject, PrivateKey $key, int $days = 365, array $dnsNames = []): Certificate;
}
