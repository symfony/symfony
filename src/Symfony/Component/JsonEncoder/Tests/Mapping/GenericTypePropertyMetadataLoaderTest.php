<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Tests\Mapping;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonEncoder\Mapping\GenericTypePropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadata;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadataLoaderInterface;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithGenerics;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;
use Symfony\Component\TypeInfo\TypeResolver\StringTypeResolver;

class GenericTypePropertyMetadataLoaderTest extends TestCase
{
    public function testReplaceGenerics()
    {
        $loader = new GenericTypePropertyMetadataLoader(self::propertyMetadataLoader([
            'foo' => new PropertyMetadata('foo', Type::template('T'), []),
        ]), new TypeContextFactory(new StringTypeResolver()));

        $metadata = $loader->load(DummyWithGenerics::class, [], ['original_type' => Type::generic(Type::object(DummyWithGenerics::class), Type::int())]);
        $this->assertEquals(['foo' => new PropertyMetadata('foo', Type::int(), [])], $metadata);

        $metadata = $loader->load(DummyWithGenerics::class, [], ['original_type' => Type::generic(Type::string(), Type::generic(Type::object(DummyWithGenerics::class), Type::int()))]);
        $this->assertEquals(['foo' => new PropertyMetadata('foo', Type::int(), [])], $metadata);

        $metadata = $loader->load(DummyWithGenerics::class, [], ['original_type' => Type::list(Type::generic(Type::object(DummyWithGenerics::class), Type::int()))]);
        $this->assertEquals(['foo' => new PropertyMetadata('foo', Type::int(), [])], $metadata);

        $metadata = $loader->load(DummyWithGenerics::class, [], ['original_type' => Type::union(Type::string(), Type::generic(Type::object(DummyWithGenerics::class), Type::int()))]);
        $this->assertEquals(['foo' => new PropertyMetadata('foo', Type::int(), [])], $metadata);

        $metadata = $loader->load(DummyWithGenerics::class, [], ['original_type' => Type::intersection(Type::string(), Type::generic(Type::object(DummyWithGenerics::class), Type::int()))]);
        $this->assertEquals(['foo' => new PropertyMetadata('foo', Type::int(), [])], $metadata);
    }

    /**
     * @param array<string, PropertyMetadata> $propertiesMetadata
     */
    private static function propertyMetadataLoader(array $propertiesMetadata = []): PropertyMetadataLoaderInterface
    {
        return new class($propertiesMetadata) implements PropertyMetadataLoaderInterface {
            public function __construct(private readonly array $propertiesMetadata)
            {
            }

            public function load(string $className, array $config, array $context): array
            {
                return $this->propertiesMetadata;
            }
        };
    }
}
