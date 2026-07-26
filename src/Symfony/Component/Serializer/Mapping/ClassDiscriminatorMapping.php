<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Mapping;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class ClassDiscriminatorMapping
{
    /**
     * @param array<string, string> $typesMapping
     */
    public function __construct(
        private readonly string $typeProperty,
        private array $typesMapping = [],
        private readonly ?string $defaultType = null,
    ) {
        uasort($this->typesMapping, static function (string $a, string $b): int {
            if (is_a($a, $b, true)) {
                return -1;
            }

            if (is_a($b, $a, true)) {
                return 1;
            }

            return 0;
        });
    }

    public function getTypeProperty(): string
    {
        return $this->typeProperty;
    }

    public function getClassForType(string $type): ?string
    {
        return $this->typesMapping[$type] ?? null;
    }

    public function getMappedObjectType(object|string $object): ?string
    {
        $mappedType = null;
        foreach ($this->typesMapping as $type => $typeClass) {
            if (is_a($object, $typeClass, true)) {
                $mappedType = $type;
                break;
            }
        }

        if (null === $mappedType || !\is_object($object) || !property_exists($object, $this->typeProperty)) {
            return $mappedType;
        }

        try {
            $value = (new \ReflectionProperty($object, $this->typeProperty))->getValue($object);
        } catch (\Error) {
            return $mappedType;
        }

        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }

        // several types can map to the same class, in which case the value carried by the object
        // decides which of them is used
        if ((\is_string($value) || \is_int($value)) && ($this->typesMapping[$value] ?? null) === $this->typesMapping[$mappedType]) {
            return (string) $value;
        }

        return $mappedType;
    }

    public function getTypesMapping(): array
    {
        return $this->typesMapping;
    }

    public function getDefaultType(): ?string
    {
        return $this->defaultType;
    }
}
