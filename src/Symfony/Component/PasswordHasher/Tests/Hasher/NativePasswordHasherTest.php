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

    public function testBcryptWithNulByte()
    {
        $hasher = new NativePasswordHasher(null, null, 4, \PASSWORD_BCRYPT);
        $plainPassword = "a\0b";

        if (\PHP_VERSION_ID < 80218 || \PHP_VERSION_ID >= 80300 && \PHP_VERSION_ID < 80305) {
            // password_hash() does not accept passwords containing NUL bytes since PHP 8.2.18 and 8.3.5
            $this->assertFalse($hasher->verify(password_hash($plainPassword, \PASSWORD_BCRYPT, ['cost' => 4]), $plainPassword));
        }

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
