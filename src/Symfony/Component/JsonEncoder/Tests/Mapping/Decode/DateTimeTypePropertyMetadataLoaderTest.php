<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Tests\Mapping\Decode;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonEncoder\Mapping\Decode\DateTimeTypePropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadata;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadataLoaderInterface;
use Symfony\Component\TypeInfo\Type;

class DateTimeTypePropertyMetadataLoaderTest extends TestCase
{
    public function testAddDateTimeDenormalizer()
    {
        $loader = new DateTimeTypePropertyMetadataLoader(self::propertyMetadataLoader([
            'interface' => new PropertyMetadata('interface', Type::object(\DateTimeInterface::class)),
            'immutable' => new PropertyMetadata('immutable', Type::object(\DateTimeImmutable::class)),
            'mutable' => new PropertyMetadata('mutable', Type::object(\DateTime::class)),
            'other' => new PropertyMetadata('other', Type::object(self::class)),
        ]));

        $this->assertEquals([
            'interface' => new PropertyMetadata('interface', Type::string(), [], ['json_encoder.denormalizer.date_time_immutable']),
            'immutable' => new PropertyMetadata('immutable', Type::string(), [], ['json_encoder.denormalizer.date_time_immutable']),
            'mutable' => new PropertyMetadata('mutable', Type::string(), [], ['json_encoder.denormalizer.date_time']),
            'other' => new PropertyMetadata('other', Type::object(self::class)),
        ], $loader->load(self::class));
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
