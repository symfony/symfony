<?php

namespace Symfony\Tests\Components\Form\ValueTransformer;

require_once __DIR__ . '/../LocalizedTestCase.php';

use Symfony\Components\Form\ValueTransformer\NumberToLocalizedStringTransformer;
use Symfony\Tests\Components\Form\LocalizedTestCase;


class NumberToLocalizedStringTransformerTest extends LocalizedTestCase
{
    public function testTransform()
    {
        $transformer = new NumberToLocalizedStringTransformer();
        $transformer->setLocale('de_AT');

        $this->assertEquals('1', $transformer->transform(1));
        $this->assertEquals('1,5', $transformer->transform(1.5));
        $this->assertEquals('1234,5', $transformer->transform(1234.5));
        $this->assertEquals('12345,912', $transformer->transform(12345.9123));
    }

    public function testTransformWithGrouping()
    {
        $transformer = new NumberToLocalizedStringTransformer(array(
            'grouping' => true,
        ));
        $transformer->setLocale('de_AT');

        $this->assertEquals('1.234,5', $transformer->transform(1234.5));
        $this->assertEquals('12.345,912', $transformer->transform(12345.9123));
    }

    public function testTransformWithPrecision()
    {
        $transformer = new NumberToLocalizedStringTransformer(array(
            'precision' => 2,
        ));
        $transformer->setLocale('de_AT');

        $this->assertEquals('1234,50', $transformer->transform(1234.5));
        $this->assertEquals('678,92', $transformer->transform(678.916));
    }

    public function testReverseTransform()
    {
        $transformer = new NumberToLocalizedStringTransformer();
        $transformer->setLocale('de_AT');

        $this->assertEquals(1, $transformer->reverseTransform('1'));
        $this->assertEquals(1.5, $transformer->reverseTransform('1,5'));
        $this->assertEquals(1234.5, $transformer->reverseTransform('1234,5'));
        $this->assertEquals(12345.912, $transformer->reverseTransform('12345,912'));
    }

    public function testReverseTransformWithGrouping()
    {
        $transformer = new NumberToLocalizedStringTransformer(array(
            'grouping' => true,
        ));
        $transformer->setLocale('de_AT');

        $this->assertEquals(1234.5, $transformer->reverseTransform('1.234,5'));
        $this->assertEquals(12345.912, $transformer->reverseTransform('12.345,912'));
        $this->assertEquals(1234.5, $transformer->reverseTransform('1234,5'));
        $this->assertEquals(12345.912, $transformer->reverseTransform('12345,912'));
    }

    public function testTransformExpectsNumeric()
    {
        $transformer = new NumberToLocalizedStringTransformer();

        $this->setExpectedException('\InvalidArgumentException');

        $transformer->transform('foo');
    }

    public function testReverseTransformExpectsString()
    {
        $transformer = new NumberToLocalizedStringTransformer();

        $this->setExpectedException('\InvalidArgumentException');

        $transformer->reverseTransform(1);
    }
}
