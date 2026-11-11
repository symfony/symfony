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

/**
 * Represents a type narrowing the values of the builtin type it wraps, such as "int<0, 10>" or "'draft'".
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @template T of BuiltinType
 *
 * @extends WrappingTypeInterface<T>
 */
interface RefinementTypeInterface extends WrappingTypeInterface
{
}
