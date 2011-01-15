<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\ParameterBag;

/**
 * 
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class FrozenParameterBag extends ParameterBag
{
    /**
     * Constructor.
     *
     * @param array $parameters An array of parameters
     */
    public function __construct(array $parameters = array())
    {
        foreach ($parameters as $key => $value) {
            $this->parameters[strtolower($key)] = $value;
        }
    }

    public function clear()
    {
        throw new \LogicException('Impossible to call clear() on a frozen ParameterBag.');
    }

    /**
     * Adds parameters to the service container parameters.
     *
     * @param array $parameters An array of parameters
     */
    public function add(array $parameters)
    {
        throw new \LogicException('Impossible to call add() on a frozen ParameterBag.');
    }

    /**
     * Sets a service container parameter.
     *
     * @param string $name       The parameter name
     * @param mixed  $parameters The parameter value
     */
    public function set($name, $value)
    {
        throw new \LogicException('Impossible to call set() on a frozen ParameterBag.');
    }
}
