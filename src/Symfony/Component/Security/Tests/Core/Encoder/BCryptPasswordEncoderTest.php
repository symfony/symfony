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
        $result = $encoder->encodePassword(self::PASSWORD);
        $this->assertEquals(60, strlen($result));
    }

    public function testValidation()
    {
        $encoder = new BCryptPasswordEncoder($this->secureRandom, self::VALID_COST);
        $result = $encoder->encodePassword(self::PASSWORD);
        $this->assertTrue($encoder->isPasswordValid($result, self::PASSWORD));
        $this->assertFalse($encoder->isPasswordValid($result, 'anotherPassword'));
    }

    public function testSecureRandomIsUsed()
    {
        $this->secureRandom
            ->expects($this->atLeastOnce())
            ->method('nextBytes')
        ;

        $salt = str_replace('+', '.', base64_encode(self::BYTES));

        $encoder = new BCryptPasswordEncoder($this->secureRandom, self::VALID_COST);
        $result = $encoder->encodePassword(self::PASSWORD);
        $expected = crypt(self::PASSWORD, BCRYPT_PREFIX . self::VALID_COST . '$' . $salt . '$');

        $this->assertEquals($expected, $result);
    }
}
