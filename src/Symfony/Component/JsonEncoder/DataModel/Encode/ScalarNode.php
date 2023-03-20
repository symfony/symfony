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
use Symfony\Component\TypeInfo\Type\BuiltinType;

/**
 * Represents a scalar in the data model graph representation.
 *
 * Scalars are leaves in the data model tree.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class ScalarNode implements DataModelNodeInterface
{
    public function __construct(
        private DataAccessorInterface $accessor,
        private BuiltinType $type,
    ) {
    }

    public function getAccessor(): DataAccessorInterface
    {
        return $this->accessor;
    }

    public function getType(): BuiltinType
    {
        return $this->type;
    }
}
