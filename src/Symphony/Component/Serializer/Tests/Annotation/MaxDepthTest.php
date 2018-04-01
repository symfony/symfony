<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Serializer\Tests\Annotation;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Serializer\Annotation\MaxDepth;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class MaxDepthTest extends TestCase
{
    /**
     * @expectedException \Symphony\Component\Serializer\Exception\InvalidArgumentException
     * @expectedExceptionMessage Parameter of annotation "Symphony\Component\Serializer\Annotation\MaxDepth" should be set.
     */
    public function testNotSetMaxDepthParameter()
    {
        new MaxDepth(array());
    }

    public function provideInvalidValues()
    {
        return array(
            array(''),
            array('foo'),
            array('1'),
            array(0),
        );
    }

    /**
     * @dataProvider provideInvalidValues
     *
     * @expectedException \Symphony\Component\Serializer\Exception\InvalidArgumentException
     * @expectedExceptionMessage Parameter of annotation "Symphony\Component\Serializer\Annotation\MaxDepth" must be a positive integer.
     */
    public function testNotAnIntMaxDepthParameter($value)
    {
        new MaxDepth(array('value' => $value));
    }

    public function testMaxDepthParameters()
    {
        $maxDepth = new MaxDepth(array('value' => 3));
        $this->assertEquals(3, $maxDepth->getMaxDepth());
    }
}
