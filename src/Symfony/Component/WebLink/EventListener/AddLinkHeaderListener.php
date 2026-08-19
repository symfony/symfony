<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\WebLink\EventListener;

use Psr\Link\LinkProviderInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\WebLink\HttpHeaderSerializer;
use Symfony\Component\WebLink\LinkTemplateHeaderSerializer;

// Help opcache.preload discover always-needed symbols
class_exists(HttpHeaderSerializer::class);
class_exists(LinkTemplateHeaderSerializer::class);

/**
 * Adds the Link and Link-Template HTTP headers to the response.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @final
 */
class AddLinkHeaderListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly HttpHeaderSerializer $serializer = new HttpHeaderSerializer(),
        private readonly LinkTemplateHeaderSerializer $templateSerializer = new LinkTemplateHeaderSerializer(),
    ) {
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $linkProvider = $event->getRequest()->attributes->get('_links');
        if (!$linkProvider instanceof LinkProviderInterface || !$links = $linkProvider->getLinks()) {
            return;
        }

        $headers = $event->getResponse()->headers;

        if (null !== $header = $this->serializer->serialize($links)) {
            $headers->set('Link', $header, false);
        }

        if (null !== $header = $this->templateSerializer->serialize($links)) {
            $headers->set('Link-Template', $header, false);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onKernelResponse'];
    }
}
