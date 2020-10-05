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
    private $typeProperty;
    private $typesMapping;

    public function __construct(string $typeProperty, array $typesMapping = [])
    {
        $this->typeProperty = $typeProperty;
        $this->typesMapping = $typesMapping;

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

    /**
     * @param object|string $object
     */
    public function getMappedObjectType($object): ?string
    {
        foreach ($this->typesMapping as $type => $typeClass) {
            if (is_a($object, $typeClass)) {
                return $type;
            }
        }

        return null;
    }

    public function getTypesMapping(): array
    {
        return $this->typesMapping;
    }
}
