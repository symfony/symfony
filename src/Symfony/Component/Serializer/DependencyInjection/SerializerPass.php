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
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

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

    private const NAME_CONVERTER_METADATA_AWARE_ID = 'serializer.name_converter.metadata_aware';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('serializer')) {
            return;
        }

        $namedSerializers = $container->hasParameter('.serializer.named_serializers')
            ? $container->getParameter('.serializer.named_serializers') : [];

        $this->createNamedSerializerTags($container, 'serializer.normalizer', 'include_built_in_normalizers', $namedSerializers);
        $this->createNamedSerializerTags($container, 'serializer.encoder', 'include_built_in_encoders', $namedSerializers);

        if (!$normalizers = $this->findAndSortTaggedServices('serializer.normalizer.default', $container)) {
            throw new RuntimeException('You must tag at least one service as "serializer.normalizer" to use the "serializer" service.');
        }

        if (!$encoders = $this->findAndSortTaggedServices('serializer.encoder.default', $container)) {
            throw new RuntimeException('You must tag at least one service as "serializer.encoder" to use the "serializer" service.');
        }

        $defaultContext = [];
        if ($container->hasParameter('serializer.default_context')) {
            $defaultContext = $container->getParameter('serializer.default_context');
            $container->getParameterBag()->remove('serializer.default_context');
            $container->getDefinition('serializer')->setArgument('$defaultContext', $defaultContext);
        }

        /** @var ?string $circularReferenceHandler */
        $circularReferenceHandler = $container->hasParameter('.serializer.circular_reference_handler')
            ? $container->getParameter('.serializer.circular_reference_handler') : null;

        /** @var ?string $maxDepthHandler */
        $maxDepthHandler = $container->hasParameter('.serializer.max_depth_handler')
            ? $container->getParameter('.serializer.max_depth_handler') : null;

        $this->bindDefaultContext($container, array_merge($normalizers, $encoders), $defaultContext, $circularReferenceHandler, $maxDepthHandler);

        $this->configureSerializer($container, 'serializer', $normalizers, $encoders, 'default');

        if ($namedSerializers) {
            $this->configureNamedSerializers($container, $circularReferenceHandler, $maxDepthHandler);
        }
    }

    private function createNamedSerializerTags(ContainerBuilder $container, string $tagName, string $configName, array $namedSerializers): void
    {
        $serializerNames = array_keys($namedSerializers);
        $withBuiltIn = array_filter($serializerNames, fn (string $name) => $namedSerializers[$name][$configName] ?? false);

        foreach ($container->findTaggedServiceIds($tagName) as $serviceId => $tags) {
            $definition = $container->getDefinition($serviceId);

            foreach ($tags as $tag) {
                $names = (array) ($tag['serializer'] ?? []);

                if (!$names) {
                    $names = ['default'];
                } elseif (\in_array('*', $names, true)) {
                    $names = array_unique(['default', ...$serializerNames]);
                }

                if ($tag['built_in'] ?? false) {
                    $names = array_unique(['default', ...$names, ...$withBuiltIn]);
                }

                unset($tag['serializer'], $tag['built_in']);

                foreach ($names as $name) {
                    $definition->addTag($tagName.'.'.$name, $tag);
                }
            }
        }
    }

    private function bindDefaultContext(ContainerBuilder $container, array $services, array $defaultContext, ?string $circularReferenceHandler, ?string $maxDepthHandler): void
    {
        foreach ($services as $id) {
            $definition = $container->getDefinition((string) $id);

            $context = $defaultContext;
            if (is_a($definition->getClass(), ObjectNormalizer::class, true)) {
                if (null !== $circularReferenceHandler) {
                    $context += ['circular_reference_handler' => new Reference($circularReferenceHandler)];
                }
                if (null !== $maxDepthHandler) {
                    $context += ['max_depth_handler' => new Reference($maxDepthHandler)];
                }
            }

            $definition->setBindings(['array $defaultContext' => new BoundArgument($context, false)] + $definition->getBindings());
        }
    }

    private function configureSerializer(ContainerBuilder $container, string $id, array $normalizers, array $encoders, string $serializerName): void
    {
        if ($container->getParameter('kernel.debug') && $container->hasDefinition('serializer.data_collector')) {
            foreach ($normalizers as $i => $normalizer) {
                $normalizers[$i] = $container->register('.debug.serializer.normalizer.'.$normalizer, TraceableNormalizer::class)
                    ->setArguments([$normalizer, new Reference('serializer.data_collector'), $serializerName]);
            }

            foreach ($encoders as $i => $encoder) {
                $encoders[$i] = $container->register('.debug.serializer.encoder.'.$encoder, TraceableEncoder::class)
                    ->setArguments([$encoder, new Reference('serializer.data_collector'), $serializerName]);
            }
        }

        $serializerDefinition = $container->getDefinition($id);
        $serializerDefinition->replaceArgument(0, $normalizers);
        $serializerDefinition->replaceArgument(1, $encoders);
    }

    private function configureNamedSerializers(ContainerBuilder $container, ?string $circularReferenceHandler, ?string $maxDepthHandler): void
    {
        $defaultSerializerNameConverter = $container->hasParameter('.serializer.name_converter')
            ? $container->getParameter('.serializer.name_converter') : null;

        foreach ($container->getParameter('.serializer.named_serializers') as $serializerName => $config) {
            $config += ['default_context' => [], 'name_converter' => null];
            $serializerId = 'serializer.'.$serializerName;

            if (!$normalizers = $this->findAndSortTaggedServices('serializer.normalizer.'.$serializerName, $container)) {
                throw new RuntimeException(\sprintf('The named serializer "%1$s" requires at least one registered normalizer. Tag the normalizers as "serializer.normalizer" with the "serializer" attribute set to "%1$s".', $serializerName));
            }

            if (!$encoders = $this->findAndSortTaggedServices('serializer.encoder.'.$serializerName, $container)) {
                throw new RuntimeException(\sprintf('The named serializer "%1$s" requires at least one registered encoder. Tag the encoders as "serializer.encoder" with the "serializer" attribute set to "%1$s".', $serializerName));
            }

            $config['name_converter'] = $defaultSerializerNameConverter !== $config['name_converter']
                ? $this->buildChildNameConverterDefinition($container, $config['name_converter'])
                : self::NAME_CONVERTER_METADATA_AWARE_ID;

            $normalizers = $this->buildChildDefinitions($container, $serializerName, $normalizers, $config);
            $encoders = $this->buildChildDefinitions($container, $serializerName, $encoders, $config);

            $this->bindDefaultContext($container, array_merge($normalizers, $encoders), $config['default_context'], $circularReferenceHandler, $maxDepthHandler);

            $container->registerChild($serializerId, 'serializer')->setArgument('$defaultContext', $config['default_context']);
            $container->registerAliasForArgument($serializerId, SerializerInterface::class, $serializerName.'.serializer');

            $this->configureSerializer($container, $serializerId, $normalizers, $encoders, $serializerName);

            if ($container->getParameter('kernel.debug') && $container->hasDefinition('debug.serializer')) {
                $container->registerChild($debugId = 'debug.'.$serializerId, 'debug.serializer')
                    ->setDecoratedService($serializerId)
                    ->replaceArgument(0, new Reference($debugId.'.inner'))
                    ->replaceArgument(2, $serializerName);
            }
        }
    }

    private function buildChildNameConverterDefinition(ContainerBuilder $container, ?string $nameConverter): ?string
    {
        $childId = self::NAME_CONVERTER_METADATA_AWARE_ID.'.'.ContainerBuilder::hash($nameConverter);

        if (!$container->hasDefinition($childId)) {
            $childDefinition = $container->registerChild($childId, self::NAME_CONVERTER_METADATA_AWARE_ID.'.abstract');
            if (null !== $nameConverter) {
                $childDefinition->addArgument(new Reference($nameConverter));
            }
        }

        return $childId;
    }

    private function buildChildDefinitions(ContainerBuilder $container, string $serializerName, array $services, array $config): array
    {
        foreach ($services as &$id) {
            $id = $this->buildChildDefinition($container, $serializerName, (string) $id, $config);
        }

        return $services;
    }

    private function buildChildDefinition(ContainerBuilder $container, string $serializerName, string $id, array $config): Reference
    {
        $childId = $id.'.'.$serializerName;

        $definition = $container->getDefinition($id);
        $childDefinition = $container->registerChild($childId, $id)
            ->setClass($definition->getClass())
        ;

        foreach ($definition->getArguments() as $index => $argument) {
            if (!$argument instanceof Reference) {
                continue;
            }

            if (self::NAME_CONVERTER_METADATA_AWARE_ID === (string) $argument) {
                $childDefinition->replaceArgument($index, new Reference($config['name_converter']));
            } elseif (
                $container->hasDefinition($argument)
                && is_a(($argDefinition = $container->getDefinition($argument))->getClass(), NormalizerInterface::class, true)
                && !$argDefinition->hasTag('serializer.normalizer')
            ) {
                $childDefinition->replaceArgument($index, $this->buildChildDefinition($container, $serializerName, (string) $argument, $config));
            }
        }

        return new Reference($childId);
    }
}
