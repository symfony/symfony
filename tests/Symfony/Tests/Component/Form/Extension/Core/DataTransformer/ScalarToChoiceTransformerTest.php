<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Extension\Core\DataTransformer;

use Symfony\Component\Form\Extension\Core\DataTransformer\ScalarToChoiceTransformer;

class ScalarToChoiceTransformerTest extends \PHPUnit_Framework_TestCase
{
    protected $transformer;

    protected function setUp()
    {
        $this->transformer = new ScalarToChoiceTransformer();
    }

    protected function tearDown()
    {
        $this->transformer = null;
    }

    public function transformProvider()
    {
        return array(
            // more extensive test set can be found in FormUtilTest
            array(0, 0),
            array(false, 0),
            array('', ''),
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
            array(0, 0),
            array('', ''),
        );
    }

    /**
     * @dataProvider reverseTransformProvider
     */
    public function testReverseTransform($in, $out)
    {
        $this->assertSame($out, $this->transformer->transform($in));
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testTransformExpectsScalar()
    {
        $this->transformer->transform(array());
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testReverseTransformExpectsScalar()
    {
        $this->transformer->reverseTransform(array());
    }
}
