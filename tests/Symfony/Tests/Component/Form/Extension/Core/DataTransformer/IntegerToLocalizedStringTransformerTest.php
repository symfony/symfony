<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Extension\Core\DataTransformer;

require_once __DIR__ . '/LocalizedTestCase.php';

use Symfony\Component\Form\Extension\Core\DataTransformer\IntegerToLocalizedStringTransformer;

class IntegerToLocalizedStringTransformerTest extends LocalizedTestCase
{
    protected function setUp()
    {
        parent::setUp();

        \Locale::setDefault('de_AT');
    }

    public function testReverseTransform()
    {
        $transformer = new IntegerToLocalizedStringTransformer();

        $this->assertEquals(1, $transformer->reverseTransform('1'));
        $this->assertEquals(1, $transformer->reverseTransform('1,5'));
        $this->assertEquals(1234, $transformer->reverseTransform('1234,5'));
        $this->assertEquals(12345, $transformer->reverseTransform('12345,912'));
    }

    public function testReverseTransform_empty()
    {
        $transformer = new IntegerToLocalizedStringTransformer();

        $this->assertSame(null, $transformer->reverseTransform(''));
    }

    public function testReverseTransformWithGrouping()
    {
        $transformer = new IntegerToLocalizedStringTransformer(null, true);

        $this->assertEquals(1234, $transformer->reverseTransform('1.234,5'));
        $this->assertEquals(12345, $transformer->reverseTransform('12.345,912'));
        $this->assertEquals(1234, $transformer->reverseTransform('1234,5'));
        $this->assertEquals(12345, $transformer->reverseTransform('12345,912'));
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testReverseTransformExpectsString()
    {
        $transformer = new IntegerToLocalizedStringTransformer();

        $transformer->reverseTransform(1);
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformExpectsValidNumber()
    {
        $transformer = new IntegerToLocalizedStringTransformer();

        $transformer->reverseTransform('foo');
    }
}
