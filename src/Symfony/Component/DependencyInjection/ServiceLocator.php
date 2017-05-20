<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

use Psr\Container\ContainerInterface as PsrContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * @author Robin Chalas <robin.chalas@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ServiceLocator implements PsrContainerInterface
{
    private $factories;

    /**
     * @param callable[] $factories
     */
    public function __construct(array $factories)
    {
        $this->factories = $factories;
    }

    /**
     * {@inheritdoc}
     */
    public function has($id)
    {
        return isset($this->factories[$id]);
    }

    /**
     * {@inheritdoc}
     */
    public function get($id)
    {
        if (!isset($this->factories[$id])) {
            throw new ServiceNotFoundException($id, null, null, array_keys($this->factories));
        }

        if (true === $factory = $this->factories[$id]) {
            throw new ServiceCircularReferenceException($id, array($id, $id));
        }

        $this->factories[$id] = true;
        try {
            return $factory();
        } finally {
            $this->factories[$id] = $factory;
        }
    }

    public function __invoke($id)
    {
        return isset($this->factories[$id]) ? $this->get($id) : null;
    }
}
