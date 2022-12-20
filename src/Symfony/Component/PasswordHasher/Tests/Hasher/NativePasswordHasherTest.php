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
        self::expectException(\InvalidArgumentException::class);
        new NativePasswordHasher(null, null, 3);
    }

    public function testCostAboveRange()
    {
        self::expectException(\InvalidArgumentException::class);
        new NativePasswordHasher(null, null, 32);
    }

    /**
     * @dataProvider validRangeData
     */
    public function testCostInRange($cost)
    {
        self::assertInstanceOf(NativePasswordHasher::class, new NativePasswordHasher(null, null, $cost));
    }

    public function validRangeData()
    {
        $costs = range(4, 31);
        array_walk($costs, function (&$cost) { $cost = [$cost]; });

        return $costs;
    }

    public function testValidation()
    {
        $hasher = new NativePasswordHasher();
        $result = $hasher->hash('password', null);
        self::assertTrue($hasher->verify($result, 'password', null));
        self::assertFalse($hasher->verify($result, 'anotherPassword', null));
        self::assertFalse($hasher->verify($result, '', null));
    }

    public function testNonArgonValidation()
    {
        $hasher = new NativePasswordHasher();
        self::assertTrue($hasher->verify('$5$abcdefgh$ZLdkj8mkc2XVSrPVjskDAgZPGjtj1VGVaa1aUkrMTU/', 'password', null));
        self::assertFalse($hasher->verify('$5$abcdefgh$ZLdkj8mkc2XVSrPVjskDAgZPGjtj1VGVaa1aUkrMTU/', 'anotherPassword', null));
        self::assertTrue($hasher->verify('$6$abcdefgh$yVfUwsw5T.JApa8POvClA1pQ5peiq97DUNyXCZN5IrF.BMSkiaLQ5kvpuEm/VQ1Tvh/KV2TcaWh8qinoW5dhA1', 'password', null));
        self::assertFalse($hasher->verify('$6$abcdefgh$yVfUwsw5T.JApa8POvClA1pQ5peiq97DUNyXCZN5IrF.BMSkiaLQ5kvpuEm/VQ1Tvh/KV2TcaWh8qinoW5dhA1', 'anotherPassword', null));
    }

    public function testConfiguredAlgorithm()
    {
        $hasher = new NativePasswordHasher(null, null, null, \PASSWORD_BCRYPT);
        $result = $hasher->hash('password', null);
        self::assertTrue($hasher->verify($result, 'password', null));
        self::assertStringStartsWith('$2', $result);
    }

    public function testDefaultAlgorithm()
    {
        $hasher = new NativePasswordHasher();
        $result = $hasher->hash('password');
        self::assertTrue($hasher->verify($result, 'password'));
        self::assertStringStartsWith('$2', $result);
    }

    public function testConfiguredAlgorithmWithLegacyConstValue()
    {
        $hasher = new NativePasswordHasher(null, null, null, '1');
        $result = $hasher->hash('password', null);
        self::assertTrue($hasher->verify($result, 'password', null));
        self::assertStringStartsWith('$2', $result);
    }

    public function testBcryptWithLongPassword()
    {
        $hasher = new NativePasswordHasher(null, null, 4, \PASSWORD_BCRYPT);
        $plainPassword = str_repeat('a', 100);

        self::assertFalse($hasher->verify(password_hash($plainPassword, \PASSWORD_BCRYPT, ['cost' => 4]), $plainPassword, 'salt'));
        self::assertTrue($hasher->verify($hasher->hash($plainPassword), $plainPassword, 'salt'));
    }

    public function testBcryptWithNulByte()
    {
        $hasher = new NativePasswordHasher(null, null, 4, \PASSWORD_BCRYPT);
        $plainPassword = "a\0b";

        self::assertFalse($hasher->verify(password_hash($plainPassword, \PASSWORD_BCRYPT, ['cost' => 4]), $plainPassword, 'salt'));
        self::assertTrue($hasher->verify($hasher->hash($plainPassword), $plainPassword, 'salt'));
    }

    public function testNeedsRehash()
    {
        $hasher = new NativePasswordHasher(4, 11000, 4);

        self::assertTrue($hasher->needsRehash('dummyhash'));

        $hash = $hasher->hash('foo', 'salt');
        self::assertFalse($hasher->needsRehash($hash));

        $hasher = new NativePasswordHasher(5, 11000, 5);
        self::assertTrue($hasher->needsRehash($hash));
    }
}
