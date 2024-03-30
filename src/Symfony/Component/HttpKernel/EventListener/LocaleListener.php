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
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RequestContextAwareInterface;

/**
 * Initializes the locale based on the current request.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class LocaleListener implements EventSubscriberInterface
{
    public function __construct(
        private RequestStack $requestStack,
        private string $defaultLocale = 'en',
        private ?RequestContextAwareInterface $router = null,
        private bool $useAcceptLanguageHeader = false,
        private array $enabledLocales = [],
    ) {
    }

    public function setDefaultLocale(KernelEvent $event): void
    {
        $event->getRequest()->setDefaultLocale($this->defaultLocale);
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        $this->setLocale($request);
        $this->setRouterContext($request);
    }

    public function onKernelFinishRequest(FinishRequestEvent $event): void
    {
        if (null !== $parentRequest = $this->requestStack->getParentRequest()) {
            $this->setRouterContext($parentRequest);
        }
    }

    private function setLocale(Request $request): void
    {
        if ($locale = $request->attributes->get('_locale')) {
            $request->setLocale($locale);
        } elseif ($this->useAcceptLanguageHeader) {
            if ($request->getLanguages() && $preferredLanguage = $request->getPreferredLanguage($this->enabledLocales)) {
                $request->setLocale($preferredLanguage);
            }
            $request->attributes->set('_vary_by_language', true);
        }
    }

    private function setRouterContext(Request $request): void
    {
        $this->router?->getContext()->setParameter('_locale', $request->getLocale());
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['setDefaultLocale', 100],
                // must be registered after the Router to have access to the _locale
                ['onKernelRequest', 16],
            ],
            KernelEvents::FINISH_REQUEST => [['onKernelFinishRequest', 0]],
        ];
    }
}
