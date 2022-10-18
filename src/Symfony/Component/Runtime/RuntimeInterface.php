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
 * Enables decoupling applications from global state.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface RuntimeInterface
{
    /**
     * Returns a resolver that should compute the arguments of a callable.
     *
     * The callable itself should return an object that represents the application to pass to the getRunner() method.
     */
    public function getResolver(callable $callable, \ReflectionFunction $reflector = null): ResolverInterface;

    /**
     * Returns a callable that knows how to run the passed object and that returns its exit status as int.
     *
     * The passed object is typically created by calling ResolverInterface::resolve().
     */
    public function getRunner(?object $application): RunnerInterface;
}
