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
use Symfony\Component\Security\Core\AuthenticationEvents;
use Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Http\Event\AuthenticationTokenCreatedEvent;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\Event\TokenDeauthenticatedEvent;
use Symfony\Component\Security\Http\SecurityEvents;

/**
 * Makes sure all event listeners on the global dispatcher are also listening
 * to events on the firewall-specific dispatchers.
 *
 * This compiler pass must be run after RegisterListenersPass of the
 * EventDispatcher component.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @internal
 */
class RegisterGlobalSecurityEventListenersPass implements CompilerPassInterface
{
    private const EVENT_BUBBLING_EVENTS = [
        CheckPassportEvent::class,
        LoginFailureEvent::class,
        LoginSuccessEvent::class,
        LogoutEvent::class,
        AuthenticationTokenCreatedEvent::class,
        AuthenticationSuccessEvent::class,
        InteractiveLoginEvent::class,
        TokenDeauthenticatedEvent::class,

        // When events are registered by their name
        AuthenticationEvents::AUTHENTICATION_SUCCESS,
        SecurityEvents::INTERACTIVE_LOGIN,
    ];

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->has('event_dispatcher') || !$container->hasParameter('security.firewalls')) {
            return;
        }

        $firewallDispatchers = [];
        foreach ($container->getParameter('security.firewalls') as $firewallName) {
            if (!$container->has('security.event_dispatcher.'.$firewallName)) {
                continue;
            }

            $firewallDispatchers[] = $container->findDefinition('security.event_dispatcher.'.$firewallName);
        }

        $globalDispatcher = $container->findDefinition('event_dispatcher');
        foreach ($globalDispatcher->getMethodCalls() as $methodCall) {
            if ('addListener' !== $methodCall[0]) {
                continue;
            }

            $methodCallArguments = $methodCall[1];
            if (!\in_array($methodCallArguments[0], self::EVENT_BUBBLING_EVENTS, true)) {
                continue;
            }

            foreach ($firewallDispatchers as $firewallDispatcher) {
                $firewallDispatcher->addMethodCall('addListener', $methodCallArguments);
            }
        }
    }
}
