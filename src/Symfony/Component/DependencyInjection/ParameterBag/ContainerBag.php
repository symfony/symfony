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

use Symfony\Component\DependencyInjection\Container;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ContainerBag extends FrozenParameterBag implements ContainerBagInterface
{
    private $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function all(): array
    {
        return $this->container->getParameterBag()->all();
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name): array|bool|string|int|float|null
    {
        return $this->container->getParameter($name);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $name): bool
    {
        return $this->container->hasParameter($name);
    }
}
