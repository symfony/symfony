<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Engine\OpenSsl;

use Symfony\Component\Encryption\Engine\CertificateEngineInterface;
use Symfony\Component\Encryption\Exception\CertificateException;
use Symfony\Component\Encryption\Exception\InvalidArgumentException;
use Symfony\Component\Encryption\Exception\InvalidKeyException;

/**
 * X.509 certificate inspection and verification via OpenSSL.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @internal
 */
final class OpenSslCertificateEngine implements CertificateEngineInterface
{
    #[\Override]
    public function normalizePem(string $certificate): string
    {
        if (str_contains($certificate, '-----BEGIN CERTIFICATE-----')) {
            return $certificate;
        }

        // Treat as DER: wrap base64 in PEM armor.
        return "-----BEGIN CERTIFICATE-----\n"
            .chunk_split(base64_encode($certificate), 64, "\n")
            ."-----END CERTIFICATE-----\n";
    }

    #[\Override]
    public function parse(string $certificatePem): array
    {
        $parsed = openssl_x509_parse($certificatePem);
        if (false === $parsed) {
            throw new CertificateException('Unable to parse the certificate.');
        }

        return [
            'subject' => $this->stringMap($this->mixedToArray($parsed['subject'] ?? null)),
            'issuer' => $this->stringMap($this->mixedToArray($parsed['issuer'] ?? null)),
            'serialNumber' => $this->mixedToString($parsed['serialNumberHex'] ?? null),
            'validFrom' => $this->mixedToInt($parsed['validFrom_time_t'] ?? null),
            'validTo' => $this->mixedToInt($parsed['validTo_time_t'] ?? null),
            'subjectAlternativeNames' => $this->parseSans(
                $this->mixedToString(
                    $this->mixedToArray($parsed['extensions'] ?? null)['subjectAltName'] ?? null
                )
            ),
            'signatureAlgorithm' => $this->mixedToString($parsed['signatureTypeSN'] ?? null),
        ];
    }

    #[\Override]
    public function publicKeyPem(string $certificatePem): string
    {
        $key = openssl_pkey_get_public($certificatePem);
        if (false === $key) {
            throw new CertificateException('Unable to read the certificate public key.');
        }

        $details = openssl_pkey_get_details($key);
        if (false === $details || !isset($details['key']) || !\is_string($details['key'])) {
            throw new CertificateException('Unable to read the certificate public key.');
        }

        return $details['key'];
    }

    #[\Override]
    public function derBytes(string $certificatePem): string
    {
        $body = preg_replace('/-----[^-]+-----|\s+/', '', $certificatePem);
        $der = base64_decode((string) $body, true);
        if (false === $der) {
            throw new CertificateException('Unable to decode the certificate.');
        }

        return $der;
    }

    #[\Override]
    public function verify(string $certificatePem, string $issuerPublicKeyPem): bool
    {
        $key = openssl_pkey_get_public($issuerPublicKeyPem);
        if (false === $key) {
            return false;
        }

        return 1 === openssl_x509_verify($certificatePem, $key);
    }

    #[\Override]
    public function isAvailable(): bool
    {
        return \extension_loaded('openssl');
    }

    #[\Override]
    public function name(): string
    {
        return 'openssl';
    }

    private const DN_LONG_NAMES = [
        'CN' => 'commonName',
        'O' => 'organizationName',
        'OU' => 'organizationalUnitName',
        'C' => 'countryName',
        'ST' => 'stateOrProvinceName',
        'L' => 'localityName',
        'emailAddress' => 'emailAddress',
    ];

    #[\Override]
    public function generateKeyPair(string $algorithm, int $rsaKeyBits): array
    {
        $config = match ($algorithm) {
            'rsa' => ['private_key_bits' => $rsaKeyBits, 'private_key_type' => \OPENSSL_KEYTYPE_RSA],
            'ecdsa-p256' => ['private_key_type' => \OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'],
            default => throw new InvalidArgumentException(\sprintf('Unsupported certificate key algorithm "%s".', $algorithm)),
        };

        $privatePem = '';
        $resource = openssl_pkey_new($config);
        if (false === $resource || !openssl_pkey_export($resource, $privatePem)) {
            throw new CertificateException('Key generation failed.');
        }

        $details = openssl_pkey_get_details($resource);
        $publicPem = $details['key'] ?? null;
        if (false === $details || !\is_string($publicPem)) {
            throw new CertificateException('Key generation failed.');
        }

        return [$publicPem, $privatePem];
    }

    #[\Override]
    public function createCsr(array $subject, string $privateKeyPem, array $dnsNames): string
    {
        $key = openssl_pkey_get_private($privateKeyPem);
        if (false === $key) {
            throw new InvalidKeyException('Invalid private key for certificate operations.');
        }

        $configPath = $this->writeSanConfig($dnsNames);
        try {
            $options = ['digest_alg' => 'sha256'];
            if (null !== $configPath) {
                $options['config'] = $configPath;
                $options['req_extensions'] = 'v3';
            }

            $csr = openssl_csr_new($this->longDn($subject), $key, $options);
            if (!$csr instanceof \OpenSSLCertificateSigningRequest) {
                throw new CertificateException('Unable to create the certificate signing request.');
            }
            $csrPem = '';
            if (!openssl_csr_export($csr, $csrPem)) {
                throw new CertificateException('Unable to export the certificate signing request.');
            }

            return $csrPem;
        } finally {
            $this->removeFile($configPath);
        }
    }

    #[\Override]
    public function createSelfSigned(array $subject, string $privateKeyPem, int $days, array $dnsNames, int $serial): string
    {
        $key = openssl_pkey_get_private($privateKeyPem);
        if (false === $key) {
            throw new InvalidKeyException('Invalid private key for certificate operations.');
        }

        $configPath = $this->writeSanConfig($dnsNames);
        try {
            $csrOptions = ['digest_alg' => 'sha256'];
            $signOptions = ['digest_alg' => 'sha256'];
            if (null !== $configPath) {
                $csrOptions['config'] = $configPath;
                $csrOptions['req_extensions'] = 'v3';
                $signOptions['config'] = $configPath;
                $signOptions['x509_extensions'] = 'v3';
            }

            $csr = openssl_csr_new($this->longDn($subject), $key, $csrOptions);
            if (!$csr instanceof \OpenSSLCertificateSigningRequest) {
                throw new CertificateException('Unable to create the certificate.');
            }

            $cert = openssl_csr_sign($csr, null, $key, $days, $signOptions, $serial);
            if (false === $cert) {
                throw new CertificateException('Unable to sign the certificate.');
            }
            $certPem = '';
            if (!openssl_x509_export($cert, $certPem)) {
                throw new CertificateException('Unable to export the certificate.');
            }

            return $certPem;
        } finally {
            $this->removeFile($configPath);
        }
    }

    #[\Override]
    public function parseCsr(string $csrPem): array
    {
        $subject = openssl_csr_get_subject($csrPem);
        if (false === $subject) {
            throw new CertificateException('Unable to read the CSR subject.');
        }

        $key = openssl_csr_get_public_key($csrPem);
        if (false === $key) {
            throw new CertificateException('Unable to read the CSR public key.');
        }

        $details = openssl_pkey_get_details($key);
        $publicKeyPem = $details['key'] ?? null;
        if (false === $details || !\is_string($publicKeyPem)) {
            throw new CertificateException('Unable to read the CSR public key.');
        }

        return ['subject' => $this->stringMap($subject), 'publicKeyPem' => $publicKeyPem];
    }

    /**
     * @param array<string, string> $subject
     *
     * @return array<string, string>
     */
    private function longDn(array $subject): array
    {
        $out = [];
        foreach ($subject as $short => $value) {
            $out[self::DN_LONG_NAMES[$short] ?? $short] = $value;
        }

        return $out;
    }

    /**
     * @param list<string> $dnsNames
     */
    private function writeSanConfig(array $dnsNames): ?string
    {
        if ([] === $dnsNames) {
            return null;
        }

        $entries = implode(',', array_map(static fn (string $name): string => 'DNS:'.$name, $dnsNames));
        $path = tempnam(sys_get_temp_dir(), 'sym-enc-csr');
        if (false === $path) {
            throw new CertificateException('Unable to prepare the certificate SAN configuration.');
        }

        $written = file_put_contents(
            $path,
            "[req]\ndistinguished_name = dn\nreq_extensions = v3\n[dn]\n[v3]\nsubjectAltName = ".$entries."\n",
        );
        if (false === $written) {
            $this->removeFile($path);

            throw new CertificateException('Unable to prepare the certificate SAN configuration.');
        }

        return $path;
    }

    private function removeFile(?string $path): void
    {
        if (null !== $path && is_file($path)) {
            unlink($path);
        }
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @return array<string, string>
     */
    private function stringMap(array $value): array
    {
        $result = [];
        /** @var mixed $item */
        foreach ($value as $key => $item) {
            if (!\is_string($key)) {
                continue;
            }
            if (\is_string($item)) {
                $result[$key] = $item;
            } elseif (\is_array($item)) {
                /** @var mixed $first */
                $first = $item[0] ?? null;
                if (\is_string($first)) {
                    $result[$key] = $first;
                }
            }
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private function parseSans(string $subjectAltName): array
    {
        if ('' === $subjectAltName) {
            return [];
        }

        /** @var list<string> */
        return array_map('trim', explode(',', $subjectAltName));
    }

    /** @return array<array-key, mixed> */
    private function mixedToArray(mixed $value): array
    {
        return \is_array($value) ? $value : [];
    }

    private function mixedToString(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }

    private function mixedToInt(mixed $value): int
    {
        return \is_int($value) ? $value : 0;
    }
}
