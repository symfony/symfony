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
use Symfony\Component\Form\Extension\Core\DataTransformer\ValueToDuplicatesTransformer;

class ValueToDuplicatesTransformerTest extends TestCase
{
    private $transformer;
    private $transformerWithSimpleCallable;

    protected function setUp()
    {
        $this->transformer = new ValueToDuplicatesTransformer(array('a', 'b', 'c'));
        $this->transformerWithSimpleCallable = new ValueToDuplicatesTransformer(array('a', 'b', 'c'), function ($value1, $value2) {
            return $value1 === $value2;
        });
    }

    protected function tearDown()
    {
        $this->transformer = null;
        $this->transformerWithSimpleCallable = null;
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

    public function testTransformWithSimpleCallable()
    {
        $output = array(
            'a' => 'Foo',
            'b' => 'Foo',
            'c' => 'Foo',
        );

        $this->assertSame($output, $this->transformerWithSimpleCallable->transform('Foo'));
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

    public function testTransformEmptyWithSimpleCallable()
    {
        $output = array(
            'a' => null,
            'b' => null,
            'c' => null,
        );

        $this->assertSame($output, $this->transformerWithSimpleCallable->transform(null));
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

    public function testReverseTransformWithSimpleCallable()
    {
        $input = array(
            'a' => 'Foo',
            'b' => 'Foo',
            'c' => 'Foo',
        );

        $this->assertSame('Foo', $this->transformerWithSimpleCallable->reverseTransform($input));
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
    
    public function testReverseTransformCompletelyEmptyWithSimpleCallable()
    {
        $input = array(
            'a' => '',
            'b' => '',
            'c' => '',
        );

        $this->assertNull($this->transformerWithSimpleCallable->reverseTransform($input));
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

    public function testReverseTransformCompletelyNullWithSimpleCallable()
    {
        $input = array(
            'a' => null,
            'b' => null,
            'c' => null,
        );

        $this->assertNull($this->transformerWithSimpleCallable->reverseTransform($input));
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

    public function testReverseTransformEmptyArrayWithSimpleCallable()
    {
        $input = array(
            'a' => array(),
            'b' => array(),
            'c' => array(),
        );

        $this->assertNull($this->transformerWithSimpleCallable->reverseTransform($input));
    }

    public function testReverseTransformZeroString()
    {
        $input = array(
            'a' => '0',
            'b' => '0',
            'c' => '0',
        );

        $this->assertSame('0', $this->transformer->reverseTransform($input));
    }

    public function testReverseTransformZeroStringWithSimpleCallable()
    {
        $input = array(
            'a' => '0',
            'b' => '0',
            'c' => '0',
        );

        $this->assertSame('0', $this->transformerWithSimpleCallable->reverseTransform($input));
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
    public function testReverseTransformPartiallyNullWithSimpleCallable()
    {
        $input = array(
            'a' => 'Foo',
            'b' => 'Foo',
            'c' => null,
        );

        $this->transformerWithSimpleCallable->reverseTransform($input);
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
    public function testReverseTransformDifferencesWithSimpleCallable()
    {
        $input = array(
            'a' => 'Foo',
            'b' => 'Bar',
            'c' => 'Foo',
        );

        $this->transformerWithSimpleCallable->reverseTransform($input);
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformRequiresArray()
    {
        $this->transformer->reverseTransform('12345');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformRequiresArrayWithSimpleCallable()
    {
        $this->transformerWithSimpleCallable->reverseTransform('12345');
    }
}
