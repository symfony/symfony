<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\ValueTransformer;

use Symfony\Component\Form\ValueTransformer\ValueTransformerChain;

class ValueTransformerChainTest extends \PHPUnit_Framework_TestCase
{
    public function testTransform()
    {
        $transformer1 = $this->getMock('Symfony\Component\Form\ValueTransformer\ValueTransformerInterface');
        $transformer1->expects($this->once())
                                 ->method('transform')
                                 ->with($this->identicalTo('foo'))
                                 ->will($this->returnValue('bar'));
        $transformer2 = $this->getMock('Symfony\Component\Form\ValueTransformer\ValueTransformerInterface');
        $transformer2->expects($this->once())
                                 ->method('transform')
                                 ->with($this->identicalTo('bar'))
                                 ->will($this->returnValue('baz'));

        $chain = new ValueTransformerChain(array($transformer1, $transformer2));

        $this->assertEquals('baz', $chain->transform('foo'));
    }

    public function testReverseTransform()
    {
        $transformer2 = $this->getMock('Symfony\Component\Form\ValueTransformer\ValueTransformerInterface');
        $transformer2->expects($this->once())
                                 ->method('reverseTransform')
                                 ->with($this->identicalTo('foo'))
                                 ->will($this->returnValue('bar'));
        $transformer1 = $this->getMock('Symfony\Component\Form\ValueTransformer\ValueTransformerInterface');
        $transformer1->expects($this->once())
                                 ->method('reverseTransform')
                                 ->with($this->identicalTo('bar'))
                                 ->will($this->returnValue('baz'));

        $chain = new ValueTransformerChain(array($transformer1, $transformer2));

        $this->assertEquals('baz', $chain->reverseTransform('foo', null));
    }
}
