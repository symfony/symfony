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
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Collects attribute listeners and registers them for ControllerAttributesListener.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ControllerAttributesListenerPass implements CompilerPassInterface
{
    private const ATTRIBUTE_EVENTS = [
        KernelEvents::CONTROLLER,
        KernelEvents::CONTROLLER_ARGUMENTS,
        KernelEvents::VIEW,
        KernelEvents::RESPONSE,
        KernelEvents::EXCEPTION,
        KernelEvents::FINISH_REQUEST,
    ];

    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('event_dispatcher') || !$container->hasDefinition('kernel.controller_attributes_listener')) {
            return;
        }

        $dispatcherDefinition = $container->findDefinition('event_dispatcher');
        $attributesWithListeners = [];

        foreach ($dispatcherDefinition->getMethodCalls() as [$method, $arguments]) {
            if ('addListener' !== $method || !\is_string($eventName = $arguments[0] ?? null)) {
                continue;
            }

            foreach (self::ATTRIBUTE_EVENTS as $kernelEvent) {
                if ('.' === ($eventName[\strlen($kernelEvent)] ?? null) && str_starts_with($eventName, $kernelEvent)) {
                    $attributesWithListeners[$kernelEvent][substr($eventName, \strlen($kernelEvent) + 1)] = true;
                    break;
                }
            }
        }

        $container->getDefinition('kernel.controller_attributes_listener')->replaceArgument(0, $attributesWithListeners);
    }
}
