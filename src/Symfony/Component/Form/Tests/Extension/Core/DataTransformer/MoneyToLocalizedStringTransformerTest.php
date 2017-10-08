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
use Symfony\Component\Form\Extension\Core\DataTransformer\MoneyToLocalizedStringTransformer;
use Symfony\Component\Intl\Util\IntlTestHelper;

class MoneyToLocalizedStringTransformerTest extends TestCase
{
    public function testTransform()
    {
        // Since we test against "de_AT", we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_AT');

        $transformer = new MoneyToLocalizedStringTransformer(null, null, null, 100);

        $this->assertEquals('1,23', $transformer->transform(123));
    }

    public function testTransformExpectsNumeric()
    {
        $transformer = new MoneyToLocalizedStringTransformer(null, null, null, 100);

        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}('Symfony\Component\Form\Exception\TransformationFailedException');

        $transformer->transform('abcd');
    }

    public function testTransformEmpty()
    {
        $transformer = new MoneyToLocalizedStringTransformer();

        $this->assertSame('', $transformer->transform(null));
    }

    public function testReverseTransform()
    {
        // Since we test against "de_AT", we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_AT');

        $transformer = new MoneyToLocalizedStringTransformer(null, null, null, 100);

        $this->assertEquals(123, $transformer->reverseTransform('1,23'));
    }

    public function testReverseTransformExpectsString()
    {
        $transformer = new MoneyToLocalizedStringTransformer(null, null, null, 100);

        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}('Symfony\Component\Form\Exception\TransformationFailedException');

        $transformer->reverseTransform(12345);
    }

    public function testReverseTransformEmpty()
    {
        $transformer = new MoneyToLocalizedStringTransformer();

        $this->assertNull($transformer->reverseTransform(''));
    }

    public function testFloatToIntConversionMismatchOnReversTransform()
    {
        $transformer = new MoneyToLocalizedStringTransformer(null, null, null, 100);
        IntlTestHelper::requireFullIntl($this, false);
        \Locale::setDefault('de_AT');
        $this->assertSame(3655, (int) $transformer->reverseTransform('36,55'));
    }
}
