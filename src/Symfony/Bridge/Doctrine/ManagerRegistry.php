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
     *
     * @return object
     */
    protected function getService($name)
    {
        return $this->container->get($name);
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    protected function resetService($name)
    {
        if (!$this->container->initialized($name)) {
            return;
        }
        $manager = $this->container->get($name);

        if (!$manager instanceof LazyLoadingInterface) {
            throw new \LogicException('Resetting a non-lazy manager service is not supported. '.(interface_exists(LazyLoadingInterface::class) && class_exists(RuntimeInstantiator::class) ? sprintf('Declare the "%s" service as lazy.', $name) : 'Try running "composer require symfony/proxy-manager-bridge".'));
        }
        if ($manager instanceof GhostObjectInterface) {
            throw new \LogicException('Resetting a lazy-ghost-object manager service is not supported.');
        }
        $manager->setProxyInitializer(\Closure::bind(
            function (&$wrappedInstance, LazyLoadingInterface $manager) use ($name) {
                if (isset($this->normalizedIds[$normalizedId = strtolower($name)])) { // BC with DI v3.4
                    $name = $this->normalizedIds[$normalizedId];
                }
                if (isset($this->aliases[$name])) {
                    $name = $this->aliases[$name];
                }
                if (isset($this->fileMap[$name])) {
                    $wrappedInstance = $this->load($this->fileMap[$name], false);
                } else {
                    $method = $this->methodMap[$name] ?? 'get'.strtr($name, $this->underscoreMap).'Service'; // BC with DI v3.4
                    $wrappedInstance = $this->{$method}(false);
                }

                $manager->setProxyInitializer(null);

                return true;
            },
            $this->container,
            Container::class
        ));
    }
}
