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
use Symfony\Component\Encryption\Engine\Sodium\SodiumSymmetricEngine;
use Symfony\Component\Encryption\Engine\SymmetricEngineInterface;
use Symfony\Component\Encryption\Exception\DecryptionException;
use Symfony\Component\Encryption\Exception\InvalidArgumentException;
use Symfony\Component\Encryption\Exception\InvalidKeyException;

final class SodiumSymmetricEngineTest extends TestCase
{
    private function engine(): SodiumSymmetricEngine
    {
        $engine = new SodiumSymmetricEngine();
        if (!$engine->isAvailable()) {
            self::markTestSkipped('ext-sodium is required.');
        }

        return $engine;
    }

    public function testRoundTrip()
    {
        $engine = $this->engine();
        $key = random_bytes(SymmetricEngineInterface::KEY_BYTES);
        $nonce = random_bytes(SymmetricEngineInterface::NONCE_BYTES);

        $ciphertext = $engine->encrypt('attack at dawn', $key, $nonce);

        self::assertNotSame('attack at dawn', $ciphertext);
        self::assertSame('attack at dawn', $engine->decrypt($ciphertext, $key, $nonce));
    }

    public function testEmptyPlaintextRoundTrips()
    {
        $engine = $this->engine();
        $key = random_bytes(SymmetricEngineInterface::KEY_BYTES);
        $nonce = random_bytes(SymmetricEngineInterface::NONCE_BYTES);

        $ciphertext = $engine->encrypt('', $key, $nonce);

        self::assertSame(SymmetricEngineInterface::TAG_BYTES, \strlen($ciphertext));
        self::assertSame('', $engine->decrypt($ciphertext, $key, $nonce));
    }

    public function testTamperedCiphertextIsRejected()
    {
        $engine = $this->engine();
        $key = random_bytes(SymmetricEngineInterface::KEY_BYTES);
        $nonce = random_bytes(SymmetricEngineInterface::NONCE_BYTES);
        $ciphertext = $engine->encrypt('secret', $key, $nonce);
        $ciphertext[0] = 'A' === $ciphertext[0] ? 'B' : 'A';

        $this->expectException(DecryptionException::class);

        $engine->decrypt($ciphertext, $key, $nonce);
    }

    public function testWrongKeyIsRejected()
    {
        $engine = $this->engine();
        $nonce = random_bytes(SymmetricEngineInterface::NONCE_BYTES);
        $ciphertext = $engine->encrypt('secret', random_bytes(SymmetricEngineInterface::KEY_BYTES), $nonce);

        $this->expectException(DecryptionException::class);

        $engine->decrypt($ciphertext, random_bytes(SymmetricEngineInterface::KEY_BYTES), $nonce);
    }

    public function testRejectsWrongKeyLength()
    {
        $engine = $this->engine();

        $this->expectException(InvalidKeyException::class);

        $engine->encrypt('x', random_bytes(16), random_bytes(SymmetricEngineInterface::NONCE_BYTES));
    }

    public function testRejectsWrongNonceLength()
    {
        $engine = $this->engine();

        $this->expectException(InvalidArgumentException::class);

        $engine->encrypt('x', random_bytes(SymmetricEngineInterface::KEY_BYTES), random_bytes(8));
    }

    public function testNameAndAvailability()
    {
        $engine = $this->engine();

        self::assertSame('sodium', $engine->name());
        self::assertTrue($engine->isAvailable());
    }
}
