<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\DependencyInjection;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireCallable;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\TypedReference;
use Symfony\Component\VarExporter\ProxyHelper;

/**
 * Creates the service-locators required by ServiceValueResolver for commands.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class RegisterCommandArgumentLocatorsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('console.argument_resolver.service')) {
            return;
        }

        $parameterBag = $container->getParameterBag();
        $serviceLocators = [];

        foreach ($container->findTaggedServiceIds('console.command.service_arguments', true) as $id => $tags) {
            $def = $container->getDefinition($id);
            $class = $def->getClass();
            $autowire = $def->isAutowired();
            $bindings = $def->getBindings();

            // Resolve service class, taking parent definitions into account
            while ($def instanceof ChildDefinition) {
                $def = $container->findDefinition($def->getParent());
                $class = $class ?: $def->getClass();
                $bindings += $def->getBindings();
            }
            $class = $parameterBag->resolveValue($class);

            if (!$r = $container->getReflectionClass($class)) {
                throw new InvalidArgumentException(\sprintf('Class "%s" used for command "%s" cannot be found.', $class, $id));
            }

            // Get all console.command tags to find command names and their methods
            $commandTags = $container->getDefinition($id)->getTag('console.command');
            $manualArguments = [];

            // Validate and collect explicit per-arguments service references
            foreach ($tags as $attributes) {
                if (!isset($attributes['argument']) && !isset($attributes['id'])) {
                    $autowire = true;
                    continue;
                }
                foreach (['argument', 'id'] as $k) {
                    if (!isset($attributes[$k][0])) {
                        throw new InvalidArgumentException(\sprintf('Missing "%s" attribute on tag "console.command.service_arguments" %s for service "%s".', $k, json_encode($attributes, \JSON_UNESCAPED_UNICODE), $id));
                    }
                }

                $manualArguments[$attributes['argument']] = $attributes['id'];
            }

            foreach ($commandTags as $commandTag) {
                $commandName = $commandTag['command'] ?? null;

                if (!$commandName) {
                    continue;
                }

                $methodName = $commandTag['method'] ?? '__invoke';

                if (!$r->hasMethod($methodName)) {
                    continue;
                }

                $method = $r->getMethod($methodName);
                $arguments = [];
                $erroredIds = 0;

                foreach ($method->getParameters() as $p) {
                    $type = preg_replace('/(^|[(|&])\\\\/', '\1', $target = ltrim(ProxyHelper::exportType($p) ?? '', '?'));
                    $invalidBehavior = ContainerInterface::IGNORE_ON_INVALID_REFERENCE;
                    $autowireAttributes = null;
                    $parsedName = $p->name;
                    $k = null;

                    if (isset($manualArguments[$p->name])) {
                        $target = $manualArguments[$p->name];
                        if ('?' !== $target[0]) {
                            $invalidBehavior = ContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE;
                        } elseif ('' === $target = substr($target, 1)) {
                            throw new InvalidArgumentException(\sprintf('A "console.command.service_arguments" tag must have non-empty "id" attributes for service "%s".', $id));
                        } elseif ($p->allowsNull() && !$p->isOptional()) {
                            $invalidBehavior = ContainerInterface::NULL_ON_INVALID_REFERENCE;
                        }
                    } elseif (isset($bindings[$bindingName = $type.' $'.$name = Target::parseName($p, $k, $parsedName)])
                        || isset($bindings[$bindingName = $type.' $'.$parsedName])
                        || isset($bindings[$bindingName = '$'.$name])
                        || isset($bindings[$bindingName = $type])
                    ) {
                        $binding = $bindings[$bindingName];

                        [$bindingValue, $bindingId, , $bindingType, $bindingFile] = $binding->getValues();
                        $binding->setValues([$bindingValue, $bindingId, true, $bindingType, $bindingFile]);

                        $arguments[$p->name] = $bindingValue;

                        continue;
                    } elseif (!$autowire || (!($autowireAttributes = $p->getAttributes(Autowire::class, \ReflectionAttribute::IS_INSTANCEOF)) && (!$type || '\\' !== $target[0]))) {
                        continue;
                    } elseif (!$autowireAttributes && is_subclass_of($type, \UnitEnum::class)) {
                        // Do not attempt to register enum typed arguments if not already present in bindings
                        continue;
                    } elseif (!$p->allowsNull()) {
                        $invalidBehavior = ContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE;
                    }

                    // Skip console-specific types that are resolved by other resolvers
                    if (InputInterface::class === $type || OutputInterface::class === $type) {
                        continue;
                    }

                    if ($autowireAttributes) {
                        $attribute = $autowireAttributes[0]->newInstance();
                        $value = $parameterBag->resolveValue($attribute->value);

                        if ($attribute instanceof AutowireCallable) {
                            $arguments[$p->name] = $attribute->buildDefinition($value, $type, $p);
                        } elseif ($value instanceof Reference) {
                            $arguments[$p->name] = $type ? new TypedReference($value, $type, $invalidBehavior, $p->name) : new Reference($value, $invalidBehavior);
                        } else {
                            $arguments[$p->name] = new Reference('.value.'.$container->hash($value));
                            $container->register((string) $arguments[$p->name], 'mixed')
                                ->setFactory('current')
                                ->addArgument([$value]);
                        }

                        continue;
                    }

                    if ($type && !$p->isOptional() && !$p->allowsNull() && !class_exists($type) && !interface_exists($type, false)) {
                        $message = \sprintf('Cannot determine command argument for "%s::%s()": the $%s argument is type-hinted with the non-existent class or interface: "%s".', $class, $method->name, $p->name, $type);

                        // See if the type-hint lives in the same namespace as the command
                        if (0 === strncmp($type, $class, strrpos($class, '\\'))) {
                            $message .= ' Did you forget to add a use statement?';
                        }

                        $container->register($erroredId = '.errored.'.$container->hash($message), $type)
                            ->addError($message);

                        $arguments[$p->name] = new Reference($erroredId, ContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
                        ++$erroredIds;
                    } else {
                        $target = preg_replace('/(^|[(|&])\\\\/', '\1', $target);
                        $arguments[$p->name] = $type ? new TypedReference($target, $type, $invalidBehavior, Target::parseName($p)) : new Reference($target, $invalidBehavior);
                    }
                }

                if ($arguments) {
                    $serviceLocators[$commandName] = ServiceLocatorTagPass::register($container, $arguments, \count($arguments) !== $erroredIds ? $commandName : null);
                }
            }
        }

        $container->getDefinition('console.argument_resolver.service')
            ->replaceArgument(0, ServiceLocatorTagPass::register($container, $serviceLocators));
    }
}
