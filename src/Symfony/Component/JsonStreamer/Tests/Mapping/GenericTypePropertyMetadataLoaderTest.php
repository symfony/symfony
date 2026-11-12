<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Tests\Mapping;

use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonStreamer\Mapping\GenericTypePropertyMetadataLoader;
use Symfony\Component\JsonStreamer\Mapping\PropertyMetadata;
use Symfony\Component\JsonStreamer\Mapping\PropertyMetadataLoader;
use Symfony\Component\JsonStreamer\Mapping\PropertyMetadataLoaderInterface;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithConcreteInheritedGenerics;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithDeeplyInheritedGenerics;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithGenerics;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithInheritedGenerics;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithUndeclaredInheritedGenerics;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\ArrayShapeType;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;
use Symfony\Component\TypeInfo\TypeResolver\StringTypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

class GenericTypePropertyMetadataLoaderTest extends TestCase
{
    #[RequiresMethod(Type::class, 'objectShape')]
    public function testReplaceGenericsInObjectShape()
    {
        $loader = new GenericTypePropertyMetadataLoader(self::propertyMetadataLoader([
            'foo' => new PropertyMetadata('foo', Type::objectShape(['item' => Type::template('T'), 'total' => ['type' => Type::int(), 'optional' => true]])),
        ]), new TypeContextFactory(new StringTypeResolver(null, null, [], true)));

        $metadata = $loader->load(DummyWithGenerics::class, [], ['original_type' => Type::generic(Type::object(DummyWithGenerics::class), Type::bool())]);

        $this->assertEquals([
            'foo' => new PropertyMetadata('foo', Type::objectShape(['item' => Type::bool(), 'total' => ['type' => Type::int(), 'optional' => true]])),
        ], $metadata);
    }

    public function testReplaceGenerics()
    {
        $loader = new GenericTypePropertyMetadataLoader(self::propertyMetadataLoader([
            'foo' => new PropertyMetadata('foo', Type::template('T')),
        ]), new TypeContextFactory(new StringTypeResolver(null, null, [], true)));

        $metadata = $loader->load(DummyWithGenerics::class, [], ['original_type' => Type::generic(Type::object(DummyWithGenerics::class), Type::int())]);
        $this->assertEquals(['foo' => new PropertyMetadata('foo', Type::int())], $metadata);

        $metadata = $loader->load(DummyWithGenerics::class, [], ['original_type' => Type::generic(Type::object(\stdClass::class), Type::generic(Type::object(DummyWithGenerics::class), Type::int()))]);
        $this->assertEquals(['foo' => new PropertyMetadata('foo', Type::int())], $metadata);

        $metadata = $loader->load(DummyWithGenerics::class, [], ['original_type' => Type::list(Type::generic(Type::object(DummyWithGenerics::class), Type::int()))]);
        $this->assertEquals(['foo' => new PropertyMetadata('foo', Type::int())], $metadata);

        $metadata = $loader->load(DummyWithGenerics::class, [], ['original_type' => Type::union(Type::string(), Type::generic(Type::object(DummyWithGenerics::class), Type::int()))]);
        $this->assertEquals(['foo' => new PropertyMetadata('foo', Type::int())], $metadata);

        $metadata = $loader->load(DummyWithGenerics::class, [], ['original_type' => Type::intersection(Type::object(\stdClass::class), Type::generic(Type::object(DummyWithGenerics::class), Type::int()))]);
        $this->assertEquals(['foo' => new PropertyMetadata('foo', Type::int())], $metadata);

        $metadata = $loader->load(DummyWithGenerics::class, [], ['original_type' => Type::generic(Type::object(DummyWithGenerics::class), Type::generic(Type::object(DummyWithGenerics::class), Type::int()))]);
        $this->assertEquals(['foo' => new PropertyMetadata('foo', Type::generic(Type::object(DummyWithGenerics::class), Type::int()))], $metadata);
    }

    public function testReplaceGenericsInArrayShape()
    {
        $loader = new GenericTypePropertyMetadataLoader(self::propertyMetadataLoader([
            'foo' => new PropertyMetadata('foo', Type::arrayShape(['items' => Type::list(Type::template('T')), 'total' => ['type' => Type::int(), 'optional' => true]], false, Type::string(), Type::template('T'))),
        ]), new TypeContextFactory(new StringTypeResolver(null, null, [], true)));

        $metadata = $loader->load(DummyWithGenerics::class, context: ['original_type' => Type::generic(Type::object(DummyWithGenerics::class), Type::bool())]);

        $this->assertEquals([
            'foo' => new PropertyMetadata('foo', Type::arrayShape(['items' => Type::list(Type::bool()), 'total' => ['type' => Type::int(), 'optional' => true]], false, Type::string(), Type::bool())),
        ], $metadata);
    }

    public function testReplaceGenericsInListShape()
    {
        $loader = new GenericTypePropertyMetadataLoader(self::propertyMetadataLoader([
            'foo' => new PropertyMetadata('foo', new ArrayShapeType([['type' => Type::template('T'), 'optional' => false]], Type::int(), Type::template('T'), true)),
        ]), new TypeContextFactory(new StringTypeResolver(null, null, [], true)));

        $metadata = $loader->load(DummyWithGenerics::class, context: ['original_type' => Type::generic(Type::object(DummyWithGenerics::class), Type::bool())]);

        $this->assertEquals([
            'foo' => new PropertyMetadata('foo', new ArrayShapeType([['type' => Type::bool(), 'optional' => false]], Type::int(), Type::bool(), true)),
        ], $metadata);
    }

    public function testReplaceGenericsWithMixedInUnion()
    {
        $loader = new GenericTypePropertyMetadataLoader(self::propertyMetadataLoader([
            'nullable' => new PropertyMetadata('nullable', Type::nullable(Type::template('T'))),
            'union' => new PropertyMetadata('union', Type::union(Type::template('T'), Type::string())),
        ]), new TypeContextFactory(new StringTypeResolver(null, null, [], true)));

        $metadata = $loader->load(DummyWithGenerics::class, [], ['original_type' => Type::generic(Type::object(DummyWithGenerics::class), Type::mixed())]);

        $this->assertEquals([
            'nullable' => new PropertyMetadata('nullable', Type::mixed()),
            'union' => new PropertyMetadata('union', Type::mixed()),
        ], $metadata);
    }

    public function testReplaceGenericsWithTheSameTypeInUnion()
    {
        $loader = new GenericTypePropertyMetadataLoader(self::propertyMetadataLoader([
            'union' => new PropertyMetadata('union', Type::union(Type::template('T'), Type::int())),
            'nullableUnion' => new PropertyMetadata('nullableUnion', Type::union(Type::template('T'), Type::int(), Type::null())),
        ]), new TypeContextFactory(new StringTypeResolver(null, null, [], true)));

        $metadata = $loader->load(DummyWithGenerics::class, [], ['original_type' => Type::generic(Type::object(DummyWithGenerics::class), Type::int())]);

        $this->assertEquals([
            'union' => new PropertyMetadata('union', Type::int()),
            'nullableUnion' => new PropertyMetadata('nullableUnion', Type::nullable(Type::int())),
        ], $metadata);

        $metadata = $loader->load(DummyWithGenerics::class, [], ['original_type' => Type::generic(Type::object(DummyWithGenerics::class), Type::nullable(Type::int()))]);

        $this->assertEquals([
            'union' => new PropertyMetadata('union', Type::nullable(Type::int())),
            'nullableUnion' => new PropertyMetadata('nullableUnion', Type::nullable(Type::int())),
        ], $metadata);
    }

    public function testReplaceGenericsOfParentClasses()
    {
        $loader = new GenericTypePropertyMetadataLoader(new PropertyMetadataLoader(TypeResolver::create(null, true)), new TypeContextFactory(new StringTypeResolver(null, null, [], true)));

        $metadata = $loader->load(DummyWithInheritedGenerics::class, [], ['original_type' => Type::generic(Type::object(DummyWithInheritedGenerics::class), Type::int(), Type::object(ClassicDummy::class))]);

        $this->assertEquals([
            'extra' => new PropertyMetadata('extra', Type::int()),
            'dummies' => new PropertyMetadata('dummies', Type::list(Type::object(ClassicDummy::class))),
        ], $metadata);

        $metadata = $loader->load(DummyWithDeeplyInheritedGenerics::class, [], ['original_type' => Type::generic(Type::object(DummyWithDeeplyInheritedGenerics::class), Type::object(ClassicDummy::class))]);

        $this->assertEquals([
            'extra' => new PropertyMetadata('extra', Type::int()),
            'dummies' => new PropertyMetadata('dummies', Type::list(Type::object(ClassicDummy::class))),
        ], $metadata);

        $metadata = $loader->load(DummyWithUndeclaredInheritedGenerics::class, [], ['original_type' => Type::generic(Type::object(DummyWithUndeclaredInheritedGenerics::class), Type::object(ClassicDummy::class))]);

        $this->assertEquals([
            'dummies' => new PropertyMetadata('dummies', Type::list(Type::object(ClassicDummy::class))),
        ], $metadata);
    }

    public function testReplaceGenericsOfParentClassesOfNonGenericClass()
    {
        $loader = new GenericTypePropertyMetadataLoader(new PropertyMetadataLoader(TypeResolver::create(null, true)), new TypeContextFactory(new StringTypeResolver(null, null, [], true)));

        $metadata = $loader->load(DummyWithConcreteInheritedGenerics::class, [], ['original_type' => Type::object(DummyWithConcreteInheritedGenerics::class)]);

        $this->assertEquals([
            'extra' => new PropertyMetadata('extra', Type::int()),
            'dummies' => new PropertyMetadata('dummies', Type::list(Type::object(ClassicDummy::class))),
        ], $metadata);

        $metadata = $loader->load(DummyWithConcreteInheritedGenerics::class, [], ['original_type' => Type::list(Type::object(DummyWithConcreteInheritedGenerics::class))]);

        $this->assertEquals([
            'extra' => new PropertyMetadata('extra', Type::int()),
            'dummies' => new PropertyMetadata('dummies', Type::list(Type::object(ClassicDummy::class))),
        ], $metadata);
    }

    /**
     * @param array<string, PropertyMetadata> $propertiesMetadata
     */
    private static function propertyMetadataLoader(array $propertiesMetadata = []): PropertyMetadataLoaderInterface
    {
        return new class($propertiesMetadata) implements PropertyMetadataLoaderInterface {
            public function __construct(private array $propertiesMetadata)
            {
            }

            public function load(string $className, array $options = [], array $context = []): array
            {
                return $this->propertiesMetadata;
            }
        };
    }
}
