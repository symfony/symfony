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

use Symfony\Component\Form\Extension\Core\DataTransformer\BooleanToStringTransformer;

class BooleanToStringTransformerTest extends \PHPUnit_Framework_TestCase
{
    protected $transformer;

    protected function setUp()
    {
        $this->transformer = new BooleanToStringTransformer();
    }

    protected function tearDown()
    {
        $this->transformer = null;
    }

    public function testTransform()
    {
        $this->assertEquals('1', $this->transformer->transform(true));
        $this->assertEquals('', $this->transformer->transform(false));
        $this->assertSame('', $this->transformer->transform(null));
    }

    public function testTransformExpectsBoolean()
    {
        $this->setExpectedException('Symfony\Component\Form\Exception\UnexpectedTypeException');

        $this->transformer->transform('1');
    }

    public function testReverseTransformExpectsString()
    {
        $this->setExpectedException('Symfony\Component\Form\Exception\UnexpectedTypeException');

        $this->transformer->reverseTransform(1);
    }

    public function testReverseTransform()
    {
        $this->assertTrue($this->transformer->reverseTransform('1'));
        $this->assertTrue($this->transformer->reverseTransform('0'));
        $this->assertFalse($this->transformer->reverseTransform(''));
        $this->assertFalse($this->transformer->reverseTransform(null));
    }
}
