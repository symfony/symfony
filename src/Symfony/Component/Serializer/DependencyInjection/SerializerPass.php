<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\DependencyInjection;

use Symfony\Component\DependencyInjection\Argument\BoundArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Serializer\Debug\TraceableEncoder;
use Symfony\Component\Serializer\Debug\TraceableNormalizer;

/**
 * Adds all services with the tags "serializer.encoder" and "serializer.normalizer" as
 * encoders and normalizers to the "serializer" service.
 *
 * @author Javier Lopez <f12loalf@gmail.com>
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
class SerializerPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    /**
     * @return void
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('serializer')) {
            return;
        }

        if (!$normalizers = $container->findTaggedServiceIds('serializer.normalizer')) {
            throw new RuntimeException('You must tag at least one service as "serializer.normalizer" to use the "serializer" service.');
        }

        if ($container->getParameter('kernel.debug') && $container->hasDefinition('serializer.data_collector')) {
            foreach (array_keys($normalizers) as $normalizer) {
                $container->register('debug.'.$normalizer, TraceableNormalizer::class)
                    ->setDecoratedService($normalizer)
                    ->setArguments([new Reference('debug.'.$normalizer.'.inner'), new Reference('serializer.data_collector')]);
            }
        }

        $serializerDefinition = $container->getDefinition('serializer');
        $serializerDefinition->replaceArgument(0, $this->findAndSortTaggedServices('serializer.normalizer', $container));

        if (!$encoders = $container->findTaggedServiceIds('serializer.encoder')) {
            throw new RuntimeException('You must tag at least one service as "serializer.encoder" to use the "serializer" service.');
        }

        if ($container->getParameter('kernel.debug') && $container->hasDefinition('serializer.data_collector')) {
            foreach (array_keys($encoders) as $encoder) {
                $container->register('debug.'.$encoder, TraceableEncoder::class)
                    ->setDecoratedService($encoder)
                    ->setArguments([new Reference('debug.'.$encoder.'.inner'), new Reference('serializer.data_collector')]);
            }
        }

        $serializerDefinition->replaceArgument(1, $this->findAndSortTaggedServices('serializer.encoder', $container));

        if (!$container->hasParameter('serializer.default_context')) {
            return;
        }

        $defaultContext = $container->getParameter('serializer.default_context');
        foreach (array_keys(array_merge($container->findTaggedServiceIds('serializer.normalizer'), $container->findTaggedServiceIds('serializer.encoder'))) as $service) {
            $definition = $container->getDefinition($service);
            $definition->setBindings(['array $defaultContext' => new BoundArgument($defaultContext, false)] + $definition->getBindings());
        }

        $container->getParameterBag()->remove('serializer.default_context');
    }
}
