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
 * ParameterBagInterface is the interface implemented by objects that manage service container parameters.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface ParameterBagInterface
{
    /**
     * Clears all parameters.
     *
     * @throws LogicException if the ParameterBagInterface cannot be cleared
     */
    public function clear();

    /**
     * Adds parameters to the service container parameters.
     *
     * @throws LogicException if the parameter cannot be added
     */
    public function add(array $parameters);

    /**
     * Gets the service container parameters.
     *
     * @return array
     */
    public function all();

    /**
     * Gets a service container parameter.
     *
     * @return array|bool|string|int|float|null
     *
     * @throws ParameterNotFoundException if the parameter is not defined
     */
    public function get(string $name);

    /**
     * Removes a parameter.
     */
    public function remove(string $name);

    /**
     * Sets a service container parameter.
     *
     * @param array|bool|string|int|float|null $value The parameter value
     *
     * @throws LogicException if the parameter cannot be set
     */
    public function set(string $name, $value);

    /**
     * Returns true if a parameter name is defined.
     *
     * @return bool
     */
    public function has(string $name);

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
