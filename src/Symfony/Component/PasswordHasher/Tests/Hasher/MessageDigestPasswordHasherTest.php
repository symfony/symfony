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
use Symfony\Component\PasswordHasher\Hasher\MessageDigestPasswordHasher;

class MessageDigestPasswordHasherTest extends TestCase
{
    public function testVerify()
    {
        $hasher = new MessageDigestPasswordHasher('sha256', false, 1);

        $this->assertTrue($hasher->verify(hash('sha256', 'password'), 'password', ''));
    }

    public function testHash()
    {
        $hasher = new MessageDigestPasswordHasher('sha256', false, 1);
        $this->assertSame(hash('sha256', 'password'), $hasher->hash('password', ''));

        $hasher = new MessageDigestPasswordHasher('sha256', true, 1);
        $this->assertSame(base64_encode(hash('sha256', 'password', true)), $hasher->hash('password', ''));

        $hasher = new MessageDigestPasswordHasher('sha256', false, 2);
        $this->assertSame(hash('sha256', hash('sha256', 'password', true).'password'), $hasher->hash('password', ''));
    }

    public function testHashAlgorithmDoesNotExist()
    {
        $this->expectException(\LogicException::class);
        $hasher = new MessageDigestPasswordHasher('foobar');
        $hasher->hash('password', '');
    }

    public function testHashLength()
    {
        $this->expectException(InvalidPasswordException::class);
        $hasher = new MessageDigestPasswordHasher();

        $hasher->hash(str_repeat('a', 5000), 'salt');
    }

    public function testCheckPasswordLength()
    {
        $hasher = new MessageDigestPasswordHasher();

        $this->assertFalse($hasher->verify('encoded', str_repeat('a', 5000), 'salt'));
    }
}
