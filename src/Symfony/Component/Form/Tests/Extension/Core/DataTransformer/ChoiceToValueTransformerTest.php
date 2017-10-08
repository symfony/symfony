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

    protected function setUp()
    {
        $list = new ArrayChoiceList(array('', false, 'X', true));
        $listWithNull = new ArrayChoiceList(array('', false, 'X', null));

        $this->transformer = new ChoiceToValueTransformer($list);
        $this->transformerWithNull = new ChoiceToValueTransformer($listWithNull);
    }

    protected function tearDown()
    {
        $this->transformer = null;
        $this->transformerWithNull = null;
    }

    public function transformProvider()
    {
        return array(
            // more extensive test set can be found in FormUtilTest
            array('', '', '', '0'),
            array(false, '0', false, '1'),
            array('X', 'X', 'X', '2'),
            array(true, '1', null, '3'),
        );
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
        return array(
            // values are expected to be valid choice keys already and stay
            // the same
            array('', '', '0', ''),
            array('0', false, '1', false),
            array('X', 'X', '2', 'X'),
            array('1', true, '3', null),
        );
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
        return array(
            array(0),
            array(true),
            array(false),
            array(array()),
        );
    }

    /**
     * @dataProvider reverseTransformExpectsStringOrNullProvider
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformExpectsStringOrNull($value)
    {
        $this->transformer->reverseTransform($value);
    }
}
