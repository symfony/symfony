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
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

/**
 * Adds configured formats to each request.
 *
 * @author Gildas Quemener <gildas.quemener@gmail.com>
 */
class AddRequestFormatsListener implements EventSubscriberInterface
{
    protected $formats;

    public function __construct(array $formats)
    {
        $this->formats = $formats;
    }

    /**
     * Adds request formats.
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();
        foreach ($this->formats as $format => $mimeTypes) {
            $request->setFormat($format, $mimeTypes);
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(KernelEvents::REQUEST => array('onKernelRequest', 1));
    }
}
