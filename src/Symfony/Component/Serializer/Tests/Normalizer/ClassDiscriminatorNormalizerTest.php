<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer;

use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Annotation\DiscriminatorMap;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\ClassDiscriminatorNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;

class ClassDiscriminatorNormalizerTest extends TestCase
{
    public function testNormalize()
    {
        $subNormalizer = $this
            ->getMockBuilder([NormalizerInterface::class, DenormalizerInterface::class])
            ->getMock()
        ;

        $subNormalizer->method('normalize')->willReturn(['foo' => 'foo']);
        $resolver = new ClassDiscriminatorFromClassMetadata(new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader())));
        $normalizer = new ClassDiscriminatorNormalizer($subNormalizer, $resolver);

        $dummy = new Dummy();

        $data = $normalizer->normalize($dummy, 'json');
        $this->assertSame(['foo' => 'foo'], $data);

        $data = $normalizer->normalize($dummy, 'json');
        $this->assertSame(['foo' => 'foo'], $data);
    }

    public function testSupportNormalization()
    {
        $subNormalizer = $this
            ->getMockBuilder([NormalizerInterface::class, DenormalizerInterface::class])
            ->getMock()
        ;

        $subNormalizer->method('supportsNormalization')->willReturn(true);
        $resolver = new ClassDiscriminatorFromClassMetadata(new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader())));
        $normalizer = new ClassDiscriminatorNormalizer($subNormalizer, $resolver);

        $this->assertTrue($normalizer->supportsNormalization([], 'json'));

        $subNormalizer = $this
            ->getMockBuilder([NormalizerInterface::class, DenormalizerInterface::class])
            ->getMock()
        ;

        $normalizer = new ClassDiscriminatorNormalizer($subNormalizer, $resolver);
        $subNormalizer->method('supportsNormalization')->willReturn(false);
        $this->assertFalse($normalizer->supportsNormalization([], 'json'));
    }

    public function testDenormalize()
    {
        $subNormalizer = $this
            ->getMockBuilder([NormalizerInterface::class, DenormalizerInterface::class])
            ->getMock()
        ;

        $dummy = new DummyCircular();
        $subNormalizer->method('denormalize')->willReturn($dummy);
        $resolver = new ClassDiscriminatorFromClassMetadata(new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader())));
        $normalizer = new ClassDiscriminatorNormalizer($subNormalizer, $resolver);

        $data = $normalizer->denormalize([], 'type', 'json');

        $this->assertSame($dummy, $data);
    }

    public function testSupportDenormalization()
    {
        $subNormalizer = $this
            ->getMockBuilder([NormalizerInterface::class, DenormalizerInterface::class])
            ->getMock()
        ;

        $subNormalizer->method('supportsDenormalization')->willReturn(true);
        $resolver = new ClassDiscriminatorFromClassMetadata(new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader())));
        $normalizer = new ClassDiscriminatorNormalizer($subNormalizer, $resolver);

        $this->assertTrue($normalizer->supportsDenormalization([], 'type', 'json'));

        $subNormalizer = $this
            ->getMockBuilder([NormalizerInterface::class, DenormalizerInterface::class])
            ->getMock()
        ;

        $normalizer = new ClassDiscriminatorNormalizer($subNormalizer, $resolver);
        $subNormalizer->method('supportsDenormalization')->willReturn(false);
        $this->assertFalse($normalizer->supportsDenormalization([], 'type', 'json'));
    }

    public function testHasCacheableSupportMethod()
    {
        $subNormalizer = $this
            ->getMockBuilder([NormalizerInterface::class, DenormalizerInterface::class, CacheableSupportsMethodInterface::class])
            ->getMock()
        ;

        $subNormalizer->method('hasCacheableSupportsMethod')->willReturn(true);
        $resolver = new ClassDiscriminatorFromClassMetadata(new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader())));
        $normalizer = new ClassDiscriminatorNormalizer($subNormalizer, $resolver);

        $this->assertTrue($normalizer->hasCacheableSupportsMethod());

        $subNormalizer = $this
            ->getMockBuilder([NormalizerInterface::class, DenormalizerInterface::class, CacheableSupportsMethodInterface::class])
            ->getMock()
        ;

        $subNormalizer->method('hasCacheableSupportsMethod')->willReturn(false);
        $normalizer = new ClassDiscriminatorNormalizer($subNormalizer, $resolver);
        $this->assertFalse($normalizer->hasCacheableSupportsMethod());
    }

    public function testDiscriminantNormalize()
    {
        $childNormalizer = new DummyChildNormalizer();
        $resolver = new ClassDiscriminatorFromClassMetadata(new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader())));
        $normalizer = new ClassDiscriminatorNormalizer($childNormalizer, $resolver);
        $serializer = new Serializer([$normalizer]);

        $data = $normalizer->normalize(new DummyFooChild(), 'json');

        $this->assertSame([
            'foo' => 'foo',
            'type' => 'foo',
        ], $data);

        $data = $normalizer->normalize(new DummyBarChild(), 'json');

        $this->assertSame([
            'bar' => 'bar',
            'type' => 'bar',
        ], $data);

        $data = $normalizer->normalize(new DummyParent(), 'json');

        $this->assertSame([], $data);
    }

    public function testDiscriminantDenormalize()
    {
        $childNormalizer = new DummyChildNormalizer();
        $resolver = new ClassDiscriminatorFromClassMetadata(new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader())));
        $normalizer = new ClassDiscriminatorNormalizer($childNormalizer, $resolver);
        $serializer = new Serializer([$normalizer]);

        $data = $normalizer->denormalize(['type' => 'foo'], DummyParent::class, 'json');
        $this->assertInstanceOf(DummyFooChild::class, $data);

        $data = $normalizer->denormalize(['type' => 'bar'], DummyParent::class, 'json');
        $this->assertInstanceOf(DummyBarChild::class, $data);
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\RuntimeException
     */
    public function testUnknowValue()
    {
        $childNormalizer = new DummyChildNormalizer();
        $resolver = new ClassDiscriminatorFromClassMetadata(new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader())));
        $normalizer = new ClassDiscriminatorNormalizer($childNormalizer, $resolver);
        $serializer = new Serializer([$normalizer]);

        $data = $normalizer->denormalize(['type' => 'unknow'], DummyParent::class, 'json');
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\RuntimeException
     */
    public function testNoType()
    {
        $childNormalizer = new DummyChildNormalizer();
        $resolver = new ClassDiscriminatorFromClassMetadata(new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader())));
        $normalizer = new ClassDiscriminatorNormalizer($childNormalizer, $resolver);
        $serializer = new Serializer([$normalizer]);

        $data = $normalizer->denormalize([], DummyParent::class, 'json');
    }
}

/**
 * @DiscriminatorMap(mapping={
 *    "foo"="Symfony\Component\Serializer\Tests\Normalizer\DummyFooChild",
 *    "bar"="Symfony\Component\Serializer\Tests\Normalizer\DummyBarChild"
 * }, typeProperty="type")
 */
class DummyParent
{
}

class DummyFooChild extends DummyParent
{
}

class DummyBarChild extends DummyParent
{
}

class DummyChildNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function denormalize($data, $class, $format = null, array $context = [])
    {
        if ($class === DummyFooChild::class) {
            return new DummyFooChild();
        }

        return new DummyBarChild();
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return true;
    }

    public function normalize($object, $format = null, array $context = [])
    {
        if ($object instanceof DummyFooChild) {
            return ['foo' => 'foo'];
        }

        if ($object instanceof DummyBarChild) {
            return ['bar' => 'bar'];
        }

        return [];
    }

    public function supportsNormalization($data, $format = null)
    {
        return true;
    }
}
