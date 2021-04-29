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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * ResponseListener fixes the Response headers based on the Request.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final since Symfony 4.3
 */
class ResponseListener implements EventSubscriberInterface
{
    private $charset;
    private $permissionsPolicy;

    public function __construct(string $charset, string $permissionsPolicy = null)
    {
        $this->charset = $charset;
        $this->permissionsPolicy = $permissionsPolicy;
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

        if (null === $this->permissionsPolicy || $response->headers->has('permissions-policy')) {
            return;
        }

        $contentType = $response->headers->get('Content-Type');

        if (false !== strpos($contentType, 'html') || false !== strpos($contentType, 'pdf')) {
            $response->headers->set('permissions-policy', $this->permissionsPolicy);
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }
}
