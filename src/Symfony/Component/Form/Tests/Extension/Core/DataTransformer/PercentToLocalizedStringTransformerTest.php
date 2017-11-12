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
use Symfony\Component\Form\Extension\Core\DataTransformer\PercentToLocalizedStringTransformer;
use Symfony\Component\Intl\Util\IntlTestHelper;

class PercentToLocalizedStringTransformerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        \Locale::setDefault('en');
    }

    public function testTransform(): void
    {
        $transformer = new PercentToLocalizedStringTransformer();

        $this->assertEquals('10', $transformer->transform(0.1));
        $this->assertEquals('15', $transformer->transform(0.15));
        $this->assertEquals('12', $transformer->transform(0.1234));
        $this->assertEquals('200', $transformer->transform(2));
    }

    public function testTransformEmpty(): void
    {
        $transformer = new PercentToLocalizedStringTransformer();

        $this->assertEquals('', $transformer->transform(null));
    }

    public function testTransformWithInteger(): void
    {
        $transformer = new PercentToLocalizedStringTransformer(null, 'integer');

        $this->assertEquals('0', $transformer->transform(0.1));
        $this->assertEquals('1', $transformer->transform(1));
        $this->assertEquals('15', $transformer->transform(15));
        $this->assertEquals('16', $transformer->transform(15.9));
    }

    public function testTransformWithScale(): void
    {
        // Since we test against "de_AT", we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_AT');

        $transformer = new PercentToLocalizedStringTransformer(2);

        $this->assertEquals('12,34', $transformer->transform(0.1234));
    }

    public function testReverseTransform(): void
    {
        $transformer = new PercentToLocalizedStringTransformer();

        $this->assertEquals(0.1, $transformer->reverseTransform('10'));
        $this->assertEquals(0.15, $transformer->reverseTransform('15'));
        $this->assertEquals(0.12, $transformer->reverseTransform('12'));
        $this->assertEquals(2, $transformer->reverseTransform('200'));
    }

    public function testReverseTransformEmpty(): void
    {
        $transformer = new PercentToLocalizedStringTransformer();

        $this->assertNull($transformer->reverseTransform(''));
    }

    public function testReverseTransformWithInteger(): void
    {
        $transformer = new PercentToLocalizedStringTransformer(null, 'integer');

        $this->assertEquals(10, $transformer->reverseTransform('10'));
        $this->assertEquals(15, $transformer->reverseTransform('15'));
        $this->assertEquals(12, $transformer->reverseTransform('12'));
        $this->assertEquals(200, $transformer->reverseTransform('200'));
    }

    public function testReverseTransformWithScale(): void
    {
        // Since we test against "de_AT", we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_AT');

        $transformer = new PercentToLocalizedStringTransformer(2);

        $this->assertEquals(0.1234, $transformer->reverseTransform('12,34'));
    }

    public function testTransformExpectsNumeric(): void
    {
        $transformer = new PercentToLocalizedStringTransformer();

        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}('Symfony\Component\Form\Exception\TransformationFailedException');

        $transformer->transform('foo');
    }

    public function testReverseTransformExpectsString(): void
    {
        $transformer = new PercentToLocalizedStringTransformer();

        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}('Symfony\Component\Form\Exception\TransformationFailedException');

        $transformer->reverseTransform(1);
    }

    public function testDecimalSeparatorMayBeDotIfGroupingSeparatorIsNotDot(): void
    {
        IntlTestHelper::requireFullIntl($this, '4.8.1.1');

        \Locale::setDefault('fr');
        $transformer = new PercentToLocalizedStringTransformer(1, 'integer');

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
    public function testDecimalSeparatorMayNotBeDotIfGroupingSeparatorIsDot(): void
    {
        // Since we test against "de_DE", we need the full implementation
        IntlTestHelper::requireFullIntl($this, '4.8.1.1');

        \Locale::setDefault('de_DE');

        $transformer = new PercentToLocalizedStringTransformer(1, 'integer');

        $transformer->reverseTransform('1.234.5');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testDecimalSeparatorMayNotBeDotIfGroupingSeparatorIsDotWithNoGroupSep(): void
    {
        // Since we test against "de_DE", we need the full implementation
        IntlTestHelper::requireFullIntl($this, '4.8.1.1');

        \Locale::setDefault('de_DE');

        $transformer = new PercentToLocalizedStringTransformer(1, 'integer');

        $transformer->reverseTransform('1234.5');
    }

    public function testDecimalSeparatorMayBeDotIfGroupingSeparatorIsDotButNoGroupingUsed(): void
    {
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('fr');
        $transformer = new PercentToLocalizedStringTransformer(1, 'integer');

        $this->assertEquals(1234.5, $transformer->reverseTransform('1234,5'));
        $this->assertEquals(1234.5, $transformer->reverseTransform('1234.5'));
    }

    public function testDecimalSeparatorMayBeCommaIfGroupingSeparatorIsNotComma(): void
    {
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this, '4.8.1.1');

        \Locale::setDefault('bg');
        $transformer = new PercentToLocalizedStringTransformer(1, 'integer');

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
    public function testDecimalSeparatorMayNotBeCommaIfGroupingSeparatorIsComma(): void
    {
        IntlTestHelper::requireFullIntl($this, '4.8.1.1');

        $transformer = new PercentToLocalizedStringTransformer(1, 'integer');

        $transformer->reverseTransform('1,234,5');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testDecimalSeparatorMayNotBeCommaIfGroupingSeparatorIsCommaWithNoGroupSep(): void
    {
        IntlTestHelper::requireFullIntl($this, '4.8.1.1');

        $transformer = new PercentToLocalizedStringTransformer(1, 'integer');

        $transformer->reverseTransform('1234,5');
    }

    public function testDecimalSeparatorMayBeCommaIfGroupingSeparatorIsCommaButNoGroupingUsed(): void
    {
        $formatter = new \NumberFormatter(\Locale::getDefault(), \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, 1);
        $formatter->setAttribute(\NumberFormatter::GROUPING_USED, false);

        $transformer = $this->getMockBuilder('Symfony\Component\Form\Extension\Core\DataTransformer\PercentToLocalizedStringTransformer')
            ->setMethods(array('getNumberFormatter'))
            ->setConstructorArgs(array(1, 'integer'))
            ->getMock();
        $transformer->expects($this->any())
            ->method('getNumberFormatter')
            ->willReturn($formatter);

        $this->assertEquals(1234.5, $transformer->reverseTransform('1234,5'));
        $this->assertEquals(1234.5, $transformer->reverseTransform('1234.5'));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformDisallowsLeadingExtraCharacters(): void
    {
        $transformer = new PercentToLocalizedStringTransformer();

        $transformer->reverseTransform('foo123');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     * @expectedExceptionMessage The number contains unrecognized characters: "foo3"
     */
    public function testReverseTransformDisallowsCenteredExtraCharacters(): void
    {
        $transformer = new PercentToLocalizedStringTransformer();

        $transformer->reverseTransform('12foo3');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     * @expectedExceptionMessage The number contains unrecognized characters: "foo8"
     * @requires extension mbstring
     */
    public function testReverseTransformDisallowsCenteredExtraCharactersMultibyte(): void
    {
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('ru');

        $transformer = new PercentToLocalizedStringTransformer();

        $transformer->reverseTransform("12\xc2\xa0345,67foo8");
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     * @expectedExceptionMessage The number contains unrecognized characters: "foo"
     */
    public function testReverseTransformDisallowsTrailingExtraCharacters(): void
    {
        $transformer = new PercentToLocalizedStringTransformer();

        $transformer->reverseTransform('123foo');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     * @expectedExceptionMessage The number contains unrecognized characters: "foo"
     * @requires extension mbstring
     */
    public function testReverseTransformDisallowsTrailingExtraCharactersMultibyte(): void
    {
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('ru');

        $transformer = new PercentToLocalizedStringTransformer();

        $transformer->reverseTransform("12\xc2\xa0345,678foo");
    }
}
