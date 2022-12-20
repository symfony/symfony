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
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\DataTransformer\ChoicesToValuesTransformer;

class ChoicesToValuesTransformerTest extends TestCase
{
    protected $transformer;
    protected $transformerWithNull;

    protected function setUp(): void
    {
        $list = new ArrayChoiceList(['', false, 'X']);
        $listWithNull = new ArrayChoiceList(['', false, 'X', null]);

        $this->transformer = new ChoicesToValuesTransformer($list);
        $this->transformerWithNull = new ChoicesToValuesTransformer($listWithNull);
    }

    protected function tearDown(): void
    {
        $this->transformer = null;
        $this->transformerWithNull = null;
    }

    public function testTransform()
    {
        $in = ['', false, 'X'];
        $out = ['', '0', 'X'];

        self::assertSame($out, $this->transformer->transform($in));

        $in[] = null;
        $outWithNull = ['0', '1', '2', '3'];

        self::assertSame($outWithNull, $this->transformerWithNull->transform($in));
    }

    public function testTransformNull()
    {
        self::assertSame([], $this->transformer->transform(null));
    }

    public function testTransformExpectsArray()
    {
        self::expectException(TransformationFailedException::class);
        $this->transformer->transform('foobar');
    }

    public function testReverseTransform()
    {
        // values are expected to be valid choices and stay the same
        $in = ['', '0', 'X'];
        $out = ['', false, 'X'];

        self::assertSame($out, $this->transformer->reverseTransform($in));
        // values are expected to be valid choices and stay the same
        $inWithNull = ['0', '1', '2', '3'];
        $out[] = null;

        self::assertSame($out, $this->transformerWithNull->reverseTransform($inWithNull));
    }

    public function testReverseTransformNull()
    {
        self::assertSame([], $this->transformer->reverseTransform(null));
        self::assertSame([], $this->transformerWithNull->reverseTransform(null));
    }

    public function testReverseTransformExpectsArray()
    {
        self::expectException(TransformationFailedException::class);
        $this->transformer->reverseTransform('foobar');
    }
}
