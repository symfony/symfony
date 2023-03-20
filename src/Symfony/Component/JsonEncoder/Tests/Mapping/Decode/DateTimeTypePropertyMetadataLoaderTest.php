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
    public function testCastStringToDateTime()
    {
        $loader = new DateTimeTypePropertyMetadataLoader(self::propertyMetadataLoader([
            'foo' => new PropertyMetadata('foo', Type::object(\DateTimeImmutable::class), []),
        ]));

        $metadata = $loader->load(self::class, [], ['original_type' => Type::string()]);

        $this->assertEquals([
            'foo' => new PropertyMetadata('foo', Type::string(), [
                \Closure::fromCallable(DateTimeTypePropertyMetadataLoader::castStringToDateTime(...)),
            ]),
        ], $metadata);

        $formatter = $metadata['foo']->formatters[0];

        $this->assertEquals(
            new \DateTimeImmutable('2023-07-26'),
            $formatter('2023-07-26', []),
        );

        $this->assertEquals(
            (new \DateTimeImmutable('2023-07-26'))->setTime(0, 0),
            $formatter('26/07/2023 00:00:00', ['date_time_format' => 'd/m/Y H:i:s']),
        );
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
