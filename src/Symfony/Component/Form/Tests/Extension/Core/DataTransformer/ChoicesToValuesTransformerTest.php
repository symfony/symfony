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
use Symfony\Component\Form\Extension\Core\DataTransformer\ChoicesToValuesTransformer;

class ChoicesToValuesTransformerTest extends TestCase
{
    protected $transformer;
    protected $transformerWithNull;

    protected function setUp()
    {
        $list = new ArrayChoiceList(['', false, 'X']);
        $listWithNull = new ArrayChoiceList(['', false, 'X', null]);

        $this->transformer = new ChoicesToValuesTransformer($list);
        $this->transformerWithNull = new ChoicesToValuesTransformer($listWithNull);
    }

    protected function tearDown()
    {
        $this->transformer = null;
        $this->transformerWithNull = null;
    }

    public function testTransform()
    {
        $in = ['', false, 'X'];
        $out = ['', '0', 'X'];

        $this->assertSame($out, $this->transformer->transform($in));

        $in[] = null;
        $outWithNull = ['0', '1', '2', '3'];

        $this->assertSame($outWithNull, $this->transformerWithNull->transform($in));
    }

    public function testTransformNull()
    {
        $this->assertSame([], $this->transformer->transform(null));
    }

    public function testTransformExpectsArray()
    {
        $this->expectException('Symfony\Component\Form\Exception\TransformationFailedException');
        $this->transformer->transform('foobar');
    }

    public function testReverseTransform()
    {
        // values are expected to be valid choices and stay the same
        $in = ['', '0', 'X'];
        $out = ['', false, 'X'];

        $this->assertSame($out, $this->transformer->reverseTransform($in));
        // values are expected to be valid choices and stay the same
        $inWithNull = ['0', '1', '2', '3'];
        $out[] = null;

        $this->assertSame($out, $this->transformerWithNull->reverseTransform($inWithNull));
    }

    public function testReverseTransformNull()
    {
        $this->assertSame([], $this->transformer->reverseTransform(null));
        $this->assertSame([], $this->transformerWithNull->reverseTransform(null));
    }

    public function testReverseTransformExpectsArray()
    {
        $this->expectException('Symfony\Component\Form\Exception\TransformationFailedException');
        $this->transformer->reverseTransform('foobar');
    }
}
