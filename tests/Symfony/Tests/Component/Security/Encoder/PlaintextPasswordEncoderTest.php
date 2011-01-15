<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Security\Encoder;

use Symfony\Component\Security\Encoder\PlaintextPasswordEncoder;

class PlaintextPasswordEncoderTest extends \PHPUnit_Framework_TestCase
{
    public function testIsPasswordValid()
    {
        $encoder = new PlaintextPasswordEncoder();

        $this->assertSame(true, $encoder->isPasswordValid('foo', 'foo', ''));
        $this->assertSame(false, $encoder->isPasswordValid('bar', 'foo', ''));
        $this->assertSame(false, $encoder->isPasswordValid('FOO', 'foo', ''));

        $encoder = new PlaintextPasswordEncoder(true);

        $this->assertSame(true, $encoder->isPasswordValid('foo', 'foo', ''));
        $this->assertSame(false, $encoder->isPasswordValid('bar', 'foo', ''));
        $this->assertSame(true, $encoder->isPasswordValid('FOO', 'foo', ''));
    }

    public function testEncodePassword()
    {
        $encoder = new PlaintextPasswordEncoder();

        $this->assertSame('foo', $encoder->encodePassword('foo', ''));
    }
}
