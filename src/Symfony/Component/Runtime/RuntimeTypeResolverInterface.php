<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Runtime;

/**
 * Resolves a value for a given type in the context of a SymfonyRuntime.
 *
 * Type resolvers can be registered in a project's composer.json under the
 * "extra.runtime.type-resolvers" key, mapping fully-qualified class names
 * to resolver class names implementing this interface.
 *
 * Arguments from the runtime can be accessed by type-hinting the constructor of
 * the implementing class in the same way as can be done by a front-controller.
 *
 * @author Alexander Varwijk <git@alexandervarwijk.com>
 */
interface RuntimeTypeResolverInterface
{
    /**
     * Resolves a value for the given fully-qualified type name.
     *
     * @param string $type The fully-qualified class or interface name to resolve
     */
    public function resolveType(string $type): mixed;
}
