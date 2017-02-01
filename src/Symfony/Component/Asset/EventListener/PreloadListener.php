<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset\EventListener;

use Symfony\Component\Asset\Preload\PreloadManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds the preload Link HTTP header to the response.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class PreloadListener implements EventSubscriberInterface
{
    private $preloadManager;

    public function __construct(PreloadManager $preloadManager)
    {
        $this->preloadManager = $preloadManager;
    }

    public function onKernelResponse(FilterResponseEvent $event)
    {
        if ($value = $this->preloadManager->getLinkValue()) {
            $event->getResponse()->headers->set('Link', $value);

            // Free memory
            $this->preloadManager->setResources(array());
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
