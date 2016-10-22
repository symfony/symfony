<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\GetEnvInterface;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;

/**
 * Checks that all env-referenced services exist and implement GetEnvInterface.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class CheckEnvReferencedServicesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $envReferencedServices = $container->getParameterBag() instanceof EnvPlaceholderParameterBag ? $container->getParameterBag()->getEnvReferencedServices() : array();

        foreach ($envReferencedServices as $id) {
            if (!$container->has($id)) {
                throw new ServiceNotFoundException($id);
            }
            $class = $container->getDefinition($id)->getClass();
            if (!is_subclass_of($class, GetEnvInterface::class)) {
                if (!class_exists($class, false)) {
                    throw new InvalidArgumentException(sprintf('Class "%s" used for service "%s" cannot be found.', $class, $id));
                }

                throw new InvalidArgumentException(sprintf('The service "%s" referenced in env parameters must implement "%s".', $id, GetEnvInterface::class));
            }
        }
    }
}
