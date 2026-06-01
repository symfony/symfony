<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Engine;

/**
 * Internal contract for an X.509 certificate backend.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @internal
 */
interface CertificateEngineInterface
{
    /**
     * Normalize a PEM or DER certificate to PEM.
     */
    public function normalizePem(string $certificate): string;

    /**
     * @return array{
     *     subject: array<string, string>,
     *     issuer: array<string, string>,
     *     serialNumber: string,
     *     validFrom: int,
     *     validTo: int,
     *     subjectAlternativeNames: list<string>,
     *     signatureAlgorithm: string,
     * }
     */
    public function parse(string $certificatePem): array;

    /**
     * The certificate's public key as an SPKI PEM.
     */
    public function publicKeyPem(string $certificatePem): string;

    /**
     * The raw DER bytes of the certificate.
     */
    public function derBytes(string $certificatePem): string;

    /**
     * Verify the certificate's signature against an issuer's public-key PEM.
     */
    public function verify(string $certificatePem, string $issuerPublicKeyPem): bool;

    public function isAvailable(): bool;

    public function name(): string;

    /**
     * @return array{0: string, 1: string} [publicPem, privatePem]
     */
    public function generateKeyPair(string $algorithm, int $rsaKeyBits): array;

    /**
     * @param array<string, string> $subject  Short-name DN fields (CN, O, ...)
     * @param list<string>          $dnsNames
     */
    public function createCsr(array $subject, string $privateKeyPem, array $dnsNames): string;

    /**
     * @param array<string, string> $subject  Short-name DN fields (CN, O, ...)
     * @param list<string>          $dnsNames
     */
    public function createSelfSigned(array $subject, string $privateKeyPem, int $days, array $dnsNames, int $serial): string;

    /**
     * @return array{subject: array<string, string>, publicKeyPem: string}
     */
    public function parseCsr(string $csrPem): array;
}
