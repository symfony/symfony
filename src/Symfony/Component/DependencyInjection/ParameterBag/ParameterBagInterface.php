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

use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;

/**
 * ParameterBagInterface.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
interface ParameterBagInterface
{
    /**
     * Clears all parameters.
     *
     * @api
     */
    function clear();

    /**
     * Adds parameters to the service container parameters.
     *
     * @param array $parameters An array of parameters
     *
     * @api
     */
    function add(array $parameters);

    /**
     * Gets the service container parameters.
     *
     * @return array An array of parameters
     *
     * @api
     */
    function all();

    /**
     * Gets a service container parameter.
     *
     * @param string $name The parameter name
     *
     * @return mixed  The parameter value
     *
     * @throws ParameterNotFoundException if the parameter is not defined
     *
     * @api
     */
    function get($name);

    /**
     * Sets a service container parameter.
     *
     * @param string $name  The parameter name
     * @param mixed  $value The parameter value
     *
     * @api
     */
    function set($name, $value);

    /**
     * Returns true if a parameter name is defined.
     *
     * @param  string  $name       The parameter name
     *
     * @return Boolean true if the parameter name is defined, false otherwise
     *
     * @api
     */
    function has($name);

    /**
     * Replaces parameter placeholders (%name%) by their values for all parameters.
     */
    function resolve();

    /**
     * Replaces parameter placeholders (%name%) by their values.
     *
     * @param  mixed $value A value
     *
     * @throws ParameterNotFoundException if a placeholder references a parameter that does not exist
     */
    function resolveValue($value);
}
