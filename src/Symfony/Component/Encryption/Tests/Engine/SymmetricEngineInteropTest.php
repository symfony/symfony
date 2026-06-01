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
use Symfony\Component\Encryption\Engine\OpenSsl\OpenSslSymmetricEngine;
use Symfony\Component\Encryption\Engine\Sodium\SodiumSymmetricEngine;
use Symfony\Component\Encryption\Engine\SymmetricEngineInterface;

final class SymmetricEngineInteropTest extends TestCase
{
    protected function setUp()
    {
        if (!(new SodiumSymmetricEngine())->isAvailable() || !(new OpenSslSymmetricEngine())->isAvailable()) {
            self::markTestSkipped('Both sodium and openssl engines are required for interop tests.');
        }
    }

    public function testSodiumEncryptOpenSslDecrypt()
    {
        $key = random_bytes(SymmetricEngineInterface::KEY_BYTES);
        $nonce = random_bytes(SymmetricEngineInterface::NONCE_BYTES);

        $ciphertext = (new SodiumSymmetricEngine())->encrypt('interop me', $key, $nonce);

        self::assertSame('interop me', (new OpenSslSymmetricEngine())->decrypt($ciphertext, $key, $nonce));
    }

    public function testOpenSslEncryptSodiumDecrypt()
    {
        $key = random_bytes(SymmetricEngineInterface::KEY_BYTES);
        $nonce = random_bytes(SymmetricEngineInterface::NONCE_BYTES);

        $ciphertext = (new OpenSslSymmetricEngine())->encrypt('interop me', $key, $nonce);

        self::assertSame('interop me', (new SodiumSymmetricEngine())->decrypt($ciphertext, $key, $nonce));
    }

    public function testEnginesProduceIdenticalCiphertext()
    {
        $key = random_bytes(SymmetricEngineInterface::KEY_BYTES);
        $nonce = random_bytes(SymmetricEngineInterface::NONCE_BYTES);

        self::assertSame(
            (new SodiumSymmetricEngine())->encrypt('deterministic', $key, $nonce),
            (new OpenSslSymmetricEngine())->encrypt('deterministic', $key, $nonce),
        );
    }
}
