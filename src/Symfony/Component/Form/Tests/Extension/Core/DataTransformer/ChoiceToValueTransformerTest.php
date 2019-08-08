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
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\Extension\Core\DataTransformer\ChoiceToValueTransformer;

class ChoiceToValueTransformerTest extends TestCase
{
    protected $transformer;
    protected $transformerWithNull;

    protected function setUp(): void
    {
        $list = new ArrayChoiceList(['', false, 'X', true]);
        $listWithNull = new ArrayChoiceList(['', false, 'X', null]);

        $this->transformer = new ChoiceToValueTransformer($list);
        $this->transformerWithNull = new ChoiceToValueTransformer($listWithNull);
    }

    protected function tearDown(): void
    {
        $this->transformer = null;
        $this->transformerWithNull = null;
    }

    public function transformProvider()
    {
        return [
            // more extensive test set can be found in FormUtilTest
            ['', '', '', '0'],
            [false, '0', false, '1'],
            ['X', 'X', 'X', '2'],
            [true, '1', null, '3'],
        ];
    }

    /**
     * @dataProvider transformProvider
     */
    public function testTransform($in, $out, $inWithNull, $outWithNull)
    {
        $this->assertSame($out, $this->transformer->transform($in));
        $this->assertSame($outWithNull, $this->transformerWithNull->transform($inWithNull));
    }

    public function reverseTransformProvider()
    {
        return [
            // values are expected to be valid choice keys already and stay
            // the same
            ['', '', '0', ''],
            ['0', false, '1', false],
            ['X', 'X', '2', 'X'],
            ['1', true, '3', null],
        ];
    }

    /**
     * @dataProvider reverseTransformProvider
     */
    public function testReverseTransform($in, $out, $inWithNull, $outWithNull)
    {
        $this->assertSame($out, $this->transformer->reverseTransform($in));
        $this->assertSame($outWithNull, $this->transformerWithNull->reverseTransform($inWithNull));
    }

    public function reverseTransformExpectsStringOrNullProvider()
    {
        return [
            [0],
            [true],
            [false],
            [[]],
        ];
    }

    /**
     * @dataProvider reverseTransformExpectsStringOrNullProvider
     */
    public function testReverseTransformExpectsStringOrNull($value)
    {
        $this->expectException('Symfony\Component\Form\Exception\TransformationFailedException');
        $this->transformer->reverseTransform($value);
    }
}
