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
 * Removes empty service-locators registered for ServiceArgumentValueResolver.
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
        $controllers = $serviceResolver->getArgument(0)->getArgument(0);

        foreach ($container->findTaggedServiceIds('controller.arguments_locator') as $id => $tags) {
            $argumentLocator = $container->getDefinition($id)->clearTag('controller.arguments_locator');
            list($class, $service, $action) = $tags[0];

            if (!$argumentLocator->getArgument(0)) {
                // remove empty argument locators
                $reason = sprintf('Removing service-argument-resolver for controller "%s:%s": no corresponding definitions were found for the referenced services/types.%s', $service, $action, !$argumentLocator->isAutowired() ? ' Did you forget to enable autowiring?' : '');
            } else {
                // any methods listed for call-at-instantiation cannot be actions
                $reason = false;
                foreach ($container->getDefinition($service)->getMethodCalls() as list($method, $args)) {
                    if (0 === strcasecmp($action, $method)) {
                        $reason = sprintf('Removing method "%s" of service "%s" from controller candidates: the method is called at instantiation, thus cannot be an action.', $action, $service);
                        break;
                    }
                }
                if (!$reason) {
                    continue;
                }
            }

            $container->removeDefinition($id);
            unset($controllers[$service.':'.$action]);
            if ($service === $class) {
                unset($controllers[$service.'::'.$action]);
            }
            $container->log($this, $reason);
        }

        $serviceResolver->getArgument(0)->replaceArgument(0, $controllers);
    }
}
