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

use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Test AbstractNormalizer::ATTRIBUTES and AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES.
 */
trait AttributesTestTrait
{
    abstract protected function getNormalizerForAttributes(): NormalizerInterface;

    abstract protected function getDenormalizerForAttributes(): DenormalizerInterface;

    public function testAttributesNormalize()
    {
        $normalizer = $this->getNormalizerForAttributes();

        $objectInner = new ObjectInner();
        $objectInner->foo = 'innerFoo';
        $objectInner->bar = 'innerBar';

        $objectDummy = new ObjectDummy();
        $objectDummy->setFoo('foo');
        $objectDummy->setBaz(true);
        $objectDummy->setObject($objectInner);

        $context = ['attributes' => ['foo', 'baz', 'object' => ['foo']]];
        $this->assertEquals(
            [
                'foo' => 'foo',
                'baz' => true,
                'object' => ['foo' => 'innerFoo'],
            ],
            $normalizer->normalize($objectDummy, null, $context)
        );

        $context = ['attributes' => ['foo', 'baz', 'object']];
        $this->assertEquals(
            [
                'foo' => 'foo',
                'baz' => true,
                'object' => ['foo' => 'innerFoo', 'bar' => 'innerBar'],
            ],
            $normalizer->normalize($objectDummy, null, $context)
        );
    }

    public function testAttributesContextDenormalize()
    {
        $normalizer = $this->getDenormalizerForAttributes();

        $objectInner = new ObjectInner();
        $objectInner->foo = 'innerFoo';

        $objectOuter = new ObjectOuter();
        $objectOuter->bar = 'bar';
        $objectOuter->setInner($objectInner);

        $context = ['attributes' => ['bar', 'inner' => ['foo']]];
        $this->assertEquals($objectOuter, $normalizer->denormalize(
            [
                'foo' => 'foo',
                'bar' => 'bar',
                'date' => '2017-02-03',
                'inner' => ['foo' => 'innerFoo', 'bar' => 'innerBar'],
            ], ObjectOuter::class, null, $context));
    }

    public function testAttributesContextDenormalizeIgnoreExtraAttributes()
    {
        $normalizer = $this->getDenormalizerForAttributes();

        $objectInner = new ObjectInner();
        $objectInner->foo = 'innerFoo';

        $objectOuter = new ObjectOuter();
        $objectOuter->setInner($objectInner);

        $context = ['attributes' => ['inner' => ['foo']]];
        $this->assertEquals($objectOuter, $normalizer->denormalize(
            [
                'foo' => 'foo',
                'bar' => 'changed',
                'date' => '2017-02-03',
                'inner' => ['foo' => 'innerFoo', 'bar' => 'innerBar'],
            ], ObjectOuter::class, null, $context));
    }

    public function testAttributesContextDenormalizeExceptionExtraAttributes()
    {
        $normalizer = $this->getDenormalizerForAttributes();

        $context = [
            'attributes' => ['bar', 'inner' => ['foo']],
            'allow_extra_attributes' => false,
        ];
        $this->expectException(ExtraAttributesException::class);
        $normalizer->denormalize(
            [
                'bar' => 'bar',
                'inner' => ['foo' => 'innerFoo', 'bar' => 'innerBar'],
            ], ObjectOuter::class, null, $context);
    }
}
