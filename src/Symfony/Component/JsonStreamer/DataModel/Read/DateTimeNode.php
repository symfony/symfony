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

use Symfony\Component\TypeInfo\Type\ObjectType;

/**
 * Represents a DateTime in the data model graph representation.
 *
 * DateTimes are leaves in the data model tree.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class DateTimeNode implements DataModelNodeInterface
{
    /**
     * @param ObjectType<class-string<\DateTimeInterface>> $type
     */
    public function __construct(
        public ObjectType $type,
    ) {
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
