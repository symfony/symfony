<?php

namespace Symfony\Component\Form\Tests;

use Symfony\Component\Form\CallbackTransformer;

class CallbackTransformerTest extends \PHPUnit_Framework_TestCase
{
    public function testTransform()
    {
        $transformer = new CallbackTransformer(
            function($value) { return $value.' has been transformed'; },
            function($value) { return $value.' has reversely been transformed'; }
        );

        $this->assertEquals('foo has been transformed', $transformer->transform('foo'));
        $this->assertEquals('bar has reversely been transformed', $transformer->reverseTransform('bar'));
    }

    /**
     * @dataProvider invalidCallbacksProvider
     *
     * @expectedException \InvalidArgumentException
     */
    public function testConstructorWithInvalidCallbacks($transformCallback, $reverseTransformCallback)
    {
        new CallbackTransformer($transformCallback, $reverseTransformCallback);
    }

    public function invalidCallbacksProvider()
    {
        return array(
            array( null, function(){} ),
            array( function(){}, null ),
        );
    }
}
