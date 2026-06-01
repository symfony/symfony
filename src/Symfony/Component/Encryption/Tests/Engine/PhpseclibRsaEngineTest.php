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
use Symfony\Component\Encryption\Engine\Phpseclib\PhpseclibRsaEngine;
use Symfony\Component\Encryption\Exception\DecryptionException;
use Symfony\Component\Encryption\Exception\InvalidArgumentException;
use Symfony\Component\Encryption\Exception\InvalidKeyException;

final class PhpseclibRsaEngineTest extends TestCase
{
    private PhpseclibRsaEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new PhpseclibRsaEngine();
        if (!$this->engine->isAvailable()) {
            self::markTestSkipped('phpseclib RSA is required.');
        }
    }

    public function testGenerateKeyPairProducesPemKeys(): void
    {
        [$public, $private] = $this->engine->generateKeyPair(2048);

        self::assertStringContainsString('PUBLIC KEY', $public);
        self::assertStringContainsString('PRIVATE KEY', $private);
    }

    public function testWrapUnwrapRoundTrip(): void
    {
        [$public, $private] = $this->engine->generateKeyPair(2048);
        $secret = random_bytes(32);

        $wrapped = $this->engine->wrap($secret, $public);

        self::assertNotSame($secret, $wrapped);
        self::assertSame($secret, $this->engine->unwrap($wrapped, $private));
    }

    public function testUnwrapWithWrongKeyFails(): void
    {
        [$public] = $this->engine->generateKeyPair(2048);
        [, $otherPrivate] = $this->engine->generateKeyPair(2048);
        $wrapped = $this->engine->wrap(random_bytes(32), $public);

        $this->expectException(DecryptionException::class);

        $this->engine->unwrap($wrapped, $otherPrivate);
    }

    public function testRejectsTooSmallKeySize(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->engine->generateKeyPair(1024);
    }

    public function testWrapWithInvalidPublicKeyThrowsInvalidKeyException(): void
    {
        $this->expectException(InvalidKeyException::class);

        $this->engine->wrap('secret', 'not-a-pem');
    }

    public function testName(): void
    {
        self::assertSame('phpseclib-rsa', $this->engine->name());
    }
}
