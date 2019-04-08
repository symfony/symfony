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
use Symfony\Component\Security\Core\Encoder\Argon2idPasswordEncoder;
use Symfony\Component\Security\Core\Encoder\Argon2iPasswordEncoder;

/**
 * @author Zan Baldwin <hello@zanbaldwin.com>
 */
class Argon2iPasswordEncoderTest extends TestCase
{
    const PASSWORD = 'password';

    public function testValidationWithConfig()
    {
        if (!Argon2iPasswordEncoder::isSupported() || Argon2idPasswordEncoder::isDefaultSodiumAlgorithm()) {
            $this->markTestSkipped('Argon2i algorithm is not supported.');
        }

        $encoder = new Argon2iPasswordEncoder(8, 4, 1);
        $result = $encoder->encodePassword(self::PASSWORD, null);
        $this->assertTrue($encoder->isPasswordValid($result, self::PASSWORD, null));
        $this->assertFalse($encoder->isPasswordValid($result, 'anotherPassword', null));
    }

    public function testValidation()
    {
        if (!Argon2iPasswordEncoder::isSupported() || Argon2idPasswordEncoder::isDefaultSodiumAlgorithm()) {
            $this->markTestSkipped('Argon2i algorithm is not supported.');
        }

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
        if (!Argon2iPasswordEncoder::isSupported() || Argon2idPasswordEncoder::isDefaultSodiumAlgorithm()) {
            $this->markTestSkipped('Argon2i algorithm is not supported.');
        }

        $encoder = new Argon2iPasswordEncoder();
        $encoder->encodePassword(str_repeat('a', 4097), 'salt');
    }

    public function testCheckPasswordLength()
    {
        if (!Argon2iPasswordEncoder::isSupported() || Argon2idPasswordEncoder::isDefaultSodiumAlgorithm()) {
            $this->markTestSkipped('Argon2i algorithm is not supported.');
        }

        $encoder = new Argon2iPasswordEncoder();
        $result = $encoder->encodePassword(str_repeat('a', 4096), null);
        $this->assertFalse($encoder->isPasswordValid($result, str_repeat('a', 4097), null));
        $this->assertTrue($encoder->isPasswordValid($result, str_repeat('a', 4096), null));
    }

    public function testUserProvidedSaltIsNotUsed()
    {
        if (!Argon2iPasswordEncoder::isSupported() || Argon2idPasswordEncoder::isDefaultSodiumAlgorithm()) {
            $this->markTestSkipped('Argon2i algorithm is not supported.');
        }

        $encoder = new Argon2iPasswordEncoder();
        $result = $encoder->encodePassword(self::PASSWORD, 'salt');
        $this->assertTrue($encoder->isPasswordValid($result, self::PASSWORD, 'anotherSalt'));
    }

    /**
     * @group legacy
     * @exectedDeprecation Using "Symfony\Component\Security\Core\Encoder\Argon2iPasswordEncoder" while only the "argon2id" algorithm is supported is deprecated since Symfony 4.3, use "Symfony\Component\Security\Core\Encoder\Argon2idPasswordEncoder" instead.
     * @exectedDeprecation Calling "Symfony\Component\Security\Core\Encoder\Argon2iPasswordEncoder::isPasswordValid()" with a password hashed using argon2id is deprecated since Symfony 4.3, use "Symfony\Component\Security\Core\Encoder\Argon2idPasswordEncoder" instead.
     */
    public function testEncodeWithArgon2idSupportOnly()
    {
        if (!Argon2iPasswordEncoder::isSupported() || !Argon2idPasswordEncoder::isDefaultSodiumAlgorithm()) {
            $this->markTestSkipped('Argon2id algorithm not available.');
        }

        $encoder = new Argon2iPasswordEncoder();
        $result = $encoder->encodePassword(self::PASSWORD, null);
        $this->assertTrue($encoder->isPasswordValid($result, self::PASSWORD, null));
        $this->assertFalse($encoder->isPasswordValid($result, 'anotherPassword', null));
    }
}
