<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\DataTransformer;

use Symfony\Component\Form\Extension\Core\DataTransformer\ArrayToPartsTransformer;

class ArrayToPartsTransformerTest extends \PHPUnit_Framework_TestCase
{
    private $transformer;

    protected function setUp()
    {
        $this->transformer = new ArrayToPartsTransformer(array(
            'first' => array('a', 'b', 'c'),
            'second' => array('d', 'e', 'f'),
        ));
    }

    protected function tearDown()
    {
        $this->transformer = null;
    }

    public function testTransform()
    {
        $input = array(
            'a' => '1',
            'b' => '2',
            'c' => '3',
            'd' => '4',
            'e' => '5',
            'f' => '6',
        );

        $output = array(
            'first' => array(
                'a' => '1',
                'b' => '2',
                'c' => '3',
            ),
            'second' => array(
                'd' => '4',
                'e' => '5',
                'f' => '6',
            ),
        );

        $this->assertSame($output, $this->transformer->transform($input));
    }

    public function testTransformEmpty()
    {
        $output = array(
            'first' => null,
            'second' => null,
        );

        $this->assertSame($output, $this->transformer->transform(null));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testTransformRequiresArray()
    {
        $this->transformer->transform('12345');
    }

    public function testReverseTransform()
    {
        $input = array(
            'first' => array(
                'a' => '1',
                'b' => '2',
                'c' => '3',
            ),
            'second' => array(
                'd' => '4',
                'e' => '5',
                'f' => '6',
            ),
        );

        $output = array(
            'a' => '1',
            'b' => '2',
            'c' => '3',
            'd' => '4',
            'e' => '5',
            'f' => '6',
        );

        $this->assertSame($output, $this->transformer->reverseTransform($input));
    }

    public function testReverseTransformCompletelyEmpty()
    {
        $input = array(
            'first' => '',
            'second' => '',
        );

        $this->assertNull($this->transformer->reverseTransform($input));
    }

    public function testReverseTransformCompletelyNull()
    {
        $input = array(
            'first' => null,
            'second' => null,
        );

        $this->assertNull($this->transformer->reverseTransform($input));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformPartiallyNull()
    {
        $input = array(
            'first' => array(
                'a' => '1',
                'b' => '2',
                'c' => '3',
            ),
            'second' => null,
        );

        $this->transformer->reverseTransform($input);
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testReverseTransformRequiresArray()
    {
        $this->transformer->reverseTransform('12345');
    }
}
