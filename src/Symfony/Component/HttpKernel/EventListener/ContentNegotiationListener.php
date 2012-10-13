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
use Symfony\Component\HttpKernel\NegotiatedRouteHandler;
use Symfony\Component\Routing\Router;

/**
 * Set response header for content negotiation.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class ContentNegotiationListener implements EventSubscriberInterface
{
    /**
     * @var Router
     */
    private $router;

    /**
     * @param Router $router
     */
    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    /**
     * @param GetResponseEvent $event
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        $this->router->addRouteHandler(new NegotiatedRouteHandler($event->getRequest()->headers));
    }

    /**
     * @param GetResponseEvent $event
     */
    public function onKernelResponse(GetResponseEvent $event)
    {
        $attributes = $event->getRequest()->attributes;

        if ($attributes->has('_negotiated_headers')) {
            $vary    = implode(', ', $attributes->get('_negotiated_headers'));
            $headers = $event->getResponse()->headers;

            $headers->set('Vary', $headers->has('Vary') ? $headers->get('Vary').', '.$vary : $vary);
        }
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(KernelEvents::RESPONSE => 'onKernelResponse');
    }
}
