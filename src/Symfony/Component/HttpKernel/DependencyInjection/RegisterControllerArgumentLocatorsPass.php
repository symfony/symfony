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

/**
 * Creates the service-locators required by ServiceValueResolver.
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

        foreach ($container->findTaggedServiceIds($this->controllerTag, true) as $id => $tags) {
            $def = $container->getDefinition($id);
            $class = $def->getClass();
            $autowire = $def->isAutowired();

            // resolve service class, taking parent definitions into account
            while (!$class && $def instanceof ChildDefinition) {
                $def = $container->findDefinition($def->getParent());
                $class = $def->getClass();
            }
            $class = $parameterBag->resolveValue($class);

            if (!$r = $container->getReflectionClass($class)) {
                throw new InvalidArgumentException(sprintf('Class "%s" used for service "%s" cannot be found.', $class, $id));
            }
            $isContainerAware = $r->implementsInterface(ContainerAwareInterface::class) || is_subclass_of($class, AbstractController::class);

            // get regular public methods
            $methods = array();
            $arguments = array();
            foreach ($r->getMethods(\ReflectionMethod::IS_PUBLIC) as $r) {
                if ('setContainer' === $r->name && $isContainerAware) {
                    continue;
                }
                if (!$r->isConstructor() && !$r->isDestructor() && !$r->isAbstract()) {
                    $methods[strtolower($r->name)] = array($r, $r->getParameters());
                }
            }

            // validate and collect explicit per-actions and per-arguments service references
            foreach ($tags as $attributes) {
                if (!isset($attributes['action']) && !isset($attributes['argument']) && !isset($attributes['id'])) {
                    $autowire = true;
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
                /** @var \ReflectionMethod $r */

                // create a per-method map of argument-names to service/type-references
                $args = array();
                foreach ($parameters as $p) {
                    /** @var \ReflectionParameter $p */
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
                    } elseif (!$type || !$autowire) {
                        continue;
                    }

                    if ($type && !$p->isOptional() && !$p->allowsNull() && !class_exists($type) && !interface_exists($type, false)) {
                        $message = sprintf('Cannot determine controller argument for "%s::%s()": the $%s argument is type-hinted with the non-existent class or interface: "%s".', $class, $r->name, $p->name, $type);

                        // see if the type-hint lives in the same namespace as the controller
                        if (0 === strncmp($type, $class, strrpos($class, '\\'))) {
                            $message .= ' Did you forget to add a use statement?';
                        }

                        throw new InvalidArgumentException($message);
                    }

                    $args[$p->name] = $type ? new TypedReference($target, $type, $r->class, $invalidBehavior) : new Reference($target, $invalidBehavior);
                }
                // register the maps as a per-method service-locators
                if ($args) {
                    $controllers[$id.':'.$r->name] = ServiceLocatorTagPass::register($container, $args);
                }
            }
        }

        $container->getDefinition($this->resolverServiceId)
            ->replaceArgument(0, ServiceLocatorTagPass::register($container, $controllers));
    }
}
