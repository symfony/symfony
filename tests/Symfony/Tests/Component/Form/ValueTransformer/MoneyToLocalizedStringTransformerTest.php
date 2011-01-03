<?php

namespace Symfony\Tests\Component\Form\ValueTransformer;

require_once __DIR__ . '/../LocalizedTestCase.php';

use Symfony\Component\Form\ValueTransformer\MoneyToLocalizedStringTransformer;
use Symfony\Tests\Component\Form\LocalizedTestCase;


class MoneyToLocalizedStringTransformerTest extends LocalizedTestCase
{
    public function testTransform()
    {
        $transformer = new MoneyToLocalizedStringTransformer(array(
            'divisor' => 100,
        ));
        $transformer->setLocale('de_AT');

        $this->assertEquals('1,23', $transformer->transform(123));
    }

    public function testTransformExpectsNumeric()
    {
        $transformer = new MoneyToLocalizedStringTransformer(array(
            'divisor' => 100,
        ));

        $this->setExpectedException('Symfony\Component\Form\Exception\UnexpectedTypeException');

        $transformer->transform('abcd');
    }

    public function testTransform_empty()
    {
        $transformer = new MoneyToLocalizedStringTransformer();

        $this->assertSame('', $transformer->transform(null));
    }

    public function testReverseTransform()
    {
        $transformer = new MoneyToLocalizedStringTransformer(array(
            'divisor' => 100,
        ));
        $transformer->setLocale('de_AT');

        $this->assertEquals(123, $transformer->reverseTransform('1,23', null));
    }

    public function testReverseTransformExpectsString()
    {
        $transformer = new MoneyToLocalizedStringTransformer(array(
            'divisor' => 100,
        ));

        $this->setExpectedException('Symfony\Component\Form\Exception\UnexpectedTypeException');

        $transformer->reverseTransform(12345, null);
    }

    public function testReverseTransform_empty()
    {
        $transformer = new MoneyToLocalizedStringTransformer();

        $this->assertSame(null, $transformer->reverseTransform('', null));
    }
}
