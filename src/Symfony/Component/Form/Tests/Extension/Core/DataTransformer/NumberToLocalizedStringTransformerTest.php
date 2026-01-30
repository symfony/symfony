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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\DataTransformer\NumberToLocalizedStringTransformer;
use Symfony\Component\Intl\Util\IntlTestHelper;

class NumberToLocalizedStringTransformerTest extends TestCase
{
    private string $defaultLocale;

    private $initialTestCaseUseException;

    protected function setUp(): void
    {
        // Normalize intl. configuration settings.
        if (\extension_loaded('intl')) {
            $this->initialTestCaseUseException = ini_set('intl.use_exceptions', 0);
        }

        $this->defaultLocale = \Locale::getDefault();
        \Locale::setDefault('en');
    }

    protected function tearDown(): void
    {
        \Locale::setDefault($this->defaultLocale);

        if (\extension_loaded('intl')) {
            ini_set('intl.use_exceptions', $this->initialTestCaseUseException);
        }
    }

    public static function provideTransformations()
    {
        return [
            [null, '', 'de_AT'],
            ['', '', 'de_AT'],
            [1, '1', 'de_AT'],
            [1.5, '1,5', 'de_AT'],
            [1234.5, '1234,5', 'de_AT'],
            [12345.912, '12345,912', 'de_AT'],
            [1234.5, '1234,5', 'ru'],
            [1234.5, '1234,5', 'fi'],
        ];
    }

    #[DataProvider('provideTransformations')]
    public function testTransform($from, $to, $locale): void
    {
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault($locale);

        $transformer = new NumberToLocalizedStringTransformer();

        $this->assertSame($to, $transformer->transform($from));
    }

    public static function provideTransformationsWithGrouping()
    {
        return [
            [1234.5, '1.234,5', 'de_DE'],
            [12345.912, '12.345,912', 'de_DE'],
            [1234.5, '1 234,5', 'fr'],
            [1234.5, '1 234,5', 'ru'],
            [1234.5, '1 234,5', 'fi'],
        ];
    }

    #[DataProvider('provideTransformationsWithGrouping')]
    public function testTransformWithGrouping($from, $to, $locale): void
    {
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault($locale);

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $this->assertSame($to, $transformer->transform($from));
    }

    public function testTransformWithScale(): void
    {
        // Since we test against "de_AT", we need the full implementation
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault('de_AT');

        $transformer = new NumberToLocalizedStringTransformer(2);

        $this->assertEquals('1234,50', $transformer->transform(1234.5));
        $this->assertEquals('678,92', $transformer->transform(678.916));
    }

    public static function transformWithRoundingProvider()
    {
        return [
            // towards positive infinity (1.6 -> 2, -1.6 -> -1)
            [0, 1234.5, '1235', \NumberFormatter::ROUND_CEILING],
            [0, 1234.4, '1235', \NumberFormatter::ROUND_CEILING],
            [0, -1234.5, '-1234', \NumberFormatter::ROUND_CEILING],
            [0, -1234.4, '-1234', \NumberFormatter::ROUND_CEILING],
            [1, 123.45, '123,5', \NumberFormatter::ROUND_CEILING],
            [1, 123.44, '123,5', \NumberFormatter::ROUND_CEILING],
            [1, -123.45, '-123,4', \NumberFormatter::ROUND_CEILING],
            [1, -123.44, '-123,4', \NumberFormatter::ROUND_CEILING],
            // towards negative infinity (1.6 -> 1, -1.6 -> -2)
            [0, 1234.5, '1234', \NumberFormatter::ROUND_FLOOR],
            [0, 1234.4, '1234', \NumberFormatter::ROUND_FLOOR],
            [0, -1234.5, '-1235', \NumberFormatter::ROUND_FLOOR],
            [0, -1234.4, '-1235', \NumberFormatter::ROUND_FLOOR],
            [1, 123.45, '123,4', \NumberFormatter::ROUND_FLOOR],
            [1, 123.44, '123,4', \NumberFormatter::ROUND_FLOOR],
            [1, -123.45, '-123,5', \NumberFormatter::ROUND_FLOOR],
            [1, -123.44, '-123,5', \NumberFormatter::ROUND_FLOOR],
            // away from zero (1.6 -> 2, -1.6 -> 2)
            [0, 1234.5, '1235', \NumberFormatter::ROUND_UP],
            [0, 1234.4, '1235', \NumberFormatter::ROUND_UP],
            [0, -1234.5, '-1235', \NumberFormatter::ROUND_UP],
            [0, -1234.4, '-1235', \NumberFormatter::ROUND_UP],
            [1, 123.45, '123,5', \NumberFormatter::ROUND_UP],
            [1, 123.44, '123,5', \NumberFormatter::ROUND_UP],
            [1, -123.45, '-123,5', \NumberFormatter::ROUND_UP],
            [1, -123.44, '-123,5', \NumberFormatter::ROUND_UP],
            // towards zero (1.6 -> 1, -1.6 -> -1)
            [0, 1234.5, '1234', \NumberFormatter::ROUND_DOWN],
            [0, 1234.4, '1234', \NumberFormatter::ROUND_DOWN],
            [0, -1234.5, '-1234', \NumberFormatter::ROUND_DOWN],
            [0, -1234.4, '-1234', \NumberFormatter::ROUND_DOWN],
            [1, 123.45, '123,4', \NumberFormatter::ROUND_DOWN],
            [1, 123.44, '123,4', \NumberFormatter::ROUND_DOWN],
            [1, -123.45, '-123,4', \NumberFormatter::ROUND_DOWN],
            [1, -123.44, '-123,4', \NumberFormatter::ROUND_DOWN],
            // round halves (.5) to the next even number
            [0, 1234.6, '1235', \NumberFormatter::ROUND_HALFEVEN],
            [0, 1234.5, '1234', \NumberFormatter::ROUND_HALFEVEN],
            [0, 1234.4, '1234', \NumberFormatter::ROUND_HALFEVEN],
            [0, 1233.5, '1234', \NumberFormatter::ROUND_HALFEVEN],
            [0, 1232.5, '1232', \NumberFormatter::ROUND_HALFEVEN],
            [0, -1234.6, '-1235', \NumberFormatter::ROUND_HALFEVEN],
            [0, -1234.5, '-1234', \NumberFormatter::ROUND_HALFEVEN],
            [0, -1234.4, '-1234', \NumberFormatter::ROUND_HALFEVEN],
            [0, -1233.5, '-1234', \NumberFormatter::ROUND_HALFEVEN],
            [0, -1232.5, '-1232', \NumberFormatter::ROUND_HALFEVEN],
            [1, 123.46, '123,5', \NumberFormatter::ROUND_HALFEVEN],
            [1, 123.45, '123,4', \NumberFormatter::ROUND_HALFEVEN],
            [1, 123.44, '123,4', \NumberFormatter::ROUND_HALFEVEN],
            [1, 123.35, '123,4', \NumberFormatter::ROUND_HALFEVEN],
            [1, 123.25, '123,2', \NumberFormatter::ROUND_HALFEVEN],
            [1, -123.46, '-123,5', \NumberFormatter::ROUND_HALFEVEN],
            [1, -123.45, '-123,4', \NumberFormatter::ROUND_HALFEVEN],
            [1, -123.44, '-123,4', \NumberFormatter::ROUND_HALFEVEN],
            [1, -123.35, '-123,4', \NumberFormatter::ROUND_HALFEVEN],
            [1, -123.25, '-123,2', \NumberFormatter::ROUND_HALFEVEN],
            // round halves (.5) away from zero
            [0, 1234.6, '1235', \NumberFormatter::ROUND_HALFUP],
            [0, 1234.5, '1235', \NumberFormatter::ROUND_HALFUP],
            [0, 1234.4, '1234', \NumberFormatter::ROUND_HALFUP],
            [0, -1234.6, '-1235', \NumberFormatter::ROUND_HALFUP],
            [0, -1234.5, '-1235', \NumberFormatter::ROUND_HALFUP],
            [0, -1234.4, '-1234', \NumberFormatter::ROUND_HALFUP],
            [1, 123.46, '123,5', \NumberFormatter::ROUND_HALFUP],
            [1, 123.45, '123,5', \NumberFormatter::ROUND_HALFUP],
            [1, 123.44, '123,4', \NumberFormatter::ROUND_HALFUP],
            [1, -123.46, '-123,5', \NumberFormatter::ROUND_HALFUP],
            [1, -123.45, '-123,5', \NumberFormatter::ROUND_HALFUP],
            [1, -123.44, '-123,4', \NumberFormatter::ROUND_HALFUP],
            // round halves (.5) towards zero
            [0, 1234.6, '1235', \NumberFormatter::ROUND_HALFDOWN],
            [0, 1234.5, '1234', \NumberFormatter::ROUND_HALFDOWN],
            [0, 1234.4, '1234', \NumberFormatter::ROUND_HALFDOWN],
            [0, -1234.6, '-1235', \NumberFormatter::ROUND_HALFDOWN],
            [0, -1234.5, '-1234', \NumberFormatter::ROUND_HALFDOWN],
            [0, -1234.4, '-1234', \NumberFormatter::ROUND_HALFDOWN],
            [1, 123.46, '123,5', \NumberFormatter::ROUND_HALFDOWN],
            [1, 123.45, '123,4', \NumberFormatter::ROUND_HALFDOWN],
            [1, 123.44, '123,4', \NumberFormatter::ROUND_HALFDOWN],
            [1, -123.46, '-123,5', \NumberFormatter::ROUND_HALFDOWN],
            [1, -123.45, '-123,4', \NumberFormatter::ROUND_HALFDOWN],
            [1, -123.44, '-123,4', \NumberFormatter::ROUND_HALFDOWN],
        ];
    }

    #[DataProvider('transformWithRoundingProvider')]
    public function testTransformWithRounding($scale, $input, $output, $roundingMode): void
    {
        // Since we test against "de_AT", we need the full implementation
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault('de_AT');

        $transformer = new NumberToLocalizedStringTransformer($scale, null, $roundingMode);

        $this->assertEquals($output, $transformer->transform($input));
    }

    public function testTransformDoesNotRoundIfNoScale(): void
    {
        // Since we test against "de_AT", we need the full implementation
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault('de_AT');

        $transformer = new NumberToLocalizedStringTransformer(null, null, \NumberFormatter::ROUND_DOWN);

        $this->assertEquals('1234,547', $transformer->transform(1234.547));
    }

    #[DataProvider('provideTransformations')]
    public function testReverseTransform($to, $from, $locale): void
    {
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault($locale);

        $transformer = new NumberToLocalizedStringTransformer();

        $this->assertEquals($to, $transformer->reverseTransform($from));
    }

    #[DataProvider('provideTransformationsWithGrouping')]
    public function testReverseTransformWithGrouping($to, $from, $locale): void
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
    public function testReverseTransformWithGroupingAndFixedSpaces(): void
    {
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault('ru');

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $this->assertEquals(1234.5, $transformer->reverseTransform("1\xc2\xa0234,5"));
    }

    public function testReverseTransformWithGroupingButWithoutGroupSeparator(): void
    {
        // Since we test against "de_AT", we need the full implementation
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault('de_AT');

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        // omit group separator
        $this->assertEquals(1234.5, $transformer->reverseTransform('1234,5'));
        $this->assertEquals(12345.912, $transformer->reverseTransform('12345,912'));
    }

    public static function reverseTransformWithRoundingProvider()
    {
        return [
            // towards positive infinity (1.6 -> 2, -1.6 -> -1)
            [0, '1234,5', 1235, \NumberFormatter::ROUND_CEILING],
            [0, '1234,4', 1235, \NumberFormatter::ROUND_CEILING],
            [0, '-1234,5', -1234, \NumberFormatter::ROUND_CEILING],
            [0, '-1234,4', -1234, \NumberFormatter::ROUND_CEILING],
            [1, '123,45', 123.5, \NumberFormatter::ROUND_CEILING],
            [1, '123,44', 123.5, \NumberFormatter::ROUND_CEILING],
            [1, '-123,45', -123.4, \NumberFormatter::ROUND_CEILING],
            [1, '-123,44', -123.4, \NumberFormatter::ROUND_CEILING],
            // towards negative infinity (1.6 -> 1, -1.6 -> -2)
            [0, '1234,5', 1234, \NumberFormatter::ROUND_FLOOR],
            [0, '1234,4', 1234, \NumberFormatter::ROUND_FLOOR],
            [0, '-1234,5', -1235, \NumberFormatter::ROUND_FLOOR],
            [0, '-1234,4', -1235, \NumberFormatter::ROUND_FLOOR],
            [1, '123,45', 123.4, \NumberFormatter::ROUND_FLOOR],
            [1, '123,44', 123.4, \NumberFormatter::ROUND_FLOOR],
            [1, '-123,45', -123.5, \NumberFormatter::ROUND_FLOOR],
            [1, '-123,44', -123.5, \NumberFormatter::ROUND_FLOOR],
            // away from zero (1.6 -> 2, -1.6 -> 2)
            [0, '1234,5', 1235, \NumberFormatter::ROUND_UP],
            [0, '1234,4', 1235, \NumberFormatter::ROUND_UP],
            [0, '-1234,5', -1235, \NumberFormatter::ROUND_UP],
            [0, '-1234,4', -1235, \NumberFormatter::ROUND_UP],
            [1, '123,45', 123.5, \NumberFormatter::ROUND_UP],
            [1, '123,44', 123.5, \NumberFormatter::ROUND_UP],
            [1, '-123,45', -123.5, \NumberFormatter::ROUND_UP],
            [1, '-123,44', -123.5, \NumberFormatter::ROUND_UP],
            // towards zero (1.6 -> 1, -1.6 -> -1)
            [0, '1234,5', 1234, \NumberFormatter::ROUND_DOWN],
            [0, '1234,4', 1234, \NumberFormatter::ROUND_DOWN],
            [0, '-1234,5', -1234, \NumberFormatter::ROUND_DOWN],
            [0, '-1234,4', -1234, \NumberFormatter::ROUND_DOWN],
            [1, '123,45', 123.4, \NumberFormatter::ROUND_DOWN],
            [1, '123,44', 123.4, \NumberFormatter::ROUND_DOWN],
            [1, '-123,45', -123.4, \NumberFormatter::ROUND_DOWN],
            [1, '-123,44', -123.4, \NumberFormatter::ROUND_DOWN],
            [2, '37.37', 37.37, \NumberFormatter::ROUND_DOWN],
            [2, '2.01', 2.01, \NumberFormatter::ROUND_DOWN],
            // round halves (.5) to the next even number
            [0, '1234,6', 1235, \NumberFormatter::ROUND_HALFEVEN],
            [0, '1234,5', 1234, \NumberFormatter::ROUND_HALFEVEN],
            [0, '1234,4', 1234, \NumberFormatter::ROUND_HALFEVEN],
            [0, '1233,5', 1234, \NumberFormatter::ROUND_HALFEVEN],
            [0, '1232,5', 1232, \NumberFormatter::ROUND_HALFEVEN],
            [0, '-1234,6', -1235, \NumberFormatter::ROUND_HALFEVEN],
            [0, '-1234,5', -1234, \NumberFormatter::ROUND_HALFEVEN],
            [0, '-1234,4', -1234, \NumberFormatter::ROUND_HALFEVEN],
            [0, '-1233,5', -1234, \NumberFormatter::ROUND_HALFEVEN],
            [0, '-1232,5', -1232, \NumberFormatter::ROUND_HALFEVEN],
            [1, '123,46', 123.5, \NumberFormatter::ROUND_HALFEVEN],
            [1, '123,45', 123.4, \NumberFormatter::ROUND_HALFEVEN],
            [1, '123,44', 123.4, \NumberFormatter::ROUND_HALFEVEN],
            [1, '123,35', 123.4, \NumberFormatter::ROUND_HALFEVEN],
            [1, '123,25', 123.2, \NumberFormatter::ROUND_HALFEVEN],
            [1, '-123,46', -123.5, \NumberFormatter::ROUND_HALFEVEN],
            [1, '-123,45', -123.4, \NumberFormatter::ROUND_HALFEVEN],
            [1, '-123,44', -123.4, \NumberFormatter::ROUND_HALFEVEN],
            [1, '-123,35', -123.4, \NumberFormatter::ROUND_HALFEVEN],
            [1, '-123,25', -123.2, \NumberFormatter::ROUND_HALFEVEN],
            // round halves (.5) away from zero
            [0, '1234,6', 1235, \NumberFormatter::ROUND_HALFUP],
            [0, '1234,5', 1235, \NumberFormatter::ROUND_HALFUP],
            [0, '1234,4', 1234, \NumberFormatter::ROUND_HALFUP],
            [0, '-1234,6', -1235, \NumberFormatter::ROUND_HALFUP],
            [0, '-1234,5', -1235, \NumberFormatter::ROUND_HALFUP],
            [0, '-1234,4', -1234, \NumberFormatter::ROUND_HALFUP],
            [1, '123,46', 123.5, \NumberFormatter::ROUND_HALFUP],
            [1, '123,45', 123.5, \NumberFormatter::ROUND_HALFUP],
            [1, '123,44', 123.4, \NumberFormatter::ROUND_HALFUP],
            [1, '-123,46', -123.5, \NumberFormatter::ROUND_HALFUP],
            [1, '-123,45', -123.5, \NumberFormatter::ROUND_HALFUP],
            [1, '-123,44', -123.4, \NumberFormatter::ROUND_HALFUP],
            // round halves (.5) towards zero
            [0, '1234,6', 1235, \NumberFormatter::ROUND_HALFDOWN],
            [0, '1234,5', 1234, \NumberFormatter::ROUND_HALFDOWN],
            [0, '1234,4', 1234, \NumberFormatter::ROUND_HALFDOWN],
            [0, '-1234,6', -1235, \NumberFormatter::ROUND_HALFDOWN],
            [0, '-1234,5', -1234, \NumberFormatter::ROUND_HALFDOWN],
            [0, '-1234,4', -1234, \NumberFormatter::ROUND_HALFDOWN],
            [1, '123,46', 123.5, \NumberFormatter::ROUND_HALFDOWN],
            [1, '123,45', 123.4, \NumberFormatter::ROUND_HALFDOWN],
            [1, '123,44', 123.4, \NumberFormatter::ROUND_HALFDOWN],
            [1, '-123,46', -123.5, \NumberFormatter::ROUND_HALFDOWN],
            [1, '-123,45', -123.4, \NumberFormatter::ROUND_HALFDOWN],
            [1, '-123,44', -123.4, \NumberFormatter::ROUND_HALFDOWN],
        ];
    }

    #[DataProvider('reverseTransformWithRoundingProvider')]
    public function testReverseTransformWithRounding($scale, $input, $output, $roundingMode): void
    {
        $transformer = new NumberToLocalizedStringTransformer($scale, null, $roundingMode);

        $this->assertSame($output, $transformer->reverseTransform($input));
    }

    public function testReverseTransformDoesNotRoundIfNoScale(): void
    {
        $transformer = new NumberToLocalizedStringTransformer(null, null, \NumberFormatter::ROUND_DOWN);

        $this->assertEquals(1234.547, $transformer->reverseTransform('1234,547'));
    }

    public function testDecimalSeparatorMayBeDotIfGroupingSeparatorIsNotDot(): void
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

    public function testDecimalSeparatorMayNotBeDotIfGroupingSeparatorIsDot(): void
    {
        $this->expectException(TransformationFailedException::class);
        // Since we test against "de_DE", we need the full implementation
        IntlTestHelper::requireFullIntl($this, '4.8.1.1');

        \Locale::setDefault('de_DE');

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $transformer->reverseTransform('1.234.5');
    }

    public function testDecimalSeparatorMayNotBeDotIfGroupingSeparatorIsDotWithNoGroupSep(): void
    {
        $this->expectException(TransformationFailedException::class);
        // Since we test against "de_DE", we need the full implementation
        IntlTestHelper::requireFullIntl($this, '4.8.1.1');

        \Locale::setDefault('de_DE');

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $transformer->reverseTransform('1234.5');
    }

    public function testDecimalSeparatorMayBeDotIfGroupingSeparatorIsDotButNoGroupingUsed(): void
    {
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault('fr');
        $transformer = new NumberToLocalizedStringTransformer();

        $this->assertEquals(1234.5, $transformer->reverseTransform('1234,5'));
        $this->assertEquals(1234.5, $transformer->reverseTransform('1234.5'));
    }

    public function testDecimalSeparatorMayBeCommaIfGroupingSeparatorIsNotComma(): void
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

    public function testDecimalSeparatorMayNotBeCommaIfGroupingSeparatorIsComma(): void
    {
        $this->expectException(TransformationFailedException::class);
        IntlTestHelper::requireFullIntl($this, '4.8.1.1');

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $transformer->reverseTransform('1,234,5');
    }

    public function testDecimalSeparatorMayNotBeCommaIfGroupingSeparatorIsCommaWithNoGroupSep(): void
    {
        $this->expectException(TransformationFailedException::class);
        IntlTestHelper::requireFullIntl($this, '4.8.1.1');

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $transformer->reverseTransform('1234,5');
    }

    public function testDecimalSeparatorMayBeCommaIfGroupingSeparatorIsCommaButNoGroupingUsed(): void
    {
        $transformer = new NumberToLocalizedStringTransformer();

        $this->assertEquals(1234.5, $transformer->reverseTransform('1234,5'));
        $this->assertEquals(1234.5, $transformer->reverseTransform('1234.5'));
    }

    public function testTransformExpectsNumeric(): void
    {
        $this->expectException(TransformationFailedException::class);
        $transformer = new NumberToLocalizedStringTransformer();

        $transformer->transform('foo');
    }

    public function testReverseTransformExpectsString(): void
    {
        $this->expectException(TransformationFailedException::class);
        $transformer = new NumberToLocalizedStringTransformer();

        $transformer->reverseTransform(1);
    }

    public function testReverseTransformExpectsValidNumber(): void
    {
        $this->expectException(TransformationFailedException::class);
        $transformer = new NumberToLocalizedStringTransformer();

        $transformer->reverseTransform('foo');
    }

    /**
     * @see https://github.com/symfony/symfony/issues/3161
     */
    #[DataProvider('nanRepresentationProvider')]
    public function testReverseTransformDisallowsNaN($nan): void
    {
        $this->expectException(TransformationFailedException::class);
        $transformer = new NumberToLocalizedStringTransformer();

        $transformer->reverseTransform($nan);
    }

    public static function nanRepresentationProvider()
    {
        return [
            ['nan'],
            ['NaN'], // see https://github.com/symfony/symfony/issues/3161
            ['NAN'],
        ];
    }

    public function testReverseTransformDisallowsInfinity(): void
    {
        $this->expectException(TransformationFailedException::class);
        $transformer = new NumberToLocalizedStringTransformer();

        $transformer->reverseTransform('∞');
    }

    public function testReverseTransformDisallowsInfinity2(): void
    {
        $this->expectException(TransformationFailedException::class);
        $transformer = new NumberToLocalizedStringTransformer();

        $transformer->reverseTransform('∞,123');
    }

    public function testReverseTransformDisallowsNegativeInfinity(): void
    {
        $this->expectException(TransformationFailedException::class);
        $transformer = new NumberToLocalizedStringTransformer();

        $transformer->reverseTransform('-∞');
    }

    public function testReverseTransformDisallowsLeadingExtraCharacters(): void
    {
        $this->expectException(TransformationFailedException::class);
        $transformer = new NumberToLocalizedStringTransformer();

        $transformer->reverseTransform('foo123');
    }

    public function testReverseTransformDisallowsCenteredExtraCharacters(): void
    {
        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessage('The number contains unrecognized characters: "foo3"');
        $transformer = new NumberToLocalizedStringTransformer();

        $transformer->reverseTransform('12foo3');
    }

    public function testReverseTransformDisallowsCenteredExtraCharactersMultibyte(): void
    {
        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessage('The number contains unrecognized characters: "foo8"');
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault('ru');

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $transformer->reverseTransform("12\xc2\xa0345,67foo8");
    }

    public function testReverseTransformIgnoresTrailingSpacesInExceptionMessage(): void
    {
        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessage('The number contains unrecognized characters: "foo8"');
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault('ru');

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $transformer->reverseTransform("12\xc2\xa0345,67foo8  \xc2\xa0\t");
    }

    public function testReverseTransformDisallowsTrailingExtraCharacters(): void
    {
        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessage('The number contains unrecognized characters: "foo"');
        $transformer = new NumberToLocalizedStringTransformer();

        $transformer->reverseTransform('123foo');
    }

    public function testReverseTransformDisallowsTrailingExtraCharactersMultibyte(): void
    {
        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessage('The number contains unrecognized characters: "foo"');
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault('ru');

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $transformer->reverseTransform("12\xc2\xa0345,678foo");
    }

    public function testReverseTransformBigInt(): void
    {
        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $this->assertEquals(\PHP_INT_MAX - 1, (int) $transformer->reverseTransform((string) (\PHP_INT_MAX - 1)));
    }

    public function testReverseTransformSmallInt(): void
    {
        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $this->assertSame(1.0, $transformer->reverseTransform('1'));
    }

    #[DataProvider('eNotationProvider')]
    public function testReverseTransformENotation($output, $input): void
    {
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault('en');

        $transformer = new NumberToLocalizedStringTransformer();

        $this->assertSame($output, $transformer->reverseTransform($input));
    }

    #[RequiresPhpExtension('intl')]
    #[RequiresPhp('< 8.5')]
    public function testReverseTransformWrapsIntlErrorsWithErrorLevel(): void
    {
        $errorLevel = ini_set('intl.error_level', \E_WARNING);

        try {
            $this->expectException(TransformationFailedException::class);
            $transformer = new NumberToLocalizedStringTransformer();
            $transformer->reverseTransform('invalid_number');
        } finally {
            ini_set('intl.error_level', $errorLevel);
        }
    }

    #[RequiresPhpExtension('intl')]
    public function testReverseTransformWrapsIntlErrorsWithExceptions(): void
    {
        $initialUseExceptions = ini_set('intl.use_exceptions', 1);

        try {
            $this->expectException(TransformationFailedException::class);
            $transformer = new NumberToLocalizedStringTransformer();
            $transformer->reverseTransform('invalid_number');
        } finally {
            ini_set('intl.use_exceptions', $initialUseExceptions);
        }
    }

    #[RequiresPhpExtension('intl')]
    #[RequiresPhp('< 8.5')]
    public function testReverseTransformWrapsIntlErrorsWithExceptionsAndErrorLevel(): void
    {
        $initialUseExceptions = ini_set('intl.use_exceptions', 1);
        $initialErrorLevel = ini_set('intl.error_level', \E_WARNING);

        try {
            $this->expectException(TransformationFailedException::class);
            $transformer = new NumberToLocalizedStringTransformer();
            $transformer->reverseTransform('invalid_number');
        } finally {
            ini_set('intl.use_exceptions', $initialUseExceptions);
            ini_set('intl.error_level', $initialErrorLevel);
        }
    }

    public static function eNotationProvider(): array
    {
        return [
            [0.001, '1E-3'],
            [0.001, '1.0E-3'],
            [0.001, '1e-3'],
            [0.001, '1.0e-03'],
            [1000.0, '1E3'],
            [1000.0, '1.0E3'],
            [1000.0, '1e3'],
            [1000.0, '1.0e3'],
            [1232.0, '1.232e3'],
        ];
    }

    public function testReverseTransformDoesNotCauseIntegerPrecisionLoss(): void
    {
        if (\PHP_INT_SIZE === 4) {
            $this->markTestSkipped('Test is not applicable on 32-bit systems where no integer loses precision when cast to float.');
        }

        $transformer = new NumberToLocalizedStringTransformer(2);

        // Test a large integer that causes actual precision loss when cast to float
        $largeInt = \PHP_INT_MAX - 1; // This value loses precision when cast to float
        $result = $transformer->reverseTransform((string) $largeInt);

        $this->assertSame($largeInt, $result);
        $this->assertIsInt($result);
    }
}
