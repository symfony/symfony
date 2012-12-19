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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Proxies URIs when the current route name is "_proxy".
 *
 * If the request does not come from a trusted, it throws an
 * AccessDeniedHttpException exception.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RouterProxyListener implements EventSubscriberInterface
{
    /**
     * Fixes request attributes when the route is '_proxy'.
     *
     * @param GetResponseEvent $event A GetResponseEvent instance
     *
     * @throws AccessDeniedHttpException if the request does not come from a trusted IP.
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        if ('_proxy' !== $request->attributes->get('_route')) {
            return;
        }

        $this->checkRequest($request);

        parse_str($request->query->get('path', ''), $attributes);
        $request->attributes->add($attributes);
        $request->attributes->set('_route_params', array_replace($request->attributes->get('_route_params'), $attributes));
        $request->query->remove('path');
    }

    protected function checkRequest(Request $request)
    {
        $trustedIps = array_merge($this->getLocalIpAddresses(), $request->getTrustedProxies());
        $remoteAddress = $request->server->get('REMOTE_ADDR');
        foreach ($trustedIps as $ip) {
            if (IpUtils::checkIp($remoteAddress, $ip)) {
                return;
            }
        }

        throw new AccessDeniedHttpException();
    }

    protected function getLocalIpAddresses()
    {
        return array('127.0.0.1', 'fe80::1', '::1');
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::REQUEST => array(array('onKernelRequest', 16)),
        );
    }
}
