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

use Symfony\Component\Form\Extension\Core\DataTransformer\NumberToLocalizedStringTransformer;
use Symfony\Component\Intl\Util\IntlTestHelper;

class NumberToLocalizedStringTransformerTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        parent::setUp();

        // Since we test against "de_AT", we need the full implementation
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault('de_AT');
    }

    public function provideTransformations()
    {
        return array(
            array(null, '', 'de_AT'),
            array(1, '1', 'de_AT'),
            array(1.5, '1,5', 'de_AT'),
            array(1234.5, '1234,5', 'de_AT'),
            array(12345.912, '12345,912', 'de_AT'),
            array(1234.5, '1234,5', 'ru'),
            array(1234.5, '1234,5', 'fi'),
        );
    }

    /**
     * @dataProvider provideTransformations
     */
    public function testTransform($from, $to, $locale)
    {
        \Locale::setDefault($locale);

        $transformer = new NumberToLocalizedStringTransformer();

        $this->assertSame($to, $transformer->transform($from));
    }

    public function provideTransformationsWithGrouping()
    {
        return array(
            array(1234.5, '1.234,5', 'de_AT'),
            array(12345.912, '12.345,912', 'de_AT'),
            array(1234.5, '1 234,5', 'fr'),
            array(1234.5, '1 234,5', 'ru'),
            array(1234.5, '1 234,5', 'fi'),
        );
    }

    /**
     * @dataProvider provideTransformationsWithGrouping
     */
    public function testTransformWithGrouping($from, $to, $locale)
    {
        \Locale::setDefault($locale);

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $this->assertSame($to, $transformer->transform($from));
    }

    public function testTransformWithPrecision()
    {
        $transformer = new NumberToLocalizedStringTransformer(2);

        $this->assertEquals('1234,50', $transformer->transform(1234.5));
        $this->assertEquals('678,92', $transformer->transform(678.916));
    }

    public function transformWithRoundingProvider()
    {
        return array(
            // towards positive infinity (1.6 -> 2, -1.6 -> -1)
            array(0, 1234.5, '1235', NumberToLocalizedStringTransformer::ROUND_CEILING),
            array(0, 1234.4, '1235', NumberToLocalizedStringTransformer::ROUND_CEILING),
            array(0, -1234.5, '-1234', NumberToLocalizedStringTransformer::ROUND_CEILING),
            array(0, -1234.4, '-1234', NumberToLocalizedStringTransformer::ROUND_CEILING),
            array(1, 123.45, '123,5', NumberToLocalizedStringTransformer::ROUND_CEILING),
            array(1, 123.44, '123,5', NumberToLocalizedStringTransformer::ROUND_CEILING),
            array(1, -123.45, '-123,4', NumberToLocalizedStringTransformer::ROUND_CEILING),
            array(1, -123.44, '-123,4', NumberToLocalizedStringTransformer::ROUND_CEILING),
            // towards negative infinity (1.6 -> 1, -1.6 -> -2)
            array(0, 1234.5, '1234', NumberToLocalizedStringTransformer::ROUND_FLOOR),
            array(0, 1234.4, '1234', NumberToLocalizedStringTransformer::ROUND_FLOOR),
            array(0, -1234.5, '-1235', NumberToLocalizedStringTransformer::ROUND_FLOOR),
            array(0, -1234.4, '-1235', NumberToLocalizedStringTransformer::ROUND_FLOOR),
            array(1, 123.45, '123,4', NumberToLocalizedStringTransformer::ROUND_FLOOR),
            array(1, 123.44, '123,4', NumberToLocalizedStringTransformer::ROUND_FLOOR),
            array(1, -123.45, '-123,5', NumberToLocalizedStringTransformer::ROUND_FLOOR),
            array(1, -123.44, '-123,5', NumberToLocalizedStringTransformer::ROUND_FLOOR),
            // away from zero (1.6 -> 2, -1.6 -> 2)
            array(0, 1234.5, '1235', NumberToLocalizedStringTransformer::ROUND_UP),
            array(0, 1234.4, '1235', NumberToLocalizedStringTransformer::ROUND_UP),
            array(0, -1234.5, '-1235', NumberToLocalizedStringTransformer::ROUND_UP),
            array(0, -1234.4, '-1235', NumberToLocalizedStringTransformer::ROUND_UP),
            array(1, 123.45, '123,5', NumberToLocalizedStringTransformer::ROUND_UP),
            array(1, 123.44, '123,5', NumberToLocalizedStringTransformer::ROUND_UP),
            array(1, -123.45, '-123,5', NumberToLocalizedStringTransformer::ROUND_UP),
            array(1, -123.44, '-123,5', NumberToLocalizedStringTransformer::ROUND_UP),
            // towards zero (1.6 -> 1, -1.6 -> -1)
            array(0, 1234.5, '1234', NumberToLocalizedStringTransformer::ROUND_DOWN),
            array(0, 1234.4, '1234', NumberToLocalizedStringTransformer::ROUND_DOWN),
            array(0, -1234.5, '-1234', NumberToLocalizedStringTransformer::ROUND_DOWN),
            array(0, -1234.4, '-1234', NumberToLocalizedStringTransformer::ROUND_DOWN),
            array(1, 123.45, '123,4', NumberToLocalizedStringTransformer::ROUND_DOWN),
            array(1, 123.44, '123,4', NumberToLocalizedStringTransformer::ROUND_DOWN),
            array(1, -123.45, '-123,4', NumberToLocalizedStringTransformer::ROUND_DOWN),
            array(1, -123.44, '-123,4', NumberToLocalizedStringTransformer::ROUND_DOWN),
            // round halves (.5) to the next even number
            array(0, 1234.6, '1235', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(0, 1234.5, '1234', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(0, 1234.4, '1234', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(0, 1233.5, '1234', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(0, 1232.5, '1232', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(0, -1234.6, '-1235', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(0, -1234.5, '-1234', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(0, -1234.4, '-1234', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(0, -1233.5, '-1234', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(0, -1232.5, '-1232', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(1, 123.46, '123,5', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(1, 123.45, '123,4', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(1, 123.44, '123,4', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(1, 123.35, '123,4', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(1, 123.25, '123,2', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(1, -123.46, '-123,5', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(1, -123.45, '-123,4', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(1, -123.44, '-123,4', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(1, -123.35, '-123,4', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(1, -123.25, '-123,2', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            // round halves (.5) away from zero
            array(0, 1234.6, '1235', NumberToLocalizedStringTransformer::ROUND_HALF_UP),
            array(0, 1234.5, '1235', NumberToLocalizedStringTransformer::ROUND_HALF_UP),
            array(0, 1234.4, '1234', NumberToLocalizedStringTransformer::ROUND_HALF_UP),
            array(0, -1234.6, '-1235', NumberToLocalizedStringTransformer::ROUND_HALF_UP),
            array(0, -1234.5, '-1235', NumberToLocalizedStringTransformer::ROUND_HALF_UP),
            array(0, -1234.4, '-1234', NumberToLocalizedStringTransformer::ROUND_HALF_UP),
            array(1, 123.46, '123,5', NumberToLocalizedStringTransformer::ROUND_HALF_UP),
            array(1, 123.45, '123,5', NumberToLocalizedStringTransformer::ROUND_HALF_UP),
            array(1, 123.44, '123,4', NumberToLocalizedStringTransformer::ROUND_HALF_UP),
            array(1, -123.46, '-123,5', NumberToLocalizedStringTransformer::ROUND_HALF_UP),
            array(1, -123.45, '-123,5', NumberToLocalizedStringTransformer::ROUND_HALF_UP),
            array(1, -123.44, '-123,4', NumberToLocalizedStringTransformer::ROUND_HALF_UP),
            // round halves (.5) towards zero
            array(0, 1234.6, '1235', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN),
            array(0, 1234.5, '1234', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN),
            array(0, 1234.4, '1234', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN),
            array(0, -1234.6, '-1235', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN),
            array(0, -1234.5, '-1234', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN),
            array(0, -1234.4, '-1234', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN),
            array(1, 123.46, '123,5', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN),
            array(1, 123.45, '123,4', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN),
            array(1, 123.44, '123,4', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN),
            array(1, -123.46, '-123,5', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN),
            array(1, -123.45, '-123,4', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN),
            array(1, -123.44, '-123,4', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN),
        );
    }

    /**
     * @dataProvider transformWithRoundingProvider
     */
    public function testTransformWithRounding($precision, $input, $output, $roundingMode)
    {
        $transformer = new NumberToLocalizedStringTransformer($precision, null, $roundingMode);

        $this->assertEquals($output, $transformer->transform($input));
    }

    public function testTransformDoesNotRoundIfNoPrecision()
    {
        $transformer = new NumberToLocalizedStringTransformer(null, null, NumberToLocalizedStringTransformer::ROUND_DOWN);

        $this->assertEquals('1234,547', $transformer->transform(1234.547));
    }

    /**
     * @dataProvider provideTransformations
     */
    public function testReverseTransform($to, $from, $locale)
    {
        \Locale::setDefault($locale);

        $transformer = new NumberToLocalizedStringTransformer();

        $this->assertEquals($to, $transformer->reverseTransform($from));
    }

    /**
     * @dataProvider provideTransformationsWithGrouping
     */
    public function testReverseTransformWithGrouping($to, $from, $locale)
    {
        \Locale::setDefault($locale);

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $this->assertEquals($to, $transformer->reverseTransform($from));
    }

    // https://github.com/symfony/symfony/issues/7609
    public function testReverseTransformWithGroupingAndFixedSpaces()
    {
        if (!function_exists('mb_detect_encoding')) {
            $this->markTestSkipped('The "mbstring" extension is required for this test.');
        }

        \Locale::setDefault('ru');

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $this->assertEquals(1234.5, $transformer->reverseTransform("1\xc2\xa0234,5"));
    }

    public function testReverseTransformWithGroupingButWithoutGroupSeparator()
    {
        $transformer = new NumberToLocalizedStringTransformer(null, true);

        // omit group separator
        $this->assertEquals(1234.5, $transformer->reverseTransform('1234,5'));
        $this->assertEquals(12345.912, $transformer->reverseTransform('12345,912'));
    }

    public function reverseTransformWithRoundingProvider()
    {
        return array(
            // towards positive infinity (1.6 -> 2, -1.6 -> -1)
            array(0, '1234,5', 1235, NumberToLocalizedStringTransformer::ROUND_CEILING),
            array(0, '1234,4', 1235, NumberToLocalizedStringTransformer::ROUND_CEILING),
            array(0, '-1234,5', -1234, NumberToLocalizedStringTransformer::ROUND_CEILING),
            array(0, '-1234,4', -1234, NumberToLocalizedStringTransformer::ROUND_CEILING),
            array(1, '123,45', 123.5, NumberToLocalizedStringTransformer::ROUND_CEILING),
            array(1, '123,44', 123.5, NumberToLocalizedStringTransformer::ROUND_CEILING),
            array(1, '-123,45', -123.4, NumberToLocalizedStringTransformer::ROUND_CEILING),
            array(1, '-123,44', -123.4, NumberToLocalizedStringTransformer::ROUND_CEILING),
            // towards negative infinity (1.6 -> 1, -1.6 -> -2)
            array(0, '1234,5', 1234, NumberToLocalizedStringTransformer::ROUND_FLOOR),
            array(0, '1234,4', 1234, NumberToLocalizedStringTransformer::ROUND_FLOOR),
            array(0, '-1234,5', -1235, NumberToLocalizedStringTransformer::ROUND_FLOOR),
            array(0, '-1234,4', -1235, NumberToLocalizedStringTransformer::ROUND_FLOOR),
            array(1, '123,45', 123.4, NumberToLocalizedStringTransformer::ROUND_FLOOR),
            array(1, '123,44', 123.4, NumberToLocalizedStringTransformer::ROUND_FLOOR),
            array(1, '-123,45', -123.5, NumberToLocalizedStringTransformer::ROUND_FLOOR),
            array(1, '-123,44', -123.5, NumberToLocalizedStringTransformer::ROUND_FLOOR),
            // away from zero (1.6 -> 2, -1.6 -> 2)
            array(0, '1234,5', 1235, NumberToLocalizedStringTransformer::ROUND_UP),
            array(0, '1234,4', 1235, NumberToLocalizedStringTransformer::ROUND_UP),
            array(0, '-1234,5', -1235, NumberToLocalizedStringTransformer::ROUND_UP),
            array(0, '-1234,4', -1235, NumberToLocalizedStringTransformer::ROUND_UP),
            array(1, '123,45', 123.5, NumberToLocalizedStringTransformer::ROUND_UP),
            array(1, '123,44', 123.5, NumberToLocalizedStringTransformer::ROUND_UP),
            array(1, '-123,45', -123.5, NumberToLocalizedStringTransformer::ROUND_UP),
            array(1, '-123,44', -123.5, NumberToLocalizedStringTransformer::ROUND_UP),
            // towards zero (1.6 -> 1, -1.6 -> -1)
            array(0, '1234,5', 1234, NumberToLocalizedStringTransformer::ROUND_DOWN),
            array(0, '1234,4', 1234, NumberToLocalizedStringTransformer::ROUND_DOWN),
            array(0, '-1234,5', -1234, NumberToLocalizedStringTransformer::ROUND_DOWN),
            array(0, '-1234,4', -1234, NumberToLocalizedStringTransformer::ROUND_DOWN),
            array(1, '123,45', 123.4, NumberToLocalizedStringTransformer::ROUND_DOWN),
            array(1, '123,44', 123.4, NumberToLocalizedStringTransformer::ROUND_DOWN),
            array(1, '-123,45', -123.4, NumberToLocalizedStringTransformer::ROUND_DOWN),
            array(1, '-123,44', -123.4, NumberToLocalizedStringTransformer::ROUND_DOWN),
            // round halves (.5) to the next even number
            array(0, '1234,6', 1235, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(0, '1234,5', 1234, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(0, '1234,4', 1234, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(0, '1233,5', 1234, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(0, '1232,5', 1232, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(0, '-1234,6', -1235, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(0, '-1234,5', -1234, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(0, '-1234,4', -1234, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(0, '-1233,5', -1234, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(0, '-1232,5', -1232, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(1, '123,46', 123.5, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(1, '123,45', 123.4, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(1, '123,44', 123.4, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(1, '123,35', 123.4, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(1, '123,25', 123.2, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(1, '-123,46', -123.5, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(1, '-123,45', -123.4, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(1, '-123,44', -123.4, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(1, '-123,35', -123.4, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(1, '-123,25', -123.2, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN),
            // round halves (.5) away from zero
            array(0, '1234,6', 1235, NumberToLocalizedStringTransformer::ROUND_HALF_UP),
            array(0, '1234,5', 1235, NumberToLocalizedStringTransformer::ROUND_HALF_UP),
            array(0, '1234,4', 1234, NumberToLocalizedStringTransformer::ROUND_HALF_UP),
            array(0, '-1234,6', -1235, NumberToLocalizedStringTransformer::ROUND_HALF_UP),
            array(0, '-1234,5', -1235, NumberToLocalizedStringTransformer::ROUND_HALF_UP),
            array(0, '-1234,4', -1234, NumberToLocalizedStringTransformer::ROUND_HALF_UP),
            array(1, '123,46', 123.5, NumberToLocalizedStringTransformer::ROUND_HALF_UP),
            array(1, '123,45', 123.5, NumberToLocalizedStringTransformer::ROUND_HALF_UP),
            array(1, '123,44', 123.4, NumberToLocalizedStringTransformer::ROUND_HALF_UP),
            array(1, '-123,46', -123.5, NumberToLocalizedStringTransformer::ROUND_HALF_UP),
            array(1, '-123,45', -123.5, NumberToLocalizedStringTransformer::ROUND_HALF_UP),
            array(1, '-123,44', -123.4, NumberToLocalizedStringTransformer::ROUND_HALF_UP),
            // round halves (.5) towards zero
            array(0, '1234,6', 1235, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN),
            array(0, '1234,5', 1234, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN),
            array(0, '1234,4', 1234, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN),
            array(0, '-1234,6', -1235, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN),
            array(0, '-1234,5', -1234, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN),
            array(0, '-1234,4', -1234, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN),
            array(1, '123,46', 123.5, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN),
            array(1, '123,45', 123.4, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN),
            array(1, '123,44', 123.4, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN),
            array(1, '-123,46', -123.5, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN),
            array(1, '-123,45', -123.4, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN),
            array(1, '-123,44', -123.4, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN),
        );
    }

    /**
     * @dataProvider reverseTransformWithRoundingProvider
     */
    public function testReverseTransformWithRounding($precision, $input, $output, $roundingMode)
    {
        $transformer = new NumberToLocalizedStringTransformer($precision, null, $roundingMode);

        $this->assertEquals($output, $transformer->reverseTransform($input));
    }

    public function testReverseTransformWithInt()
    {
        $transformer = new NumberToLocalizedStringTransformer();

        $this->assertEquals(10, $transformer->reverseTransform(10));
    }

    public function testReverseTransformWithRoundingFloat()
    {
        $transformer = new NumberToLocalizedStringTransformer(1, null, NumberToLocalizedStringTransformer::ROUND_DOWN);

        $this->assertEquals(10.3, $transformer->reverseTransform(10.35));
    }

    public function testReverseTransformDoesNotRoundIfNoPrecision()
    {
        $transformer = new NumberToLocalizedStringTransformer(null, null, NumberToLocalizedStringTransformer::ROUND_DOWN);

        $this->assertEquals(1234.547, $transformer->reverseTransform('1234,547'));
    }

    public function testDecimalSeparatorMayBeDotIfGroupingSeparatorIsNotDot()
    {
        \Locale::setDefault('fr');
        $transformer = new NumberToLocalizedStringTransformer(null, true);

        // completely valid format
        $this->assertEquals(1234.5, $transformer->reverseTransform('1 234,5'));
        // accept dots
        $this->assertEquals(1234.5, $transformer->reverseTransform('1 234.5'));
        // omit group separator
        $this->assertEquals(1234.5, $transformer->reverseTransform('1234,5'));
        $this->assertEquals(1234.5, $transformer->reverseTransform('1234.5'));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testDecimalSeparatorMayNotBeDotIfGroupingSeparatorIsDot()
    {
        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $transformer->reverseTransform('1.234.5');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testDecimalSeparatorMayNotBeDotIfGroupingSeparatorIsDotWithNoGroupSep()
    {
        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $transformer->reverseTransform('1234.5');
    }

    public function testDecimalSeparatorMayBeDotIfGroupingSeparatorIsDotButNoGroupingUsed()
    {
        \Locale::setDefault('fr');
        $transformer = new NumberToLocalizedStringTransformer();

        $this->assertEquals(1234.5, $transformer->reverseTransform('1234,5'));
        $this->assertEquals(1234.5, $transformer->reverseTransform('1234.5'));
    }

    public function testDecimalSeparatorMayBeCommaIfGroupingSeparatorIsNotComma()
    {
        \Locale::setDefault('bg');
        $transformer = new NumberToLocalizedStringTransformer(null, true);

        // completely valid format
        $this->assertEquals(1234.5, $transformer->reverseTransform('1 234.5'));
        // accept commas
        $this->assertEquals(1234.5, $transformer->reverseTransform('1 234,5'));
        // omit group separator
        $this->assertEquals(1234.5, $transformer->reverseTransform('1234.5'));
        $this->assertEquals(1234.5, $transformer->reverseTransform('1234,5'));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testDecimalSeparatorMayNotBeCommaIfGroupingSeparatorIsComma()
    {
        \Locale::setDefault('en');
        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $transformer->reverseTransform('1,234,5');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testDecimalSeparatorMayNotBeCommaIfGroupingSeparatorIsCommaWithNoGroupSep()
    {
        \Locale::setDefault('en');
        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $transformer->reverseTransform('1234,5');
    }

    public function testDecimalSeparatorMayBeCommaIfGroupingSeparatorIsCommaButNoGroupingUsed()
    {
        \Locale::setDefault('en');
        $transformer = new NumberToLocalizedStringTransformer();

        $this->assertEquals(1234.5, $transformer->reverseTransform('1234,5'));
        $this->assertEquals(1234.5, $transformer->reverseTransform('1234.5'));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testTransformExpectsNumeric()
    {
        $transformer = new NumberToLocalizedStringTransformer();

        $transformer->transform('foo');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformExpectsValidNumber()
    {
        $transformer = new NumberToLocalizedStringTransformer();

        $transformer->reverseTransform('foo');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     *
     * @link https://github.com/symfony/symfony/issues/3161
     */
    public function testReverseTransformDisallowsNaN()
    {
        $transformer = new NumberToLocalizedStringTransformer();

        $transformer->reverseTransform('NaN');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformDisallowsNaN2()
    {
        $transformer = new NumberToLocalizedStringTransformer();

        $transformer->reverseTransform('nan');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformDisallowsInfinity()
    {
        $transformer = new NumberToLocalizedStringTransformer();

        $transformer->reverseTransform('∞');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformDisallowsInfinity2()
    {
        $transformer = new NumberToLocalizedStringTransformer();

        $transformer->reverseTransform('∞,123');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformDisallowsNegativeInfinity()
    {
        $transformer = new NumberToLocalizedStringTransformer();

        $transformer->reverseTransform('-∞');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformDisallowsLeadingExtraCharacters()
    {
        $transformer = new NumberToLocalizedStringTransformer();

        $transformer->reverseTransform('foo123');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     * @expectedExceptionMessage The number contains unrecognized characters: "foo3"
     */
    public function testReverseTransformDisallowsCenteredExtraCharacters()
    {
        $transformer = new NumberToLocalizedStringTransformer();

        $transformer->reverseTransform('12foo3');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     * @expectedExceptionMessage The number contains unrecognized characters: "foo8"
     */
    public function testReverseTransformDisallowsCenteredExtraCharactersMultibyte()
    {
        if (!function_exists('mb_detect_encoding')) {
            $this->markTestSkipped('The "mbstring" extension is required for this test.');
        }

        \Locale::setDefault('ru');

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $transformer->reverseTransform("12\xc2\xa0345,67foo8");
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     * @expectedExceptionMessage The number contains unrecognized characters: "foo8"
     */
    public function testReverseTransformIgnoresTrailingSpacesInExceptionMessage()
    {
        if (!function_exists('mb_detect_encoding')) {
            $this->markTestSkipped('The "mbstring" extension is required for this test.');
        }

        \Locale::setDefault('ru');

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $transformer->reverseTransform("12\xc2\xa0345,67foo8  \xc2\xa0\t");
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     * @expectedExceptionMessage The number contains unrecognized characters: "foo"
     */
    public function testReverseTransformDisallowsTrailingExtraCharacters()
    {
        $transformer = new NumberToLocalizedStringTransformer();

        $transformer->reverseTransform('123foo');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     * @expectedExceptionMessage The number contains unrecognized characters: "foo"
     */
    public function testReverseTransformDisallowsTrailingExtraCharactersMultibyte()
    {
        if (!function_exists('mb_detect_encoding')) {
            $this->markTestSkipped('The "mbstring" extension is required for this test.');
        }

        \Locale::setDefault('ru');

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $transformer->reverseTransform("12\xc2\xa0345,678foo");
    }
}
