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

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonStreamer\Exception\InvalidArgumentException;
use Symfony\Component\JsonStreamer\Mapping\PropertyMetadata;
use Symfony\Component\JsonStreamer\Mapping\PropertyMetadataLoaderInterface;
use Symfony\Component\JsonStreamer\Mapping\Read\DateTimeTypePropertyMetadataLoader;
use Symfony\Component\TypeInfo\Type;

class DateTimeTypePropertyMetadataLoaderTest extends TestCase
{
    public function testAddStringToDateTimeValueTransformer()
    {
        $loader = new DateTimeTypePropertyMetadataLoader(self::propertyMetadataLoader([
            'interface' => new PropertyMetadata('interface', Type::object(\DateTimeInterface::class)),
            'immutable' => new PropertyMetadata('immutable', Type::object(\DateTimeImmutable::class)),
            'other' => new PropertyMetadata('other', Type::object(self::class)),
        ]));

        $this->assertEquals([
            'interface' => new PropertyMetadata('interface', Type::string(), [], ['json_streamer.value_transformer.string_to_date_time']),
            'immutable' => new PropertyMetadata('immutable', Type::string(), [], ['json_streamer.value_transformer.string_to_date_time']),
            'other' => new PropertyMetadata('other', Type::object(self::class)),
        ], $loader->load(self::class));
    }

    public function testThrowWhenDateTimeType()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "DateTime" class is not supported. Use "DateTimeImmutable" instead.');

        $loader = new DateTimeTypePropertyMetadataLoader(self::propertyMetadataLoader([
            'mutable' => new PropertyMetadata('mutable', Type::object(\DateTime::class)),
        ]));

        $loader->load(self::class);
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
