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
use Symfony\Component\Security\Core\Encoder\BCryptPasswordEncoder;

/**
 * @author Elnur Abdurrakhimov <elnur@elnur.pro>
 */
class BCryptPasswordEncoderTest extends TestCase
{
    const PASSWORD = 'password';
    const VALID_COST = '04';

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

    /**
     * @dataProvider validRangeData
     */
    public function testCostInRange($cost)
    {
        $this->assertInstanceOf('Symfony\Component\Security\Core\Encoder\BCryptPasswordEncoder', new BCryptPasswordEncoder($cost));
    }

    public function validRangeData()
    {
        $costs = range(4, 31);
        array_walk($costs, function (&$cost) { $cost = array($cost); });

        return $costs;
    }

    public function testResultLength()
    {
        $encoder = new BCryptPasswordEncoder(self::VALID_COST);
        $result = $encoder->encodePassword(self::PASSWORD, null);
        $this->assertEquals(60, \strlen($result));
    }

    public function testValidation()
    {
        $encoder = new BCryptPasswordEncoder(self::VALID_COST);
        $result = $encoder->encodePassword(self::PASSWORD, null);
        $this->assertTrue($encoder->isPasswordValid($result, self::PASSWORD, null));
        $this->assertFalse($encoder->isPasswordValid($result, 'anotherPassword', null));
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\BadCredentialsException
     */
    public function testEncodePasswordLength()
    {
        $encoder = new BCryptPasswordEncoder(self::VALID_COST);

        $encoder->encodePassword(str_repeat('a', 73), 'salt');
    }

    public function testCheckPasswordLength()
    {
        $encoder = new BCryptPasswordEncoder(self::VALID_COST);
        $result = $encoder->encodePassword(str_repeat('a', 72), null);

        $this->assertFalse($encoder->isPasswordValid($result, str_repeat('a', 73), 'salt'));
        $this->assertTrue($encoder->isPasswordValid($result, str_repeat('a', 72), 'salt'));
    }
}
