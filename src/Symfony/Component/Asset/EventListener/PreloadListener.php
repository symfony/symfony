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

use Symfony\Component\Asset\Preload\HttpFoundationPreloadManager;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

/**
 * Adds preload's Link HTTP headers to the response.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class PreloadListener
{
    private $preloadManager;

    public function __construct(HttpFoundationPreloadManager $preloadManager)
    {
        $this->preloadManager = $preloadManager;
    }

    public function onKernelResponse(FilterResponseEvent $event)
    {
        $this->preloadManager->setLinkHeader($event->getResponse());
    }
}
