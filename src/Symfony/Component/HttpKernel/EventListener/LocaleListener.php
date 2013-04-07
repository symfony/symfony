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

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RequestContextAwareInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Initializes the locale based on the current request.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class LocaleListener implements EventSubscriberInterface
{
    private $router;
    private $defaultLocale;
    private $locales = array();

    public function __construct($defaultLocale = 'en', RequestContextAwareInterface $router = null)
    {
        $this->defaultLocale = $defaultLocale;
        $this->router = $router;
    }

    public function onKernelResponse(FilterResponseEvent $event)
    {
        array_shift($this->locales);

        // setting back the locale to the previous value
        $locale = isset($this->locales[0]) ? $this->locales[0] : $this->defaultLocale;
        $request = $event->getRequest();
        $this->setLocale($request, $locale);
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        $request->setDefaultLocale($this->defaultLocale);
        $this->setLocale($request, $request->attributes->get('_locale', $request->getLocale()));

        array_unshift($this->locales, $request->getLocale());
    }

    public static function getSubscribedEvents()
    {
        return array(
            // must be registered after the Router to have access to the _locale
            KernelEvents::REQUEST => array(array('onKernelRequest', 16)),
            KernelEvents::RESPONSE => 'onKernelResponse',
        );
    }

    private function setLocale(Request $request, $locale)
    {
        $request->setLocale($locale);

        if (null !== $this->router) {
            $this->router->getContext()->setParameter('_locale', $request->getLocale());
        }
    }
}
