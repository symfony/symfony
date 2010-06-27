<?php

namespace Symfony\Tests\Components\Form\ValueTransformer;

use Symfony\Components\Form\ValueTransformer\ValueTransformerChain;


class ValueTransformerChainTest extends \PHPUnit_Framework_TestCase
{
    public function testTransform()
    {
        $transformer1 = $this->getMock('Symfony\Components\Form\ValueTransformer\ValueTransformerInterface');
        $transformer1->expects($this->once())
                                 ->method('transform')
                                 ->with($this->identicalTo('foo'))
                                 ->will($this->returnValue('bar'));
        $transformer2 = $this->getMock('Symfony\Components\Form\ValueTransformer\ValueTransformerInterface');
        $transformer2->expects($this->once())
                                 ->method('transform')
                                 ->with($this->identicalTo('bar'))
                                 ->will($this->returnValue('baz'));

        $chain = new ValueTransformerChain(array($transformer1, $transformer2));

        $this->assertEquals('baz', $chain->transform('foo'));
    }

    public function testReverseTransform()
    {
        $transformer2 = $this->getMock('Symfony\Components\Form\ValueTransformer\ValueTransformerInterface');
        $transformer2->expects($this->once())
                                 ->method('reverseTransform')
                                 ->with($this->identicalTo('foo'))
                                 ->will($this->returnValue('bar'));
        $transformer1 = $this->getMock('Symfony\Components\Form\ValueTransformer\ValueTransformerInterface');
        $transformer1->expects($this->once())
                                 ->method('reverseTransform')
                                 ->with($this->identicalTo('bar'))
                                 ->will($this->returnValue('baz'));

        $chain = new ValueTransformerChain(array($transformer1, $transformer2));

        $this->assertEquals('baz', $chain->reverseTransform('foo'));
    }

    public function testSetLocale()
    {
        $transformer1 = $this->getMock('Symfony\Components\Form\ValueTransformer\ValueTransformerInterface');
        $transformer1->expects($this->once())
                                 ->method('setLocale')
                                 ->with($this->identicalTo('de_DE'));
        $transformer2 = $this->getMock('Symfony\Components\Form\ValueTransformer\ValueTransformerInterface');
        $transformer2->expects($this->once())
                                 ->method('setLocale')
                                 ->with($this->identicalTo('de_DE'));

        $chain = new ValueTransformerChain(array($transformer1, $transformer2));

        $chain->setLocale('de_DE');
    }
}
