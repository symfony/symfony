<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\EventListener;

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Set response header for content negotiation.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class ContentNegotiationListener implements EventSubscriberInterface
{
    public function onKernelRequest(GetResponseEvent $event)
    {
        $attributes = $event->getRequest()->attributes;

        if ($attributes->has('_negotiation')) {
            $vary    = implode(', ', $attributes->get('_negotiation'));
            $headers = $event->getResponse()->headers;

            $headers->set('Vary', $headers->has('Vary') ? $headers->get('Vary').', '.$vary : $vary);
        }
    }

    public static function getSubscribedEvents()
    {
        return array(KernelEvents::REQUEST => 'onKernelRequest');
    }
}
