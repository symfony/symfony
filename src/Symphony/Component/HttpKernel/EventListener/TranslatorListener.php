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

use Symphony\Component\EventDispatcher\EventSubscriberInterface;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpKernel\Event\GetResponseEvent;
use Symphony\Component\HttpKernel\Event\FinishRequestEvent;
use Symphony\Component\HttpKernel\KernelEvents;
use Symphony\Component\HttpFoundation\RequestStack;
use Symphony\Component\Translation\TranslatorInterface;

/**
 * Synchronizes the locale between the request and the translator.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class TranslatorListener implements EventSubscriberInterface
{
    private $translator;
    private $requestStack;

    public function __construct(TranslatorInterface $translator, RequestStack $requestStack)
    {
        $this->translator = $translator;
        $this->requestStack = $requestStack;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        $this->setLocale($event->getRequest());
    }

    public function onKernelFinishRequest(FinishRequestEvent $event)
    {
        if (null === $parentRequest = $this->requestStack->getParentRequest()) {
            return;
        }

        $this->setLocale($parentRequest);
    }

    public static function getSubscribedEvents()
    {
        return array(
            // must be registered after the Locale listener
            KernelEvents::REQUEST => array(array('onKernelRequest', 10)),
            KernelEvents::FINISH_REQUEST => array(array('onKernelFinishRequest', 0)),
        );
    }

    private function setLocale(Request $request)
    {
        try {
            $this->translator->setLocale($request->getLocale());
        } catch (\InvalidArgumentException $e) {
            $this->translator->setLocale($request->getDefaultLocale());
        }
    }
}
