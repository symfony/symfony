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

require_once __DIR__ . '/../LocalizedTestCase.php';

use Symfony\Component\Form\ValueTransformer\NumberToLocalizedStringTransformer;
use Symfony\Tests\Component\Form\LocalizedTestCase;

class NumberToLocalizedStringTransformerTest extends LocalizedTestCase
{
    protected function setUp()
    {
        parent::setUp();

        \Locale::setDefault('de_AT');
    }

    public function testTransform()
    {
        $transformer = new NumberToLocalizedStringTransformer();

        $this->assertEquals('1', $transformer->transform(1));
        $this->assertEquals('1,5', $transformer->transform(1.5));
        $this->assertEquals('1234,5', $transformer->transform(1234.5));
        $this->assertEquals('12345,912', $transformer->transform(12345.9123));
    }

    public function testTransform_empty()
    {
        $transformer = new NumberToLocalizedStringTransformer();

        $this->assertSame('', $transformer->transform(null));
    }

    public function testTransformWithGrouping()
    {
        $transformer = new NumberToLocalizedStringTransformer(array(
            'grouping' => true,
        ));

        $this->assertEquals('1.234,5', $transformer->transform(1234.5));
        $this->assertEquals('12.345,912', $transformer->transform(12345.9123));
    }

    public function testTransformWithPrecision()
    {
        $transformer = new NumberToLocalizedStringTransformer(array(
            'precision' => 2,
        ));

        $this->assertEquals('1234,50', $transformer->transform(1234.5));
        $this->assertEquals('678,92', $transformer->transform(678.916));
    }

    public function testTransformWithRoundingMode()
    {
        $transformer = new NumberToLocalizedStringTransformer(array(
            'rounding-mode' => NumberToLocalizedStringTransformer::ROUND_DOWN,
        ));
        $this->assertEquals('1234,547', $transformer->transform(1234.547), '->transform() only applies rounding mode if precision set');

        $transformer = new NumberToLocalizedStringTransformer(array(
            'rounding-mode' => NumberToLocalizedStringTransformer::ROUND_DOWN,
            'precision' => 2,
        ));
        $this->assertEquals('1234,54', $transformer->transform(1234.547), '->transform() rounding-mode works');

    }

    public function testReverseTransform()
    {
        $transformer = new NumberToLocalizedStringTransformer();

        $this->assertEquals(1, $transformer->reverseTransform('1', null));
        $this->assertEquals(1.5, $transformer->reverseTransform('1,5', null));
        $this->assertEquals(1234.5, $transformer->reverseTransform('1234,5', null));
        $this->assertEquals(12345.912, $transformer->reverseTransform('12345,912', null));
    }

    public function testReverseTransform_empty()
    {
        $transformer = new NumberToLocalizedStringTransformer();

        $this->assertSame(null, $transformer->reverseTransform('', null));
    }

    public function testReverseTransformWithGrouping()
    {
        $transformer = new NumberToLocalizedStringTransformer(array(
            'grouping' => true,
        ));

        $this->assertEquals(1234.5, $transformer->reverseTransform('1.234,5', null));
        $this->assertEquals(12345.912, $transformer->reverseTransform('12.345,912', null));
        $this->assertEquals(1234.5, $transformer->reverseTransform('1234,5', null));
        $this->assertEquals(12345.912, $transformer->reverseTransform('12345,912', null));
    }

    public function testTransformExpectsNumeric()
    {
        $transformer = new NumberToLocalizedStringTransformer();

        $this->setExpectedException('Symfony\Component\Form\Exception\UnexpectedTypeException');

        $transformer->transform('foo');
    }

    public function testReverseTransformExpectsString()
    {
        $transformer = new NumberToLocalizedStringTransformer();

        $this->setExpectedException('Symfony\Component\Form\Exception\UnexpectedTypeException');

        $transformer->reverseTransform(1, null);
    }
}
