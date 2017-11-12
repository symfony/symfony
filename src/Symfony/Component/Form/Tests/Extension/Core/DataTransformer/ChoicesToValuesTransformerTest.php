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

    protected function setUp(): void
    {
        $list = new ArrayChoiceList(array('', false, 'X'));
        $listWithNull = new ArrayChoiceList(array('', false, 'X', null));

        $this->transformer = new ChoicesToValuesTransformer($list);
        $this->transformerWithNull = new ChoicesToValuesTransformer($listWithNull);
    }

    protected function tearDown(): void
    {
        $this->transformer = null;
        $this->transformerWithNull = null;
    }

    public function testTransform(): void
    {
        $in = array('', false, 'X');
        $out = array('', '0', 'X');

        $this->assertSame($out, $this->transformer->transform($in));

        $in[] = null;
        $outWithNull = array('0', '1', '2', '3');

        $this->assertSame($outWithNull, $this->transformerWithNull->transform($in));
    }

    public function testTransformNull(): void
    {
        $this->assertSame(array(), $this->transformer->transform(null));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testTransformExpectsArray(): void
    {
        $this->transformer->transform('foobar');
    }

    public function testReverseTransform(): void
    {
        // values are expected to be valid choices and stay the same
        $in = array('', '0', 'X');
        $out = array('', false, 'X');

        $this->assertSame($out, $this->transformer->reverseTransform($in));
        // values are expected to be valid choices and stay the same
        $inWithNull = array('0', '1', '2', '3');
        $out[] = null;

        $this->assertSame($out, $this->transformerWithNull->reverseTransform($inWithNull));
    }

    public function testReverseTransformNull(): void
    {
        $this->assertSame(array(), $this->transformer->reverseTransform(null));
        $this->assertSame(array(), $this->transformerWithNull->reverseTransform(null));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformExpectsArray(): void
    {
        $this->transformer->reverseTransform('foobar');
    }
}
