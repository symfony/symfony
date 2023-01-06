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
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Form\DataTransformer\CollectionToArrayTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CollectionToArrayTransformerTest extends TestCase
{
    /**
     * @var CollectionToArrayTransformer
     */
    private $transformer;

    protected function setUp(): void
    {
        $this->transformer = new CollectionToArrayTransformer();
    }

    public function testTransform()
    {
        $array = [
            2 => 'foo',
            3 => 'bar',
        ];

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
        $array = [
            2 => 'foo',
            3 => 'bar',
        ];

        $this->assertSame($array, $this->transformer->transform($array));
    }

    public function testTransformNull()
    {
        $this->assertSame([], $this->transformer->transform(null));
    }

    public function testTransformExpectsArrayOrCollection()
    {
        $this->expectException(TransformationFailedException::class);
        $this->transformer->transform('Foo');
    }

    public function testReverseTransform()
    {
        $array = [
            2 => 'foo',
            3 => 'bar',
        ];

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
