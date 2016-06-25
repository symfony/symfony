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
use Symfony\Component\HttpFoundation\Exception\ConflictingHeadersException;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Validates that the headers and other information indicating the
 * client IP address of a request are consistent.
 *
 * @author Magnus Nordlander <magnus@fervo.se>
 */
class ValidateRequestClientIpListener implements EventSubscriberInterface
{
    /**
     * Performs the validation
     *
     * @param GetResponseEvent $event
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        try {
            // This will throw an exception if the headers are inconsistent.
            $event->getRequest()->getClientIps();
        } catch (ConflictingHeadersException $e) {
            throw new HttpException(400, "The request headers contain conflicting information regarding the origin of this request.", $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::REQUEST => array(
                array('onKernelRequest', 256),
            ),
        );
    }
}
