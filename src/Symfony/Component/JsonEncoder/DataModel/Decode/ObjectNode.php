<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\DataModel\Decode;

use Symfony\Component\JsonEncoder\DataModel\DataAccessorInterface;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\UnionType;

/**
 * Represents an object in the data model graph representation.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
final readonly class ObjectNode implements DataModelNodeInterface
{
    /**
     * @param array<string, array{name: string, value: DataModelNodeInterface, accessor: callable(DataAccessorInterface): DataAccessorInterface}> $properties
     */
    public function __construct(
        public ObjectType $type,
        public array $properties,
        public bool $ghost = false,
    ) {
    }

    public static function ghost(ObjectType|UnionType $type): self
    {
        return new self($type, [], true);
    }

    public function getIdentifier(): string
    {
        return (string) $this->type;
    }

    public function getType(): ObjectType
    {
        return $this->type;
    }
}
