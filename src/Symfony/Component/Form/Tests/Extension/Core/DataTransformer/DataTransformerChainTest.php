<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\DataTransformer;

use Symfony\Component\Form\Extension\Core\DataTransformer\DataTransformerChain;

class DataTransformerChainTest extends \PHPUnit_Framework_TestCase
{
    public function testTransform()
    {
        $transformer1 = $this->getMock('Symfony\Component\Form\DataTransformerInterface');
        $transformer1->expects($this->once())
                                 ->method('transform')
                                 ->with($this->identicalTo('foo'))
                                 ->will($this->returnValue('bar'));
        $transformer2 = $this->getMock('Symfony\Component\Form\DataTransformerInterface');
        $transformer2->expects($this->once())
                                 ->method('transform')
                                 ->with($this->identicalTo('bar'))
                                 ->will($this->returnValue('baz'));

        $chain = new DataTransformerChain(array($transformer1, $transformer2));

        $this->assertEquals('baz', $chain->transform('foo'));
    }

    public function testReverseTransform()
    {
        $transformer2 = $this->getMock('Symfony\Component\Form\DataTransformerInterface');
        $transformer2->expects($this->once())
                                 ->method('reverseTransform')
                                 ->with($this->identicalTo('foo'))
                                 ->will($this->returnValue('bar'));
        $transformer1 = $this->getMock('Symfony\Component\Form\DataTransformerInterface');
        $transformer1->expects($this->once())
                                 ->method('reverseTransform')
                                 ->with($this->identicalTo('bar'))
                                 ->will($this->returnValue('baz'));

        $chain = new DataTransformerChain(array($transformer1, $transformer2));

        $this->assertEquals('baz', $chain->reverseTransform('foo'));
    }
}
