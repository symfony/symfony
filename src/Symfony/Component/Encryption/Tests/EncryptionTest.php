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
use Symfony\Component\Encryption\AsymmetricCipherInterface;
use Symfony\Component\Encryption\CertificateManagerInterface;
use Symfony\Component\Encryption\Encryption;
use Symfony\Component\Encryption\Engine\EngineSelector;
use Symfony\Component\Encryption\HasherInterface;
use Symfony\Component\Encryption\MacInterface;
use Symfony\Component\Encryption\PasswordHasherInterface;
use Symfony\Component\Encryption\SignerInterface;
use Symfony\Component\Encryption\SymmetricCipherInterface;

final class EncryptionTest extends TestCase
{
    public function testAccessorsReturnTheRightInterfaces()
    {
        $encryption = new Encryption();

        self::assertInstanceOf(SymmetricCipherInterface::class, $encryption->symmetric());
        self::assertInstanceOf(AsymmetricCipherInterface::class, $encryption->asymmetric());
        self::assertInstanceOf(SignerInterface::class, $encryption->signing());
        self::assertInstanceOf(CertificateManagerInterface::class, $encryption->certificates());
        self::assertInstanceOf(HasherInterface::class, $encryption->digest());
        self::assertInstanceOf(MacInterface::class, $encryption->mac());
        self::assertInstanceOf(PasswordHasherInterface::class, $encryption->passwords());
    }

    public function testAccessorsAreCached()
    {
        $encryption = new Encryption();

        self::assertSame($encryption->symmetric(), $encryption->symmetric());
        self::assertSame($encryption->asymmetric(), $encryption->asymmetric());
        self::assertSame($encryption->signing(), $encryption->signing());
        self::assertSame($encryption->certificates(), $encryption->certificates());
        self::assertSame($encryption->digest(), $encryption->digest());
        self::assertSame($encryption->mac(), $encryption->mac());
        self::assertSame($encryption->passwords(), $encryption->passwords());
    }

    public function testEngineBackedServicesShareTheInjectedEngineSelector()
    {
        $engines = new EngineSelector();
        $encryption = new Encryption($engines);

        foreach ([$encryption->symmetric(), $encryption->asymmetric(), $encryption->signing(), $encryption->certificates()] as $service) {
            $prop = new \ReflectionProperty($service, 'engines');
            self::assertSame($engines, $prop->getValue($service), 'Engine-backed service must use the injected EngineSelector.');
        }
    }

    public function testEndToEndSymmetricThroughFacade()
    {
        $encryption = new Encryption();
        $key = $encryption->symmetric()->generateKey();

        $ciphertext = $encryption->symmetric()->encrypt('via the facade', $key);

        self::assertSame('via the facade', $encryption->symmetric()->decrypt($ciphertext, $key));
    }

    public function testEndToEndAsymmetricThroughFacade()
    {
        if (!\function_exists('sodium_crypto_box_seal')) {
            self::markTestSkipped('ext-sodium is required for X25519 asymmetric encryption.');
        }

        $encryption = new Encryption();
        $recipient = $encryption->asymmetric()->generateKeyPair();

        $ciphertext = $encryption->asymmetric()->encryptAnonymous('secret via facade', $recipient->public());

        self::assertSame('secret via facade', $encryption->asymmetric()->decryptAnonymous($ciphertext, $recipient));
    }

    public function testEndToEndSigningThroughFacade()
    {
        $encryption = new Encryption();
        $pair = $encryption->signing()->generateKeyPair();

        $signature = $encryption->signing()->signDetached('message', $pair->private());

        self::assertTrue($encryption->signing()->verifyDetached($signature, 'message', $pair->public()));
    }

    public function testDigestSmokeThroughFacade()
    {
        $encryption = new Encryption();

        // Well-known SHA-256 of "abc" — verifies the facade wires Hasher correctly.
        self::assertSame(
            'ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad',
            $encryption->digest()->hash('abc'),
        );
    }

    public function testMacRoundTripThroughFacade()
    {
        $encryption = new Encryption();
        $key = $encryption->mac()->generateKey();
        $tag = $encryption->mac()->sign('hello', $key);

        self::assertTrue($encryption->mac()->verify($tag, 'hello', $key));
        self::assertFalse($encryption->mac()->verify($tag, 'tampered', $key));
    }

    public function testPasswordsSmokeThroughFacade()
    {
        if (!\extension_loaded('sodium')) {
            self::markTestSkipped('ext-sodium is required for PasswordHasher.');
        }

        $encryption = new Encryption();
        $hash = $encryption->passwords()->hash('s3cr3t');

        self::assertTrue($encryption->passwords()->verify('s3cr3t', $hash));
        self::assertFalse($encryption->passwords()->verify('wrong', $hash));
    }
}
