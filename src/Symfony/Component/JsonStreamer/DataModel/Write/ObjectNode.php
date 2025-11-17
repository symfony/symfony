<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\DataModel\Write;

use Symfony\Component\TypeInfo\Type\ObjectType;

/**
 * Represents an object in the data model graph representation.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class ObjectNode implements DataModelNodeInterface
{
    /**
     * @param array<string, DataModelNodeInterface> $properties
     */
    public function __construct(
        private string $accessor,
        private ObjectType $type,
        private array $properties,
        private bool $mock = false,
    ) {
    }

    public static function createMock(string $accessor, ObjectType $type): self
    {
        return new self($accessor, $type, [], true);
    }

    public function withAccessor(string $accessor): self
    {
        $properties = [];
        foreach ($this->properties as $key => $property) {
            $properties[$key] = $property->withAccessor(str_replace($this->accessor, $accessor, $property->getAccessor()));
        }

        return new self($accessor, $this->type, $properties, $this->mock);
    }

    public function getIdentifier(): string
    {
        return (string) $this->getType();
    }

    public function getAccessor(): string
    {
        return $this->accessor;
    }

    public function getType(): ObjectType
    {
        return $this->type;
    }

    /**
     * @return array<string, DataModelNodeInterface>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    public function isMock(): bool
    {
        return $this->mock;
    }
}
