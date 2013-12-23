<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Form\DataTransformer;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bridge\Doctrine\Form\DataTransformer\CollectionToArrayTransformer;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CollectionToArrayTransformerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var CollectionToArrayTransformer
     */
    private $transformer;

    protected function setUp()
    {
        $this->transformer = new CollectionToArrayTransformer();
    }

    public function testTransform()
    {
        $array = array(
            2 => 'foo',
            3 => 'bar',
        );

        $this->assertSame($array, $this->transformer->transform(new ArrayCollection($array)));
    }

    /**
     * This test is needed for cases when getXxxs() in the entity returns the
     * result of $collection->toArray(), in order to prevent modifications of
     * the inner collection.
     *
     * See https://github.com/symfony/symfony/pull/9308
     */
    public function testTransformArray()
    {
        $array = array(
            2 => 'foo',
            3 => 'bar',
        );

        $this->assertSame($array, $this->transformer->transform($array));
    }

    public function testTransformNull()
    {
        $this->assertSame(array(), $this->transformer->transform(null));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testTransformExpectsArrayOrCollection()
    {
        $this->transformer->transform('Foo');
    }

    public function testReverseTransform()
    {
        $array = array(
            2 => 'foo',
            3 => 'bar',
        );

        $this->assertEquals(new ArrayCollection($array), $this->transformer->reverseTransform($array));
    }

    public function testReverseTransformEmpty()
    {
        $this->assertEquals(new ArrayCollection(), $this->transformer->reverseTransform(''));
    }

    public function testReverseTransformNull()
    {
        $this->assertEquals(new ArrayCollection(), $this->transformer->reverseTransform(null));
    }
}
