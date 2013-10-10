<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Tests\Core\Encoder;

use Symfony\Component\Security\Core\Encoder\BCryptPasswordEncoder;

/**
 * @author Elnur Abdurrakhimov <elnur@elnur.pro>
 */
class BCryptPasswordEncoderTest extends \PHPUnit_Framework_TestCase
{
    const PASSWORD = 'password';
    const BYTES = '0123456789abcdef';
    const VALID_COST = '04';

    const SECURE_RANDOM_INTERFACE = 'Symfony\Component\Security\Core\Util\SecureRandomInterface';

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $secureRandom;

    protected function setUp()
    {
        $this->secureRandom = $this->getMock(self::SECURE_RANDOM_INTERFACE);

        $this->secureRandom
            ->expects($this->any())
            ->method('nextBytes')
            ->will($this->returnValue(self::BYTES))
        ;
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCostBelowRange()
    {
        new BCryptPasswordEncoder($this->secureRandom, 3);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCostAboveRange()
    {
        new BCryptPasswordEncoder($this->secureRandom, 32);
    }

    public function testCostInRange()
    {
        for ($cost = 4; $cost <= 31; $cost++) {
            new BCryptPasswordEncoder($this->secureRandom, $cost);
        }
    }

    public function testResultLength()
    {
        $encoder = new BCryptPasswordEncoder($this->secureRandom, self::VALID_COST);
        $result = $encoder->encodePassword(self::PASSWORD, null);
        $this->assertEquals(60, strlen($result));
    }

    public function testValidation()
    {
        $encoder = new BCryptPasswordEncoder($this->secureRandom, self::VALID_COST);
        $result = $encoder->encodePassword(self::PASSWORD, null);
        $this->assertTrue($encoder->isPasswordValid($result, self::PASSWORD, null));
        $this->assertFalse($encoder->isPasswordValid($result, 'anotherPassword', null));
    }

    public function testValidationKnownPassword()
    {
        $encoder = new BCryptPasswordEncoder($this->secureRandom, self::VALID_COST);
        $prefix = '$'.(version_compare(phpversion(), '5.3.7', '>=')
                       ? '2y' : '2a').'$';

        $encrypted = $prefix.'04$ABCDEFGHIJKLMNOPQRSTU.uTmwd4KMSHxbUsG7bng8x7YdA0PM1iq';
        $this->assertTrue($encoder->isPasswordValid($encrypted, self::PASSWORD, null));
    }

    public function testSecureRandomIsUsed()
    {
        if (function_exists('mcrypt_create_iv')) {
            return;
        }

        $this->secureRandom
            ->expects($this->atLeastOnce())
            ->method('nextBytes')
        ;

        $encoder = new BCryptPasswordEncoder($this->secureRandom, self::VALID_COST);
        $result = $encoder->encodePassword(self::PASSWORD, null);

        $prefix = '$'.(version_compare(phpversion(), '5.3.7', '>=')
                       ? '2y' : '2a').'$';
        $salt = 'MDEyMzQ1Njc4OWFiY2RlZe';
        $expected = crypt(self::PASSWORD, $prefix . self::VALID_COST . '$' . $salt);

        $this->assertEquals($expected, $result);
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\BadCredentialsException
     */
    public function testEncodePasswordLength()
    {
        $encoder = new BCryptPasswordEncoder($this->secureRandom, self::VALID_COST);

        $encoder->encodePassword(str_repeat('a', 5000), 'salt');
    }

    public function testCheckPasswordLength()
    {
        $encoder = new BCryptPasswordEncoder($this->secureRandom, self::VALID_COST);

        $this->assertFalse($encoder->isPasswordValid('encoded', str_repeat('a', 5000), 'salt'));
    }
}
