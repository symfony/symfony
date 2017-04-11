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

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Removes empty service-locators registered for ServiceValueResolver.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class RemoveEmptyControllerArgumentLocatorsPass implements CompilerPassInterface
{
    private $resolverServiceId;

    public function __construct($resolverServiceId = 'argument_resolver.service')
    {
        $this->resolverServiceId = $resolverServiceId;
    }

    public function process(ContainerBuilder $container)
    {
        if (false === $container->hasDefinition($this->resolverServiceId)) {
            return;
        }

        $serviceResolver = $container->getDefinition($this->resolverServiceId);
        $controllerLocator = $container->getDefinition((string) $serviceResolver->getArgument(0));
        $controllers = $controllerLocator->getArgument(0);

        foreach ($controllers as $controller => $argumentRef) {
            $argumentLocator = $container->getDefinition((string) $argumentRef->getValues()[0]);

            if (!$argumentLocator->getArgument(0)) {
                // remove empty argument locators
                $reason = sprintf('Removing service-argument resolver for controller "%s": no corresponding services exist for the referenced types.', $controller);
            } else {
                // any methods listed for call-at-instantiation cannot be actions
                $reason = false;
                $action = substr(strrchr($controller, ':'), 1);
                $id = substr($controller, 0, -1 - strlen($action));
                $controllerDef = $container->getDefinition($id);
                foreach ($controllerDef->getMethodCalls() as list($method, $args)) {
                    if (0 === strcasecmp($action, $method)) {
                        $reason = sprintf('Removing method "%s" of service "%s" from controller candidates: the method is called at instantiation, thus cannot be an action.', $action, $id);
                        break;
                    }
                }
                if (!$reason) {
                    if ($controllerDef->getClass() === $id) {
                        $controllers[$id.'::'.$action] = $argumentRef;
                    }
                    if ('__invoke' === $action) {
                        $controllers[$id] = $argumentRef;
                    }
                    continue;
                }
            }

            unset($controllers[$controller]);
            $container->log($this, $reason);
        }

        $controllerLocator->replaceArgument(0, $controllers);
    }
}
