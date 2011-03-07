<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Security\Core\Encoder;

use Symfony\Component\Security\Core\Encoder\PlaintextPasswordEncoder;

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
