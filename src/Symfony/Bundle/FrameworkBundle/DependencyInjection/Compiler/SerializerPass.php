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
 * Adds all services with the tags "serializer.encoder" and "serializer.normalizer" as
 * encoders and normalizers to the Serializer service.
 *
 * @author Javier Lopez <f12loalf@gmail.com>
 */
class SerializerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('serializer')) {
            return;
        }

        // Looks for all the services tagged "serializer.normalizer" and adds them to the Serializer service
        $normalizers = $this->findAndSortTaggedServices('serializer.normalizer', $container);
        $container->getDefinition('serializer')->replaceArgument(0, $normalizers);

        // Looks for all the services tagged "serializer.encoders" and adds them to the Serializer service
        $encoders = $this->findAndSortTaggedServices('serializer.encoder', $container);
        $container->getDefinition('serializer')->replaceArgument(1, $encoders);
    }

    private function findAndSortTaggedServices($tag, $container)
    {
        // Find tagged services
        $servs = array();
        foreach ($container->findTaggedServiceIds($tag) as $serviceId => $value) {
            $priority = isset($value[0]['priority']) ? $value[0]['priority'] : 0;
            $servs[$priority][] = new Reference($serviceId);
        }

        // Sort them
        krsort($servs);

        // Flatten the array
        $services = array();
        array_walk_recursive($servs, function($a) use (&$services) { $services[] = $a; });
        
        return $services;
    }
}
