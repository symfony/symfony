<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarExporter\Internal;

/**
 * Stores the state of lazy objects and caches related reflection information.
 *
 * As a micro-optimization, this class uses no type declarations.
 *
 * @internal
 */
class LazyObjectRegistry
{
    public static \WeakMap $initializers;
}
