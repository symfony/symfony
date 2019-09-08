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
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sets the session in the request.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
abstract class AbstractSessionListener implements EventSubscriberInterface
{
    private $sessionUsageStack = [];

    public function onKernelRequest(GetResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();
        $session = $this->getSession();
        $this->sessionUsageStack[] = $session instanceof Session ? $session->getUsageIndex() : null;
        if (null === $session || $request->hasSession()) {
            return;
        }

        $request->setSession($session);
    }

    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        if (!$session = $event->getRequest()->getSession()) {
            return;
        }

        if ($session instanceof Session ? $session->getUsageIndex() !== end($this->sessionUsageStack) : $session->isStarted()) {
            $event->getResponse()
                ->setExpires(new \DateTime())
                ->setPrivate()
                ->setMaxAge(0)
                ->headers->addCacheControlDirective('must-revalidate');
        }
    }

    /**
     * @internal
     */
    public function onFinishRequest(FinishRequestEvent $event)
    {
        if ($event->isMasterRequest()) {
            array_pop($this->sessionUsageStack);
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 128],
            // low priority to come after regular response listeners, same as SaveSessionListener
            KernelEvents::RESPONSE => ['onKernelResponse', -1000],
            KernelEvents::FINISH_REQUEST => ['onFinishRequest'],
        ];
    }

    /**
     * Gets the session object.
     *
     * @return SessionInterface|null A SessionInterface instance or null if no session is available
     */
    abstract protected function getSession();
}
