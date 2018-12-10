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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;

/**
 * Synchronizes the locale between the request and the translator.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TranslatorListener implements EventSubscriberInterface
{
    private $translator;
    private $requestStack;

    /**
     * @param LocaleAwareInterface $translator
     */
    public function __construct($translator, RequestStack $requestStack)
    {
        if (!$translator instanceof TranslatorInterface && !$translator instanceof LocaleAwareInterface) {
            throw new \TypeError(sprintf('Argument 1 passed to %s() must be an instance of %s, %s given.', __METHOD__, LocaleAwareInterface::class, \is_object($translator) ? \get_class($translator) : \gettype($translator)));
        }
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
