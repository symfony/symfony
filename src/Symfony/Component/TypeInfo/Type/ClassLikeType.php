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
 * A string naming a class, interface or trait, such as "class-string<Foo>".
 *
 * @author Martin Rademacher <mano@radebatz.net>
 *
 * @template T of ObjectType
 *
 * @extends ExplicitType<TypeIdentifier::STRING>
 */
final class ClassLikeType extends ExplicitType
{
    /**
     * @param T $objectType
     */
    public function __construct(
        string $explicitType,
        private readonly ObjectType $objectType,
    ) {
        parent::__construct(TypeIdentifier::STRING, $explicitType);
    }

    /**
     * @return T
     */
    public function getObjectType(): ObjectType
    {
        return $this->objectType;
    }

    public function __toString(): string
    {
        return \sprintf('%s<%s>', $this->getExplicitType(), $this->objectType);
    }
}
