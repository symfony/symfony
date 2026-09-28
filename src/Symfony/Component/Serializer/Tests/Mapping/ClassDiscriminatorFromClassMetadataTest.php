<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Mapping;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\AbstractDummy;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\AbstractDummyFirstChild;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\AbstractDummySecondChild;
use Symfony\Component\Serializer\Tests\Fixtures\DummyMessageInterface;
use Symfony\Component\Serializer\Tests\Fixtures\DummyMessageNumberFour;
use Symfony\Component\Serializer\Tests\Fixtures\DummyMessageNumberOne;

class ClassDiscriminatorFromClassMetadataTest extends TestCase
{
    public function testGetMappingForMappedObject()
    {
        $resolver = new ClassDiscriminatorFromClassMetadata(new ClassMetadataFactory(new AttributeLoader()));

        $mapping = $resolver->getMappingForMappedObject(AbstractDummy::class);
        $this->assertSame('type', $mapping->getTypeProperty());
        $this->assertSame(AbstractDummyFirstChild::class, $mapping->getClassForType('first'));

        $this->assertSame($mapping, $resolver->getMappingForMappedObject(new AbstractDummyFirstChild()));
        $this->assertSame($mapping, $resolver->getMappingForMappedObject(AbstractDummySecondChild::class));

        $interfaceMapping = $resolver->getMappingForMappedObject(DummyMessageInterface::class);
        $this->assertSame(DummyMessageNumberOne::class, $interfaceMapping->getClassForType('one'));
        $this->assertSame($interfaceMapping, $resolver->getMappingForMappedObject(new DummyMessageNumberOne()));
        $this->assertSame($interfaceMapping, $resolver->getMappingForMappedObject(DummyMessageNumberFour::class));

        $this->assertNull($resolver->getMappingForMappedObject(new \stdClass()));
        $this->assertNull($resolver->getMappingForMappedObject(\ArrayObject::class));
    }

    public function testGetTypeForMappedObject()
    {
        $resolver = new ClassDiscriminatorFromClassMetadata(new ClassMetadataFactory(new AttributeLoader()));

        $this->assertSame('first', $resolver->getTypeForMappedObject(new AbstractDummyFirstChild()));
        $this->assertSame('second', $resolver->getTypeForMappedObject(AbstractDummySecondChild::class));
        $this->assertSame('one', $resolver->getTypeForMappedObject(new DummyMessageNumberOne()));
        $this->assertNull($resolver->getTypeForMappedObject(new \stdClass()));
    }

    public function testMetadataIsReadOncePerClass()
    {
        $factory = new class(new ClassMetadataFactory(new AttributeLoader())) implements ClassMetadataFactoryInterface {
            public array $calls = [];

            public function __construct(
                private readonly ClassMetadataFactoryInterface $decorated,
            ) {
            }

            public function getMetadataFor(string|object $value): ClassMetadataInterface
            {
                $this->calls[] = \is_object($value) ? $value::class : $value;

                return $this->decorated->getMetadataFor($value);
            }

            public function hasMetadataFor(mixed $value): bool
            {
                return $this->decorated->hasMetadataFor($value);
            }
        };
        $resolver = new ClassDiscriminatorFromClassMetadata($factory);

        $mapping = $resolver->getMappingForMappedObject(new AbstractDummyFirstChild());
        $this->assertSame([AbstractDummyFirstChild::class, AbstractDummy::class], $factory->calls);

        $this->assertSame($mapping, $resolver->getMappingForMappedObject(new AbstractDummyFirstChild()));
        $this->assertSame($mapping, $resolver->getMappingForMappedObject(AbstractDummyFirstChild::class));
        $this->assertSame($mapping, $resolver->getMappingForMappedObject(AbstractDummy::class));
        $this->assertSame('first', $resolver->getTypeForMappedObject(new AbstractDummyFirstChild()));
        $this->assertSame([AbstractDummyFirstChild::class, AbstractDummy::class], $factory->calls);
    }
}
