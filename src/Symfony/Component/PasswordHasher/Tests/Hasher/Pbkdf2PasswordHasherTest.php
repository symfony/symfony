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
use Symfony\Component\PasswordHasher\Hasher\Pbkdf2PasswordHasher;

class Pbkdf2PasswordHasherTest extends TestCase
{
    public function testVerify()
    {
        $hasher = new Pbkdf2PasswordHasher('sha256', false, 1, 40);

        $this->assertTrue($hasher->verify('c1232f10f62715fda06ae7c0a2037ca19b33cf103b727ba56d870c11f290a2ab106974c75607c8a3', 'password', ''));
    }

    public function testHash()
    {
        $hasher = new Pbkdf2PasswordHasher('sha256', false, 1, 40);
        $this->assertSame('c1232f10f62715fda06ae7c0a2037ca19b33cf103b727ba56d870c11f290a2ab106974c75607c8a3', $hasher->hash('password', ''));

        $hasher = new Pbkdf2PasswordHasher('sha256', true, 1, 40);
        $this->assertSame('wSMvEPYnFf2gaufAogN8oZszzxA7cnulbYcMEfKQoqsQaXTHVgfIow==', $hasher->hash('password', ''));

        $hasher = new Pbkdf2PasswordHasher('sha256', false, 2, 40);
        $this->assertSame('8bc2f9167a81cdcfad1235cd9047f1136271c1f978fcfcb35e22dbeafa4634f6fd2214218ed63ebb', $hasher->hash('password', ''));
    }

    public function testHashAlgorithmDoesNotExist()
    {
        $this->expectException(\LogicException::class);
        $hasher = new Pbkdf2PasswordHasher('foobar');
        $hasher->hash('password', '');
    }

    public function testHashLength()
    {
        $this->expectException(InvalidPasswordException::class);
        $hasher = new Pbkdf2PasswordHasher('foobar');

        $hasher->hash(str_repeat('a', 5000), 'salt');
    }

    public function testCheckPasswordLength()
    {
        $hasher = new Pbkdf2PasswordHasher('foobar');

        $this->assertFalse($hasher->verify('encoded', str_repeat('a', 5000), 'salt'));
    }
}
