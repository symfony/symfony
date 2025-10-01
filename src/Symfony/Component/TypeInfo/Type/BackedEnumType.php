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

use Symfony\Component\TypeInfo\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 *
 * @template T of class-string<\BackedEnum>
 * @template U of BuiltinType<TypeIdentifier::INT>|BuiltinType<TypeIdentifier::STRING>
 *
 * @extends EnumType<T>
 */
final class BackedEnumType extends EnumType
{
    /**
     * @param T $className
     * @param U $backingType
     */
    public function __construct(
        string $className,
        private readonly BuiltinType $backingType,
    ) {
        if (TypeIdentifier::INT !== $backingType->getTypeIdentifier() && TypeIdentifier::STRING !== $backingType->getTypeIdentifier()) {
            throw new InvalidArgumentException(\sprintf('Cannot create "%s" with "%s" backing type.', self::class, $backingType));
        }

        parent::__construct($className);
    }

    /**
     * @return U
     */
    public function getBackingType(): BuiltinType
    {
        return $this->backingType;
    }
}
