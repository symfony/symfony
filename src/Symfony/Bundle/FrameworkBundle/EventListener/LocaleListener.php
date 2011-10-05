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

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Routing\RouterInterface;

/**
 * Initializes the locale based on the current request.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class LocaleListener
{
    private $router;
    private $defaultLocale;

    public function __construct($defaultLocale = 'en', RouterInterface $router = null)
    {
        $this->defaultLocale = $defaultLocale;
        $this->router = $router;
    }

    public function onEarlyKernelRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();
        if ($request->hasPreviousSession()) {
            $request->setDefaultLocale($request->getSession()->get('_locale', $this->defaultLocale));
        } else {
            $request->setDefaultLocale($this->defaultLocale);
        }
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->getRequestType()) {
            return;
        }

        $request = $event->getRequest();
        if ($locale = $request->attributes->get('_locale')) {
            $request->setLocale($locale);

            if ($request->hasPreviousSession()) {
                $request->getSession()->set('_locale', $request->getLocale());
            }
        }

        if (null !== $this->router) {
            $this->router->getContext()->setParameter('_locale', $request->getLocale());
        }
    }
}
