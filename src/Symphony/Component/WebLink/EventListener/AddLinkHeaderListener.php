<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\WebLink\EventListener;

use Psr\Link\LinkProviderInterface;
use Symphony\Component\EventDispatcher\EventSubscriberInterface;
use Symphony\Component\HttpKernel\Event\FilterResponseEvent;
use Symphony\Component\HttpKernel\KernelEvents;
use Symphony\Component\WebLink\HttpHeaderSerializer;

/**
 * Adds the Link HTTP header to the response.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @final
 */
class AddLinkHeaderListener implements EventSubscriberInterface
{
    private $serializer;

    public function __construct()
    {
        $this->serializer = new HttpHeaderSerializer();
    }

    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $linkProvider = $event->getRequest()->attributes->get('_links');
        if (!$linkProvider instanceof LinkProviderInterface || !$links = $linkProvider->getLinks()) {
            return;
        }

        $event->getResponse()->headers->set('Link', $this->serializer->serialize($links), false);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(KernelEvents::RESPONSE => 'onKernelResponse');
    }
}
