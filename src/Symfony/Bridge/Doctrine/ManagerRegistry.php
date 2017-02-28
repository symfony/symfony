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
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Doctrine\Common\Persistence\AbstractManagerRegistry;

/**
 * References Doctrine connections and entity/document managers.
 *
 * @author  Lukas Kahwe Smith <smith@pooteeweet.org>
 */
abstract class ManagerRegistry extends AbstractManagerRegistry implements ContainerAwareInterface
{
    use ContainerAwareTrait;

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
            @trigger_error(sprintf('Resetting a non-lazy manager service is deprecated since Symfony 3.2 and will throw an exception in version 4.0. Set the "%s" service as lazy and require "symfony/proxy-manager-bridge" in your composer.json file instead.', $name), E_USER_DEPRECATED);

            $this->container->set($name, null);

            return;
        }
        $manager->setProxyInitializer(\Closure::bind(
            function (&$wrappedInstance, LazyLoadingInterface $manager) use ($name) {
                if (isset($this->aliases[$name = strtolower($name)])) {
                    $name = $this->aliases[$name];
                }
                $method = !isset($this->methodMap[$name]) ? 'get'.strtr($name, $this->underscoreMap).'Service' : $this->methodMap[$name];
                $wrappedInstance = $this->{$method}(false);

                $manager->setProxyInitializer(null);

                return true;
            },
            $this->container,
            Container::class
        ));
    }
}
