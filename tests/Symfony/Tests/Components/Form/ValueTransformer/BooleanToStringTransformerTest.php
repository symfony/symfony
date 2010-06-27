<?php

namespace Symfony\Tests\Components\Form\ValueTransformer;

use Symfony\Components\Form\ValueTransformer\BooleanToStringTransformer;

class BooleanToStringTransformerTest extends \PHPUnit_Framework_TestCase
{
    protected $transformer;

    public function setUp()
    {
        $this->transformer = new BooleanToStringTransformer();
    }

    public function testTransform()
    {
        $this->assertEquals('1', $this->transformer->transform(true));
        $this->assertEquals('', $this->transformer->transform(false));
    }

    public function testTransformExpectsBoolean()
    {
        $this->setExpectedException('\InvalidArgumentException');

        $this->transformer->transform('1');
    }

    public function testReverseTransformExpectsString()
    {
        $this->setExpectedException('\InvalidArgumentException');

        $this->transformer->reverseTransform(1);
    }

    public function testReverseTransform()
    {
        $this->assertEquals(true, $this->transformer->reverseTransform('1'));
        $this->assertEquals(true, $this->transformer->reverseTransform('0'));
        $this->assertEquals(false, $this->transformer->reverseTransform(''));
    }
}
