<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\EventListener;

@trigger_error(sprintf('The "%s" class is deprecated since Symphony 4.1, use AbstractSessionListener instead.', SaveSessionListener::class), E_USER_DEPRECATED);

use Symphony\Component\EventDispatcher\EventSubscriberInterface;
use Symphony\Component\HttpKernel\Event\FilterResponseEvent;
use Symphony\Component\HttpKernel\KernelEvents;

/**
 * @author Tobias Schultze <http://tobion.de>
 *
 * @deprecated since Symphony 4.1, use AbstractSessionListener instead
 */
class SaveSessionListener implements EventSubscriberInterface
{
    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $session = $event->getRequest()->getSession();
        if ($session && $session->isStarted()) {
            $session->save();
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            // low priority but higher than StreamedResponseListener
            KernelEvents::RESPONSE => array(array('onKernelResponse', -1000)),
        );
    }
}
