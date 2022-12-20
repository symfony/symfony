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
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\DataTransformer\IntegerToLocalizedStringTransformer;
use Symfony\Component\Intl\Util\IntlTestHelper;

class IntegerToLocalizedStringTransformerTest extends TestCase
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

    public function transformWithRoundingProvider()
    {
        return [
            // towards positive infinity (1.6 -> 2, -1.6 -> -1)
            [1234.5, '1235', \NumberFormatter::ROUND_CEILING],
            [1234.4, '1235', \NumberFormatter::ROUND_CEILING],
            [-1234.5, '-1234', \NumberFormatter::ROUND_CEILING],
            [-1234.4, '-1234', \NumberFormatter::ROUND_CEILING],
            // towards negative infinity (1.6 -> 1, -1.6 -> -2)
            [1234.5, '1234', \NumberFormatter::ROUND_FLOOR],
            [1234.4, '1234', \NumberFormatter::ROUND_FLOOR],
            [-1234.5, '-1235', \NumberFormatter::ROUND_FLOOR],
            [-1234.4, '-1235', \NumberFormatter::ROUND_FLOOR],
            // away from zero (1.6 -> 2, -1.6 -> 2)
            [1234.5, '1235', \NumberFormatter::ROUND_UP],
            [1234.4, '1235', \NumberFormatter::ROUND_UP],
            [-1234.5, '-1235', \NumberFormatter::ROUND_UP],
            [-1234.4, '-1235', \NumberFormatter::ROUND_UP],
            // towards zero (1.6 -> 1, -1.6 -> -1)
            [1234.5, '1234', \NumberFormatter::ROUND_DOWN],
            [1234.4, '1234', \NumberFormatter::ROUND_DOWN],
            [-1234.5, '-1234', \NumberFormatter::ROUND_DOWN],
            [-1234.4, '-1234', \NumberFormatter::ROUND_DOWN],
            // round halves (.5) to the next even number
            [1234.6, '1235', \NumberFormatter::ROUND_HALFEVEN],
            [1234.5, '1234', \NumberFormatter::ROUND_HALFEVEN],
            [1234.4, '1234', \NumberFormatter::ROUND_HALFEVEN],
            [1233.5, '1234', \NumberFormatter::ROUND_HALFEVEN],
            [1232.5, '1232', \NumberFormatter::ROUND_HALFEVEN],
            [-1234.6, '-1235', \NumberFormatter::ROUND_HALFEVEN],
            [-1234.5, '-1234', \NumberFormatter::ROUND_HALFEVEN],
            [-1234.4, '-1234', \NumberFormatter::ROUND_HALFEVEN],
            [-1233.5, '-1234', \NumberFormatter::ROUND_HALFEVEN],
            [-1232.5, '-1232', \NumberFormatter::ROUND_HALFEVEN],
            // round halves (.5) away from zero
            [1234.6, '1235', \NumberFormatter::ROUND_HALFUP],
            [1234.5, '1235', \NumberFormatter::ROUND_HALFUP],
            [1234.4, '1234', \NumberFormatter::ROUND_HALFUP],
            [-1234.6, '-1235', \NumberFormatter::ROUND_HALFUP],
            [-1234.5, '-1235', \NumberFormatter::ROUND_HALFUP],
            [-1234.4, '-1234', \NumberFormatter::ROUND_HALFUP],
            // round halves (.5) towards zero
            [1234.6, '1235', \NumberFormatter::ROUND_HALFDOWN],
            [1234.5, '1234', \NumberFormatter::ROUND_HALFDOWN],
            [1234.4, '1234', \NumberFormatter::ROUND_HALFDOWN],
            [-1234.6, '-1235', \NumberFormatter::ROUND_HALFDOWN],
            [-1234.5, '-1234', \NumberFormatter::ROUND_HALFDOWN],
            [-1234.4, '-1234', \NumberFormatter::ROUND_HALFDOWN],
        ];
    }

    /**
     * @dataProvider transformWithRoundingProvider
     */
    public function testTransformWithRounding($input, $output, $roundingMode)
    {
        $transformer = new IntegerToLocalizedStringTransformer(null, $roundingMode);

        self::assertEquals($output, $transformer->transform($input));
    }

    public function testReverseTransform()
    {
        // Since we test against "de_AT", we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_AT');

        $transformer = new IntegerToLocalizedStringTransformer();

        self::assertEquals(1, $transformer->reverseTransform('1'));
        self::assertEquals(12345, $transformer->reverseTransform('12345'));
    }

    public function testReverseTransformEmpty()
    {
        $transformer = new IntegerToLocalizedStringTransformer();

        self::assertNull($transformer->reverseTransform(''));
    }

    public function testReverseTransformWithGrouping()
    {
        // Since we test against "de_DE", we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_DE');

        $transformer = new IntegerToLocalizedStringTransformer(true);

        self::assertEquals(1234, $transformer->reverseTransform('1.234'));
        self::assertEquals(12345, $transformer->reverseTransform('12.345'));
        self::assertEquals(1234, $transformer->reverseTransform('1234'));
        self::assertEquals(12345, $transformer->reverseTransform('12345'));
    }

    public function reverseTransformWithRoundingProvider()
    {
        return [
            // towards positive infinity (1.6 -> 2, -1.6 -> -1)
            ['1234,5', 1235, \NumberFormatter::ROUND_CEILING],
            ['1234,4', 1235, \NumberFormatter::ROUND_CEILING],
            ['-1234,5', -1234, \NumberFormatter::ROUND_CEILING],
            ['-1234,4', -1234, \NumberFormatter::ROUND_CEILING],
            // towards negative infinity (1.6 -> 1, -1.6 -> -2)
            ['1234,5', 1234, \NumberFormatter::ROUND_FLOOR],
            ['1234,4', 1234, \NumberFormatter::ROUND_FLOOR],
            ['-1234,5', -1235, \NumberFormatter::ROUND_FLOOR],
            ['-1234,4', -1235, \NumberFormatter::ROUND_FLOOR],
            // away from zero (1.6 -> 2, -1.6 -> 2)
            ['1234,5', 1235, \NumberFormatter::ROUND_UP],
            ['1234,4', 1235, \NumberFormatter::ROUND_UP],
            ['-1234,5', -1235, \NumberFormatter::ROUND_UP],
            ['-1234,4', -1235, \NumberFormatter::ROUND_UP],
            // towards zero (1.6 -> 1, -1.6 -> -1)
            ['1234,5', 1234, \NumberFormatter::ROUND_DOWN],
            ['1234,4', 1234, \NumberFormatter::ROUND_DOWN],
            ['-1234,5', -1234, \NumberFormatter::ROUND_DOWN],
            ['-1234,4', -1234, \NumberFormatter::ROUND_DOWN],
            // round halves (.5) to the next even number
            ['1234,6', 1235, \NumberFormatter::ROUND_HALFEVEN],
            ['1234,5', 1234, \NumberFormatter::ROUND_HALFEVEN],
            ['1234,4', 1234, \NumberFormatter::ROUND_HALFEVEN],
            ['1233,5', 1234, \NumberFormatter::ROUND_HALFEVEN],
            ['1232,5', 1232, \NumberFormatter::ROUND_HALFEVEN],
            ['-1234,6', -1235, \NumberFormatter::ROUND_HALFEVEN],
            ['-1234,5', -1234, \NumberFormatter::ROUND_HALFEVEN],
            ['-1234,4', -1234, \NumberFormatter::ROUND_HALFEVEN],
            ['-1233,5', -1234, \NumberFormatter::ROUND_HALFEVEN],
            ['-1232,5', -1232, \NumberFormatter::ROUND_HALFEVEN],
            // round halves (.5) away from zero
            ['1234,6', 1235, \NumberFormatter::ROUND_HALFUP],
            ['1234,5', 1235, \NumberFormatter::ROUND_HALFUP],
            ['1234,4', 1234, \NumberFormatter::ROUND_HALFUP],
            ['-1234,6', -1235, \NumberFormatter::ROUND_HALFUP],
            ['-1234,5', -1235, \NumberFormatter::ROUND_HALFUP],
            ['-1234,4', -1234, \NumberFormatter::ROUND_HALFUP],
            // round halves (.5) towards zero
            ['1234,6', 1235, \NumberFormatter::ROUND_HALFDOWN],
            ['1234,5', 1234, \NumberFormatter::ROUND_HALFDOWN],
            ['1234,4', 1234, \NumberFormatter::ROUND_HALFDOWN],
            ['-1234,6', -1235, \NumberFormatter::ROUND_HALFDOWN],
            ['-1234,5', -1234, \NumberFormatter::ROUND_HALFDOWN],
            ['-1234,4', -1234, \NumberFormatter::ROUND_HALFDOWN],
        ];
    }

    /**
     * @dataProvider reverseTransformWithRoundingProvider
     */
    public function testReverseTransformWithRounding($input, $output, $roundingMode)
    {
        $transformer = new IntegerToLocalizedStringTransformer(null, $roundingMode);

        self::assertEquals($output, $transformer->reverseTransform($input));
    }

    public function testReverseTransformExpectsString()
    {
        self::expectException(TransformationFailedException::class);
        $transformer = new IntegerToLocalizedStringTransformer();

        $transformer->reverseTransform(1);
    }

    public function testReverseTransformExpectsValidNumber()
    {
        self::expectException(TransformationFailedException::class);
        $transformer = new IntegerToLocalizedStringTransformer();

        $transformer->reverseTransform('foo');
    }

    /**
     * @dataProvider floatNumberProvider
     */
    public function testReverseTransformExpectsInteger($number, $locale)
    {
        self::expectException(TransformationFailedException::class);
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault($locale);

        $transformer = new IntegerToLocalizedStringTransformer();

        $transformer->reverseTransform($number);
    }

    public function floatNumberProvider()
    {
        return [
            ['12345.912', 'en'],
            ['1.234,5', 'de_DE'],
        ];
    }

    public function testReverseTransformDisallowsNaN()
    {
        self::expectException(TransformationFailedException::class);
        $transformer = new IntegerToLocalizedStringTransformer();

        $transformer->reverseTransform('NaN');
    }

    public function testReverseTransformDisallowsNaN2()
    {
        self::expectException(TransformationFailedException::class);
        $transformer = new IntegerToLocalizedStringTransformer();

        $transformer->reverseTransform('nan');
    }

    public function testReverseTransformDisallowsInfinity()
    {
        self::expectException(TransformationFailedException::class);
        $transformer = new IntegerToLocalizedStringTransformer();

        $transformer->reverseTransform('∞');
    }

    public function testReverseTransformDisallowsNegativeInfinity()
    {
        self::expectException(TransformationFailedException::class);
        $transformer = new IntegerToLocalizedStringTransformer();

        $transformer->reverseTransform('-∞');
    }
}
