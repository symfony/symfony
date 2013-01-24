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

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * SsiListener adds a Surrogate-Control HTTP header when the Response needs to be parsed for SSI.
 *
 * @author Sebastian Krebs <krebs.seb@gmail.com>
 */
class SsiListener implements EventSubscriberInterface
{
    /**
     * Filters the Response.
     *
     * @param FilterResponseEvent $event A FilterResponseEvent instance
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST === $event->getRequestType()) {
            $this->updateResponseHeader($event->getResponse());
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::RESPONSE => 'onKernelResponse',
        );
    }

    private function updateResponseHeader (Response $response)
    {
        if (false !== strpos($response->getContent(), '<!--#include')) {
            $header = $response->headers->get('Surrogate-Control');
            $response->headers->set('Surrogate-Control', ($header ? $header . ', ' : '') . 'content=SSI/1.0');
        }
    }
}
