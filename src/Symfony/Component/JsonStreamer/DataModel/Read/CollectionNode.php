<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\DataModel\Read;

use Symfony\Component\TypeInfo\Type\CollectionType;

/**
 * Represents a collection in the data model graph representation.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class CollectionNode implements DataModelNodeInterface
{
    public function __construct(
        private CollectionType $type,
        private DataModelNodeInterface $item,
    ) {
    }

    public function getIdentifier(): string
    {
        return (string) $this->type;
    }

    public function getType(): CollectionType
    {
        return $this->type;
    }

    public function getItemNode(): DataModelNodeInterface
    {
        return $this->item;
    }
}
