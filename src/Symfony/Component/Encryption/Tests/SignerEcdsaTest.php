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

final class SignerEcdsaTest extends TestCase
{
    protected function setUp(): void
    {
        if (!\extension_loaded('openssl')) {
            self::markTestSkipped('ext-openssl is required for ECDSA signing.');
        }
    }

    public function testGenerateEcdsaKeyPairIsSigning(): void
    {
        $pair = (new Signer())->generateKeyPair('ecdsa-p256');

        self::assertInstanceOf(KeyPair::class, $pair);
        self::assertSame('ecdsa-p256', $pair->algorithm());
        self::assertSame('signing', $pair->public()->purpose());
        self::assertStringContainsString('PUBLIC KEY', $pair->public()->bytes());
    }

    public function testEcdsaDetachedRoundTrip(): void
    {
        $signer = new Signer();
        $pair = $signer->generateKeyPair('ecdsa-p256');

        $signature = $signer->signDetached('audit log', $pair->private());

        self::assertTrue($signer->verifyDetached($signature, 'audit log', $pair->public()));
    }

    public function testEcdsaDetachedRejectsTamperedMessage(): void
    {
        $signer = new Signer();
        $pair = $signer->generateKeyPair('ecdsa-p256');
        $signature = $signer->signDetached('original', $pair->private());

        self::assertFalse($signer->verifyDetached($signature, 'tampered', $pair->public()));
    }

    public function testEcdsaAttachedRoundTrip(): void
    {
        $signer = new Signer();
        $pair = $signer->generateKeyPair('ecdsa-p256');

        $signed = $signer->signAttached('shipment', $pair->private());

        self::assertSame('shipment', $signer->openAttached($signed, $pair->public()));
    }

    public function testEcdsaOpenAttachedRejectsTampered(): void
    {
        $signer = new Signer();
        $pair = $signer->generateKeyPair('ecdsa-p256');
        $signed = $signer->signAttached('original', $pair->private());
        $signed[\strlen($signed) - 4] = $signed[\strlen($signed) - 4] === 'A' ? 'B' : 'A';

        $this->expectException(SignatureVerificationException::class);

        $signer->openAttached($signed, $pair->public());
    }

    public function testExportedEcdsaSigningKeyVerifiesLater(): void
    {
        $signer = new Signer();
        $pair = $signer->generateKeyPair('ecdsa-p256');
        $signature = $signer->signDetached('persisted', $pair->private());

        $reloaded = KeyPair::import($pair->export());

        self::assertTrue($signer->verifyDetached($signature, 'persisted', $reloaded->public()));
    }
}
