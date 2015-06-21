<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Debug;

use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher as BaseTraceableEventDispatcher;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\Event;

/**
 * Collects some data about event listeners.
 *
 * This event dispatcher delegates the dispatching to another one.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TraceableEventDispatcher extends BaseTraceableEventDispatcher
{
    /**
     * {@inheritdoc}
     */
    protected function preDispatch($eventName, Event $event)
    {
        switch ($eventName) {
            case KernelEvents::REQUEST:
                $this->stopwatch->openSection();
                break;
            case KernelEvents::VIEW:
            case KernelEvents::RESPONSE:
                // stop only if a controller has been executed
                if ($this->stopwatch->isStarted('controller')) {
                    $this->stopwatch->stop('controller');
                }
                break;
            case KernelEvents::TERMINATE:
                $token = $event->getResponse()->headers->get('X-Debug-Token');
                // There is a very special case when using built-in AppCache class as kernel wrapper, in the case
                // of an ESI request leading to a `stale` response [B]  inside a `fresh` cached response [A].
                // In this case, `$token` contains the [B] debug token, but the  open `stopwatch` section ID
                // is equal to the [A] debug token. Trying to reopen section with the [B] token throws an exception
                // which must be caught.
                try {
                    $this->stopwatch->openSection($token);
                } catch (\LogicException $e) {
                }
                break;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function postDispatch($eventName, Event $event)
    {
        switch ($eventName) {
            case KernelEvents::CONTROLLER:
                $this->stopwatch->start('controller', 'section');
                break;
            case KernelEvents::RESPONSE:
                $token = $event->getResponse()->headers->get('X-Debug-Token');
                $this->stopwatch->stopSection($token);
                break;
            case KernelEvents::TERMINATE:
                // In the special case described in the `preDispatch` method above, the `$token` section
                // does not exist, then closing it throws an exception which must be caught.
                $token = $event->getResponse()->headers->get('X-Debug-Token');
                try {
                    $this->stopwatch->stopSection($token);
                } catch (\LogicException $e) {
                }
                break;
        }
    }
}
