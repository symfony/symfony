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
use Symfony\Component\Encryption\Engine\Sodium\SodiumEd25519Engine;
use Symfony\Component\Encryption\Exception\InvalidKeyException;

final class SodiumEd25519EngineTest extends TestCase
{
    private function engine(): SodiumEd25519Engine
    {
        $engine = new SodiumEd25519Engine();
        if (!$engine->isAvailable()) {
            self::markTestSkipped('ext-sodium is required.');
        }

        return $engine;
    }

    public function testGenerateKeyPairLengths()
    {
        [$public, $private] = $this->engine()->generateKeyPair();

        self::assertSame(32, \strlen($public));
        self::assertSame(64, \strlen($private));
    }

    public function testSignVerifyRoundTrip()
    {
        $engine = $this->engine();
        [$public, $private] = $engine->generateKeyPair();

        $signature = $engine->sign('contract terms', $private);

        self::assertSame(64, \strlen($signature));
        self::assertTrue($engine->verify($signature, 'contract terms', $public));
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
        $signature = $engine->sign('message', $private);

        self::assertFalse($engine->verify($signature, 'message', $otherPublic));
    }

    public function testVerifyReturnsFalseForMalformedSignatureWithoutThrowing()
    {
        $engine = $this->engine();
        [$public] = $engine->generateKeyPair();

        self::assertFalse($engine->verify('too short', 'message', $public));
    }

    public function testSignRejectsWrongSecretKeyLength()
    {
        $this->expectException(InvalidKeyException::class);

        $this->engine()->sign('m', str_repeat("\x01", 32));
    }

    public function testNameAndAlgorithm()
    {
        self::assertSame('sodium', $this->engine()->name());
        self::assertSame('ed25519', $this->engine()->algorithm());
    }
}
