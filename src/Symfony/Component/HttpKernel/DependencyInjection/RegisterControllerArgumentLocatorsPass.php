<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\DependencyInjection;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\LazyProxy\ProxyHelper;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\TypedReference;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Creates the service-locators required by ServiceValueResolver.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class RegisterControllerArgumentLocatorsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('argument_resolver.service') && !$container->hasDefinition('argument_resolver.not_tagged_controller')) {
            return;
        }

        $parameterBag = $container->getParameterBag();
        $controllers = [];

        $publicAliases = [];
        foreach ($container->getAliases() as $id => $alias) {
            if ($alias->isPublic() && !$alias->isPrivate()) {
                $publicAliases[(string) $alias][] = $id;
            }
        }

        foreach ($container->findTaggedServiceIds('controller.service_arguments', true) as $id => $tags) {
            $def = $container->getDefinition($id);
            $def->setPublic(true);
            $class = $def->getClass();
            $autowire = $def->isAutowired();
            $bindings = $def->getBindings();

            // resolve service class, taking parent definitions into account
            while ($def instanceof ChildDefinition) {
                $def = $container->findDefinition($def->getParent());
                $class = $class ?: $def->getClass();
                $bindings += $def->getBindings();
            }
            $class = $parameterBag->resolveValue($class);

            if (!$r = $container->getReflectionClass($class)) {
                throw new InvalidArgumentException(sprintf('Class "%s" used for service "%s" cannot be found.', $class, $id));
            }
            $isContainerAware = $r->implementsInterface(ContainerAwareInterface::class) || is_subclass_of($class, AbstractController::class);

            // get regular public methods
            $methods = [];
            $arguments = [];
            foreach ($r->getMethods(\ReflectionMethod::IS_PUBLIC) as $r) {
                if ('setContainer' === $r->name && $isContainerAware) {
                    continue;
                }
                if (!$r->isConstructor() && !$r->isDestructor() && !$r->isAbstract()) {
                    $methods[strtolower($r->name)] = [$r, $r->getParameters()];
                }
            }

            // validate and collect explicit per-actions and per-arguments service references
            foreach ($tags as $attributes) {
                if (!isset($attributes['action']) && !isset($attributes['argument']) && !isset($attributes['id'])) {
                    $autowire = true;
                    continue;
                }
                foreach (['action', 'argument', 'id'] as $k) {
                    if (!isset($attributes[$k][0])) {
                        throw new InvalidArgumentException(sprintf('Missing "%s" attribute on tag "controller.service_arguments" %s for service "%s".', $k, json_encode($attributes, \JSON_UNESCAPED_UNICODE), $id));
                    }
                }
                if (!isset($methods[$action = strtolower($attributes['action'])])) {
                    throw new InvalidArgumentException(sprintf('Invalid "action" attribute on tag "controller.service_arguments" for service "%s": no public "%s()" method found on class "%s".', $id, $attributes['action'], $class));
                }
                [$r, $parameters] = $methods[$action];
                $found = false;

                foreach ($parameters as $p) {
                    if ($attributes['argument'] === $p->name) {
                        if (!isset($arguments[$r->name][$p->name])) {
                            $arguments[$r->name][$p->name] = $attributes['id'];
                        }
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    throw new InvalidArgumentException(sprintf('Invalid "controller.service_arguments" tag for service "%s": method "%s()" has no "%s" argument on class "%s".', $id, $r->name, $attributes['argument'], $class));
                }
            }

            foreach ($methods as [$r, $parameters]) {
                /** @var \ReflectionMethod $r */

                // create a per-method map of argument-names to service/type-references
                $args = [];
                foreach ($parameters as $p) {
                    /** @var \ReflectionParameter $p */
                    $type = ltrim($target = (string) ProxyHelper::getTypeHint($r, $p), '\\');
                    $invalidBehavior = ContainerInterface::IGNORE_ON_INVALID_REFERENCE;

                    if (isset($arguments[$r->name][$p->name])) {
                        $target = $arguments[$r->name][$p->name];
                        if ('?' !== $target[0]) {
                            $invalidBehavior = ContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE;
                        } elseif ('' === $target = (string) substr($target, 1)) {
                            throw new InvalidArgumentException(sprintf('A "controller.service_arguments" tag must have non-empty "id" attributes for service "%s".', $id));
                        } elseif ($p->allowsNull() && !$p->isOptional()) {
                            $invalidBehavior = ContainerInterface::NULL_ON_INVALID_REFERENCE;
                        }
                    } elseif (isset($bindings[$bindingName = $type.' $'.$name = Target::parseName($p)]) || isset($bindings[$bindingName = '$'.$name]) || isset($bindings[$bindingName = $type])) {
                        $binding = $bindings[$bindingName];

                        [$bindingValue, $bindingId, , $bindingType, $bindingFile] = $binding->getValues();
                        $binding->setValues([$bindingValue, $bindingId, true, $bindingType, $bindingFile]);

                        if (!$bindingValue instanceof Reference) {
                            $args[$p->name] = new Reference('.value.'.$container->hash($bindingValue));
                            $container->register((string) $args[$p->name], 'mixed')
                                ->setFactory('current')
                                ->addArgument([$bindingValue]);
                        } else {
                            $args[$p->name] = $bindingValue;
                        }

                        continue;
                    } elseif (!$type || !$autowire || '\\' !== $target[0]) {
                        continue;
                    } elseif (is_subclass_of($type, \UnitEnum::class)) {
                        // do not attempt to register enum typed arguments if not already present in bindings
                        continue;
                    } elseif (!$p->allowsNull()) {
                        $invalidBehavior = ContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE;
                    }

                    if (Request::class === $type || SessionInterface::class === $type) {
                        continue;
                    }

                    if ($type && !$p->isOptional() && !$p->allowsNull() && !class_exists($type) && !interface_exists($type, false)) {
                        $message = sprintf('Cannot determine controller argument for "%s::%s()": the $%s argument is type-hinted with the non-existent class or interface: "%s".', $class, $r->name, $p->name, $type);

                        // see if the type-hint lives in the same namespace as the controller
                        if (0 === strncmp($type, $class, strrpos($class, '\\'))) {
                            $message .= ' Did you forget to add a use statement?';
                        }

                        $container->register($erroredId = '.errored.'.$container->hash($message), $type)
                            ->addError($message);

                        $args[$p->name] = new Reference($erroredId, ContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
                    } else {
                        $target = ltrim($target, '\\');
                        $args[$p->name] = $type ? new TypedReference($target, $type, $invalidBehavior, Target::parseName($p)) : new Reference($target, $invalidBehavior);
                    }
                }
                // register the maps as a per-method service-locators
                if ($args) {
                    $controllers[$id.'::'.$r->name] = ServiceLocatorTagPass::register($container, $args);

                    foreach ($publicAliases[$id] ?? [] as $alias) {
                        $controllers[$alias.'::'.$r->name] = clone $controllers[$id.'::'.$r->name];
                    }
                }
            }
        }

        $controllerLocatorRef = ServiceLocatorTagPass::register($container, $controllers);

        if ($container->hasDefinition('argument_resolver.service')) {
            $container->getDefinition('argument_resolver.service')
                ->replaceArgument(0, $controllerLocatorRef);
        }

        if ($container->hasDefinition('argument_resolver.not_tagged_controller')) {
            $container->getDefinition('argument_resolver.not_tagged_controller')
                ->replaceArgument(0, $controllerLocatorRef);
        }

        $container->setAlias('argument_resolver.controller_locator', (string) $controllerLocatorRef);
    }
}
