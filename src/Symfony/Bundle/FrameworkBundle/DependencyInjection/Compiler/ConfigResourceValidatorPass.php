<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Finds ResourceValidators and adds them to the ConfigCache factory service.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 */
class ConfigResourceValidatorPass implements CompilerPassInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('config.cache_factory.default')) {
            return;
        }

        $definition = $container->getDefinition('config.cache_factory.default');
        $debug = $container->getParameter('kernel.debug');

        foreach ($container->findTaggedServiceIds('config.resource_validator') as $id => $attributesList) {
            foreach ($attributesList as $attributes) {
                if (!$debug && !(isset($attributes['debugOnly']) && $attributes['debugOnly'] == false)) {
                    continue;
                }

                // We must assume that the class value has been correctly filled, even if the service is created by a factory
                $class = $container->getDefinition($id)->getClass();

                $refClass = new \ReflectionClass($class);
                $interface = 'Symfony\Component\Config\Resource\ResourceValidatorInterface';

                if (!$refClass->implementsInterface($interface)) {
                    throw new \InvalidArgumentException(sprintf('Service "%s" must implement interface "%s".', $id, $interface));
                }

                $definition->addMethodCall('addResourceValidator', array(new Reference($id)));
            }
        }
    }
}
