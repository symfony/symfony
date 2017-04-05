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

use Symfony\Component\WebLink\WebLinkManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds the Link HTTP header to the response.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @final
 */
class AddLinkHeaderListener implements EventSubscriberInterface
{
    private $linkManager;

    public function __construct(WebLinkManagerInterface $linkManager)
    {
        $this->linkManager = $linkManager;
    }

    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        if ($value = $this->linkManager->buildHeaderValue()) {
            $event->getResponse()->headers->set('Link', $value, false);

            // Free memory
            $this->linkManager->clear();
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(KernelEvents::RESPONSE => 'onKernelResponse');
    }
}
