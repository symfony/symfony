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

namespace Symfony\Component\Encryption\Tests\Engine;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Encryption\Engine\Phpseclib\PhpseclibEcdsaSignatureEngine;
use Symfony\Component\Encryption\Exception\InvalidKeyException;

final class PhpseclibEcdsaSignatureEngineTest extends TestCase
{
    private function engine(): PhpseclibEcdsaSignatureEngine
    {
        $engine = new PhpseclibEcdsaSignatureEngine();
        if (!$engine->isAvailable()) {
            self::markTestSkipped('phpseclib EC is required.');
        }

        return $engine;
    }

    public function testGenerateKeyPairProducesPemKeys(): void
    {
        [$public, $private] = $this->engine()->generateKeyPair();

        self::assertStringContainsString('PUBLIC KEY', $public);
        self::assertStringContainsString('PRIVATE KEY', $private);
    }

    public function testSignVerifyRoundTrip(): void
    {
        $engine = $this->engine();
        [$public, $private] = $engine->generateKeyPair();

        self::assertTrue($engine->verify($engine->sign('contract', $private), 'contract', $public));
    }

    public function testVerifyRejectsTamperedMessage(): void
    {
        $engine = $this->engine();
        [$public, $private] = $engine->generateKeyPair();
        $signature = $engine->sign('original', $private);

        self::assertFalse($engine->verify($signature, 'tampered', $public));
    }

    public function testVerifyRejectsWrongKey(): void
    {
        $engine = $this->engine();
        [, $private] = $engine->generateKeyPair();
        [$otherPublic] = $engine->generateKeyPair();

        self::assertFalse($engine->verify($engine->sign('m', $private), 'm', $otherPublic));
    }

    public function testVerifyReturnsFalseForGarbageKey(): void
    {
        $engine = $this->engine();
        [, $private] = $engine->generateKeyPair();

        self::assertFalse($engine->verify($engine->sign('m', $private), 'm', 'not a pem'));
    }

    public function testVerifyReturnsFalseForGarbageSignature(): void
    {
        $engine = $this->engine();
        [$public] = $engine->generateKeyPair();

        self::assertFalse($engine->verify('garbage', 'm', $public));
    }

    public function testSignRejectsInvalidKey(): void
    {
        $this->expectException(InvalidKeyException::class);

        $this->engine()->sign('m', 'not a pem');
    }

    public function testNameAndAlgorithm(): void
    {
        self::assertSame('phpseclib', $this->engine()->name());
        self::assertSame('ecdsa-p256', $this->engine()->algorithm());
    }
}
