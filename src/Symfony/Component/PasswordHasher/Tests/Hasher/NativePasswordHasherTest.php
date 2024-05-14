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
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;

/**
 * @author Elnur Abdurrakhimov <elnur@elnur.pro>
 */
class NativePasswordHasherTest extends TestCase
{
    public function testCostBelowRange()
    {
        $this->expectException(\InvalidArgumentException::class);
        new NativePasswordHasher(null, null, 3);
    }

    public function testCostAboveRange()
    {
        $this->expectException(\InvalidArgumentException::class);
        new NativePasswordHasher(null, null, 32);
    }

    /**
     * @dataProvider validRangeData
     */
    public function testCostInRange($cost)
    {
        $this->assertInstanceOf(NativePasswordHasher::class, new NativePasswordHasher(null, null, $cost));
    }

    public static function validRangeData()
    {
        $costs = range(4, 31);
        array_walk($costs, function (&$cost) { $cost = [$cost]; });

        return $costs;
    }

    public function testValidation()
    {
        $hasher = new NativePasswordHasher();
        $result = $hasher->hash('password', null);
        $this->assertTrue($hasher->verify($result, 'password'));
        $this->assertFalse($hasher->verify($result, 'anotherPassword'));
        $this->assertFalse($hasher->verify($result, ''));
    }

    public function testNonArgonValidation()
    {
        $hasher = new NativePasswordHasher();
        $this->assertTrue($hasher->verify('$5$abcdefgh$ZLdkj8mkc2XVSrPVjskDAgZPGjtj1VGVaa1aUkrMTU/', 'password'));
        $this->assertFalse($hasher->verify('$5$abcdefgh$ZLdkj8mkc2XVSrPVjskDAgZPGjtj1VGVaa1aUkrMTU/', 'anotherPassword'));
        $this->assertTrue($hasher->verify('$6$abcdefgh$yVfUwsw5T.JApa8POvClA1pQ5peiq97DUNyXCZN5IrF.BMSkiaLQ5kvpuEm/VQ1Tvh/KV2TcaWh8qinoW5dhA1', 'password'));
        $this->assertFalse($hasher->verify('$6$abcdefgh$yVfUwsw5T.JApa8POvClA1pQ5peiq97DUNyXCZN5IrF.BMSkiaLQ5kvpuEm/VQ1Tvh/KV2TcaWh8qinoW5dhA1', 'anotherPassword'));
    }

    public function testConfiguredAlgorithm()
    {
        $hasher = new NativePasswordHasher(null, null, null, \PASSWORD_BCRYPT);
        $result = $hasher->hash('password');
        $this->assertTrue($hasher->verify($result, 'password'));
        $this->assertStringStartsWith('$2', $result);
    }

    public function testDefaultAlgorithm()
    {
        $hasher = new NativePasswordHasher();
        $result = $hasher->hash('password');
        $this->assertTrue($hasher->verify($result, 'password'));
        $this->assertStringStartsWith('$2', $result);
    }

    public function testConfiguredAlgorithmWithLegacyConstValue()
    {
        $hasher = new NativePasswordHasher(null, null, null, '1');
        $result = $hasher->hash('password');
        $this->assertTrue($hasher->verify($result, 'password'));
        $this->assertStringStartsWith('$2', $result);
    }

    public function testBcryptWithLongPassword()
    {
        $hasher = new NativePasswordHasher(null, null, 4, \PASSWORD_BCRYPT);
        $plainPassword = str_repeat('a', 100);

        $this->assertFalse($hasher->verify(password_hash($plainPassword, \PASSWORD_BCRYPT, ['cost' => 4]), $plainPassword));
        $this->assertTrue($hasher->verify($hasher->hash($plainPassword), $plainPassword));
    }

    /**
     * @requires PHP < 8.4
     */
    public function testBcryptWithNulByteWithNativePasswordHash()
    {
        $hasher = new NativePasswordHasher(null, null, 4, \PASSWORD_BCRYPT);
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
        $hasher = new NativePasswordHasher(null, null, 4, \PASSWORD_BCRYPT);
        $plainPassword = "a\0b";

        $this->assertTrue($hasher->verify($hasher->hash($plainPassword), $plainPassword));
    }

    public function testNeedsRehash()
    {
        $hasher = new NativePasswordHasher(4, 11000, 4);

        $this->assertTrue($hasher->needsRehash('dummyhash'));

        $hash = $hasher->hash('foo');
        $this->assertFalse($hasher->needsRehash($hash));

        $hasher = new NativePasswordHasher(5, 11000, 5);
        $this->assertTrue($hasher->needsRehash($hash));
    }
}
