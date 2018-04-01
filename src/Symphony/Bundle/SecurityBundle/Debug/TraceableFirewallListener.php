<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\SecurityBundle\Debug;

use Symphony\Bundle\SecurityBundle\EventListener\FirewallListener;
use Symphony\Component\HttpKernel\Event\GetResponseEvent;

/**
 * Firewall collecting called listeners.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class TraceableFirewallListener extends FirewallListener
{
    private $wrappedListeners;

    public function getWrappedListeners()
    {
        return $this->wrappedListeners;
    }

    protected function handleRequest(GetResponseEvent $event, $listeners)
    {
        foreach ($listeners as $listener) {
            $wrappedListener = new WrappedListener($listener);
            $wrappedListener->handle($event);
            $this->wrappedListeners[] = $wrappedListener->getInfo();

            if ($event->hasResponse()) {
                break;
            }
        }
    }
}
