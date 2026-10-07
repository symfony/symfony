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

use Symfony\Component\DependencyInjection\Attribute\AsCallable;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * Records the #[AsCallable] attributes as the "container.callable_service" tag, on definitions that are autoconfigured and don't have the "container.ignore_attributes" tag.
 *
 * An abstract method is recorded as an autoconfiguration rule, so that it reaches every service implementing it the way #[Autoconfigure] does, through {@see ResolveInstanceofConditionalsPass}.
 * A method a class declares itself is tagged directly, so that a subclass overriding it without the attribute gets nothing, which is what reflection already says.
 *
 * {@see RegisterCallableServicesPass} turns the tag into the services and removes it.
 *
 * @internal
 */
final class RegisterAsCallableAttributesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $definition) {
            if (!$definition->isAutoconfigured() || $definition->hasTag('container.ignore_attributes')) {
                continue;
            }

            if (!($r = $container->getReflectionClass($definition->getClass(), false))) {
                continue;
            }

            foreach ($r->getMethods() as $method) {
                if ($method->isConstructor() || $method->isDestructor()) {
                    continue;
                }

                if (!$attributes = $method->getAttributes(AsCallable::class, \ReflectionAttribute::IS_INSTANCEOF)) {
                    continue;
                }

                if (!$method->isPublic()) {
                    throw new InvalidArgumentException(\sprintf('The "#[AsCallable]" attribute cannot be used on the non-public method "%s::%s()".', $r->name, $method->name));
                }

                $declaredBy = $method->getDeclaringClass()->name;
                $tag = ['method' => $method->name, 'declared_by' => $declaredBy];

                if ($method->isAbstract() || $declaredBy !== $r->name) {
                    $attribute = $attributes[0]->newInstance();

                    if (null !== $attribute->id || null !== $attribute->target) {
                        throw new InvalidArgumentException(\sprintf('The "id" and "target" options of "#[AsCallable]" cannot be used on the %s method "%s::%s()": every service %s it would claim them.', $method->isAbstract() ? 'abstract' : 'inherited', $declaredBy, $method->name, $method->isAbstract() ? 'implementing' : 'inheriting'));
                    }
                }

                if ($method->isAbstract()) {
                    $container->registerForAutoconfiguration($r->name)
                        ->addTag(RegisterCallableServicesPass::TAG, $tag + ['inherited' => true]);
                } elseif (!$definition->isAbstract()) {
                    $definition->addTag(RegisterCallableServicesPass::TAG, $tag);
                }
            }
        }
    }
}
