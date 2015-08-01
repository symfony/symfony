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

use Symfony\Component\Form\Extension\Core\DataTransformer\IntegerToLocalizedStringTransformer;
use Symfony\Component\Intl\Util\IntlTestHelper;

class IntegerToLocalizedStringTransformerTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        parent::setUp();

        \Locale::setDefault('en');
    }

    public function testReverseTransform()
    {
        // Since we test against "de_AT", we need the full implementation
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault('de_AT');

        $transformer = new IntegerToLocalizedStringTransformer();

        $this->assertEquals(1, $transformer->reverseTransform('1'));
        $this->assertEquals(1, $transformer->reverseTransform('1,5'));
        $this->assertEquals(1234, $transformer->reverseTransform('1234,5'));
        $this->assertEquals(12345, $transformer->reverseTransform('12345,912'));
    }

    public function testReverseTransformEmpty()
    {
        $transformer = new IntegerToLocalizedStringTransformer();

        $this->assertNull($transformer->reverseTransform(''));
    }

    public function testReverseTransformWithGrouping()
    {
        // Since we test against "de_AT", we need the full implementation
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault('de_AT');

        $transformer = new IntegerToLocalizedStringTransformer(null, true);

        $this->assertEquals(1234, $transformer->reverseTransform('1.234,5'));
        $this->assertEquals(12345, $transformer->reverseTransform('12.345,912'));
        $this->assertEquals(1234, $transformer->reverseTransform('1234,5'));
        $this->assertEquals(12345, $transformer->reverseTransform('12345,912'));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformExpectsString()
    {
        $transformer = new IntegerToLocalizedStringTransformer();

        $transformer->reverseTransform(1);
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformExpectsValidNumber()
    {
        $transformer = new IntegerToLocalizedStringTransformer();

        $transformer->reverseTransform('foo');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformDisallowsNaN()
    {
        $transformer = new IntegerToLocalizedStringTransformer();

        $transformer->reverseTransform('NaN');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformDisallowsNaN2()
    {
        $transformer = new IntegerToLocalizedStringTransformer();

        $transformer->reverseTransform('nan');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformDisallowsInfinity()
    {
        $transformer = new IntegerToLocalizedStringTransformer();

        $transformer->reverseTransform('∞');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformDisallowsNegativeInfinity()
    {
        $transformer = new IntegerToLocalizedStringTransformer();

        $transformer->reverseTransform('-∞');
    }
}
