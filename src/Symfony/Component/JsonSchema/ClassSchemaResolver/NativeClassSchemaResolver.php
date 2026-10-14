<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\ClassSchemaResolver;

use BcMath\Number;
use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\DefinitionParent;
use Symfony\Component\JsonSchema\Direction;
use Symfony\Component\JsonSchema\SchemaDocument;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Describes the classes the serializer normalizes to strings.
 *
 * The formats of dates and UIDs follow the "datetime_format" and "uid_normalization_format" options of the serializer context,
 * read from the default context and from the #[Context] attribute of the property.
 *
 * @experimental
 */
final class NativeClassSchemaResolver implements ClassSchemaResolverInterface
{
    /**
     * @param array<string, mixed> $defaultContext The default context of the serializer
     */
    public function __construct(
        private readonly array $defaultContext = [],
        private readonly ?ClassMetadataFactoryInterface $classMetadataFactory = null,
    ) {
    }

    public function resolve(string $class, Configuration $config, SchemaDocument $document, ?DefinitionParent $parent = null): ?array
    {
        return match (true) {
            is_a($class, \DateTimeInterface::class, true) => ['type' => 'string', ...match ($this->getContext($config, $parent)['datetime_format'] ?? \DateTimeInterface::RFC3339) {
                \DateTimeInterface::RFC3339, \DateTimeInterface::RFC3339_EXTENDED => ['format' => 'date-time'],
                'Y-m-d' => ['format' => 'date'],
                default => [],
            }],
            is_a($class, \DateInterval::class, true) => ['type' => 'string', 'format' => 'duration'],
            is_a($class, \SplFileInfo::class, true) => ['type' => 'string', 'format' => 'binary'],
            is_a($class, \DateTimeZone::class, true),
            is_a($class, Number::class, true),
            is_a($class, \GMP::class, true),
            is_a($class, TranslatableInterface::class, true) => ['type' => 'string'],
            is_a($class, Uuid::class, true) => ['type' => 'string', ...match ($this->getContext($config, $parent)['uid_normalization_format'] ?? 'canonical') {
                'canonical', 'rfc4122' => ['format' => 'uuid'],
                default => [],
            }],
            is_a($class, Ulid::class, true) => ['type' => 'string', ...match ($this->getContext($config, $parent)['uid_normalization_format'] ?? 'canonical') {
                'canonical', 'base32' => ['format' => 'ulid'],
                'rfc4122' => ['format' => 'uuid'],
                default => [],
            }],
            is_a($class, \BackedEnum::class, true) => [
                'type' => 'int' === (string) (new \ReflectionEnum($class))->getBackingType() ? 'integer' : 'string',
                'enum' => array_map(static fn (\BackedEnum $case): int|string => $case->value, $class::cases()),
            ],
            is_a($class, \UnitEnum::class, true) => [
                'type' => 'string',
                'enum' => array_map(static fn (\UnitEnum $case): string => $case->name, $class::cases()),
            ],
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function getContext(Configuration $config, ?DefinitionParent $parent): array
    {
        if (null === $parent || null === $this->classMetadataFactory || !$this->classMetadataFactory->hasMetadataFor($parent->class)) {
            return $this->defaultContext;
        }

        if (null === $attributeMetadata = $this->classMetadataFactory->getMetadataFor($parent->class)->getAttributesMetadata()[$parent->property] ?? null) {
            return $this->defaultContext;
        }

        $context = Direction::Input === $config->direction ? $attributeMetadata->getDenormalizationContextForGroups($config->groups) : $attributeMetadata->getNormalizationContextForGroups($config->groups);

        return $context + $this->defaultContext;
    }
}
