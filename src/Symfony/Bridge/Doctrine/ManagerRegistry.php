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

use ProxyManager\Proxy\LazyLoadingInterface;
use Symfony\Component\DependencyInjection\Container;
use Doctrine\Common\Persistence\AbstractManagerRegistry;

/**
 * References Doctrine connections and entity/document managers.
 *
 * @author  Lukas Kahwe Smith <smith@pooteeweet.org>
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
    protected function getService($name)
    {
        return $this->container->get($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function resetService($name)
    {
        if (!$this->container->initialized($name)) {
            return;
        }
        $manager = $this->container->get($name);

        if (!$manager instanceof LazyLoadingInterface) {
            throw new \LogicException(sprintf('Resetting a non-lazy manager service is not supported. Set the "%s" service as lazy and require "symfony/proxy-manager-bridge" in your composer.json file instead.', $name));
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
                    $wrappedInstance = $this->load($this->fileMap[$name]);
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
