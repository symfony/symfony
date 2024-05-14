<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PasswordHasher\Tests\Hasher;

use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Exception\InvalidPasswordException;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\SodiumPasswordHasher;

class SodiumPasswordHasherTest extends TestCase
{
    protected function setUp(): void
    {
        if (!SodiumPasswordHasher::isSupported()) {
            $this->markTestSkipped('Libsodium is not available.');
        }
    }

    public function testValidation()
    {
        $hasher = new SodiumPasswordHasher();
        $result = $hasher->hash('password');
        $this->assertTrue($hasher->verify($result, 'password'));
        $this->assertFalse($hasher->verify($result, 'anotherPassword'));
        $this->assertFalse($hasher->verify($result, ''));
    }

    public function testBcryptValidation()
    {
        $hasher = new SodiumPasswordHasher();
        $this->assertTrue($hasher->verify('$2y$04$M8GDODMoGQLQRpkYCdoJh.lbiZPee3SZI32RcYK49XYTolDGwoRMm', 'abc'));
    }

    public function testNonArgonValidation()
    {
        $hasher = new SodiumPasswordHasher();
        $this->assertTrue($hasher->verify('$5$abcdefgh$ZLdkj8mkc2XVSrPVjskDAgZPGjtj1VGVaa1aUkrMTU/', 'password'));
        $this->assertFalse($hasher->verify('$5$abcdefgh$ZLdkj8mkc2XVSrPVjskDAgZPGjtj1VGVaa1aUkrMTU/', 'anotherPassword'));
        $this->assertTrue($hasher->verify('$6$abcdefgh$yVfUwsw5T.JApa8POvClA1pQ5peiq97DUNyXCZN5IrF.BMSkiaLQ5kvpuEm/VQ1Tvh/KV2TcaWh8qinoW5dhA1', 'password'));
        $this->assertFalse($hasher->verify('$6$abcdefgh$yVfUwsw5T.JApa8POvClA1pQ5peiq97DUNyXCZN5IrF.BMSkiaLQ5kvpuEm/VQ1Tvh/KV2TcaWh8qinoW5dhA1', 'anotherPassword'));
    }

    public function testHashLength()
    {
        $this->expectException(InvalidPasswordException::class);
        $hasher = new SodiumPasswordHasher();
        $hasher->hash(str_repeat('a', 4097));
    }

    public function testCheckPasswordLength()
    {
        $hasher = new SodiumPasswordHasher();
        $result = $hasher->hash(str_repeat('a', 4096));
        $this->assertFalse($hasher->verify($result, str_repeat('a', 4097)));
        $this->assertTrue($hasher->verify($result, str_repeat('a', 4096)));
    }

    public function testBcryptWithLongPassword()
    {
        $hasher = new SodiumPasswordHasher(null, null);
        $plainPassword = str_repeat('a', 100);

        $this->assertFalse($hasher->verify(password_hash($plainPassword, \PASSWORD_BCRYPT, ['cost' => 4]), $plainPassword));
        $this->assertTrue($hasher->verify((new NativePasswordHasher(null, null, 4, \PASSWORD_BCRYPT))->hash($plainPassword), $plainPassword));
    }

    /**
     * @requires PHP < 8.4
     */
    public function testBcryptWithNulByteWithNativePasswordHash()
    {
        $hasher = new SodiumPasswordHasher(null, null);
        $plainPassword = "a\0b";

        try {
            $hash = password_hash($plainPassword, \PASSWORD_BCRYPT, ['cost' => 4]);
        } catch (\Throwable $throwable) {
            // we skip the test in case the current PHP version does not support NUL bytes in passwords
            // with bcrypt
            //
            // @see https://github.com/php/php-src/commit/11f2568767660ffe92fbc6799800e01203aad73a
            if (str_contains($throwable->getMessage(), 'Bcrypt password must not contain null character')) {
                $this->markTestSkipped('password_hash() does not accept passwords containing NUL bytes.');
            }

            throw $throwable;
        }

        if (null === $hash) {
            // we also skip the test in case password_hash() returns null as
            // implemented in security patches backports
            //
            // @see https://github.com/shivammathur/php-src-backports/commit/d22d9ebb29dce86edd622205dd1196a2796c08c7
            $this->markTestSkipped('password_hash() does not accept passwords containing NUL bytes.');
        }

        $this->assertFalse($hasher->verify($hash, $plainPassword));
    }

    public function testPasswordNulByteGracefullyHandled()
    {
        $hasher = new SodiumPasswordHasher(null, null);
        $plainPassword = "a\0b";

        $this->assertTrue($hasher->verify($hasher->hash($plainPassword), $plainPassword));
    }

    public function testUserProvidedSaltIsNotUsed()
    {
        $hasher = new SodiumPasswordHasher();
        $result = $hasher->hash('password');
        $this->assertTrue($hasher->verify($result, 'password'));
    }

    public function testNeedsRehash()
    {
        $hasher = new SodiumPasswordHasher(4, 11000);

        $this->assertTrue($hasher->needsRehash('dummyhash'));

        $hash = $hasher->hash('foo');
        $this->assertFalse($hasher->needsRehash($hash));

        $hasher = new SodiumPasswordHasher(5, 11000);
        $this->assertTrue($hasher->needsRehash($hash));
    }
}
