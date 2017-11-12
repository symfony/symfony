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

    protected function setUp(): void
    {
        $this->transformer = new ValueToDuplicatesTransformer(array('a', 'b', 'c'));
    }

    protected function tearDown(): void
    {
        $this->transformer = null;
    }

    public function testTransform(): void
    {
        $output = array(
            'a' => 'Foo',
            'b' => 'Foo',
            'c' => 'Foo',
        );

        $this->assertSame($output, $this->transformer->transform('Foo'));
    }

    public function testTransformEmpty(): void
    {
        $output = array(
            'a' => null,
            'b' => null,
            'c' => null,
        );

        $this->assertSame($output, $this->transformer->transform(null));
    }

    public function testReverseTransform(): void
    {
        $input = array(
            'a' => 'Foo',
            'b' => 'Foo',
            'c' => 'Foo',
        );

        $this->assertSame('Foo', $this->transformer->reverseTransform($input));
    }

    public function testReverseTransformCompletelyEmpty(): void
    {
        $input = array(
            'a' => '',
            'b' => '',
            'c' => '',
        );

        $this->assertNull($this->transformer->reverseTransform($input));
    }

    public function testReverseTransformCompletelyNull(): void
    {
        $input = array(
            'a' => null,
            'b' => null,
            'c' => null,
        );

        $this->assertNull($this->transformer->reverseTransform($input));
    }

    public function testReverseTransformEmptyArray(): void
    {
        $input = array(
            'a' => array(),
            'b' => array(),
            'c' => array(),
        );

        $this->assertNull($this->transformer->reverseTransform($input));
    }

    public function testReverseTransformZeroString(): void
    {
        $input = array(
            'a' => '0',
            'b' => '0',
            'c' => '0',
        );

        $this->assertSame('0', $this->transformer->reverseTransform($input));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformPartiallyNull(): void
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
    public function testReverseTransformDifferences(): void
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
    public function testReverseTransformRequiresArray(): void
    {
        $this->transformer->reverseTransform('12345');
    }
}
