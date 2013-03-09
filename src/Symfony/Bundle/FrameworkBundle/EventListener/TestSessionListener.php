<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\EventListener;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * TestSessionListener.
 *
 * Saves session in test environment.
 *
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TestSessionListener implements EventSubscriberInterface
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->getRequestType()) {
            return;
        }

        // bootstrap the session
        if (!$this->container->has('session')) {
            return;
        }

        $session = $this->container->get('session');
        $cookies = $event->getRequest()->cookies;

        if ($cookies->has($session->getName())) {
            $session->setId($cookies->get($session->getName()));
        }
    }

    /**
     * Checks if session was initialized and saves if current request is master
     * Runs on 'kernel.response' in test environment
     *
     * @param FilterResponseEvent $event
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->getRequestType()) {
            return;
        }

        $session = $event->getRequest()->getSession();
        if ($session && $session->isStarted()) {
            $session->save();
            $params = session_get_cookie_params();
            $event->getResponse()->headers->setCookie(new Cookie($session->getName(), $session->getId(), 0 === $params['lifetime'] ? 0 : time() + $params['lifetime'], $params['path'], $params['domain'], $params['secure'], $params['httponly']));
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::REQUEST => array('onKernelRequest', 192),
            KernelEvents::RESPONSE => array('onKernelResponse', -128),
        );
    }
}
