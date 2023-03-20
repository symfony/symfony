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

use Symfony\Component\TypeInfo\Type;

/**
 * Represents a node in the decoding data model graph representation.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
interface DataModelNodeInterface
{
    public function getIdentifier(): string;

    public function getType(): Type;
}
