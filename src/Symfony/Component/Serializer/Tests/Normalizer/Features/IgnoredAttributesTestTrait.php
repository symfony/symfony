<?php

namespace Symfony\Component\Serializer\Tests\Normalizer\Features;

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Test AbstractNormalizer::IGNORED_ATTRIBUTES.
 */
trait IgnoredAttributesTestTrait
{
    abstract protected function getNormalizerForIgnoredAttributes(): NormalizerInterface;

    abstract protected function getDenormalizerForIgnoredAttributes(): DenormalizerInterface;

    public function testIgnoredAttributesNormalize()
    {
        $normalizer = $this->getNormalizerForIgnoredAttributes();

        $objectInner = new ObjectInner();
        $objectInner->foo = 'innerFoo';
        $objectInner->bar = 'innerBar';

        $objectOuter = new ObjectOuter();
        $objectOuter->foo = 'foo';
        $objectOuter->bar = 'bar';
        $objectOuter->setInner($objectInner);

        $context = ['ignored_attributes' => ['bar']];
        $this->assertEquals(
            [
                'foo' => 'foo',
                'inner' => ['foo' => 'innerFoo'],
                'date' => null,
                'inners' => null,
            ],
            $normalizer->normalize($objectOuter, null, $context)
        );

        $this->markTestIncomplete('AbstractObjectNormalizer::getAttributes caches attributes by class instead of by class+context, reusing the normalizer with different config therefore fails. This is being fixed in https://github.com/symfony/symfony/pull/30907');

        $context = ['ignored_attributes' => ['foo', 'inner']];
        $this->assertEquals(
            [
                'bar' => 'bar',
                'date' => null,
                'inners' => null,
            ],
            $normalizer->normalize($objectOuter, null, $context)
        );
    }

    public function testIgnoredAttributesContextDenormalize()
    {
        $normalizer = $this->getDenormalizerForIgnoredAttributes();

        $objectOuter = new ObjectOuter();
        $objectOuter->bar = 'bar';

        $context = ['ignored_attributes' => ['foo', 'inner']];
        $this->assertEquals($objectOuter, $normalizer->denormalize(
            [
                'foo' => 'foo',
                'bar' => 'bar',
                'inner' => ['foo' => 'innerFoo', 'bar' => 'innerBar'],
            ], ObjectOuter::class, null, $context));
    }

    public function testIgnoredAttributesContextDenormalizeInherit()
    {
        $normalizer = $this->getDenormalizerForIgnoredAttributes();

        $objectInner = new ObjectInner();
        $objectInner->foo = 'innerFoo';

        $objectOuter = new ObjectOuter();
        $objectOuter->foo = 'foo';
        $objectOuter->setInner($objectInner);

        $context = ['ignored_attributes' => ['bar']];
        $this->assertEquals($objectOuter, $normalizer->denormalize(
            [
                'foo' => 'foo',
                'bar' => 'bar',
                'inner' => ['foo' => 'innerFoo', 'bar' => 'innerBar'],
            ], ObjectOuter::class, null, $context));
    }
}
