<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Tests\Mapping\Read;

use BcMath\Number;
use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonStreamer\Exception\InvalidArgumentException;
use Symfony\Component\JsonStreamer\Mapping\PropertyMetadata;
use Symfony\Component\JsonStreamer\Mapping\PropertyMetadataLoaderInterface;
use Symfony\Component\JsonStreamer\Mapping\Read\ValueObjectTypePropertyMetadataLoader;
use Symfony\Component\TypeInfo\Type;

class ValueObjectTypePropertyMetadataLoaderTest extends TestCase
{
    public function testAddDateTimeValueTransformer()
    {
        $loader = new ValueObjectTypePropertyMetadataLoader(self::propertyMetadataLoader([
            'dateTime' => new PropertyMetadata('dateTime', Type::object(\DateTimeImmutable::class)),
            'other' => new PropertyMetadata('other', Type::object(self::class)),
        ]));

        $this->assertEquals([
            'dateTime' => new PropertyMetadata('dateTime', Type::string(), [], ['json_streamer.value_transformer.string_to_date_time']),
            'other' => new PropertyMetadata('other', Type::object(self::class)),
        ], $loader->load(self::class));
    }

    public function testThrowWhenDateTimeType()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "DateTime" class is not supported. Use "DateTimeImmutable" instead.');

        $loader = new ValueObjectTypePropertyMetadataLoader(self::propertyMetadataLoader([
            'mutable' => new PropertyMetadata('mutable', Type::object(\DateTime::class)),
        ]));

        $loader->load(self::class);
    }

    /**
     * @requires PHP 8.4
     * @requires extension bcmath
     */
    public function testAddBcMathNumberValueTransformer()
    {
        $loader = new ValueObjectTypePropertyMetadataLoader(self::propertyMetadataLoader([
            'number' => new PropertyMetadata('number', Type::object(Number::class)),
            'other' => new PropertyMetadata('other', Type::object(self::class)),
        ]));

        $this->assertEquals([
            'number' => new PropertyMetadata('number', Type::union(Type::int(), Type::string()), [], ['json_streamer.value_transformer.int_string_to_bc_math_number']),
            'other' => new PropertyMetadata('other', Type::object(self::class)),
        ], $loader->load(self::class));
    }

    /**
     * @requires extension gmp
     */
    public function testAddGmpNumberValueTransformer()
    {
        $loader = new ValueObjectTypePropertyMetadataLoader(self::propertyMetadataLoader([
            'number' => new PropertyMetadata('number', Type::object(\GMP::class)),
            'other' => new PropertyMetadata('other', Type::object(self::class)),
        ]));

        $this->assertEquals([
            'number' => new PropertyMetadata('number', Type::union(Type::int(), Type::string()), [], ['json_streamer.value_transformer.int_string_to_gmp_number']),
            'other' => new PropertyMetadata('other', Type::object(self::class)),
        ], $loader->load(self::class));
    }

    /**
     * @requires PHP 8.4
     * @requires extension bcmath
     */
    public function testSetProperTypeWhenUnion()
    {
        $loader = new ValueObjectTypePropertyMetadataLoader(self::propertyMetadataLoader([
            'dateTime' => new PropertyMetadata('dateTime', Type::union(Type::bool(), Type::string(), Type::object(\DateTimeImmutable::class))),
            'number' => new PropertyMetadata('number', Type::union(Type::bool(), Type::string(), Type::object(Number::class))),
        ]));

        $propertyMetadata = $loader->load(self::class);

        $this->assertEquals(Type::union(Type::bool(), Type::string()), $propertyMetadata['dateTime']->getType());
        $this->assertEquals(Type::union(Type::bool(), Type::int(), Type::string()), $propertyMetadata['number']->getType());
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
