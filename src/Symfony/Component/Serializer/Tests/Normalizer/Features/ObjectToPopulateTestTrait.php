<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer\Features;

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Tests\Fixtures\DeepObjectPopulateChildDummy;
use Symfony\Component\Serializer\Tests\Fixtures\DeepObjectPopulateParentDummy;
use Symfony\Component\Serializer\Tests\Fixtures\ProxyDummy;
use Symfony\Component\Serializer\Tests\Fixtures\ToBeProxyfiedDummy;

trait ObjectToPopulateTestTrait
{
    abstract protected function getDenormalizerForObjectToPopulate(): DenormalizerInterface;

    public function testObjectToPopulate()
    {
        $dummy = new ObjectDummy();
        $dummy->bar = 'bar';

        $denormalizer = $this->getDenormalizerForObjectToPopulate();

        $obj = $denormalizer->denormalize(
            ['foo' => 'foo'],
            ObjectDummy::class,
            null,
            ['object_to_populate' => $dummy]
        );

        $this->assertEquals($dummy, $obj);
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertEquals('bar', $obj->bar);
    }

    public function testObjectToPopulateWithProxy()
    {
        $proxyDummy = new ProxyDummy();

        $context = ['object_to_populate' => $proxyDummy];

        $denormalizer = $this->getDenormalizerForObjectToPopulate();
        $denormalizer->denormalize(['foo' => 'bar'], ToBeProxyfiedDummy::class, null, $context);

        $this->assertSame('bar', $proxyDummy->getFoo());
    }

    public function testObjectToPopulateNoMatch()
    {
        $this->markTestSkipped('something broken here!');
        $denormalizer = $this->getDenormalizerForObjectToPopulate();

        $objectToPopulate = new ObjectInner();
        $objectToPopulate->foo = 'foo';

        $outer = $denormalizer->denormalize([
            'foo' => 'foo',
            'inner' => [
                'bar' => 'bar',
            ],
        ], ObjectOuter::class, null, ['object_to_popuplate' => $objectToPopulate]);

        $this->assertInstanceOf(ObjectOuter::class, $outer);
        $inner = $outer->getInner();
        $this->assertInstanceOf(ObjectInner::class, $inner);
        $this->assertNotSame($objectToPopulate, $inner);
        $this->assertSame('bar', $inner->bar);
        $this->assertNull($inner->foo);
    }

    public function testDeepObjectToPopulate()
    {
        $child = new DeepObjectPopulateChildDummy();
        $child->bar = 'bar-old';
        $child->foo = 'foo-old';

        $parent = new DeepObjectPopulateParentDummy();
        $parent->setChild($child);

        $context = [
            'object_to_populate' => $parent,
            'deep_object_to_populate' => true,
        ];

        $normalizer = $this->getDenormalizerForObjectToPopulate();

        $newChild = new DeepObjectPopulateChildDummy();
        $newChild->bar = 'bar-new';
        $newChild->foo = 'foo-old';

        $normalizer->denormalize([
            'child' => [
                'bar' => 'bar-new',
            ],
        ], DeepObjectPopulateParentDummy::class, null, $context);

        $this->assertSame('bar-new', $parent->getChild()->bar);
        $this->assertSame('foo-old', $parent->getChild()->foo);
    }
}
