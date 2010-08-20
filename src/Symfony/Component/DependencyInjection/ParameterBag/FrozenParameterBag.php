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
 * 
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
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
