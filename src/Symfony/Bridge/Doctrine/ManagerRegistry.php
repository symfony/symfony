<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine;

use Doctrine\Persistence\AbstractManagerRegistry;
use ProxyManager\Proxy\GhostObjectInterface;
use ProxyManager\Proxy\LazyLoadingInterface;
use Symfony\Bridge\ProxyManager\LazyProxy\Instantiator\RuntimeInstantiator;
use Symfony\Component\DependencyInjection\Container;

/**
 * References Doctrine connections and entity/document managers.
 *
 * @author Lukas Kahwe Smith <smith@pooteeweet.org>
 */
abstract class ManagerRegistry extends AbstractManagerRegistry
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * {@inheritdoc}
     */
    protected function getService($name): object
    {
        return $this->container->get($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function resetService($name): void
    {
        if (!$this->container->initialized($name)) {
            return;
        }
        $manager = $this->container->get($name);

        if (!$manager instanceof LazyLoadingInterface) {
            throw new \LogicException('Resetting a non-lazy manager service is not supported. '.(interface_exists(LazyLoadingInterface::class) && class_exists(RuntimeInstantiator::class) ? sprintf('Declare the "%s" service as lazy.', $name) : 'Try running "composer require symfony/proxy-manager-bridge".'));
        }

        $load = \Closure::bind(function () use ($name) {
            if (isset($this->aliases[$name])) {
                $name = $this->aliases[$name];
            }
            if (isset($this->fileMap[$name])) {
                return fn ($lazyLoad) => $this->load($this->fileMap[$name], $lazyLoad);
            }

            return $this->{$this->methodMap[$name]}(...);
        }, $this->container, Container::class)();

        if ($manager instanceof GhostObjectInterface) {
            $initializer = function (GhostObjectInterface $manager, string $method, array $parameters, &$initializer, array $properties) use ($load) {
                $instance = $load($manager);
                $initializer = null;

                if ($instance !== $manager) {
                    throw new \LogicException(sprintf('A lazy initializer should return the ghost object proxy it was given as argument, but an instance of "%s" was returned.', get_debug_type($instance)));
                }

                return true;
            };
        } else {
            $initializer = function (&$wrappedInstance, LazyLoadingInterface $manager) use ($load) {
                $wrappedInstance = $load(false);
                $manager->setProxyInitializer(null);

                return true;
            };
        }

        $manager->setProxyInitializer($initializer);
    }
}
