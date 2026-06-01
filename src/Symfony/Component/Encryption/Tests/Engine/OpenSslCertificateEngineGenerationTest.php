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
use Symfony\Component\Encryption\Exception\InvalidArgumentException;
use Symfony\Component\Encryption\Exception\InvalidKeyException;

final class OpenSslCertificateEngineGenerationTest extends TestCase
{
    private OpenSslCertificateEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new OpenSslCertificateEngine();
        if (!$this->engine->isAvailable()) {
            self::markTestSkipped('ext-openssl is required.');
        }
    }

    public function testGenerateRsaKeyPair()
    {
        [$public, $private] = $this->engine->generateKeyPair('rsa', 2048);

        self::assertStringContainsString('PUBLIC KEY', $public);
        self::assertStringContainsString('PRIVATE KEY', $private);
    }

    public function testGenerateEcKeyPair()
    {
        [$public, $private] = $this->engine->generateKeyPair('ecdsa-p256', 0);

        self::assertStringContainsString('PUBLIC KEY', $public);
        self::assertStringContainsString('PRIVATE KEY', $private);
    }

    public function testGenerateKeyPairRejectsUnknownAlgorithm()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->engine->generateKeyPair('dsa', 2048);
    }

    public function testCreateAndParseCsr()
    {
        [, $private] = $this->engine->generateKeyPair('rsa', 2048);

        $csrPem = $this->engine->createCsr(['CN' => 'example.com', 'O' => 'Acme'], $private, []);

        self::assertStringContainsString('CERTIFICATE REQUEST', $csrPem);

        $parsed = $this->engine->parseCsr($csrPem);
        self::assertSame('example.com', $parsed['subject']['CN']);
        self::assertStringContainsString('PUBLIC KEY', $parsed['publicKeyPem']);
    }

    public function testCreateSelfSignedRoundTripsThroughParse()
    {
        [, $private] = $this->engine->generateKeyPair('rsa', 2048);

        $certPem = $this->engine->createSelfSigned(['CN' => 'self.example', 'O' => 'Acme'], $private, 365, [], 12345);

        $parsed = $this->engine->parse($certPem);
        self::assertSame('self.example', $parsed['subject']['CN']);
        self::assertSame('self.example', $parsed['issuer']['CN']);
        self::assertSame(12345, hexdec($parsed['serialNumber']));
        self::assertGreaterThan($parsed['validFrom'], $parsed['validTo']);
    }

    public function testCreateSelfSignedWithSans()
    {
        [, $private] = $this->engine->generateKeyPair('rsa', 2048);

        $certPem = $this->engine->createSelfSigned(['CN' => 'example.com'], $private, 365, ['example.com', 'www.example.com'], 1);

        $sans = $this->engine->parse($certPem)['subjectAlternativeNames'];
        self::assertContains('DNS:example.com', $sans);
        self::assertContains('DNS:www.example.com', $sans);
    }

    public function testEcSelfSignedCertVerifies()
    {
        [, $private] = $this->engine->generateKeyPair('ecdsa-p256', 0);

        $certPem = $this->engine->createSelfSigned(['CN' => 'ec.example'], $private, 365, [], 1);

        self::assertTrue($this->engine->verify($certPem, $this->engine->publicKeyPem($certPem)));
    }

    public function testCreateCsrWithSans()
    {
        [, $private] = $this->engine->generateKeyPair('rsa', 2048);

        $csrPem = $this->engine->createCsr(['CN' => 'example.com'], $private, ['example.com', 'www.example.com']);

        self::assertStringContainsString('CERTIFICATE REQUEST', $csrPem);
    }

    public function testCreateCsrRejectsInvalidKey()
    {
        $this->expectException(InvalidKeyException::class);

        $this->engine->createCsr(['CN' => 'x'], 'not a key', []);
    }

    public function testCreateSelfSignedRejectsInvalidKey()
    {
        $this->expectException(InvalidKeyException::class);

        $this->engine->createSelfSigned(['CN' => 'x'], 'not a key', 365, [], 1);
    }
}
