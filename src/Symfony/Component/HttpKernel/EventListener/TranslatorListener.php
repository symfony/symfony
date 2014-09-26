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
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translator\TranslatorInterface;

/**
 * Synchronizes the locale between the request and the translator.
 *
 * @author Fabien Potencier <fabien@symfony.com>
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
        $this->translator->setLocale($event->getRequest()->getLocale());
    }

    public function onKernelFinishRequest(FinishRequestEvent $event)
    {
        if (null !== $parentRequest = $this->requestStack->getParentRequest()) {
            $this->translator->setLocale($parentRequest->getLocale());
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            // must be registered after the Locale listener
            KernelEvents::REQUEST => array(array('onKernelRequest', 10)),
            KernelEvents::FINISH_REQUEST => array(array('onKernelFinishRequest', 0)),
        );
    }
}
