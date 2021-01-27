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
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\DataTransformer\ValueToDuplicatesTransformer;

class ValueToDuplicatesTransformerTest extends TestCase
{
    private $transformer;

    protected function setUp(): void
    {
        $this->transformer = new ValueToDuplicatesTransformer(['a', 'b', 'c']);
    }

    protected function tearDown(): void
    {
        $this->transformer = null;
    }

    public function testTransform()
    {
        $output = [
            'a' => 'Foo',
            'b' => 'Foo',
            'c' => 'Foo',
        ];

        $this->assertSame($output, $this->transformer->transform('Foo'));
    }

    public function testTransformEmpty()
    {
        $output = [
            'a' => null,
            'b' => null,
            'c' => null,
        ];

        $this->assertSame($output, $this->transformer->transform(null));
    }

    public function testReverseTransform()
    {
        $input = [
            'a' => 'Foo',
            'b' => 'Foo',
            'c' => 'Foo',
        ];

        $this->assertSame('Foo', $this->transformer->reverseTransform($input));
    }

    public function testReverseTransformCompletelyEmpty()
    {
        $input = [
            'a' => '',
            'b' => '',
            'c' => '',
        ];

        $this->assertNull($this->transformer->reverseTransform($input));
    }

    public function testReverseTransformCompletelyNull()
    {
        $input = [
            'a' => null,
            'b' => null,
            'c' => null,
        ];

        $this->assertNull($this->transformer->reverseTransform($input));
    }

    public function testReverseTransformEmptyArray()
    {
        $input = [
            'a' => [],
            'b' => [],
            'c' => [],
        ];

        $this->assertNull($this->transformer->reverseTransform($input));
    }

    public function testReverseTransformZeroString()
    {
        $input = [
            'a' => '0',
            'b' => '0',
            'c' => '0',
        ];

        $this->assertSame('0', $this->transformer->reverseTransform($input));
    }

    public function testReverseTransformPartiallyNull()
    {
        $this->expectException(TransformationFailedException::class);
        $input = [
            'a' => 'Foo',
            'b' => 'Foo',
            'c' => null,
        ];

        $this->transformer->reverseTransform($input);
    }

    public function testReverseTransformDifferences()
    {
        $this->expectException(TransformationFailedException::class);
        $input = [
            'a' => 'Foo',
            'b' => 'Bar',
            'c' => 'Foo',
        ];

        $this->transformer->reverseTransform($input);
    }

    public function testReverseTransformRequiresArray()
    {
        $this->expectException(TransformationFailedException::class);
        $this->transformer->reverseTransform('12345');
    }
}
