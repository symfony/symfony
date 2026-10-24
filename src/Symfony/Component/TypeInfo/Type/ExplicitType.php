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
 * A builtin type that is more specific than its type identifier, such as "non-empty-string".
 *
 * @author Martin Rademacher <mano@radebatz.net>
 *
 * @template T of TypeIdentifier
 *
 * @extends BuiltinType<T>
 */
class ExplicitType extends BuiltinType
{
    /**
     * @param T $typeIdentifier
     */
    public function __construct(
        TypeIdentifier $typeIdentifier,
        private readonly string $explicitType,
    ) {
        parent::__construct($typeIdentifier);
    }

    public function getExplicitType(): string
    {
        return $this->explicitType;
    }

    /**
     * @return BuiltinType<T>
     */
    public function getBuiltinType(): BuiltinType
    {
        return new BuiltinType($this->getTypeIdentifier());
    }

    public function __toString(): string
    {
        return $this->explicitType;
    }
}
