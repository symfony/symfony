<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\ParameterBag;

/**
 * ParameterBagInterface.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface ParameterBagInterface
{
    /**
     * Clears all parameters.
     */
    function clear();

    /**
     * Adds parameters to the service container parameters.
     *
     * @param array $parameters An array of parameters
     */
    function add(array $parameters);

    /**
     * Gets the service container parameters.
     *
     * @return array An array of parameters
     */
    function all();

    /**
     * Gets a service container parameter.
     *
     * @param string $name The parameter name
     *
     * @return mixed  The parameter value
     *
     * @throws  \InvalidArgumentException if the parameter is not defined
     */
    function get($name);

    /**
     * Sets a service container parameter.
     *
     * @param string $name       The parameter name
     * @param mixed  $parameters The parameter value
     */
    function set($name, $value);

    /**
     * Returns true if a parameter name is defined.
     *
     * @param  string  $name       The parameter name
     *
     * @return Boolean true if the parameter name is defined, false otherwise
     */
    function has($name);
}
