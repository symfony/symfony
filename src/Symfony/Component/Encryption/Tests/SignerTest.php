<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Encryption\Exception\InvalidKeyException;
use Symfony\Component\Encryption\Exception\SignatureVerificationException;
use Symfony\Component\Encryption\Key\KeyPair;
use Symfony\Component\Encryption\Key\PrivateKey;
use Symfony\Component\Encryption\Signer;

final class SignerTest extends TestCase
{
    protected function setUp()
    {
        if (!\function_exists('sodium_crypto_sign_detached')) {
            self::markTestSkipped('ext-sodium is required.');
        }
    }

    public function testGenerateKeyPairIsEd25519Signing()
    {
        $pair = (new Signer())->generateKeyPair();

        self::assertInstanceOf(KeyPair::class, $pair);
        self::assertSame('ed25519', $pair->algorithm());
        self::assertSame('signing', $pair->public()->purpose());
    }

    public function testDetachedSignVerifyRoundTrip()
    {
        $signer = new Signer();
        $pair = $signer->generateKeyPair();

        $signature = $signer->signDetached('release notes', $pair->private());

        self::assertTrue($signer->verifyDetached($signature, 'release notes', $pair->public()));
    }

    public function testEmptyMessageSigns()
    {
        $signer = new Signer();
        $pair = $signer->generateKeyPair();

        self::assertTrue($signer->verifyDetached($signer->signDetached('', $pair->private()), '', $pair->public()));
    }

    public function testVerifyDetachedRejectsTamperedMessage()
    {
        $signer = new Signer();
        $pair = $signer->generateKeyPair();
        $signature = $signer->signDetached('original', $pair->private());

        self::assertFalse($signer->verifyDetached($signature, 'tampered', $pair->public()));
    }

    public function testVerifyDetachedReturnsFalseForGarbageSignature()
    {
        $signer = new Signer();
        $pair = $signer->generateKeyPair();

        self::assertFalse($signer->verifyDetached('!!!not base64 sig!!!', 'm', $pair->public()));
    }

    public function testVerifyDetachedReturnsFalseForWrongLengthSignature()
    {
        // Valid base64 whose decoded length (10 bytes) is shorter than the
        // required 64-byte Ed25519 signature — the "wrong-length but valid
        // base64" class of input that previously caused sodium to throw.
        $signer = new Signer();
        $pair = $signer->generateKeyPair();
        $shortSig = base64_encode(str_repeat("\x00", 10));

        self::assertFalse($signer->verifyDetached($shortSig, 'm', $pair->public()));
    }

    public function testAttachedSignOpenRoundTrip()
    {
        $signer = new Signer();
        $pair = $signer->generateKeyPair();

        $signed = $signer->signAttached('shipment manifest', $pair->private());

        self::assertNotSame('shipment manifest', $signed);
        self::assertSame('shipment manifest', $signer->openAttached($signed, $pair->public()));
    }

    public function testOpenAttachedRejectsTamperedMessage()
    {
        $signer = new Signer();
        $pair = $signer->generateKeyPair();
        $signed = $signer->signAttached('original', $pair->private());
        $signed[\strlen($signed) - 2] = $signed[\strlen($signed) - 2] === 'A' ? 'B' : 'A';

        $this->expectException(SignatureVerificationException::class);

        $signer->openAttached($signed, $pair->public());
    }

    public function testSignRejectsEncryptionPurposeKey()
    {
        $signer = new Signer();
        $signingPair = $signer->generateKeyPair();
        $wrongPurpose = PrivateKey::fromBytes('ed25519', 'encryption', $signingPair->private()->bytes());

        $this->expectException(InvalidKeyException::class);

        $signer->signDetached('m', $wrongPurpose);
    }

    public function testAttachedLayoutIsLengthPrefixed()
    {
        $signer = new Signer();
        $pair = $signer->generateKeyPair();

        $raw = \Symfony\Component\Encryption\Encoding::fromBase64($signer->signAttached('hi', $pair->private()));

        // pack('n', 64) = "\x00\x40" for an Ed25519 signature, then 64 sig bytes + "hi".
        self::assertSame("\x00\x40", substr($raw, 0, 2));
        self::assertSame(2 + 64 + 2, \strlen($raw));
    }

    public function testOpenAttachedRejectsLengthHeaderOverrun()
    {
        $signer = new Signer();
        $pair = $signer->generateKeyPair();
        $forged = \Symfony\Component\Encryption\Encoding::toBase64(pack('n', 60000).'short');

        $this->expectException(\Symfony\Component\Encryption\Exception\SignatureVerificationException::class);

        $signer->openAttached($forged, $pair->public());
    }
}
