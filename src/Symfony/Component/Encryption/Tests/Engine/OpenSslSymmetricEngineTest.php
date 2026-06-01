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
use Symfony\Component\Encryption\Engine\OpenSsl\OpenSslSymmetricEngine;
use Symfony\Component\Encryption\Engine\SymmetricEngineInterface;
use Symfony\Component\Encryption\Exception\DecryptionException;
use Symfony\Component\Encryption\Exception\InvalidArgumentException;
use Symfony\Component\Encryption\Exception\InvalidKeyException;

final class OpenSslSymmetricEngineTest extends TestCase
{
    private function engine(): OpenSslSymmetricEngine
    {
        $engine = new OpenSslSymmetricEngine();
        if (!$engine->isAvailable()) {
            self::markTestSkipped('OpenSSL ChaCha20-Poly1305 is required.');
        }

        return $engine;
    }

    public function testRoundTrip(): void
    {
        $engine = $this->engine();
        $key = random_bytes(SymmetricEngineInterface::KEY_BYTES);
        $nonce = random_bytes(SymmetricEngineInterface::NONCE_BYTES);

        $ciphertext = $engine->encrypt('attack at dawn', $key, $nonce);

        self::assertNotSame('attack at dawn', $ciphertext);
        self::assertSame('attack at dawn', $engine->decrypt($ciphertext, $key, $nonce));
    }

    public function testEmptyPlaintextRoundTrips(): void
    {
        $engine = $this->engine();
        $key = random_bytes(SymmetricEngineInterface::KEY_BYTES);
        $nonce = random_bytes(SymmetricEngineInterface::NONCE_BYTES);

        $ciphertext = $engine->encrypt('', $key, $nonce);

        self::assertSame(SymmetricEngineInterface::TAG_BYTES, \strlen($ciphertext));
        self::assertSame('', $engine->decrypt($ciphertext, $key, $nonce));
    }

    public function testTamperedCiphertextIsRejected(): void
    {
        $engine = $this->engine();
        $key = random_bytes(SymmetricEngineInterface::KEY_BYTES);
        $nonce = random_bytes(SymmetricEngineInterface::NONCE_BYTES);
        $ciphertext = $engine->encrypt('secret', $key, $nonce);
        $ciphertext[0] = $ciphertext[0] === 'A' ? 'B' : 'A';

        $this->expectException(DecryptionException::class);

        $engine->decrypt($ciphertext, $key, $nonce);
    }

    public function testRejectsWrongKeyLength(): void
    {
        $engine = $this->engine();

        $this->expectException(InvalidKeyException::class);

        $engine->encrypt('x', random_bytes(16), random_bytes(SymmetricEngineInterface::NONCE_BYTES));
    }

    public function testRejectsWrongNonceLength(): void
    {
        $engine = $this->engine();

        $this->expectException(InvalidArgumentException::class);

        $engine->encrypt('x', random_bytes(SymmetricEngineInterface::KEY_BYTES), random_bytes(8));
    }

    public function testWrongKeyIsRejected(): void
    {
        $engine = $this->engine();
        $nonce = random_bytes(SymmetricEngineInterface::NONCE_BYTES);
        $ciphertext = $engine->encrypt('secret', random_bytes(SymmetricEngineInterface::KEY_BYTES), $nonce);

        $this->expectException(DecryptionException::class);

        $engine->decrypt($ciphertext, random_bytes(SymmetricEngineInterface::KEY_BYTES), $nonce);
    }

    public function testName(): void
    {
        self::assertSame('openssl', $this->engine()->name());
    }
}
