<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\JsonStreamer\Transformer\PropertyValueTransformerInterface;
use Symfony\Component\JsonStreamer\Transformer\ValueObjectTransformerInterface;

/**
 * Collects and merges services tagged with "json_streamer.property_value_transformer"
 * or "json_streamer.value_object_transformer" and sets them as arguments for the
 * services that need transformers.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
class TransformerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('json_streamer.stream_writer')) {
            return;
        }

        $map = [];

        foreach ($container->findTaggedServiceIds('json_streamer.property_value_transformer', true) as $id => $_) {
            $class = $container->getParameterBag()->resolveValue($container->getDefinition($id)->getClass());
            if (!\is_string($class) || !is_a($class, PropertyValueTransformerInterface::class, true)) {
                throw new InvalidArgumentException(\sprintf('The service "%s" tagged "json_streamer.property_value_transformer" must implement "%s".', $id, PropertyValueTransformerInterface::class));
            }

            $map[$id] = new Reference($id);
        }

        foreach ($container->findTaggedServiceIds('json_streamer.value_object_transformer', true) as $id => $_) {
            $class = $container->getParameterBag()->resolveValue($container->getDefinition($id)->getClass());
            if (!\is_string($class) || !is_a($class, ValueObjectTransformerInterface::class, true)) {
                throw new InvalidArgumentException(\sprintf('The service "%s" tagged "json_streamer.value_object_transformer" must implement "%s".', $id, ValueObjectTransformerInterface::class));
            }

            $valueObjectClassName = $class::getValueObjectClassName();
            $map[$valueObjectClassName] = new Reference($id);

            if ($id !== $valueObjectClassName && !$container->hasDefinition($valueObjectClassName) && !$container->hasAlias($valueObjectClassName)) {
                $container->setDefinition($valueObjectClassName, (new Definition($class))
                    ->setPublic(false)
                    ->setArguments($container->getDefinition($id)->getArguments())
                    ->addTag('json_streamer.value_transformer')
                );
            }
        }

        $transformersLocator = (new Definition(ServiceLocator::class, [$map]))
            ->addTag('container.service_locator');

        $container->setDefinition('json_streamer.transformers', $transformersLocator);

        $transformersReference = new Reference('json_streamer.transformers');

        $container->getDefinition('json_streamer.stream_reader')
            ->replaceArgument(0, $transformersReference);

        $container->getDefinition('json_streamer.stream_writer')
            ->replaceArgument(0, $transformersReference);

        $container->getDefinition('.json_streamer.cache_warmer.streamer')
            ->setArgument(7, $transformersReference);
    }
}
