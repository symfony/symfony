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

use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\LazyProxy\ProxyHelper;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\DependencyInjection\TypedReference;

/**
 * Creates the service-locators required by ServiceArgumentValueResolver.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class RegisterControllerArgumentLocatorsPass implements CompilerPassInterface
{
    private $resolverServiceId;
    private $controllerTag;

    public function __construct($resolverServiceId = 'argument_resolver.service', $controllerTag = 'controller.service_arguments')
    {
        $this->resolverServiceId = $resolverServiceId;
        $this->controllerTag = $controllerTag;
    }

    public function process(ContainerBuilder $container)
    {
        if (false === $container->hasDefinition($this->resolverServiceId)) {
            return;
        }

        $parameterBag = $container->getParameterBag();
        $controllers = array();

        foreach ($container->findTaggedServiceIds($this->controllerTag) as $id => $tags) {
            $def = $container->getDefinition($id);

            if ($def->isAbstract()) {
                continue;
            }
            $class = $def->getClass();
            $isAutowired = $def->isAutowired();

            // resolve service class, taking parent definitions into account
            while (!$class && $def instanceof ChildDefinition) {
                $def = $container->findDefinition($def->getParent());
                $class = $def->getClass();
            }
            $class = $parameterBag->resolveValue($class);

            if (!$r = $container->getReflectionClass($class)) {
                throw new InvalidArgumentException(sprintf('Class "%s" used for service "%s" cannot be found.', $class, $id));
            }

            // get regular public methods
            $methods = array();
            $arguments = array();
            foreach ($r->getMethods(\ReflectionMethod::IS_PUBLIC) as $r) {
                if (!$r->isConstructor() && !$r->isDestructor() && !$r->isAbstract()) {
                    $methods[strtolower($r->name)] = array($r, $r->getParameters());
                }
            }

            // validate and collect explicit per-actions and per-arguments service references
            foreach ($tags as $attributes) {
                if (!isset($attributes['action']) && !isset($attributes['argument']) && !isset($attributes['id'])) {
                    continue;
                }
                foreach (array('action', 'argument', 'id') as $k) {
                    if (!isset($attributes[$k][0])) {
                        throw new InvalidArgumentException(sprintf('Missing "%s" attribute on tag "%s" %s for service "%s".', $k, $this->controllerTag, json_encode($attributes, JSON_UNESCAPED_UNICODE), $id));
                    }
                }
                if (!isset($methods[$action = strtolower($attributes['action'])])) {
                    throw new InvalidArgumentException(sprintf('Invalid "action" attribute on tag "%s" for service "%s": no public "%s()" method found on class "%s".', $this->controllerTag, $id, $attributes['action'], $class));
                }
                list($r, $parameters) = $methods[$action];
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
                    throw new InvalidArgumentException(sprintf('Invalid "%s" tag for service "%s": method "%s()" has no "%s" argument on class "%s".', $this->controllerTag, $id, $r->name, $attributes['argument'], $class));
                }
            }

            foreach ($methods as list($r, $parameters)) {
                // create a per-method map of argument-names to service/type-references
                $args = array();
                foreach ($parameters as $p) {
                    $type = $target = ProxyHelper::getTypeHint($r, $p, true);
                    $invalidBehavior = ContainerInterface::IGNORE_ON_INVALID_REFERENCE;

                    if (isset($arguments[$r->name][$p->name])) {
                        $target = $arguments[$r->name][$p->name];
                        if ('?' !== $target[0]) {
                            $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE;
                        } elseif ('' === $target = (string) substr($target, 1)) {
                            throw new InvalidArgumentException(sprintf('A "%s" tag must have non-empty "id" attributes for service "%s".', $this->controllerTag, $id));
                        } elseif ($p->allowsNull() && !$p->isOptional()) {
                            $invalidBehavior = ContainerInterface::NULL_ON_INVALID_REFERENCE;
                        }
                    } elseif (!$type) {
                        continue;
                    }

                    $args[$p->name] = new ServiceClosureArgument($type ? new TypedReference($target, $type, $invalidBehavior, false) : new Reference($target, $invalidBehavior));
                }
                // register the maps as a per-method service-locators
                if ($args) {
                    $argsId = sprintf('arguments.%s:%s', $id, $r->name);
                    $container->register($argsId, ServiceLocator::class)
                        ->addArgument($args)
                        ->setPublic(false)
                        ->setAutowired($isAutowired)
                        ->addTag('controller.arguments_locator', array($class, $id, $r->name));
                    $controllers[$id.':'.$r->name] = new ServiceClosureArgument(new Reference($argsId));
                    if ($id === $class) {
                        $controllers[$id.'::'.$r->name] = new ServiceClosureArgument(new Reference($argsId));
                    }
                }
            }
        }

        $container->getDefinition($this->resolverServiceId)
            ->replaceArgument(0, (new Definition(ServiceLocator::class, array($controllers)))->addTag('container.service_locator'));
    }
}
