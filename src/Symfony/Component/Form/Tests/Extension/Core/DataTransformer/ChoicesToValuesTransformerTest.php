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

use Symfony\Component\Form\Extension\Core\ChoiceList\SimpleChoiceList;
use Symfony\Component\Form\Extension\Core\DataTransformer\ChoicesToValuesTransformer;

class ChoicesToValuesTransformerTest extends \PHPUnit_Framework_TestCase
{
    protected $transformer;

    protected function setUp()
    {
        $list = new SimpleChoiceList(array(0 => 'A', 1 => 'B', 2 => 'C'));
        $this->transformer = new ChoicesToValuesTransformer($list);
    }

    protected function tearDown()
    {
        $this->transformer = null;
    }

    public function testTransform()
    {
        // Value strategy in SimpleChoiceList is to copy and convert to string
        $in = array(0, 1, 2);
        $out = array('0', '1', '2');

        $this->assertSame($out, $this->transformer->transform($in));
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
        $in = array('0', '1', '2');
        $out = array(0, 1, 2);

        $this->assertSame($out, $this->transformer->reverseTransform($in));
    }

    public function testReverseTransformNull()
    {
        $this->assertSame(array(), $this->transformer->reverseTransform(null));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformExpectsArray()
    {
        $this->transformer->reverseTransform('foobar');
    }
}
