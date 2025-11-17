<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Debug;

use Symfony\Bundle\SecurityBundle\EventListener\FirewallListener;
use Symfony\Bundle\SecurityBundle\Security\FirewallContext;
use Symfony\Bundle\SecurityBundle\Security\LazyFirewallContext;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Http\Authenticator\Debug\TraceableAuthenticatorManagerListener;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Firewall collecting called security listeners and authenticators.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class TraceableFirewallListener extends FirewallListener implements ResetInterface
{
    private array $wrappedListeners = [];
    private ?TraceableAuthenticatorManagerListener $authenticatorManagerListener = null;

    public function getWrappedListeners(): array
    {
        return array_map(
            static fn (WrappedLazyListener $listener) => $listener->getInfo(),
            $this->wrappedListeners
        );
    }

    public function getAuthenticatorsInfo(): array
    {
        return $this->authenticatorManagerListener?->getAuthenticatorsInfo() ?? [];
    }

    public function reset(): void
    {
        $this->wrappedListeners = [];
        $this->authenticatorManagerListener = null;
    }

    protected function callListeners(RequestEvent $event, iterable $listeners): void
    {
        $requestListeners = [];
        foreach ($listeners as $listener) {
            if ($listener instanceof LazyFirewallContext) {
                $contextWrappedListeners = [];
                $contextAuthenticatorManagerListener = null;

                \Closure::bind(function () use (&$contextWrappedListeners, &$contextAuthenticatorManagerListener) {
                    foreach ($this->listeners as $listener) {
                        if ($listener instanceof TraceableAuthenticatorManagerListener) {
                            $contextAuthenticatorManagerListener ??= $listener;
                        }
                        $contextWrappedListeners[] = new WrappedLazyListener($listener);
                    }
                    $this->listeners = $contextWrappedListeners;
                }, $listener, FirewallContext::class)();

                $this->authenticatorManagerListener ??= $contextAuthenticatorManagerListener;
                $this->wrappedListeners = array_merge($this->wrappedListeners, $contextWrappedListeners);

                $requestListeners[] = $listener;
            } else {
                if ($listener instanceof TraceableAuthenticatorManagerListener) {
                    $this->authenticatorManagerListener ??= $listener;
                }
                $wrappedListener = new WrappedLazyListener($listener);
                $this->wrappedListeners[] = $wrappedListener;

                $requestListeners[] = $wrappedListener;
            }
        }

        foreach ($requestListeners as $listener) {
            if (false === $listener->supports($event->getRequest())) {
                continue;
            }

            $listener->authenticate($event);

            if ($event->hasResponse()) {
                break;
            }
        }
    }
}
