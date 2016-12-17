<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Annotation;

use Symfony\Component\Serializer\Annotation\MaxDepth;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class MaxDepthTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \Symfony\Component\Serializer\Exception\InvalidArgumentException
     * @expectedExceptionMessage Parameter of annotation "Symfony\Component\Serializer\Annotation\MaxDepth" should be set.
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
     * @expectedException \Symfony\Component\Serializer\Exception\InvalidArgumentException
     * @expectedExceptionMessage Parameter of annotation "Symfony\Component\Serializer\Annotation\MaxDepth" must be a positive integer.
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
