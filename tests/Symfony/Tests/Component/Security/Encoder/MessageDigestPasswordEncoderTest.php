<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Security\Encoder;

use Symfony\Component\Security\Encoder\MessageDigestPasswordEncoder;

class MessageDigestPasswordEncoderTest extends \PHPUnit_Framework_TestCase
{
    public function testIsPasswordValid()
    {
        $encoder = new MessageDigestPasswordEncoder();

        $this->assertTrue($encoder->isPasswordValid(hash('sha256', 'password'), 'password', ''));
    }

    public function testEncodePassword()
    {
        $encoder = new MessageDigestPasswordEncoder();
        $this->assertSame(hash('sha256', 'password'), $encoder->encodePassword('password', ''));

        $encoder = new MessageDigestPasswordEncoder('sha256', true);
        $this->assertSame(base64_encode(hash('sha256', 'password')), $encoder->encodePassword('password', ''));

        $encoder = new MessageDigestPasswordEncoder('sha256', false, 2);
        $this->assertSame(hash('sha256', hash('sha256', 'password')), $encoder->encodePassword('password', ''));
    }

    /**
     * @expectedException LogicException
     */
    public function testEncodePasswordAlgorithmDoesNotExist()
    {
        $encoder = new MessageDigestPasswordEncoder('foobar');
        $encoder->encodePassword('password', '');
    }
}
