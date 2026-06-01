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
use Symfony\Component\Encryption\Engine\OpenSsl\OpenSslEcdsaSignatureEngine;
use Symfony\Component\Encryption\Exception\InvalidKeyException;

final class OpenSslEcdsaSignatureEngineTest extends TestCase
{
    private function engine(): OpenSslEcdsaSignatureEngine
    {
        $engine = new OpenSslEcdsaSignatureEngine();
        if (!$engine->isAvailable()) {
            self::markTestSkipped('ext-openssl is required.');
        }

        return $engine;
    }

    public function testGenerateKeyPairProducesPemKeys()
    {
        [$public, $private] = $this->engine()->generateKeyPair();

        self::assertStringContainsString('PUBLIC KEY', $public);
        self::assertStringContainsString('PRIVATE KEY', $private);
    }

    public function testSignVerifyRoundTrip()
    {
        $engine = $this->engine();
        [$public, $private] = $engine->generateKeyPair();

        $signature = $engine->sign('contract', $private);

        self::assertTrue($engine->verify($signature, 'contract', $public));
    }

    public function testVerifyRejectsTamperedMessage()
    {
        $engine = $this->engine();
        [$public, $private] = $engine->generateKeyPair();
        $signature = $engine->sign('original', $private);

        self::assertFalse($engine->verify($signature, 'tampered', $public));
    }

    public function testVerifyRejectsWrongKey()
    {
        $engine = $this->engine();
        [, $private] = $engine->generateKeyPair();
        [$otherPublic] = $engine->generateKeyPair();
        $signature = $engine->sign('m', $private);

        self::assertFalse($engine->verify($signature, 'm', $otherPublic));
    }

    public function testVerifyReturnsFalseForGarbageKeyWithoutThrowing()
    {
        $engine = $this->engine();
        [, $private] = $engine->generateKeyPair();
        $signature = $engine->sign('m', $private);

        self::assertFalse($engine->verify($signature, 'm', 'not a pem'));
    }

    public function testVerifyReturnsFalseForGarbageSignature()
    {
        $engine = $this->engine();
        [$public] = $engine->generateKeyPair();

        self::assertFalse($engine->verify('garbage', 'm', $public));
    }

    public function testSignRejectsInvalidKey()
    {
        $this->expectException(InvalidKeyException::class);

        $this->engine()->sign('m', 'not a pem');
    }

    public function testNameAndAlgorithm()
    {
        self::assertSame('openssl', $this->engine()->name());
        self::assertSame('ecdsa-p256', $this->engine()->algorithm());
    }
}
