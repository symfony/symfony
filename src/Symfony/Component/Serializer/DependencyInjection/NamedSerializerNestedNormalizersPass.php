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

use Symfony\Component\DependencyInjection\Argument\AbstractArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Gives each named serializer its own instances of the normalizers its normalizers depend on.
 *
 * This runs after autowiring so that autowired arguments are taken into account.
 *
 * @internal
 */
final class NamedSerializerNestedNormalizersPass implements CompilerPassInterface
{
    private const NAME_CONVERTER_METADATA_AWARE_ID = 'serializer.name_converter.metadata_aware';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('.serializer.nested_normalizers')) {
            return;
        }

        $config = $container->getParameter('.serializer.nested_normalizers');
        $container->getParameterBag()->remove('.serializer.nested_normalizers');

        foreach ($config['named_serializers'] as $serializerName => $serializerConfig) {
            $serializerConfig['circular_reference_handler'] = $config['circular_reference_handler'];
            $serializerConfig['max_depth_handler'] = $config['max_depth_handler'];

            $copies = [];
            foreach ($serializerConfig['children'] as $id => $childId) {
                $copies[$this->resolveAlias($container, $id)] = $childId;
            }

            $nestedCopies = [];
            foreach ($serializerConfig['children'] as $childId) {
                if ($container->hasDefinition($childId)) {
                    $this->copyNestedNormalizers($container, $container->getDefinition($childId), $serializerName, $serializerConfig, $copies, $nestedCopies);
                }
            }

            $serializer = new Reference($this->resolveAlias($container, $serializerConfig['serializer']));

            foreach ($nestedCopies as $copyId) {
                $this->setSerializer($container, $container->getDefinition($copyId), $serializer);
            }
        }
    }

    /**
     * @param array<string, string> $copies
     * @param array<string, string> $nestedCopies
     */
    private function copyNestedNormalizers(ContainerBuilder $container, Definition $definition, string $serializerName, array $config, array &$copies, array &$nestedCopies): void
    {
        $arguments = $definition->getArguments();

        foreach ($arguments as $index => $argument) {
            if (!$argument instanceof Reference) {
                continue;
            }

            $id = $this->resolveAlias($container, (string) $argument);

            if (\in_array($id, $copies, true)) {
                continue;
            }

            if (!isset($copies[$id])) {
                if (!$this->isNormalizer($container, $id)) {
                    continue;
                }

                $copies[$id] = $nestedCopies[$id] = $id.'.'.$serializerName;
                $copy = $container->setDefinition($copies[$id], $this->copyDefinition($container, $id, $config));
                $this->copyNestedNormalizers($container, $copy, $serializerName, $config, $copies, $nestedCopies);
            }

            $arguments[$index] = new Reference($copies[$id], $argument->getInvalidBehavior());
        }

        $definition->setArguments($arguments);
    }

    private function copyDefinition(ContainerBuilder $container, string $id, array $config): Definition
    {
        $copy = clone $container->getDefinition($id);
        $copy->setTags([]);

        $arguments = $copy->getArguments();

        foreach ($arguments as $index => $argument) {
            if ($argument instanceof Reference && self::NAME_CONVERTER_METADATA_AWARE_ID === $this->resolveAlias($container, (string) $argument)) {
                $arguments[$index] = new Reference($this->resolveAlias($container, $config['name_converter']), $argument->getInvalidBehavior());
            }
        }

        $copy->setArguments($arguments);

        $context = $container->getParameterBag()->resolveValue($config['default_context']);

        if (is_a($copy->getClass(), ObjectNormalizer::class, true)) {
            if (null !== $config['circular_reference_handler']) {
                $context += ['circular_reference_handler' => new Reference($this->resolveAlias($container, $config['circular_reference_handler']))];
            }
            if (null !== $config['max_depth_handler']) {
                $context += ['max_depth_handler' => new Reference($this->resolveAlias($container, $config['max_depth_handler']))];
            }
        }

        $this->setDefaultContext($container, $copy, $context);

        return $copy;
    }

    /**
     * Replaces the default context that comes from a binding, as a binding on a child definition would.
     */
    private function setDefaultContext(ContainerBuilder $container, Definition $definition, array $context): void
    {
        if ($definition->getFactory() || !$constructor = $container->getReflectionClass($definition->getClass(), false)?->getConstructor()) {
            return;
        }

        $bindings = $definition->getBindings();
        $binding = $bindings['array $defaultContext'] ?? $bindings['$defaultContext'] ?? null;
        $arguments = $definition->getArguments();

        if (array_filter(array_keys($arguments), \is_string(...))) {
            return;
        }

        foreach ($constructor->getParameters() as $index => $parameter) {
            if ('defaultContext' === $parameter->name) {
                if (\array_key_exists($index, $arguments) && !$arguments[$index] instanceof AbstractArgument && (null === $binding || $arguments[$index] != $binding->getValues()[0])) {
                    return;
                }

                $arguments[$index] = $context;
                $definition->setArguments($arguments);

                return;
            }

            if (!\array_key_exists($index, $arguments)) {
                if (!$parameter->isDefaultValueAvailable() || \is_object($default = $parameter->getDefaultValue())) {
                    return;
                }

                $arguments[$index] = $default;
            }
        }
    }

    private function setSerializer(ContainerBuilder $container, Definition $definition, Reference $serializer): void
    {
        if (!$class = $container->getReflectionClass($definition->getClass(), false)) {
            return;
        }

        if ($class->implementsInterface(SerializerAwareInterface::class)) {
            $definition->addMethodCall('setSerializer', [$serializer]);
        }

        if ($class->implementsInterface(DenormalizerAwareInterface::class)) {
            $definition->addMethodCall('setDenormalizer', [$serializer]);
        }

        if ($class->implementsInterface(NormalizerAwareInterface::class)) {
            $definition->addMethodCall('setNormalizer', [$serializer]);
        }
    }

    private function isNormalizer(ContainerBuilder $container, string $id): bool
    {
        if (!$container->hasDefinition($id) || !$class = $container->getReflectionClass($container->getDefinition($id)->getClass(), false)) {
            return false;
        }

        return ($class->implementsInterface(NormalizerInterface::class) || $class->implementsInterface(DenormalizerInterface::class))
            && !$class->implementsInterface(SerializerInterface::class);
    }

    private function resolveAlias(ContainerBuilder $container, string $id): string
    {
        $seen = [];

        while ($container->hasAlias($id) && !isset($seen[$id])) {
            $seen[$id] = true;
            $id = (string) $container->getAlias($id);
        }

        return $id;
    }
}
