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
use Symfony\Component\Security\Core\Encoder\NativePasswordEncoder;

/**
 * @author Elnur Abdurrakhimov <elnur@elnur.pro>
 */
class NativePasswordEncoderTest extends TestCase
{
    public function testCostBelowRange()
    {
        $this->expectException('InvalidArgumentException');
        new NativePasswordEncoder(null, null, 3);
    }

    public function testCostAboveRange()
    {
        $this->expectException('InvalidArgumentException');
        new NativePasswordEncoder(null, null, 32);
    }

    /**
     * @dataProvider validRangeData
     */
    public function testCostInRange($cost)
    {
        $this->assertInstanceOf(NativePasswordEncoder::class, new NativePasswordEncoder(null, null, $cost));
    }

    public function validRangeData()
    {
        $costs = range(4, 31);
        array_walk($costs, function (&$cost) { $cost = [$cost]; });

        return $costs;
    }

    public function testValidation()
    {
        $encoder = new NativePasswordEncoder();
        $result = $encoder->encodePassword('password', null);
        $this->assertTrue($encoder->isPasswordValid($result, 'password', null));
        $this->assertFalse($encoder->isPasswordValid($result, 'anotherPassword', null));
    }

    public function testConfiguredAlgorithm()
    {
        $encoder = new NativePasswordEncoder(null, null, null, PASSWORD_BCRYPT);
        $result = $encoder->encodePassword('password', null);
        $this->assertTrue($encoder->isPasswordValid($result, 'password', null));
        $this->assertStringStartsWith('$2', $result);
    }

    public function testCheckPasswordLength()
    {
        $encoder = new NativePasswordEncoder(null, null, 4);
        $result = password_hash(str_repeat('a', 72), PASSWORD_BCRYPT, ['cost' => 4]);

        $this->assertFalse($encoder->isPasswordValid($result, str_repeat('a', 73), 'salt'));
        $this->assertTrue($encoder->isPasswordValid($result, str_repeat('a', 72), 'salt'));
    }

    public function testNeedsRehash()
    {
        $encoder = new NativePasswordEncoder(4, 11000, 4);

        $this->assertTrue($encoder->needsRehash('dummyhash'));

        $hash = $encoder->encodePassword('foo', 'salt');
        $this->assertFalse($encoder->needsRehash($hash));

        $encoder = new NativePasswordEncoder(5, 11000, 5);
        $this->assertTrue($encoder->needsRehash($hash));
    }
}
