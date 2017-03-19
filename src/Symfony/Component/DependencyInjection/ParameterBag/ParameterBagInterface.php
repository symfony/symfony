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

use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;

/**
 * ParameterBagInterface.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface ParameterBagInterface
{
    /**
     * Clears all parameters.
     *
     * @throws LogicException if the ParameterBagInterface can not be cleared
     */
    public function clear();

    /**
     * Adds parameters to the service container parameters.
     *
     * @param array $parameters An array of parameters
     *
     * @throws LogicException if the parameter can not be added
     */
    public function add(array $parameters);

    /**
     * Gets the service container parameters.
     *
     * @return array An array of parameters
     */
    public function all();

    /**
     * Gets a service container parameter.
     *
     * @param string $name The parameter name
     *
     * @return mixed The parameter value
     *
     * @throws ParameterNotFoundException if the parameter is not defined
     */
    public function get($name);

    /**
     * Removes a parameter.
     *
     * @param string $name The parameter name
     */
    public function remove($name);

    /**
     * Sets a service container parameter.
     *
     * @param string $name  The parameter name
     * @param mixed  $value The parameter value
     *
     * @throws LogicException if the parameter can not be set
     */
    public function set($name, $value);

    /**
     * Returns true if a parameter name is defined.
     *
     * @param string $name The parameter name
     *
     * @return bool true if the parameter name is defined, false otherwise
     */
    public function has($name);

    /**
     * Replaces parameter placeholders (%name%) by their values for all parameters.
     */
    public function resolve();

    /**
     * Replaces parameter placeholders (%name%) by their values.
     *
     * @param mixed $value A value
     *
     * @throws ParameterNotFoundException if a placeholder references a parameter that does not exist
     */
    public function resolveValue($value);

    /**
     * Escape parameter placeholders %.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function escapeValue($value);

    /**
     * Unescape parameter placeholders %.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function unescapeValue($value);
}
