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

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCostBelowRange()
    {
        new BCryptPasswordEncoder(3);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCostAboveRange()
    {
        new BCryptPasswordEncoder(32);
    }

    public function testCostInRange()
    {
        for ($cost = 4; $cost <= 31; $cost++) {
            new BCryptPasswordEncoder($cost);
        }
    }

    public function testResultLength()
    {
        $encoder = new BCryptPasswordEncoder(4);
        $result = $encoder->encodePassword(self::PASSWORD);
        $this->assertEquals(60, strlen($result));
    }

    public function testValidation()
    {
        $encoder = new BCryptPasswordEncoder(4);
        $result = $encoder->encodePassword(self::PASSWORD);
        $this->assertTrue($encoder->isPasswordValid($result, self::PASSWORD));
        $this->assertFalse($encoder->isPasswordValid($result, 'anotherPassword'));
    }
}
