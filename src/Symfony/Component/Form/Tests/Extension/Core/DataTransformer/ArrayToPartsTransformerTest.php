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
use Symfony\Component\Form\Extension\Core\DataTransformer\ArrayToPartsTransformer;

class ArrayToPartsTransformerTest extends TestCase
{
    private $transformer;

    protected function setUp()
    {
        $this->transformer = new ArrayToPartsTransformer([
            'first' => ['a', 'b', 'c'],
            'second' => ['d', 'e', 'f'],
        ]);
    }

    protected function tearDown()
    {
        $this->transformer = null;
    }

    public function testTransform()
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

    public function testTransformEmpty()
    {
        $output = [
            'first' => null,
            'second' => null,
        ];

        $this->assertSame($output, $this->transformer->transform(null));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testTransformRequiresArray()
    {
        $this->transformer->transform('12345');
    }

    public function testReverseTransform()
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

    public function testReverseTransformCompletelyEmpty()
    {
        $input = [
            'first' => '',
            'second' => '',
        ];

        $this->assertNull($this->transformer->reverseTransform($input));
    }

    public function testReverseTransformCompletelyNull()
    {
        $input = [
            'first' => null,
            'second' => null,
        ];

        $this->assertNull($this->transformer->reverseTransform($input));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformPartiallyNull()
    {
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

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformRequiresArray()
    {
        $this->transformer->reverseTransform('12345');
    }
}
