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
                throw new \LogicException(\sprintf('Resetting a non-lazy manager service is not supported. Declare the "%s" service as lazy.', $name));
            }

            return;
        }
        if (\PHP_VERSION_ID < 80400) {
            if (!$manager instanceof LazyLoadingInterface) {
                throw new \LogicException(\sprintf('Resetting a non-lazy manager service is not supported. Declare the "%s" service as lazy.', $name));
            }
            trigger_deprecation('symfony/doctrine-bridge', '7.3', 'Support for proxy-manager is deprecated.');

            if ($manager instanceof GhostObjectInterface) {
                throw new \LogicException('Resetting a lazy-ghost-object manager service is not supported.');
            }
            $manager->setProxyInitializer(\Closure::bind(
                function (&$wrappedInstance, LazyLoadingInterface $manager) use ($name) {
                    $name = $this->aliases[$name] ?? $name;
                    $wrappedInstance = match (true) {
                        isset($this->fileMap[$name]) => $this->load($this->fileMap[$name], false),
                        !$method = $this->methodMap[$name] ?? null => throw new \LogicException(\sprintf('The "%s" service is synthetic and cannot be reset.', $name)),
                        (new \ReflectionMethod($this, $method))->isStatic() => $this->{$method}($this, false),
                        default => $this->{$method}(false),
                    };
                    $manager->setProxyInitializer(null);

                    return true;
                },
                $this->container,
                Container::class
            ));

            return;
        }

        $r = new \ReflectionClass($manager);

        if ($r->isUninitializedLazyObject($manager)) {
            return;
        }

        $asProxy = $r->initializeLazyObject($manager) !== $manager;
        $initializer = \Closure::bind(
            function ($manager) use ($name, $asProxy) {
                $name = $this->aliases[$name] ?? $name;
                if ($asProxy) {
                    $manager = false;
                }

                $manager = match (true) {
                    isset($this->fileMap[$name]) => $this->load($this->fileMap[$name], $manager),
                    !$method = $this->methodMap[$name] ?? null => throw new \LogicException(\sprintf('The "%s" service is synthetic and cannot be reset.', $name)),
                    (new \ReflectionMethod($this, $method))->isStatic() => $this->{$method}($this, $manager),
                    default => $this->{$method}($manager),
                };

                if ($asProxy) {
                    return $manager;
                }
            },
            $this->container,
            Container::class
        );

        try {
            if ($asProxy) {
                $r->resetAsLazyProxy($manager, $initializer);
            } else {
                $r->resetAsLazyGhost($manager, $initializer);
            }
        } catch (\Error $e) {
            if (__FILE__ !== $e->getFile()) {
                throw $e;
            }

            throw new \LogicException(\sprintf('Resetting a non-lazy manager service is not supported. Declare the "%s" service as lazy.', $name), 0, $e);
        }
    }
}
