<?php

namespace Symfony\Tests\Component\Form\ValueTransformer;

use Symfony\Component\Form\ValueTransformer\BooleanToStringTransformer;

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

        $this->transformer->reverseTransform(1, null);
    }

    public function testReverseTransform()
    {
        $this->assertEquals(true, $this->transformer->reverseTransform('1', null));
        $this->assertEquals(true, $this->transformer->reverseTransform('0', null));
        $this->assertEquals(false, $this->transformer->reverseTransform('', null));
    }
}
