<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Test;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * SessionListener.
 *
 * Saves session in test environment.
 *
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SessionListener
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function onCoreRequest(GetResponseEvent $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->getRequestType()) {
            return;
        }

        // bootstrap the session
        if ($this->container->has('session')) {
            $this->container->get('session');
        }

        $cookies = $event->getRequest()->cookies;
        if ($cookies->has(session_name())) {
            session_id($cookies->get(session_name()));
        }
    }

    /**
     * Checks if session was initialized and saves if current request is master
     * Runs on 'onCoreResponse' in test environment
     *
     * @param FilterResponseEvent $event
     */
    public function onCoreResponse(FilterResponseEvent $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->getRequestType()) {
            return;
        }

        if ($session = $event->getRequest()->getSession()) {
            $session->save();

            $params = session_get_cookie_params();

            $event->getResponse()->headers->setCookie(new Cookie(session_name(), session_id(), time() + $params['lifetime'], $params['path'], $params['domain'], $params['secure'], $params['httponly']));
        }
    }
}
