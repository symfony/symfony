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
use Symfony\Component\Security\Core\Encoder\Argon2iPasswordEncoder;

/**
 * @author Zan Baldwin <hello@zanbaldwin.com>
 *
 * @group legacy
 */
class Argon2iPasswordEncoderTest extends TestCase
{
    const PASSWORD = 'password';

    protected function setUp()
    {
        if (!Argon2iPasswordEncoder::isSupported()) {
            $this->markTestSkipped('Argon2i algorithm is not supported.');
        }
    }

    public function testValidationWithConfig()
    {
        $encoder = new Argon2iPasswordEncoder(8, 4, 1);
        $result = $encoder->encodePassword(self::PASSWORD, null);
        $this->assertTrue($encoder->isPasswordValid($result, self::PASSWORD, null));
        $this->assertFalse($encoder->isPasswordValid($result, 'anotherPassword', null));
    }

    public function testValidation()
    {
        $encoder = new Argon2iPasswordEncoder();
        $result = $encoder->encodePassword(self::PASSWORD, null);
        $this->assertTrue($encoder->isPasswordValid($result, self::PASSWORD, null));
        $this->assertFalse($encoder->isPasswordValid($result, 'anotherPassword', null));
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\BadCredentialsException
     */
    public function testEncodePasswordLength()
    {
        $encoder = new Argon2iPasswordEncoder();
        $encoder->encodePassword(str_repeat('a', 4097), 'salt');
    }

    public function testCheckPasswordLength()
    {
        $encoder = new Argon2iPasswordEncoder();
        $result = $encoder->encodePassword(str_repeat('a', 4096), null);
        $this->assertFalse($encoder->isPasswordValid($result, str_repeat('a', 4097), null));
        $this->assertTrue($encoder->isPasswordValid($result, str_repeat('a', 4096), null));
    }

    public function testUserProvidedSaltIsNotUsed()
    {
        $encoder = new Argon2iPasswordEncoder();
        $result = $encoder->encodePassword(self::PASSWORD, 'salt');
        $this->assertTrue($encoder->isPasswordValid($result, self::PASSWORD, 'anotherSalt'));
    }
}
