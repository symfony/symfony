<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\Loader\Configurator;

use Symphony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ParametersConfigurator extends AbstractConfigurator
{
    const FACTORY = 'parameters';

    private $container;

    public function __construct(ContainerBuilder $container)
    {
        $this->container = $container;
    }

    /**
     * Creates a parameter.
     *
     * @return $this
     */
    final public function set(string $name, $value)
    {
        $this->container->setParameter($name, static::processValue($value, true));

        return $this;
    }

    /**
     * Creates a parameter.
     *
     * @return $this
     */
    final public function __invoke(string $name, $value)
    {
        return $this->set($name, $value);
    }
}
