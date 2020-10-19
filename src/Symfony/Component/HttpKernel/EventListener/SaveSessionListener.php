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

@trigger_error(sprintf('The "%s" class is deprecated since Symfony 4.1, use AbstractSessionListener instead.', SaveSessionListener::class), \E_USER_DEPRECATED);

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @author Tobias Schultze <http://tobion.de>
 *
 * @deprecated since Symfony 4.1, use AbstractSessionListener instead
 */
class SaveSessionListener implements EventSubscriberInterface
{
    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ($request->hasSession() && ($session = $request->getSession())->isStarted()) {
            $session->save();
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            // low priority but higher than StreamedResponseListener
            KernelEvents::RESPONSE => [['onKernelResponse', -1000]],
        ];
    }
}
