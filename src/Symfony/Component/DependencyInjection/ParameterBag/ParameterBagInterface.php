<?php

namespace Symfony\Component\DependencyInjection\ParameterBag;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * ParameterBagInterface.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
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
