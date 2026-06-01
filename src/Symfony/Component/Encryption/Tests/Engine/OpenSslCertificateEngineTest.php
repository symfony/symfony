<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Tests\Engine;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Encryption\Engine\OpenSsl\OpenSslCertificateEngine;
use Symfony\Component\Encryption\Exception\CertificateException;

final class OpenSslCertificateEngineTest extends TestCase
{
    private OpenSslCertificateEngine $engine;
    private string $certPem = '';
    private string $privateKeyPem = '';

    protected function setUp()
    {
        $this->engine = new OpenSslCertificateEngine();
        if (!$this->engine->isAvailable()) {
            self::markTestSkipped('ext-openssl is required.');
        }

        $pk = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($pk);
        openssl_pkey_export($pk, $this->privateKeyPem);
        $csr = openssl_csr_new(['commonName' => 'example.com', 'organizationName' => 'Acme'], $pk, ['digest_alg' => 'sha256']);
        self::assertNotFalse($csr);
        $cert = openssl_csr_sign($csr, null, $pk, 365, ['digest_alg' => 'sha256']);
        self::assertNotFalse($cert);
        openssl_x509_export($cert, $this->certPem);
    }

    public function testParseExtractsFields()
    {
        $parsed = $this->engine->parse($this->certPem);

        self::assertSame('example.com', $parsed['subject']['CN']);
        self::assertSame('example.com', $parsed['issuer']['CN']);
        self::assertIsInt($parsed['validFrom']);
        self::assertIsInt($parsed['validTo']);
        self::assertSame('RSA-SHA256', $parsed['signatureAlgorithm']);
        self::assertSame([], $parsed['subjectAlternativeNames']);
    }

    public function testParseExtractsSubjectAlternativeNames()
    {
        $tmpConf = sys_get_temp_dir().'/openssl_san_test.cnf';
        file_put_contents($tmpConf, implode("\n", [
            '[req]',
            'distinguished_name=dn',
            'req_extensions=v3_req',
            '[dn]',
            '[v3_req]',
            'subjectAltName=DNS:foo.example.com,DNS:bar.example.com',
        ]));

        try {
            $pk = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
            self::assertNotFalse($pk);
            $csr = openssl_csr_new(
                ['commonName' => 'san.example.com'],
                $pk,
                ['digest_alg' => 'sha256', 'config' => $tmpConf],
            );
            self::assertNotFalse($csr);
            $cert = openssl_csr_sign(
                $csr,
                null,
                $pk,
                365,
                ['digest_alg' => 'sha256', 'config' => $tmpConf, 'x509_extensions' => 'v3_req'],
            );
            self::assertNotFalse($cert);
            openssl_x509_export($cert, $sanPem);

            $parsed = $this->engine->parse($sanPem);

            self::assertSame(
                ['DNS:foo.example.com', 'DNS:bar.example.com'],
                $parsed['subjectAlternativeNames'],
            );
        } finally {
            @unlink($tmpConf);
        }
    }

    public function testParseRejectsGarbage()
    {
        $this->expectException(CertificateException::class);

        $this->engine->parse('not a certificate');
    }

    public function testPublicKeyPem()
    {
        self::assertStringContainsString('PUBLIC KEY', $this->engine->publicKeyPem($this->certPem));
    }

    public function testVerifySelfSigned()
    {
        $publicKeyPem = $this->engine->publicKeyPem($this->certPem);

        self::assertTrue($this->engine->verify($this->certPem, $publicKeyPem));
    }

    public function testVerifyRejectsWrongIssuerKey()
    {
        $other = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($other);
        $otherPublicPem = openssl_pkey_get_details($other)['key'];

        self::assertFalse($this->engine->verify($this->certPem, $otherPublicPem));
    }

    public function testVerifyReturnsFalseForGarbageIssuerKey()
    {
        self::assertFalse($this->engine->verify($this->certPem, 'not a pem'));
    }

    public function testNormalizePemAcceptsDer()
    {
        $body = preg_replace('/-----[^-]+-----|\s+/', '', $this->certPem);
        $der = base64_decode((string) $body, true);
        self::assertIsString($der);

        $normalized = $this->engine->normalizePem($der);

        self::assertStringContainsString('BEGIN CERTIFICATE', $normalized);
        self::assertSame('example.com', $this->engine->parse($normalized)['subject']['CN']);
    }

    public function testDerBytes()
    {
        $der = $this->engine->derBytes($this->certPem);

        self::assertSame(openssl_x509_fingerprint($this->certPem, 'sha256'), hash('sha256', $der));
    }

    public function testName()
    {
        self::assertSame('openssl', $this->engine->name());
    }
}
