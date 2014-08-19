<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\DataTransformer;

use Symfony\Component\Form\Extension\Core\DataTransformer\ValueToDuplicatesTransformer;

class ValueToDuplicatesTransformerTest extends \PHPUnit_Framework_TestCase
{
    private $transformer;

    protected function setUp()
    {
        $this->transformer = new ValueToDuplicatesTransformer(array('a', 'b', 'c'));
    }

    protected function tearDown()
    {
        $this->transformer = null;
    }

    public function testTransform()
    {
        $output = array(
            'a' => 'Foo',
            'b' => 'Foo',
            'c' => 'Foo',
        );

        $this->assertSame($output, $this->transformer->transform('Foo'));
    }

    public function testTransformEmpty()
    {
        $output = array(
            'a' => null,
            'b' => null,
            'c' => null,
        );

        $this->assertSame($output, $this->transformer->transform(null));
    }

    public function testReverseTransform()
    {
        $input = array(
            'a' => 'Foo',
            'b' => 'Foo',
            'c' => 'Foo',
        );

        $this->assertSame('Foo', $this->transformer->reverseTransform($input));
    }

    public function testReverseTransformCompletelyEmpty()
    {
        $input = array(
            'a' => '',
            'b' => '',
            'c' => '',
        );

        $this->assertNull($this->transformer->reverseTransform($input));
    }

    public function testReverseTransformCompletelyNull()
    {
        $input = array(
            'a' => null,
            'b' => null,
            'c' => null,
        );

        $this->assertNull($this->transformer->reverseTransform($input));
    }

    public function testReverseTransformEmptyArray()
    {
        $input = array(
            'a' => array(),
            'b' => array(),
            'c' => array(),
        );

        $this->assertNull($this->transformer->reverseTransform($input));
    }


    public function testReverseTransformZeroString()
    {
        $input = array(
            'a' => '0',
            'b' => '0',
            'c' => '0'
        );

        $this->assertSame('0', $this->transformer->reverseTransform($input));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformPartiallyNull()
    {
        $input = array(
            'a' => 'Foo',
            'b' => 'Foo',
            'c' => null,
        );

        $this->transformer->reverseTransform($input);
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformDifferences()
    {
        $input = array(
            'a' => 'Foo',
            'b' => 'Bar',
            'c' => 'Foo',
        );

        $this->transformer->reverseTransform($input);
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformRequiresArray()
    {
        $this->transformer->reverseTransform('12345');
    }
}
