<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Extension\Core\DataTransformer;

use Symfony\Component\Form\Extension\Core\DataTransformer\ArrayToChoicesTransformer;

class ArrayToChoicesTransformerTest extends \PHPUnit_Framework_TestCase
{
    protected $transformer;

    protected function setUp()
    {
        $this->transformer = new ArrayToChoicesTransformer();
    }

    protected function tearDown()
    {
        $this->transformer = null;
    }

    public function testTransform()
    {
        $in = array(0, false, '');
        $out = array(0, 0, '');

        $this->assertSame($out, $this->transformer->transform($in));
    }

    public function testTransformNull()
    {
        $this->assertSame(array(), $this->transformer->transform(null));
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testTransformExpectsArray()
    {
        $this->transformer->transform('foobar');
    }

    public function testReverseTransform()
    {
        // values are expected to be valid choices and stay the same
        $in = array(0, 0, '');
        $out = array(0, 0, '');

        $this->assertSame($out, $this->transformer->transform($in));
    }

    public function testReverseTransformNull()
    {
        $this->assertSame(array(), $this->transformer->reverseTransform(null));
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testReverseTransformExpectsArray()
    {
        $this->transformer->reverseTransform('foobar');
    }
}
