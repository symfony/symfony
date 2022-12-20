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
use Symfony\Component\PasswordHasher\Hasher\PlaintextPasswordHasher;

class PlaintextPasswordHasherTest extends TestCase
{
    public function testVerify()
    {
        $hasher = new PlaintextPasswordHasher();

        self::assertTrue($hasher->verify('foo', 'foo', ''));
        self::assertFalse($hasher->verify('bar', 'foo', ''));
        self::assertFalse($hasher->verify('FOO', 'foo', ''));

        $hasher = new PlaintextPasswordHasher(true);

        self::assertTrue($hasher->verify('foo', 'foo', ''));
        self::assertFalse($hasher->verify('bar', 'foo', ''));
        self::assertTrue($hasher->verify('FOO', 'foo', ''));
    }

    public function testHash()
    {
        $hasher = new PlaintextPasswordHasher();

        self::assertSame('foo', $hasher->hash('foo', ''));
    }

    public function testHashLength()
    {
        self::expectException(InvalidPasswordException::class);
        $hasher = new PlaintextPasswordHasher();

        $hasher->hash(str_repeat('a', 5000), 'salt');
    }

    public function testCheckPasswordLength()
    {
        $hasher = new PlaintextPasswordHasher();

        self::assertFalse($hasher->verify('encoded', str_repeat('a', 5000), 'salt'));
    }
}
