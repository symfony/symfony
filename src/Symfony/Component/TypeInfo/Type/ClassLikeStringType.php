<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\Type;

use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * Explicit string type.
 *
 * @author Martin Rademacher <mano@radebatz.net>
 *
 * @extends BuiltinType<TypeIdentifier::STRING>
 */
final class ClassLikeStringType extends ExplicitStringType
{
    public function __construct(string $explicitType, private ObjectType $objectType)
    {
        parent::__construct($explicitType);
    }

    public function getObjectType(): ObjectType
    {
        return $this->objectType;
    }

    public function __toString(): string
    {
        return \sprintf('%s<%s>', $this->getExplicitType(), $this->objectType);
    }
}
