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
use Symfony\Component\HttpKernel\HttpCache\Ssi;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * EsiListener adds a Surrogate-Control HTTP header when the Response needs to be parsed for ESI.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SsiListener implements EventSubscriberInterface
{
    private $ssi;

    /**
     * Constructor.
     *
     * @param Ssi $ssi An ESI instance
     */
    public function __construct(Ssi $ssi = null)
    {
        $this->ssi = $ssi;
    }

    /**
     * Filters the Response.
     *
     * @param FilterResponseEvent $event A FilterResponseEvent instance
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->getRequestType() || null === $this->ssi) {
            return;
        }

        $this->ssi->addSurrogateControl($event->getResponse());
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::RESPONSE => 'onKernelResponse',
        );
    }
}
