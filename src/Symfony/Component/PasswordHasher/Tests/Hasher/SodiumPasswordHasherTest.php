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
        $result = $hasher->hash('password', null);
        $this->assertTrue($hasher->verify($result, 'password', null));
        $this->assertFalse($hasher->verify($result, 'anotherPassword', null));
        $this->assertFalse($hasher->verify($result, '', null));
    }

    public function testBcryptValidation()
    {
        $hasher = new SodiumPasswordHasher();
        $this->assertTrue($hasher->verify('$2y$04$M8GDODMoGQLQRpkYCdoJh.lbiZPee3SZI32RcYK49XYTolDGwoRMm', 'abc', null));
    }

    public function testNonArgonValidation()
    {
        $hasher = new SodiumPasswordHasher();
        $this->assertTrue($hasher->verify('$5$abcdefgh$ZLdkj8mkc2XVSrPVjskDAgZPGjtj1VGVaa1aUkrMTU/', 'password', null));
        $this->assertFalse($hasher->verify('$5$abcdefgh$ZLdkj8mkc2XVSrPVjskDAgZPGjtj1VGVaa1aUkrMTU/', 'anotherPassword', null));
        $this->assertTrue($hasher->verify('$6$abcdefgh$yVfUwsw5T.JApa8POvClA1pQ5peiq97DUNyXCZN5IrF.BMSkiaLQ5kvpuEm/VQ1Tvh/KV2TcaWh8qinoW5dhA1', 'password', null));
        $this->assertFalse($hasher->verify('$6$abcdefgh$yVfUwsw5T.JApa8POvClA1pQ5peiq97DUNyXCZN5IrF.BMSkiaLQ5kvpuEm/VQ1Tvh/KV2TcaWh8qinoW5dhA1', 'anotherPassword', null));
    }

    public function testHashLength()
    {
        $this->expectException(InvalidPasswordException::class);
        $hasher = new SodiumPasswordHasher();
        $hasher->hash(str_repeat('a', 4097), 'salt');
    }

    public function testCheckPasswordLength()
    {
        $hasher = new SodiumPasswordHasher();
        $result = $hasher->hash(str_repeat('a', 4096), null);
        $this->assertFalse($hasher->verify($result, str_repeat('a', 4097), null));
        $this->assertTrue($hasher->verify($result, str_repeat('a', 4096), null));
    }

    public function testBcryptWithLongPassword()
    {
        $hasher = new SodiumPasswordHasher(null, null, 4);
        $plainPassword = str_repeat('a', 100);

        $this->assertFalse($hasher->verify(password_hash($plainPassword, \PASSWORD_BCRYPT, ['cost' => 4]), $plainPassword, 'salt'));
        $this->assertTrue($hasher->verify((new NativePasswordHasher(null, null, 4, \PASSWORD_BCRYPT))->hash($plainPassword), $plainPassword, 'salt'));
    }

    public function testBcryptWithNulByte()
    {
        $hasher = new SodiumPasswordHasher(null, null, 4);
        $plainPassword = "a\0b";

        $this->assertFalse($hasher->verify(password_hash($plainPassword, \PASSWORD_BCRYPT, ['cost' => 4]), $plainPassword, 'salt'));
        $this->assertTrue($hasher->verify((new NativePasswordHasher(null, null, 4, \PASSWORD_BCRYPT))->hash($plainPassword), $plainPassword, 'salt'));
    }

    public function testUserProvidedSaltIsNotUsed()
    {
        $hasher = new SodiumPasswordHasher();
        $result = $hasher->hash('password', 'salt');
        $this->assertTrue($hasher->verify($result, 'password', 'anotherSalt'));
    }

    public function testNeedsRehash()
    {
        $hasher = new SodiumPasswordHasher(4, 11000);

        $this->assertTrue($hasher->needsRehash('dummyhash'));

        $hash = $hasher->hash('foo', 'salt');
        $this->assertFalse($hasher->needsRehash($hash));

        $hasher = new SodiumPasswordHasher(5, 11000);
        $this->assertTrue($hasher->needsRehash($hash));
    }
}
