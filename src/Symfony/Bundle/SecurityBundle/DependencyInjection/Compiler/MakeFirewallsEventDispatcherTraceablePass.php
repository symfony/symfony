<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;

/**
 * @author Mathieu Lechat <mathieu.lechat@les-tilleuls.coop>
 */
class MakeFirewallsEventDispatcherTraceablePass implements CompilerPassInterface
{
    /**
     * @return void
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->has('event_dispatcher') || !$container->hasParameter('security.firewalls')) {
            return;
        }

        if (!$container->getParameter('kernel.debug') || !$container->has('debug.stopwatch')) {
            return;
        }

        $dispatchersId = [];

        foreach ($container->getParameter('security.firewalls') as $firewallName) {
            $dispatcherId = 'security.event_dispatcher.'.$firewallName;

            if (!$container->has($dispatcherId)) {
                continue;
            }

            $dispatchersId[$dispatcherId] = 'debug.'.$dispatcherId;

            $container->register($dispatchersId[$dispatcherId], TraceableEventDispatcher::class)
                ->setDecoratedService($dispatcherId)
                ->setArguments([
                    new Reference($dispatchersId[$dispatcherId].'.inner'),
                    new Reference('debug.stopwatch'),
                    new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference('request_stack', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ]);
        }

        foreach (['kernel.event_subscriber', 'kernel.event_listener'] as $tagName) {
            foreach ($container->findTaggedServiceIds($tagName) as $taggedServiceId => $tags) {
                $taggedServiceDefinition = $container->findDefinition($taggedServiceId);
                $taggedServiceDefinition->clearTag($tagName);

                foreach ($tags as $tag) {
                    if ($dispatcherId = $tag['dispatcher'] ?? null) {
                        $tag['dispatcher'] = $dispatchersId[$dispatcherId] ?? $dispatcherId;
                    }
                    $taggedServiceDefinition->addTag($tagName, $tag);
                }
            }
        }
    }
}
