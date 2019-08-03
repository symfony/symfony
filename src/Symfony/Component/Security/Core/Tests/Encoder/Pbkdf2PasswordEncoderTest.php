<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Encoder;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Encoder\Pbkdf2PasswordEncoder;

class Pbkdf2PasswordEncoderTest extends TestCase
{
    public function testIsPasswordValid()
    {
        $encoder = new Pbkdf2PasswordEncoder('sha256', false, 1, 40);

        $this->assertTrue($encoder->isPasswordValid('c1232f10f62715fda06ae7c0a2037ca19b33cf103b727ba56d870c11f290a2ab106974c75607c8a3', 'password', ''));
    }

    public function testEncodePassword()
    {
        $encoder = new Pbkdf2PasswordEncoder('sha256', false, 1, 40);
        $this->assertSame('c1232f10f62715fda06ae7c0a2037ca19b33cf103b727ba56d870c11f290a2ab106974c75607c8a3', $encoder->encodePassword('password', ''));

        $encoder = new Pbkdf2PasswordEncoder('sha256', true, 1, 40);
        $this->assertSame('wSMvEPYnFf2gaufAogN8oZszzxA7cnulbYcMEfKQoqsQaXTHVgfIow==', $encoder->encodePassword('password', ''));

        $encoder = new Pbkdf2PasswordEncoder('sha256', false, 2, 40);
        $this->assertSame('8bc2f9167a81cdcfad1235cd9047f1136271c1f978fcfcb35e22dbeafa4634f6fd2214218ed63ebb', $encoder->encodePassword('password', ''));
    }

    public function testEncodePasswordAlgorithmDoesNotExist()
    {
        $this->expectException('LogicException');
        $encoder = new Pbkdf2PasswordEncoder('foobar');
        $encoder->encodePassword('password', '');
    }

    public function testEncodePasswordLength()
    {
        $this->expectException('Symfony\Component\Security\Core\Exception\BadCredentialsException');
        $encoder = new Pbkdf2PasswordEncoder('foobar');

        $encoder->encodePassword(str_repeat('a', 5000), 'salt');
    }

    public function testCheckPasswordLength()
    {
        $encoder = new Pbkdf2PasswordEncoder('foobar');

        $this->assertFalse($encoder->isPasswordValid('encoded', str_repeat('a', 5000), 'salt'));
    }
}
