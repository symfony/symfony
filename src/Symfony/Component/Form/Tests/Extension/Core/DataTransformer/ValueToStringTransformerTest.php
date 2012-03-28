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

use Symfony\Component\Form\Extension\Core\DataTransformer\ValueToStringTransformer;

class ValueToStringTransformerTest extends \PHPUnit_Framework_TestCase
{
    protected $transformer;

    protected function setUp()
    {
        $this->transformer = new ValueToStringTransformer();
    }

    protected function tearDown()
    {
        $this->transformer = null;
    }

    /**
     * @dataProvider validDataProvider
     */
    public function testTransform($value, $transformed)
    {
        $this->assertEquals($transformed, $this->transformer->transform($value));
    }

    /**
     * @dataProvider validDataProvider
     */
    public function testReverseTransform($value, $transformed)
    {
        $this->assertEquals($transformed, $this->transformer->reverseTransform($transformed));
    }

    public function validDataProvider()
    {
        return array(
            array('test', 'test'),
            array('', null),
            array(null, null),

            array(0, '0'),
            array('0', '0'),
            array(1, '1'),
            array('123', '123'),
            array(1.23, '1.23'),
        );
    }

    /**
     * @dataProvider invalidDataProvider
     */
    public function testTransformExpectsStringOrNumber($value)
    {
        $this->setExpectedException('Symfony\Component\Form\Exception\UnexpectedTypeException');

        $this->transformer->transform($value);
    }

    /**
     * @dataProvider invalidDataProvider
     */
    public function testReverseTransformExpectsString($value)
    {
        $this->setExpectedException('Symfony\Component\Form\Exception\UnexpectedTypeException');

        $this->transformer->reverseTransform($value);
    }

    public function invalidDataProvider()
    {
        return array(
            array(true),
            array(false),
            array(new \stdClass),
            array(array()),
        );
    }
}
