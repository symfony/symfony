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

use Symfony\Component\TypeInfo\Type;

/**
 * Represents a node in the stream writing data model graph representation.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
interface DataModelNodeInterface
{
    public function getIdentifier(): string;

    public function getType(): Type;

    public function getAccessor(): string;

    public function withAccessor(string $accessor): self;
}
