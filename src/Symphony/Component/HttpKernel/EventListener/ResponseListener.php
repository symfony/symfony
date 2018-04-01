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

use Symphony\Component\HttpKernel\Event\FilterResponseEvent;
use Symphony\Component\HttpKernel\KernelEvents;
use Symphony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * ResponseListener fixes the Response headers based on the Request.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class ResponseListener implements EventSubscriberInterface
{
    private $charset;

    public function __construct(string $charset)
    {
        $this->charset = $charset;
    }

    /**
     * Filters the Response.
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $response = $event->getResponse();

        if (null === $response->getCharset()) {
            $response->setCharset($this->charset);
        }

        $response->prepare($event->getRequest());
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::RESPONSE => 'onKernelResponse',
        );
    }
}
