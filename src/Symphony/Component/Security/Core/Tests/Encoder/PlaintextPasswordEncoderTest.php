<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Core\Tests\Encoder;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Security\Core\Encoder\PlaintextPasswordEncoder;

class PlaintextPasswordEncoderTest extends TestCase
{
    public function testIsPasswordValid()
    {
        $encoder = new PlaintextPasswordEncoder();

        $this->assertTrue($encoder->isPasswordValid('foo', 'foo', ''));
        $this->assertFalse($encoder->isPasswordValid('bar', 'foo', ''));
        $this->assertFalse($encoder->isPasswordValid('FOO', 'foo', ''));

        $encoder = new PlaintextPasswordEncoder(true);

        $this->assertTrue($encoder->isPasswordValid('foo', 'foo', ''));
        $this->assertFalse($encoder->isPasswordValid('bar', 'foo', ''));
        $this->assertTrue($encoder->isPasswordValid('FOO', 'foo', ''));
    }

    public function testEncodePassword()
    {
        $encoder = new PlaintextPasswordEncoder();

        $this->assertSame('foo', $encoder->encodePassword('foo', ''));
    }

    /**
     * @expectedException \Symphony\Component\Security\Core\Exception\BadCredentialsException
     */
    public function testEncodePasswordLength()
    {
        $encoder = new PlaintextPasswordEncoder();

        $encoder->encodePassword(str_repeat('a', 5000), 'salt');
    }

    public function testCheckPasswordLength()
    {
        $encoder = new PlaintextPasswordEncoder();

        $this->assertFalse($encoder->isPasswordValid('encoded', str_repeat('a', 5000), 'salt'));
    }
}
