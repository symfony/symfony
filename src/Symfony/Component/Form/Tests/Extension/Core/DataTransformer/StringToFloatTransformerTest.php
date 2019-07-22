<?php

/*
 * This file is part of the symfony/symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\DataTransformer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\DataTransformer\StringToFloatTransformer;

class StringToFloatTransformerTest extends TestCase
{
    private $transformer;

    protected function setUp()
    {
        $this->transformer = new StringToFloatTransformer();
    }

    protected function tearDown()
    {
        $this->transformer = null;
    }

    public function provideTransformations(): array
    {
        return [
            [null, null],
            ['1', 1.],
            ['1.', 1.],
            ['1.0', 1.],
            ['1.23', 1.23],
        ];
    }

    /**
     * @dataProvider provideTransformations
     */
    public function testTransform($from, $to): void
    {
        $transformer = new StringToFloatTransformer();

        $this->assertSame($to, $transformer->transform($from));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testFailIfTransformingANonString(): void
    {
        $transformer = new StringToFloatTransformer();
        $transformer->transform(1.0);
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testFailIfTransformingANonNumericString(): void
    {
        $transformer = new StringToFloatTransformer();
        $transformer->transform('foobar');
    }

    public function provideReverseTransformations(): array
    {
        return [
            [null, null],
            [1, '1'],
            [1., '1'],
            [1.0, '1'],
            [1.23, '1.23'],
            [1, '1.000', 3],
            [1.0, '1.000', 3],
            [1.23, '1.230', 3],
            [1.2344, '1.234', 3],
            [1.2345, '1.235', 3],
        ];
    }

    /**
     * @dataProvider provideReverseTransformations
     */
    public function testReverseTransform($from, $to, int $scale = null): void
    {
        $transformer = new StringToFloatTransformer($scale);

        $this->assertSame($to, $transformer->reverseTransform($from));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testFailIfReverseTransformingANonNumeric(): void
    {
        $transformer = new StringToFloatTransformer();
        $transformer->reverseTransform('foobar');
    }
}
