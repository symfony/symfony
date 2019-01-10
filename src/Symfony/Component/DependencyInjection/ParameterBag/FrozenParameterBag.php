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

/**
 * Holds read-only parameters.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class FrozenParameterBag extends ParameterBag
{
    /**
     * For performance reasons, the constructor assumes that
     * all keys are already lowercased.
     *
     * This is always the case when used internally.
     *
     * @param array $parameters An array of parameters
     */
    public function __construct(array $parameters = [])
    {
        $this->parameters = $parameters;
        $this->resolved = true;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        throw new LogicException('Impossible to call clear() on a frozen ParameterBag.');
    }

    /**
     * {@inheritdoc}
     */
    public function add(array $parameters)
    {
        throw new LogicException('Impossible to call add() on a frozen ParameterBag.');
    }

    /**
     * {@inheritdoc}
     */
    public function set($name, $value)
    {
        throw new LogicException('Impossible to call set() on a frozen ParameterBag.');
    }

    /**
     * {@inheritdoc}
     */
    public function remove($name)
    {
        throw new LogicException('Impossible to call remove() on a frozen ParameterBag.');
    }
}
