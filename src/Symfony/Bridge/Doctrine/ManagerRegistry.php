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
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\VarExporter\LazyObjectInterface;

/**
 * References Doctrine connections and entity/document managers.
 *
 * @author Lukas Kahwe Smith <smith@pooteeweet.org>
 */
abstract class ManagerRegistry extends AbstractManagerRegistry
{
    protected Container $container;

    protected function getService($name): object
    {
        return $this->container->get($name);
    }

    protected function resetService($name): void
    {
        if (!$this->container->initialized($name)) {
            return;
        }
        $manager = $this->container->get($name);

        if ($manager instanceof LazyObjectInterface) {
            if (!$manager->resetLazyObject()) {
                throw new \LogicException(sprintf('Resetting a non-lazy manager service is not supported. Declare the "%s" service as lazy.', $name));
            }

            return;
        }
        if (!$manager instanceof LazyLoadingInterface) {
            throw new \LogicException(sprintf('Resetting a non-lazy manager service is not supported. Declare the "%s" service as lazy.', $name));
        }
        if ($manager instanceof GhostObjectInterface) {
            throw new \LogicException('Resetting a lazy-ghost-object manager service is not supported.');
        }
        $manager->setProxyInitializer(\Closure::bind(
            function (&$wrappedInstance, LazyLoadingInterface $manager) use ($name) {
                if (isset($this->aliases[$name])) {
                    $name = $this->aliases[$name];
                }
                if (isset($this->fileMap[$name])) {
                    $wrappedInstance = $this->load($this->fileMap[$name], false);
                } else {
                    $wrappedInstance = $this->{$this->methodMap[$name]}(false);
                }

                $manager->setProxyInitializer(null);

                return true;
            },
            $this->container,
            Container::class
        ));
    }
}
