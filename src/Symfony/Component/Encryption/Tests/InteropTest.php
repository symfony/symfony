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
use Symfony\Component\Encryption\Engine\EngineSelector;
use Symfony\Component\Encryption\Engine\OpenSsl\OpenSslSymmetricEngine;
use Symfony\Component\Encryption\Engine\Sodium\SodiumSymmetricEngine;
use Symfony\Component\Encryption\Key\SecretKey;
use Symfony\Component\Encryption\SymmetricCipher;

final class InteropTest extends TestCase
{
    private SymmetricCipher $sodiumCipher;
    private SymmetricCipher $opensslCipher;

    protected function setUp()
    {
        $sodium = new SodiumSymmetricEngine();
        $openssl = new OpenSslSymmetricEngine();
        if (!$sodium->isAvailable() || !$openssl->isAvailable()) {
            self::markTestSkipped('Both sodium and openssl engines are required.');
        }

        $this->sodiumCipher = new SymmetricCipher(new EngineSelector([$sodium]));
        $this->opensslCipher = new SymmetricCipher(new EngineSelector([$openssl]));
    }

    public function testRawKeyEncryptedWithSodiumDecryptsWithOpenSsl()
    {
        $key = $this->sodiumCipher->generateKey();

        $ciphertext = $this->sodiumCipher->encrypt('cross-engine payload', $key);

        self::assertSame('cross-engine payload', $this->opensslCipher->decrypt($ciphertext, $key));
    }

    public function testRawKeyEncryptedWithOpenSslDecryptsWithSodium()
    {
        $key = $this->opensslCipher->generateKey();

        $ciphertext = $this->opensslCipher->encrypt('cross-engine payload', $key);

        self::assertSame('cross-engine payload', $this->sodiumCipher->decrypt($ciphertext, $key));
    }

    public function testPasswordEncryptedWithSodiumDecryptsWithOpenSsl()
    {
        $ciphertext = $this->sodiumCipher->encryptWithPassword('cross-engine secret', 'shared pw');

        self::assertSame('cross-engine secret', $this->opensslCipher->decryptWithPassword($ciphertext, 'shared pw'));
    }

    public function testPasswordEncryptedWithOpenSslDecryptsWithSodium()
    {
        $ciphertext = $this->opensslCipher->encryptWithPassword('cross-engine secret', 'shared pw');

        self::assertSame('cross-engine secret', $this->sodiumCipher->decryptWithPassword($ciphertext, 'shared pw'));
    }

    public function testExportedKeyIsPortableAcrossEngines()
    {
        $key = $this->sodiumCipher->generateKey();
        $ciphertext = $this->sodiumCipher->encrypt('portable', $key);

        $reloaded = SecretKey::import($key->export());

        self::assertSame('portable', $this->opensslCipher->decrypt($ciphertext, $reloaded));
    }
}
