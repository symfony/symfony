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

use Symfony\Component\Form\ValueTransformer\BooleanToStringTransformer;

class BooleanToStringTransformerTest extends \PHPUnit_Framework_TestCase
{
    protected $transformer;

    protected function setUp()
    {
        $this->transformer = new BooleanToStringTransformer();
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

        $this->transformer->reverseTransform(1, null);
    }

    public function testReverseTransform()
    {
        $this->assertEquals(true, $this->transformer->reverseTransform('1', null));
        $this->assertEquals(true, $this->transformer->reverseTransform('0', null));
        $this->assertEquals(false, $this->transformer->reverseTransform('', null));
    }
}
