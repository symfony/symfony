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
final class ExplicitStringType extends BuiltinType
{
    public function __construct(private string $explicitType)
    {
        parent::__construct(TypeIdentifier::STRING);
    }

    public function getExplicitType(): string
    {
        return $this->explicitType;
    }

    public function __toString(): string
    {
        return $this->explicitType;
    }
}
