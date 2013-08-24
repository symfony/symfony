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

use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * ResponseListener fixes the Response headers based on the Request.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @since v2.1.0
 */
class ResponseListener implements EventSubscriberInterface
{
    private $charset;

    /**
     * @since v2.0.0
     */
    public function __construct($charset)
    {
        $this->charset = $charset;
    }

    /**
     * Filters the Response.
     *
     * @param FilterResponseEvent $event A FilterResponseEvent instance
     *
     * @since v2.0.0
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

    /**
     * @since v2.1.0
     */
    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::RESPONSE => 'onKernelResponse',
        );
    }
}
