<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ParametersConfigurator extends AbstractConfigurator
{
    public const FACTORY = 'parameters';

    private $container;

    public function __construct(ContainerBuilder $container)
    {
        $this->container = $container;
    }

    /**
     * @return $this
     */
    final public function set(string $name, mixed $value): static
    {
        $this->container->setParameter($name, static::processValue($value, true));

        return $this;
    }

    /**
     * @return $this
     */
    final public function __invoke(string $name, mixed $value): static
    {
        return $this->set($name, $value);
    }
}
