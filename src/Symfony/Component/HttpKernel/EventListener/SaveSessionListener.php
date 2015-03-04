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
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Saves the session, in case it is still open, before sending the response/headers.
 *
 * This ensures several things in case the developer did not save the session explicitly:
 *
 *  * If a session save handler without locking is used, it ensures the data is available
 *    on the next request, e.g. after a redirect. PHPs auto-save at script end via
 *    session_register_shutdown is executed after fastcgi_finish_request. So in this case
 *    the data could be missing the next request because it might not be saved the moment
 *    the new request is processed.
 *  * A locking save handler (e.g. the native 'files') circumvents concurrency problems like
 *    the one above. But by saving the session before long-running things in the terminate event,
 *    we ensure the session is not blocked longer than needed.
 *  * When regenerating the session ID no locking is involved in PHPs session design. See
 *    https://bugs.php.net/bug.php?id=61470 for a discussion. So in this case, the session must
 *    be saved anyway before sending the headers with the new session ID. Otherwise session
 *    data could get lost again for concurrent requests with the new ID. One result could be
 *    that you get logged out after just logging in.
 *
 * This listener should be executed as one of the last listeners, so that previous listeners
 * can still operate on the open session. This prevents the overhead of restarting it.
 * Listeners after closing the session can still work with the session as usual because
 * Symfonys session implementation starts the session on demand. So writing to it after
 * it is saved will just restart it.
 *
 * @author Tobias Schultze <http://tobion.de>
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
