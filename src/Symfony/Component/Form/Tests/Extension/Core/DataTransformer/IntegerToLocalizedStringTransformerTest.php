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

    public function transformWithRoundingProvider()
    {
        return array(
            // towards positive infinity (1.6 -> 2, -1.6 -> -1)
            array(1234.5, '1235', IntegerToLocalizedStringTransformer::ROUND_CEILING),
            array(1234.4, '1235', IntegerToLocalizedStringTransformer::ROUND_CEILING),
            array(-1234.5, '-1234', IntegerToLocalizedStringTransformer::ROUND_CEILING),
            array(-1234.4, '-1234', IntegerToLocalizedStringTransformer::ROUND_CEILING),
            // towards negative infinity (1.6 -> 1, -1.6 -> -2)
            array(1234.5, '1234', IntegerToLocalizedStringTransformer::ROUND_FLOOR),
            array(1234.4, '1234', IntegerToLocalizedStringTransformer::ROUND_FLOOR),
            array(-1234.5, '-1235', IntegerToLocalizedStringTransformer::ROUND_FLOOR),
            array(-1234.4, '-1235', IntegerToLocalizedStringTransformer::ROUND_FLOOR),
            // away from zero (1.6 -> 2, -1.6 -> 2)
            array(1234.5, '1235', IntegerToLocalizedStringTransformer::ROUND_UP),
            array(1234.4, '1235', IntegerToLocalizedStringTransformer::ROUND_UP),
            array(-1234.5, '-1235', IntegerToLocalizedStringTransformer::ROUND_UP),
            array(-1234.4, '-1235', IntegerToLocalizedStringTransformer::ROUND_UP),
            // towards zero (1.6 -> 1, -1.6 -> -1)
            array(1234.5, '1234', IntegerToLocalizedStringTransformer::ROUND_DOWN),
            array(1234.4, '1234', IntegerToLocalizedStringTransformer::ROUND_DOWN),
            array(-1234.5, '-1234', IntegerToLocalizedStringTransformer::ROUND_DOWN),
            array(-1234.4, '-1234', IntegerToLocalizedStringTransformer::ROUND_DOWN),
            // round halves (.5) to the next even number
            array(1234.6, '1235', IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(1234.5, '1234', IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(1234.4, '1234', IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(1233.5, '1234', IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(1232.5, '1232', IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(-1234.6, '-1235', IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(-1234.5, '-1234', IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(-1234.4, '-1234', IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(-1233.5, '-1234', IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array(-1232.5, '-1232', IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN),
            // round halves (.5) away from zero
            array(1234.6, '1235', IntegerToLocalizedStringTransformer::ROUND_HALF_UP),
            array(1234.5, '1235', IntegerToLocalizedStringTransformer::ROUND_HALF_UP),
            array(1234.4, '1234', IntegerToLocalizedStringTransformer::ROUND_HALF_UP),
            array(-1234.6, '-1235', IntegerToLocalizedStringTransformer::ROUND_HALF_UP),
            array(-1234.5, '-1235', IntegerToLocalizedStringTransformer::ROUND_HALF_UP),
            array(-1234.4, '-1234', IntegerToLocalizedStringTransformer::ROUND_HALF_UP),
            // round halves (.5) towards zero
            array(1234.6, '1235', IntegerToLocalizedStringTransformer::ROUND_HALF_DOWN),
            array(1234.5, '1234', IntegerToLocalizedStringTransformer::ROUND_HALF_DOWN),
            array(1234.4, '1234', IntegerToLocalizedStringTransformer::ROUND_HALF_DOWN),
            array(-1234.6, '-1235', IntegerToLocalizedStringTransformer::ROUND_HALF_DOWN),
            array(-1234.5, '-1234', IntegerToLocalizedStringTransformer::ROUND_HALF_DOWN),
            array(-1234.4, '-1234', IntegerToLocalizedStringTransformer::ROUND_HALF_DOWN),
        );
    }

    /**
     * @dataProvider transformWithRoundingProvider
     */
    public function testTransformWithRounding($input, $output, $roundingMode)
    {
        $transformer = new IntegerToLocalizedStringTransformer(null, null, $roundingMode);

        $this->assertEquals($output, $transformer->transform($input));
    }

    public function testReverseTransform()
    {
        // Since we test against "de_AT", we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

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
        // Since we test against "de_DE", we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_DE');

        $transformer = new IntegerToLocalizedStringTransformer(null, true);

        $this->assertEquals(1234, $transformer->reverseTransform('1.234,5'));
        $this->assertEquals(12345, $transformer->reverseTransform('12.345,912'));
        $this->assertEquals(1234, $transformer->reverseTransform('1234,5'));
        $this->assertEquals(12345, $transformer->reverseTransform('12345,912'));
    }

    public function reverseTransformWithRoundingProvider()
    {
        return array(
            // towards positive infinity (1.6 -> 2, -1.6 -> -1)
            array('1234,5', 1235, IntegerToLocalizedStringTransformer::ROUND_CEILING),
            array('1234,4', 1235, IntegerToLocalizedStringTransformer::ROUND_CEILING),
            array('-1234,5', -1234, IntegerToLocalizedStringTransformer::ROUND_CEILING),
            array('-1234,4', -1234, IntegerToLocalizedStringTransformer::ROUND_CEILING),
            // towards negative infinity (1.6 -> 1, -1.6 -> -2)
            array('1234,5', 1234, IntegerToLocalizedStringTransformer::ROUND_FLOOR),
            array('1234,4', 1234, IntegerToLocalizedStringTransformer::ROUND_FLOOR),
            array('-1234,5', -1235, IntegerToLocalizedStringTransformer::ROUND_FLOOR),
            array('-1234,4', -1235, IntegerToLocalizedStringTransformer::ROUND_FLOOR),
            // away from zero (1.6 -> 2, -1.6 -> 2)
            array('1234,5', 1235, IntegerToLocalizedStringTransformer::ROUND_UP),
            array('1234,4', 1235, IntegerToLocalizedStringTransformer::ROUND_UP),
            array('-1234,5', -1235, IntegerToLocalizedStringTransformer::ROUND_UP),
            array('-1234,4', -1235, IntegerToLocalizedStringTransformer::ROUND_UP),
            // towards zero (1.6 -> 1, -1.6 -> -1)
            array('1234,5', 1234, IntegerToLocalizedStringTransformer::ROUND_DOWN),
            array('1234,4', 1234, IntegerToLocalizedStringTransformer::ROUND_DOWN),
            array('-1234,5', -1234, IntegerToLocalizedStringTransformer::ROUND_DOWN),
            array('-1234,4', -1234, IntegerToLocalizedStringTransformer::ROUND_DOWN),
            // round halves (.5) to the next even number
            array('1234,6', 1235, IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array('1234,5', 1234, IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array('1234,4', 1234, IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array('1233,5', 1234, IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array('1232,5', 1232, IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array('-1234,6', -1235, IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array('-1234,5', -1234, IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array('-1234,4', -1234, IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array('-1233,5', -1234, IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN),
            array('-1232,5', -1232, IntegerToLocalizedStringTransformer::ROUND_HALF_EVEN),
            // round halves (.5) away from zero
            array('1234,6', 1235, IntegerToLocalizedStringTransformer::ROUND_HALF_UP),
            array('1234,5', 1235, IntegerToLocalizedStringTransformer::ROUND_HALF_UP),
            array('1234,4', 1234, IntegerToLocalizedStringTransformer::ROUND_HALF_UP),
            array('-1234,6', -1235, IntegerToLocalizedStringTransformer::ROUND_HALF_UP),
            array('-1234,5', -1235, IntegerToLocalizedStringTransformer::ROUND_HALF_UP),
            array('-1234,4', -1234, IntegerToLocalizedStringTransformer::ROUND_HALF_UP),
            // round halves (.5) towards zero
            array('1234,6', 1235, IntegerToLocalizedStringTransformer::ROUND_HALF_DOWN),
            array('1234,5', 1234, IntegerToLocalizedStringTransformer::ROUND_HALF_DOWN),
            array('1234,4', 1234, IntegerToLocalizedStringTransformer::ROUND_HALF_DOWN),
            array('-1234,6', -1235, IntegerToLocalizedStringTransformer::ROUND_HALF_DOWN),
            array('-1234,5', -1234, IntegerToLocalizedStringTransformer::ROUND_HALF_DOWN),
            array('-1234,4', -1234, IntegerToLocalizedStringTransformer::ROUND_HALF_DOWN),
        );
    }

    /**
     * @dataProvider reverseTransformWithRoundingProvider
     */
    public function testReverseTransformWithRounding($input, $output, $roundingMode)
    {
        $transformer = new IntegerToLocalizedStringTransformer(null, null, $roundingMode);

        $this->assertEquals($output, $transformer->reverseTransform($input));
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
