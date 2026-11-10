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
use Symfony\Component\Security\Http\Firewall\FirewallListenerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Firewall collecting called security listeners and authenticators.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class TraceableFirewallListener extends FirewallListener implements ResetInterface
{
    private array $wrappedListeners = [];
    private array $authenticatorsInfo = [];

    /**
     * @return array
     */
    public function getWrappedListeners()
    {
        return $this->wrappedListeners;
    }

    public function getAuthenticatorsInfo(): array
    {
        return $this->authenticatorsInfo;
    }

    public function reset(): void
    {
        $this->wrappedListeners = [];
        $this->authenticatorsInfo = [];
    }

    protected function callListeners(RequestEvent $event, iterable $listeners): void
    {
        $wrappedListeners = [];
        $wrappedLazyListeners = [];
        $authenticatorManagerListener = null;

        foreach ($listeners as $listener) {
            if ($listener instanceof LazyFirewallContext) {
                $contextListeners = [];
                \Closure::bind(function () use (&$wrappedLazyListeners, &$wrappedListeners, &$authenticatorManagerListener, &$contextListeners) {
                    $listeners = [];
                    foreach ($this->listeners as $listener) {
                        $contextListeners[] = $listener;
                        if (!$authenticatorManagerListener && $listener instanceof TraceableAuthenticatorManagerListener) {
                            $authenticatorManagerListener = $listener;
                        }
                        if ($listener instanceof FirewallListenerInterface) {
                            $listener = new WrappedLazyListener($listener);
                            $listeners[] = $listener;
                            $wrappedLazyListeners[] = $listener;
                        } else {
                            $listeners[] = static function (RequestEvent $event) use ($listener, &$wrappedListeners) {
                                $wrappedListener = new WrappedListener($listener);
                                $wrappedListener($event);
                                $wrappedListeners[] = $wrappedListener->getInfo();
                            };
                        }
                    }
                    $this->listeners = $listeners;
                }, $listener, FirewallContext::class)();

                try {
                    $listener($event);
                } finally {
                    // The context outlives the request: put its listeners back so that the next request does not wrap the wrapped ones.
                    // Listeners that run lazily keep their wrapper, the context captured them when it was called.
                    \Closure::bind(function () use ($contextListeners) {
                        $this->listeners = $contextListeners;
                    }, $listener, FirewallContext::class)();
                }
            } else {
                $wrappedListener = $listener instanceof FirewallListenerInterface ? new WrappedLazyListener($listener) : new WrappedListener($listener);
                $wrappedListener($event);
                $wrappedListeners[] = $wrappedListener->getInfo();
                if (!$authenticatorManagerListener && $listener instanceof TraceableAuthenticatorManagerListener) {
                    $authenticatorManagerListener = $listener;
                }
            }

            if ($event->hasResponse()) {
                break;
            }
        }

        if ($wrappedLazyListeners) {
            foreach ($wrappedLazyListeners as $lazyListener) {
                $this->wrappedListeners[] = $lazyListener->getInfo();
            }
        }

        $this->wrappedListeners = array_merge($this->wrappedListeners, $wrappedListeners);

        if ($authenticatorManagerListener) {
            $this->authenticatorsInfo = $authenticatorManagerListener->getAuthenticatorsInfo();
        }
    }
}
