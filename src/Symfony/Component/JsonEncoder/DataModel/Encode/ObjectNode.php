<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\DataModel\Encode;

use Symfony\Component\JsonEncoder\DataModel\DataAccessorInterface;
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
        private DataAccessorInterface $accessor,
        private ObjectType $type,
        private array $properties,
        private bool $transformed,
    ) {
    }

    public function getAccessor(): DataAccessorInterface
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

    public function isTransformed(): bool
    {
        return $this->transformed;
    }
}
