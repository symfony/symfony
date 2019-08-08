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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\DataTransformer\NumberToLocalizedStringTransformer;
use Symfony\Component\Intl\Util\IntlTestHelper;

class NumberToLocalizedStringTransformerTest extends TestCase
{
    private $defaultLocale;

    protected function setUp(): void
    {
        $this->defaultLocale = \Locale::getDefault();
        \Locale::setDefault('en');
    }

    protected function tearDown(): void
    {
        \Locale::setDefault($this->defaultLocale);
    }

    public function provideTransformations()
    {
        return [
            [null, '', 'de_AT'],
            [1, '1', 'de_AT'],
            [1.5, '1,5', 'de_AT'],
            [1234.5, '1234,5', 'de_AT'],
            [12345.912, '12345,912', 'de_AT'],
            [1234.5, '1234,5', 'ru'],
            [1234.5, '1234,5', 'fi'],
        ];
    }

    /**
     * @dataProvider provideTransformations
     */
    public function testTransform($from, $to, $locale)
    {
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault($locale);

        $transformer = new NumberToLocalizedStringTransformer();

        $this->assertSame($to, $transformer->transform($from));
    }

    public function provideTransformationsWithGrouping()
    {
        return [
            [1234.5, '1.234,5', 'de_DE'],
            [12345.912, '12.345,912', 'de_DE'],
            [1234.5, '1 234,5', 'fr'],
            [1234.5, '1 234,5', 'ru'],
            [1234.5, '1 234,5', 'fi'],
        ];
    }

    /**
     * @dataProvider provideTransformationsWithGrouping
     */
    public function testTransformWithGrouping($from, $to, $locale)
    {
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault($locale);

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $this->assertSame($to, $transformer->transform($from));
    }

    public function testTransformWithScale()
    {
        // Since we test against "de_AT", we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_AT');

        $transformer = new NumberToLocalizedStringTransformer(2);

        $this->assertEquals('1234,50', $transformer->transform(1234.5));
        $this->assertEquals('678,92', $transformer->transform(678.916));
    }

    public function transformWithRoundingProvider()
    {
        return [
            // towards positive infinity (1.6 -> 2, -1.6 -> -1)
            [0, 1234.5, '1235', NumberToLocalizedStringTransformer::ROUND_CEILING],
            [0, 1234.4, '1235', NumberToLocalizedStringTransformer::ROUND_CEILING],
            [0, -1234.5, '-1234', NumberToLocalizedStringTransformer::ROUND_CEILING],
            [0, -1234.4, '-1234', NumberToLocalizedStringTransformer::ROUND_CEILING],
            [1, 123.45, '123,5', NumberToLocalizedStringTransformer::ROUND_CEILING],
            [1, 123.44, '123,5', NumberToLocalizedStringTransformer::ROUND_CEILING],
            [1, -123.45, '-123,4', NumberToLocalizedStringTransformer::ROUND_CEILING],
            [1, -123.44, '-123,4', NumberToLocalizedStringTransformer::ROUND_CEILING],
            // towards negative infinity (1.6 -> 1, -1.6 -> -2)
            [0, 1234.5, '1234', NumberToLocalizedStringTransformer::ROUND_FLOOR],
            [0, 1234.4, '1234', NumberToLocalizedStringTransformer::ROUND_FLOOR],
            [0, -1234.5, '-1235', NumberToLocalizedStringTransformer::ROUND_FLOOR],
            [0, -1234.4, '-1235', NumberToLocalizedStringTransformer::ROUND_FLOOR],
            [1, 123.45, '123,4', NumberToLocalizedStringTransformer::ROUND_FLOOR],
            [1, 123.44, '123,4', NumberToLocalizedStringTransformer::ROUND_FLOOR],
            [1, -123.45, '-123,5', NumberToLocalizedStringTransformer::ROUND_FLOOR],
            [1, -123.44, '-123,5', NumberToLocalizedStringTransformer::ROUND_FLOOR],
            // away from zero (1.6 -> 2, -1.6 -> 2)
            [0, 1234.5, '1235', NumberToLocalizedStringTransformer::ROUND_UP],
            [0, 1234.4, '1235', NumberToLocalizedStringTransformer::ROUND_UP],
            [0, -1234.5, '-1235', NumberToLocalizedStringTransformer::ROUND_UP],
            [0, -1234.4, '-1235', NumberToLocalizedStringTransformer::ROUND_UP],
            [1, 123.45, '123,5', NumberToLocalizedStringTransformer::ROUND_UP],
            [1, 123.44, '123,5', NumberToLocalizedStringTransformer::ROUND_UP],
            [1, -123.45, '-123,5', NumberToLocalizedStringTransformer::ROUND_UP],
            [1, -123.44, '-123,5', NumberToLocalizedStringTransformer::ROUND_UP],
            // towards zero (1.6 -> 1, -1.6 -> -1)
            [0, 1234.5, '1234', NumberToLocalizedStringTransformer::ROUND_DOWN],
            [0, 1234.4, '1234', NumberToLocalizedStringTransformer::ROUND_DOWN],
            [0, -1234.5, '-1234', NumberToLocalizedStringTransformer::ROUND_DOWN],
            [0, -1234.4, '-1234', NumberToLocalizedStringTransformer::ROUND_DOWN],
            [1, 123.45, '123,4', NumberToLocalizedStringTransformer::ROUND_DOWN],
            [1, 123.44, '123,4', NumberToLocalizedStringTransformer::ROUND_DOWN],
            [1, -123.45, '-123,4', NumberToLocalizedStringTransformer::ROUND_DOWN],
            [1, -123.44, '-123,4', NumberToLocalizedStringTransformer::ROUND_DOWN],
            // round halves (.5) to the next even number
            [0, 1234.6, '1235', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, 1234.5, '1234', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, 1234.4, '1234', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, 1233.5, '1234', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, 1232.5, '1232', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, -1234.6, '-1235', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, -1234.5, '-1234', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, -1234.4, '-1234', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, -1233.5, '-1234', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, -1232.5, '-1232', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, 123.46, '123,5', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, 123.45, '123,4', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, 123.44, '123,4', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, 123.35, '123,4', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, 123.25, '123,2', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, -123.46, '-123,5', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, -123.45, '-123,4', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, -123.44, '-123,4', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, -123.35, '-123,4', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, -123.25, '-123,2', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            // round halves (.5) away from zero
            [0, 1234.6, '1235', NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [0, 1234.5, '1235', NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [0, 1234.4, '1234', NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [0, -1234.6, '-1235', NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [0, -1234.5, '-1235', NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [0, -1234.4, '-1234', NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [1, 123.46, '123,5', NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [1, 123.45, '123,5', NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [1, 123.44, '123,4', NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [1, -123.46, '-123,5', NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [1, -123.45, '-123,5', NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [1, -123.44, '-123,4', NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            // round halves (.5) towards zero
            [0, 1234.6, '1235', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [0, 1234.5, '1234', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [0, 1234.4, '1234', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [0, -1234.6, '-1235', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [0, -1234.5, '-1234', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [0, -1234.4, '-1234', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [1, 123.46, '123,5', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [1, 123.45, '123,4', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [1, 123.44, '123,4', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [1, -123.46, '-123,5', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [1, -123.45, '-123,4', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [1, -123.44, '-123,4', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
        ];
    }

    /**
     * @dataProvider transformWithRoundingProvider
     */
    public function testTransformWithRounding($scale, $input, $output, $roundingMode)
    {
        // Since we test against "de_AT", we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_AT');

        $transformer = new NumberToLocalizedStringTransformer($scale, null, $roundingMode);

        $this->assertEquals($output, $transformer->transform($input));
    }

    public function testTransformDoesNotRoundIfNoScale()
    {
        // Since we test against "de_AT", we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_AT');

        $transformer = new NumberToLocalizedStringTransformer(null, null, NumberToLocalizedStringTransformer::ROUND_DOWN);

        $this->assertEquals('1234,547', $transformer->transform(1234.547));
    }

    /**
     * @dataProvider provideTransformations
     */
    public function testReverseTransform($to, $from, $locale)
    {
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault($locale);

        $transformer = new NumberToLocalizedStringTransformer();

        $this->assertEquals($to, $transformer->reverseTransform($from));
    }

    /**
     * @dataProvider provideTransformationsWithGrouping
     */
    public function testReverseTransformWithGrouping($to, $from, $locale)
    {
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this, '4.8.1.1');

        \Locale::setDefault($locale);

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $this->assertEquals($to, $transformer->reverseTransform($from));
    }

    /**
     * @see https://github.com/symfony/symfony/issues/7609
     */
    public function testReverseTransformWithGroupingAndFixedSpaces()
    {
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('ru');

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $this->assertEquals(1234.5, $transformer->reverseTransform("1\xc2\xa0234,5"));
    }

    public function testReverseTransformWithGroupingButWithoutGroupSeparator()
    {
        // Since we test against "de_AT", we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_AT');

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        // omit group separator
        $this->assertEquals(1234.5, $transformer->reverseTransform('1234,5'));
        $this->assertEquals(12345.912, $transformer->reverseTransform('12345,912'));
    }

    public function reverseTransformWithRoundingProvider()
    {
        return [
            // towards positive infinity (1.6 -> 2, -1.6 -> -1)
            [0, '1234,5', 1235, NumberToLocalizedStringTransformer::ROUND_CEILING],
            [0, '1234,4', 1235, NumberToLocalizedStringTransformer::ROUND_CEILING],
            [0, '-1234,5', -1234, NumberToLocalizedStringTransformer::ROUND_CEILING],
            [0, '-1234,4', -1234, NumberToLocalizedStringTransformer::ROUND_CEILING],
            [1, '123,45', 123.5, NumberToLocalizedStringTransformer::ROUND_CEILING],
            [1, '123,44', 123.5, NumberToLocalizedStringTransformer::ROUND_CEILING],
            [1, '-123,45', -123.4, NumberToLocalizedStringTransformer::ROUND_CEILING],
            [1, '-123,44', -123.4, NumberToLocalizedStringTransformer::ROUND_CEILING],
            // towards negative infinity (1.6 -> 1, -1.6 -> -2)
            [0, '1234,5', 1234, NumberToLocalizedStringTransformer::ROUND_FLOOR],
            [0, '1234,4', 1234, NumberToLocalizedStringTransformer::ROUND_FLOOR],
            [0, '-1234,5', -1235, NumberToLocalizedStringTransformer::ROUND_FLOOR],
            [0, '-1234,4', -1235, NumberToLocalizedStringTransformer::ROUND_FLOOR],
            [1, '123,45', 123.4, NumberToLocalizedStringTransformer::ROUND_FLOOR],
            [1, '123,44', 123.4, NumberToLocalizedStringTransformer::ROUND_FLOOR],
            [1, '-123,45', -123.5, NumberToLocalizedStringTransformer::ROUND_FLOOR],
            [1, '-123,44', -123.5, NumberToLocalizedStringTransformer::ROUND_FLOOR],
            // away from zero (1.6 -> 2, -1.6 -> 2)
            [0, '1234,5', 1235, NumberToLocalizedStringTransformer::ROUND_UP],
            [0, '1234,4', 1235, NumberToLocalizedStringTransformer::ROUND_UP],
            [0, '-1234,5', -1235, NumberToLocalizedStringTransformer::ROUND_UP],
            [0, '-1234,4', -1235, NumberToLocalizedStringTransformer::ROUND_UP],
            [1, '123,45', 123.5, NumberToLocalizedStringTransformer::ROUND_UP],
            [1, '123,44', 123.5, NumberToLocalizedStringTransformer::ROUND_UP],
            [1, '-123,45', -123.5, NumberToLocalizedStringTransformer::ROUND_UP],
            [1, '-123,44', -123.5, NumberToLocalizedStringTransformer::ROUND_UP],
            // towards zero (1.6 -> 1, -1.6 -> -1)
            [0, '1234,5', 1234, NumberToLocalizedStringTransformer::ROUND_DOWN],
            [0, '1234,4', 1234, NumberToLocalizedStringTransformer::ROUND_DOWN],
            [0, '-1234,5', -1234, NumberToLocalizedStringTransformer::ROUND_DOWN],
            [0, '-1234,4', -1234, NumberToLocalizedStringTransformer::ROUND_DOWN],
            [1, '123,45', 123.4, NumberToLocalizedStringTransformer::ROUND_DOWN],
            [1, '123,44', 123.4, NumberToLocalizedStringTransformer::ROUND_DOWN],
            [1, '-123,45', -123.4, NumberToLocalizedStringTransformer::ROUND_DOWN],
            [1, '-123,44', -123.4, NumberToLocalizedStringTransformer::ROUND_DOWN],
            [2, '37.37', 37.37, NumberToLocalizedStringTransformer::ROUND_DOWN],
            [2, '2.01', 2.01, NumberToLocalizedStringTransformer::ROUND_DOWN],
            // round halves (.5) to the next even number
            [0, '1234,6', 1235, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, '1234,5', 1234, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, '1234,4', 1234, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, '1233,5', 1234, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, '1232,5', 1232, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, '-1234,6', -1235, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, '-1234,5', -1234, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, '-1234,4', -1234, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, '-1233,5', -1234, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, '-1232,5', -1232, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, '123,46', 123.5, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, '123,45', 123.4, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, '123,44', 123.4, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, '123,35', 123.4, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, '123,25', 123.2, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, '-123,46', -123.5, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, '-123,45', -123.4, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, '-123,44', -123.4, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, '-123,35', -123.4, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, '-123,25', -123.2, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            // round halves (.5) away from zero
            [0, '1234,6', 1235, NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [0, '1234,5', 1235, NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [0, '1234,4', 1234, NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [0, '-1234,6', -1235, NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [0, '-1234,5', -1235, NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [0, '-1234,4', -1234, NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [1, '123,46', 123.5, NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [1, '123,45', 123.5, NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [1, '123,44', 123.4, NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [1, '-123,46', -123.5, NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [1, '-123,45', -123.5, NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [1, '-123,44', -123.4, NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            // round halves (.5) towards zero
            [0, '1234,6', 1235, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [0, '1234,5', 1234, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [0, '1234,4', 1234, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [0, '-1234,6', -1235, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [0, '-1234,5', -1234, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [0, '-1234,4', -1234, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [1, '123,46', 123.5, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [1, '123,45', 123.4, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [1, '123,44', 123.4, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [1, '-123,46', -123.5, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [1, '-123,45', -123.4, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [1, '-123,44', -123.4, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
        ];
    }

    /**
     * @dataProvider reverseTransformWithRoundingProvider
     */
    public function testReverseTransformWithRounding($scale, $input, $output, $roundingMode)
    {
        $transformer = new NumberToLocalizedStringTransformer($scale, null, $roundingMode);

        $this->assertEquals($output, $transformer->reverseTransform($input));
    }

    public function testReverseTransformDoesNotRoundIfNoScale()
    {
        $transformer = new NumberToLocalizedStringTransformer(null, null, NumberToLocalizedStringTransformer::ROUND_DOWN);

        $this->assertEquals(1234.547, $transformer->reverseTransform('1234,547'));
    }

    public function testDecimalSeparatorMayBeDotIfGroupingSeparatorIsNotDot()
    {
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this, '4.8.1.1');

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

    public function testDecimalSeparatorMayNotBeDotIfGroupingSeparatorIsDot()
    {
        $this->expectException('Symfony\Component\Form\Exception\TransformationFailedException');
        // Since we test against "de_DE", we need the full implementation
        IntlTestHelper::requireFullIntl($this, '4.8.1.1');

        \Locale::setDefault('de_DE');

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $transformer->reverseTransform('1.234.5');
    }

    public function testDecimalSeparatorMayNotBeDotIfGroupingSeparatorIsDotWithNoGroupSep()
    {
        $this->expectException('Symfony\Component\Form\Exception\TransformationFailedException');
        // Since we test against "de_DE", we need the full implementation
        IntlTestHelper::requireFullIntl($this, '4.8.1.1');

        \Locale::setDefault('de_DE');

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $transformer->reverseTransform('1234.5');
    }

    public function testDecimalSeparatorMayBeDotIfGroupingSeparatorIsDotButNoGroupingUsed()
    {
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('fr');
        $transformer = new NumberToLocalizedStringTransformer();

        $this->assertEquals(1234.5, $transformer->reverseTransform('1234,5'));
        $this->assertEquals(1234.5, $transformer->reverseTransform('1234.5'));
    }

    public function testDecimalSeparatorMayBeCommaIfGroupingSeparatorIsNotComma()
    {
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this, '4.8.1.1');

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

    public function testDecimalSeparatorMayNotBeCommaIfGroupingSeparatorIsComma()
    {
        $this->expectException('Symfony\Component\Form\Exception\TransformationFailedException');
        IntlTestHelper::requireFullIntl($this, '4.8.1.1');

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $transformer->reverseTransform('1,234,5');
    }

    public function testDecimalSeparatorMayNotBeCommaIfGroupingSeparatorIsCommaWithNoGroupSep()
    {
        $this->expectException('Symfony\Component\Form\Exception\TransformationFailedException');
        IntlTestHelper::requireFullIntl($this, '4.8.1.1');

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $transformer->reverseTransform('1234,5');
    }

    public function testDecimalSeparatorMayBeCommaIfGroupingSeparatorIsCommaButNoGroupingUsed()
    {
        $transformer = new NumberToLocalizedStringTransformer();

        $this->assertEquals(1234.5, $transformer->reverseTransform('1234,5'));
        $this->assertEquals(1234.5, $transformer->reverseTransform('1234.5'));
    }

    public function testTransformExpectsNumeric()
    {
        $this->expectException('Symfony\Component\Form\Exception\TransformationFailedException');
        $transformer = new NumberToLocalizedStringTransformer();

        $transformer->transform('foo');
    }

    public function testReverseTransformExpectsString()
    {
        $this->expectException('Symfony\Component\Form\Exception\TransformationFailedException');
        $transformer = new NumberToLocalizedStringTransformer();

        $transformer->reverseTransform(1);
    }

    public function testReverseTransformExpectsValidNumber()
    {
        $this->expectException('Symfony\Component\Form\Exception\TransformationFailedException');
        $transformer = new NumberToLocalizedStringTransformer();

        $transformer->reverseTransform('foo');
    }

    /**
     * @dataProvider nanRepresentationProvider
     *
     * @see https://github.com/symfony/symfony/issues/3161
     */
    public function testReverseTransformDisallowsNaN($nan)
    {
        $this->expectException('Symfony\Component\Form\Exception\TransformationFailedException');
        $transformer = new NumberToLocalizedStringTransformer();

        $transformer->reverseTransform($nan);
    }

    public function nanRepresentationProvider()
    {
        return [
            ['nan'],
            ['NaN'], // see https://github.com/symfony/symfony/issues/3161
            ['NAN'],
        ];
    }

    public function testReverseTransformDisallowsInfinity()
    {
        $this->expectException('Symfony\Component\Form\Exception\TransformationFailedException');
        $transformer = new NumberToLocalizedStringTransformer();

        $transformer->reverseTransform('∞');
    }

    public function testReverseTransformDisallowsInfinity2()
    {
        $this->expectException('Symfony\Component\Form\Exception\TransformationFailedException');
        $transformer = new NumberToLocalizedStringTransformer();

        $transformer->reverseTransform('∞,123');
    }

    public function testReverseTransformDisallowsNegativeInfinity()
    {
        $this->expectException('Symfony\Component\Form\Exception\TransformationFailedException');
        $transformer = new NumberToLocalizedStringTransformer();

        $transformer->reverseTransform('-∞');
    }

    public function testReverseTransformDisallowsLeadingExtraCharacters()
    {
        $this->expectException('Symfony\Component\Form\Exception\TransformationFailedException');
        $transformer = new NumberToLocalizedStringTransformer();

        $transformer->reverseTransform('foo123');
    }

    public function testReverseTransformDisallowsCenteredExtraCharacters()
    {
        $this->expectException('Symfony\Component\Form\Exception\TransformationFailedException');
        $this->expectExceptionMessage('The number contains unrecognized characters: "foo3"');
        $transformer = new NumberToLocalizedStringTransformer();

        $transformer->reverseTransform('12foo3');
    }

    public function testReverseTransformDisallowsCenteredExtraCharactersMultibyte()
    {
        $this->expectException('Symfony\Component\Form\Exception\TransformationFailedException');
        $this->expectExceptionMessage('The number contains unrecognized characters: "foo8"');
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('ru');

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $transformer->reverseTransform("12\xc2\xa0345,67foo8");
    }

    public function testReverseTransformIgnoresTrailingSpacesInExceptionMessage()
    {
        $this->expectException('Symfony\Component\Form\Exception\TransformationFailedException');
        $this->expectExceptionMessage('The number contains unrecognized characters: "foo8"');
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('ru');

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $transformer->reverseTransform("12\xc2\xa0345,67foo8  \xc2\xa0\t");
    }

    public function testReverseTransformDisallowsTrailingExtraCharacters()
    {
        $this->expectException('Symfony\Component\Form\Exception\TransformationFailedException');
        $this->expectExceptionMessage('The number contains unrecognized characters: "foo"');
        $transformer = new NumberToLocalizedStringTransformer();

        $transformer->reverseTransform('123foo');
    }

    public function testReverseTransformDisallowsTrailingExtraCharactersMultibyte()
    {
        $this->expectException('Symfony\Component\Form\Exception\TransformationFailedException');
        $this->expectExceptionMessage('The number contains unrecognized characters: "foo"');
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('ru');

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $transformer->reverseTransform("12\xc2\xa0345,678foo");
    }

    public function testReverseTransformBigInt()
    {
        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $this->assertEquals(PHP_INT_MAX - 1, (int) $transformer->reverseTransform((string) (PHP_INT_MAX - 1)));
    }

    public function testReverseTransformSmallInt()
    {
        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $this->assertSame(1.0, $transformer->reverseTransform('1'));
    }
}
