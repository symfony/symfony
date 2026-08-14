<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @author Jérémy Romey jeremyFreeAgent <jeremy@free-agent.fr>
 */
final class ProfilerLinkLogListener implements EventSubscriberInterface
{
    public function __construct(
        private ?LoggerInterface $logger = null,
        private ?UrlGeneratorInterface $urlGenerator = null,
    ) {
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (null === $this->logger || null === $this->urlGenerator || !$event->isMainRequest()) {
            return;
        }

        if (null === $token = $event->getResponse()->headers->get('X-Debug-Token')) {
            return;
        }

        // the "X-Debug-Token-Link" header holds the same URL, but it is set by
        // WebDebugToolbarListener, which is only registered when the toolbar is enabled
        $this->logger->debug('See profiler at {profiler_url}', [
            'profiler_url' => $this->urlGenerator->generate('_profiler', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -2048],
        ];
    }
}
