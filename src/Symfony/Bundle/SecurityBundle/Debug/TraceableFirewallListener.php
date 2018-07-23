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
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

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
