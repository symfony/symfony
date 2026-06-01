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
use Symfony\Component\Encryption\Exception\SignatureVerificationException;
use Symfony\Component\Encryption\Key\KeyPair;
use Symfony\Component\Encryption\Signer;

final class SignerRsaTest extends TestCase
{
    protected function setUp(): void
    {
        if (!\extension_loaded('openssl')) {
            self::markTestSkipped('ext-openssl is required for RSA signing.');
        }
    }

    public function testGenerateRsaKeyPairIsSigning(): void
    {
        $pair = (new Signer())->generateKeyPair('rsa');

        self::assertInstanceOf(KeyPair::class, $pair);
        self::assertSame('rsa', $pair->algorithm());
        self::assertSame('signing', $pair->public()->purpose());
        self::assertStringContainsString('PUBLIC KEY', $pair->public()->bytes());
    }

    public function testRsaDetachedRoundTrip(): void
    {
        $signer = new Signer();
        $pair = $signer->generateKeyPair('rsa');

        $signature = $signer->signDetached('audit log', $pair->private());

        self::assertTrue($signer->verifyDetached($signature, 'audit log', $pair->public()));
    }

    public function testRsaDetachedRejectsTamperedMessage(): void
    {
        $signer = new Signer();
        $pair = $signer->generateKeyPair('rsa');
        $signature = $signer->signDetached('original', $pair->private());

        self::assertFalse($signer->verifyDetached($signature, 'tampered', $pair->public()));
    }

    public function testRsaAttachedRoundTrip(): void
    {
        $signer = new Signer();
        $pair = $signer->generateKeyPair('rsa');

        $signed = $signer->signAttached('shipment', $pair->private());

        self::assertSame('shipment', $signer->openAttached($signed, $pair->public()));
    }

    public function testRsaOpenAttachedRejectsTampered(): void
    {
        $signer = new Signer();
        $pair = $signer->generateKeyPair('rsa');
        $signed = $signer->signAttached('original', $pair->private());
        $signed[\strlen($signed) - 4] = $signed[\strlen($signed) - 4] === 'A' ? 'B' : 'A';

        $this->expectException(SignatureVerificationException::class);

        $signer->openAttached($signed, $pair->public());
    }

    public function testExportedRsaSigningKeyVerifiesLater(): void
    {
        $signer = new Signer();
        $pair = $signer->generateKeyPair('rsa');
        $signature = $signer->signDetached('persisted', $pair->private());

        $reloaded = KeyPair::import($pair->export());

        self::assertTrue($signer->verifyDetached($signature, 'persisted', $reloaded->public()));
    }
}
