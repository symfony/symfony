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

use Symfony\Component\Form\Extension\Core\DataTransformer\PercentToLocalizedStringTransformer;

class PercentToLocalizedStringTransformerTest extends LocalizedTestCase
{
    protected function setUp()
    {
        parent::setUp();

        \Locale::setDefault('de_AT');
    }

    public function testTransform()
    {
        $transformer = new PercentToLocalizedStringTransformer();

        $this->assertEquals('10', $transformer->transform(0.1));
        $this->assertEquals('15', $transformer->transform(0.15));
        $this->assertEquals('12', $transformer->transform(0.1234));
        $this->assertEquals('200', $transformer->transform(2));
    }

    public function testTransformEmpty()
    {
        $transformer = new PercentToLocalizedStringTransformer();

        $this->assertEquals('', $transformer->transform(null));
    }

    public function testTransformWithInteger()
    {
        $transformer = new PercentToLocalizedStringTransformer(null, 'integer');

        $this->assertEquals('0', $transformer->transform(0.1));
        $this->assertEquals('1', $transformer->transform(1));
        $this->assertEquals('15', $transformer->transform(15));
        $this->assertEquals('16', $transformer->transform(15.9));
    }

    public function testTransformWithPrecision()
    {
        $transformer = new PercentToLocalizedStringTransformer(2);

        $this->assertEquals('12,34', $transformer->transform(0.1234));
    }

    public function testReverseTransform()
    {
        $transformer = new PercentToLocalizedStringTransformer();

        $this->assertEquals(0.1, $transformer->reverseTransform('10'));
        $this->assertEquals(0.15, $transformer->reverseTransform('15'));
        $this->assertEquals(0.12, $transformer->reverseTransform('12'));
        $this->assertEquals(2, $transformer->reverseTransform('200'));
    }

    public function testReverseTransformEmpty()
    {
        $transformer = new PercentToLocalizedStringTransformer();

        $this->assertNull($transformer->reverseTransform(''));
    }

    public function testReverseTransformWithInteger()
    {
        $transformer = new PercentToLocalizedStringTransformer(null, 'integer');

        $this->assertEquals(10, $transformer->reverseTransform('10'));
        $this->assertEquals(15, $transformer->reverseTransform('15'));
        $this->assertEquals(12, $transformer->reverseTransform('12'));
        $this->assertEquals(200, $transformer->reverseTransform('200'));
    }

    public function testReverseTransformWithPrecision()
    {
        $transformer = new PercentToLocalizedStringTransformer(2);

        $this->assertEquals(0.1234, $transformer->reverseTransform('12,34'));
    }

    public function testTransformExpectsNumeric()
    {
        $transformer = new PercentToLocalizedStringTransformer();

        $this->setExpectedException('Symfony\Component\Form\Exception\TransformationFailedException');

        $transformer->transform('foo');
    }

    public function testReverseTransformExpectsString()
    {
        $transformer = new PercentToLocalizedStringTransformer();

        $this->setExpectedException('Symfony\Component\Form\Exception\TransformationFailedException');

        $transformer->reverseTransform(1);
    }
}
