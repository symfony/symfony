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
 * Enables decoupling apps from global state.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface RuntimeInterface
{
    /**
     * Returns a resolver that should return the object representing your app.
     *
     * This object representing your app should then be passed to the start() method.
     */
    public function resolve(\Closure $app): ResolvedAppInterface;

    /**
     * Returns a starter that runs the app and returns its exit status.
     *
     * The passed object should be created by calling the resolver returned by the resolve() method.
     */
    public function start(object $app): StartedAppInterface;
}
