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
use Symfony\Component\Form\Extension\Core\DataTransformer\ArrayToPartsTransformer;

class ArrayToPartsTransformerTest extends TestCase
{
    private ArrayToPartsTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new ArrayToPartsTransformer([
            'first' => ['a', 'b', 'c'],
            'second' => ['d', 'e', 'f'],
        ]);
    }

    public function testTransform(): void
    {
        $input = [
            'a' => '1',
            'b' => '2',
            'c' => '3',
            'd' => '4',
            'e' => '5',
            'f' => '6',
        ];

        $output = [
            'first' => [
                'a' => '1',
                'b' => '2',
                'c' => '3',
            ],
            'second' => [
                'd' => '4',
                'e' => '5',
                'f' => '6',
            ],
        ];

        $this->assertSame($output, $this->transformer->transform($input));
    }

    public function testTransformEmpty(): void
    {
        $output = [
            'first' => null,
            'second' => null,
        ];

        $this->assertSame($output, $this->transformer->transform(null));
    }

    public function testTransformRequiresArray(): void
    {
        $this->expectException(TransformationFailedException::class);
        $this->transformer->transform('12345');
    }

    public function testReverseTransform(): void
    {
        $input = [
            'first' => [
                'a' => '1',
                'b' => '2',
                'c' => '3',
            ],
            'second' => [
                'd' => '4',
                'e' => '5',
                'f' => '6',
            ],
        ];

        $output = [
            'a' => '1',
            'b' => '2',
            'c' => '3',
            'd' => '4',
            'e' => '5',
            'f' => '6',
        ];

        $this->assertSame($output, $this->transformer->reverseTransform($input));
    }

    public function testReverseTransformCompletelyEmpty(): void
    {
        $input = [
            'first' => '',
            'second' => '',
        ];

        $this->assertNull($this->transformer->reverseTransform($input));
    }

    public function testReverseTransformCompletelyNull(): void
    {
        $input = [
            'first' => null,
            'second' => null,
        ];

        $this->assertNull($this->transformer->reverseTransform($input));
    }

    public function testReverseTransformPartiallyNull(): void
    {
        $this->expectException(TransformationFailedException::class);
        $input = [
            'first' => [
                'a' => '1',
                'b' => '2',
                'c' => '3',
            ],
            'second' => null,
        ];

        $this->transformer->reverseTransform($input);
    }

    public function testReverseTransformRequiresArray(): void
    {
        $this->expectException(TransformationFailedException::class);
        $this->transformer->reverseTransform('12345');
    }
}
