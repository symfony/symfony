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

use Symfony\Component\Form\Extension\Core\DataTransformer\ValuesToStringTransformer;

class ValuesToStringTransformerTest extends \PHPUnit_Framework_TestCase
{
    private $transformer;

    protected function setUp()
    {
        $this->transformer = new ValuesToStringTransformer(',', true);
    }

    protected function tearDown()
    {
        $this->transformer = null;
    }

    public function testTransform()
    {
        $output = 'a,b,c';

        $this->assertSame($output, $this->transformer->transform(array('a', 'b', 'c')));
    }

    public function testTransformNull()
    {
        $this->assertSame('', $this->transformer->transform(null));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformRequiresAnArray()
    {
        $this->transformer->transform('a, b, c');
    }

    public function testReverseTransform()
    {
        $input = 'a, b  ,c ';

        $this->assertSame(array('a', 'b', 'c'), $this->transformer->reverseTransform($input));
    }

    public function testReverseTransformEmpty()
    {
        $input = '';

        $this->assertSame(array(), $this->transformer->reverseTransform($input));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformRequiresAString()
    {
        $this->transformer->reverseTransform(array('a', 'b', 'c'));
    }
}
