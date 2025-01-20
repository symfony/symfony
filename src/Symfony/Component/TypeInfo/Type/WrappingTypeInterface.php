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

use Symfony\Component\TypeInfo\Type;

/**
 * Represents a type wrapping another type.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @template T of Type
 */
interface WrappingTypeInterface
{
    /**
     * @return T
     */
    public function getWrappedType(): Type;

    /**
     * @param callable(Type): bool $specification
     */
    public function wrappedTypeIsSatisfiedBy(callable $specification): bool;
}
