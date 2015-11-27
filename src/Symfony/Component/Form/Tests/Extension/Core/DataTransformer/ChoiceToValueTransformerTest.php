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
use Symfony\Component\Form\Extension\Core\DataTransformer\ChoiceToValueTransformer;

class ChoiceToValueTransformerTest extends \PHPUnit_Framework_TestCase
{
    protected $transformer;

    protected function setUp()
    {
        $list = new ArrayChoiceList(array('', false, 'X'));

        $this->transformer = new ChoiceToValueTransformer($list);
    }

    protected function tearDown()
    {
        $this->transformer = null;
    }

    public function transformProvider()
    {
        return array(
            // more extensive test set can be found in FormUtilTest
            array('', '0'),
            array(false, '1'),
        );
    }

    /**
     * @dataProvider transformProvider
     */
    public function testTransform($in, $out)
    {
        $this->assertSame($out, $this->transformer->transform($in));
    }

    public function reverseTransformProvider()
    {
        return array(
            // values are expected to be valid choice keys already and stay
            // the same
            array('0', ''),
            array('1', false),
            array('2', 'X'),
        );
    }

    /**
     * @dataProvider reverseTransformProvider
     */
    public function testReverseTransform($in, $out)
    {
        $this->assertSame($out, $this->transformer->reverseTransform($in));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformExpectsScalar()
    {
        $this->transformer->reverseTransform(array());
    }
}
