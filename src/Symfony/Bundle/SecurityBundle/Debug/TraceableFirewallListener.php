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
use Symfony\Component\Security\Http\Firewall\AbstractListener;

/**
 * Firewall collecting called listeners.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class TraceableFirewallListener extends FirewallListener
{
    private $wrappedListeners = [];

    public function getWrappedListeners()
    {
        return $this->wrappedListeners;
    }

    protected function callListeners(RequestEvent $event, iterable $listeners)
    {
        $wrappedListeners = [];
        $wrappedLazyListeners = [];

        foreach ($listeners as $listener) {
            if ($listener instanceof LazyFirewallContext) {
                \Closure::bind(function () use (&$wrappedLazyListeners, &$wrappedListeners) {
                    $listeners = [];
                    foreach ($this->listeners as $listener) {
                        if ($listener instanceof AbstractListener) {
                            $listener = new WrappedLazyListener($listener);
                            $listeners[] = $listener;
                            $wrappedLazyListeners[] = $listener;
                        } else {
                            $listeners[] = function (RequestEvent $event) use ($listener, &$wrappedListeners) {
                                $wrappedListener = new WrappedListener($listener);
                                $wrappedListener($event);
                                $wrappedListeners[] = $wrappedListener->getInfo();
                            };
                        }
                    }
                    $this->listeners = $listeners;
                }, $listener, FirewallContext::class)();

                $listener($event);
            } else {
                $wrappedListener = $listener instanceof AbstractListener ? new WrappedLazyListener($listener) : new WrappedListener($listener);
                $wrappedListener($event);
                $wrappedListeners[] = $wrappedListener->getInfo();
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
    }
}
