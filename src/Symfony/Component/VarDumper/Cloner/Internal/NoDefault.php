<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Cloner\Internal;

/**
 * Flags a typed property that has no default value.
 *
 * This dummy object is used to distinguish a property with a default value of null
 * from a property that is uninitialized by default.
 *
 * @internal
 */
enum NoDefault
{
    case NoDefault;
}
