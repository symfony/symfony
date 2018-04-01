<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\EventListener;

use Symphony\Component\EventDispatcher\EventSubscriberInterface;
use Symphony\Component\HttpKernel\Event\GetResponseEvent;
use Symphony\Component\HttpKernel\KernelEvents;

/**
 * Validates Requests.
 *
 * @author Magnus Nordlander <magnus@fervo.se>
 */
class ValidateRequestListener implements EventSubscriberInterface
{
    /**
     * Performs the validation.
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }
        $request = $event->getRequest();

        if ($request::getTrustedProxies()) {
            $request->getClientIps();
        }

        $request->getHost();
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::REQUEST => array(
                array('onKernelRequest', 256),
            ),
        );
    }
}
