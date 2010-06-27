<?php

namespace Symfony\Tests\Components\Form\ValueTransformer;

require_once __DIR__ . '/../LocalizedTestCase.php';

use Symfony\Components\Form\ValueTransformer\PercentToLocalizedStringTransformer;
use Symfony\Tests\Components\Form\LocalizedTestCase;


class PercentToLocalizedStringTransformerTest extends LocalizedTestCase
{
    public function testTransform()
    {
        $transformer = new PercentToLocalizedStringTransformer();
        $transformer->setLocale('de_AT');

        $this->assertEquals('10', $transformer->transform(0.1));
        $this->assertEquals('15', $transformer->transform(0.15));
        $this->assertEquals('12', $transformer->transform(0.1234));
        $this->assertEquals('200', $transformer->transform(2));
    }

    public function testTransformWithInteger()
    {
        $transformer = new PercentToLocalizedStringTransformer(array(
            'type' => 'integer',
        ));
        $transformer->setLocale('de_AT');

        $this->assertEquals('0', $transformer->transform(0.1));
        $this->assertEquals('1', $transformer->transform(1));
        $this->assertEquals('15', $transformer->transform(15));
        $this->assertEquals('16', $transformer->transform(15.9));
    }

    public function testTransformWithPrecision()
    {
        $transformer = new PercentToLocalizedStringTransformer(array(
            'precision' => 2,
        ));
        $transformer->setLocale('de_AT');

        $this->assertEquals('12,34', $transformer->transform(0.1234));
    }

    public function testReverseTransform()
    {
        $transformer = new PercentToLocalizedStringTransformer();
        $transformer->setLocale('de_AT');

        $this->assertEquals(0.1, $transformer->reverseTransform('10'));
        $this->assertEquals(0.15, $transformer->reverseTransform('15'));
        $this->assertEquals(0.12, $transformer->reverseTransform('12'));
        $this->assertEquals(2, $transformer->reverseTransform('200'));
    }

    public function testReverseTransformWithInteger()
    {
        $transformer = new PercentToLocalizedStringTransformer(array(
            'type' => 'integer',
        ));
        $transformer->setLocale('de_AT');

        $this->assertEquals(10, $transformer->reverseTransform('10'));
        $this->assertEquals(15, $transformer->reverseTransform('15'));
        $this->assertEquals(12, $transformer->reverseTransform('12'));
        $this->assertEquals(200, $transformer->reverseTransform('200'));
    }

    public function testReverseTransformWithPrecision()
    {
        $transformer = new PercentToLocalizedStringTransformer(array(
            'precision' => 2,
        ));
        $transformer->setLocale('de_AT');

        $this->assertEquals(0.1234, $transformer->reverseTransform('12,34'));
    }

    public function testTransformExpectsNumeric()
    {
        $transformer = new PercentToLocalizedStringTransformer();

        $this->setExpectedException('\InvalidArgumentException');

        $transformer->transform('foo');
    }

    public function testReverseTransformExpectsString()
    {
        $transformer = new PercentToLocalizedStringTransformer();

        $this->setExpectedException('\InvalidArgumentException');

        $transformer->reverseTransform(1);
    }
}
