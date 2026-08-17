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

use Symfony\Component\DependencyInjection\Argument\LazyProxyArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\TypedReference;

/**
 * Resolves lazy proxy arguments by generating the lazy-loading definitions that implement them.
 *
 * @author HypeMC <hypemc@gmail.com>
 */
class ResolveLazyProxyPass extends AbstractRecursivePass
{
    protected bool $skipScalars = true;

    protected function processValue(mixed $value, bool $isRoot = false): mixed
    {
        if (!$value instanceof LazyProxyArgument) {
            return parent::processValue($value, $isRoot);
        }

        [$reference, $interfaces] = $value->getValues();

        if (!$definition = self::createProxyDefinition($this->container, $reference, $interfaces, $this->currentId)) {
            return $reference;
        }

        $id = (string) $reference;

        if ($definition->getClass() !== $id || $definition->getTag('proxy')) {
            $id .= '.'.$this->container->hash([$definition->getClass(), $definition->getTag('proxy')]);
        }
        $this->container->setDefinition($id = '.lazy.'.$id, $definition);

        $value->setValues([$reference, $interfaces, new Reference($id)]);

        return $value;
    }

    /**
     * Creates the definition of the lazy-loading service that should replace the given reference,
     * or null when the reference should be used as is.
     *
     * @internal
     */
    public static function createProxyDefinition(ContainerBuilder $container, Reference $reference, array $interfaces = [], ?string $currentId = null): ?Definition
    {
        $id = (string) $reference;
        $hasService = $container->has($id);

        if (!$hasService && ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE !== $reference->getInvalidBehavior()) {
            return null;
        }

        if ($reference instanceof TypedReference) {
            $type = $reference->getType();
        } elseif (!$hasService) {
            throw new ServiceNotFoundException($id, $currentId);
        } elseif (!$type = $container->findDefinition($id)->getClass()) {
            throw new InvalidArgumentException(\sprintf('Cannot create a lazy proxy for service "%s" because it has no class; configure the interface(s) that should be proxied instead.', $id));
        }

        if (!$interfaces) {
            if (str_contains($type, '|')) {
                throw new RuntimeException(\sprintf('Cannot lazily proxy union type "%s" for service "%s"; configure the interface(s) that should be proxied instead.', $type, $currentId));
            }
            $interfaces = str_contains($type, '&') ? explode('&', $type) : [];
        }

        if (!$interfaces && $hasService && $container->findDefinition($id)->isLazy()) {
            return null;
        }

        $proxyType = $interfaces ? $type : self::resolveProxyType($container, $type, $id);
        $definition = (new Definition($proxyType))
            ->setFactory('current')
            ->setArguments([[$reference]])
            ->setLazy(true);

        if ($interfaces) {
            if (!$container->getReflectionClass($proxyType, false)) {
                $definition->setClass('object');
            }
            foreach ($interfaces as $v) {
                $definition->addTag('proxy', ['interface' => $v]);
            }
        }

        return $definition;
    }

    /**
     * Resolves the class name that should be proxied.
     *
     * @param string $originalType The original parameter type-hint (e.g., the interface)
     * @param string $serviceId    The service ID the type-hint resolved to (e.g., the alias)
     */
    private static function resolveProxyType(ContainerBuilder $container, string $originalType, string $serviceId): string
    {
        if (!$container->has($serviceId)) {
            return $originalType;
        }

        $resolvedType = $container->findDefinition($serviceId)->getClass();
        $resolvedType = $container->getParameterBag()->resolveValue($resolvedType);

        if (!$resolvedType || !$container->getReflectionClass($resolvedType, false)) {
            return $originalType;
        }

        return $resolvedType;
    }
}
