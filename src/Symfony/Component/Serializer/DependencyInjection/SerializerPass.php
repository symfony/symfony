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
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Serializer\Debug\TraceableEncoder;
use Symfony\Component\Serializer\Debug\TraceableNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

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

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('serializer')) {
            return;
        }

        if (!$encoders = $this->findAndSortTaggedServices('serializer.encoder', $container)) {
            throw new RuntimeException('You must tag at least one service as "serializer.encoder" to use the "serializer" service.');
        }

        $normalizers = $this->findAndSortTaggedServices('serializer.normalizer', $container);
        $denormalizers = $this->findAndSortTaggedServices('serializer.denormalizer', $container);

        if ($container->hasParameter('serializer.default_context')) {
            $defaultContext = $container->getParameter('serializer.default_context');
            foreach (array_merge($normalizers, $denormalizers, $encoders) as $service) {
                $definition = $container->getDefinition($service);
                $definition->setBindings(['array $defaultContext' => new BoundArgument($defaultContext, false)] + $definition->getBindings());
            }

            $container->getParameterBag()->remove('serializer.default_context');
        }

        if ($container->getParameter('kernel.debug') && $container->hasDefinition('serializer.data_collector')) {
            foreach ($normalizers as $i => $normalizer) {
                $normalizers[$i] = $container->register('.debug.serializer.normalizer.'.$normalizer, TraceableNormalizer::class)
                    ->setArguments([$normalizer, new Reference('serializer.data_collector')]);
            }

            foreach ($denormalizers as $i => $denormalizer ) {
                $normalizers[$i] = $container->register('.debug.serializer.denormalizer.'.$denormalizer, TraceableNormalizer::class)
                    ->setArguments([$denormalizer, new Reference('serializer.data_collector')]);
            }

            foreach ($encoders as $i => $encoder) {
                $encoders[$i] = $container->register('.debug.serializer.encoder.'.$encoder, TraceableEncoder::class)
                    ->setArguments([$encoder, new Reference('serializer.data_collector')]);
            }
        }

        $serializerDefinition = $container->getDefinition('serializer');
        $serializerDefinition->replaceArgument(1, $encoders);

        // if FrameworkBundle 7.2 or above
        if ($container->hasDefinition('serializer.normalizer') && $container->hasDefinition('serializer.denormalizer')) {
            $container->getDefinition('serializer.normalizer')->replaceArgument(0, $normalizers);
            $container->getDefinition('serializer.denormalizer')->replaceArgument(0, $denormalizers);
        } else {
            if (!$normalizers) {
                throw new RuntimeException('You must tag at least one service as "serializer.normalizer" to use the "serializer" service.');
            }
            $serializerDefinition->replaceArgument(0, $normalizers);
        }
    }
}
