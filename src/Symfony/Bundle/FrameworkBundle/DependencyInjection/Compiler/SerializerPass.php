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
        $normalizers = array();
        
        foreach ($container->findTaggedServiceIds('serializer.normalizer') as $serviceId => $tag) {
            $normalizers[] = new Reference($serviceId);
        }

        $container->getDefinition('serializer')->replaceArgument(0, $normalizers);

        // Looks for all the services tagged "serializer.encoders" and adds them to the Serializer service
        $encoders = array();

        foreach ($container->findTaggedServiceIds('serializer.encoder') as $serviceId => $tag) {
            $encoders[] = new Reference($serviceId);
        }

        $container->getDefinition('serializer')->replaceArgument(1, $encoders);
    }
}
