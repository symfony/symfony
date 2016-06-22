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

use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\Extension\Core\DataTransformer\ChoicesToValuesTransformer;

class ChoicesToValuesTransformerTest extends \PHPUnit_Framework_TestCase
{
    protected $transformer;
    protected $transformerWithNull;

    protected function setUp()
    {
        $list = new ArrayChoiceList(array('', false, 'X'));
        $listWithNull = new ArrayChoiceList(array('', false, 'X', null));

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
        $in = array('', false, 'X');
        $out = array('', '0', 'X');

        $this->assertSame($out, $this->transformer->transform($in));

        $in[] = null;
        $outWithNull = array('0', '1', '2', '3');

        $this->assertSame($outWithNull, $this->transformerWithNull->transform($in));
    }

    public function testTransformNull()
    {
        $this->assertSame(array(), $this->transformer->transform(null));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testTransformExpectsArray()
    {
        $this->transformer->transform('foobar');
    }

    public function testReverseTransform()
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

    public function testReverseTransformNull()
    {
        $this->assertSame(array(), $this->transformer->reverseTransform(null));
        $this->assertSame(array(), $this->transformerWithNull->reverseTransform(null));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformExpectsArray()
    {
        $this->transformer->reverseTransform('foobar');
    }
}
