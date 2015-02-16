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

use Symfony\Component\Form\Extension\Core\DataTransformer\BooleanToStringTransformer;

class BooleanToStringTransformerTest extends \PHPUnit_Framework_TestCase
{
    const TRUE_VALUE = '1';

    /**
     * @var BooleanToStringTransformer
     */
    protected $transformer;

    /**
     * @var BooleanToStringTransformer
     */
    protected $transformerStrict;

    protected function setUp()
    {
        $this->transformer = new BooleanToStringTransformer(self::TRUE_VALUE);
        $this->transformerStrict = new BooleanToStringTransformer(self::TRUE_VALUE, true);
    }

    protected function tearDown()
    {
        $this->transformer = null;
        $this->transformerStrict = null;
    }

    public function testTransform()
    {
        $this->assertEquals(self::TRUE_VALUE, $this->transformer->transform(true));
        $this->assertNull($this->transformer->transform(false));
    }

    // https://github.com/symfony/symfony/issues/8989
    public function testTransformAcceptsNull()
    {
        $this->assertNull($this->transformer->transform(null));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testTransformFailsIfString()
    {
        $this->transformer->transform('1');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformFailsIfInteger()
    {
        $this->transformer->reverseTransform(1);
    }

    /**
     * @dataProvider reverseTransformDataProvider
     */
    public function testReverseTransform($value, $assertion, $strict)
    {
        $transformer = $strict ? $this->transformerStrict : $this->transformer;

        if ($assertion) {
            $this->assertTrue($transformer->reverseTransform($value));
        } else {
            $this->assertFalse($transformer->reverseTransform($value));
        }
    }

    public function reverseTransformDataProvider()
    {
        return array(
            array(self::TRUE_VALUE, true, false),
            array('foobar', true, false),
            array('', true, false),
            array(null, false, false),
            array(self::TRUE_VALUE, true, true),
            array('foobar', false, true),
            array('', false, true),
            array(null, false, true),
        );
    }
}
