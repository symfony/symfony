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
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\LocaleAwareInterface;

/**
 * Pass the current locale to the provided services.
 *
 * @author Pierre Bobiet <pierrebobiet@gmail.com>
 */
class LocaleAwareListener implements EventSubscriberInterface
{
    private iterable $localeAwareServices;
    private RequestStack $requestStack;
    private array $storedLocales = [];

    /**
     * @param iterable<mixed, LocaleAwareInterface> $localeAwareServices
     */
    public function __construct(iterable $localeAwareServices, RequestStack $requestStack)
    {
        $this->localeAwareServices = $localeAwareServices;
        $this->requestStack = $requestStack;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            $locales = [];

            foreach ($this->localeAwareServices as $key => $service) {
                $locales[$key] = $service->getLocale();
            }

            $this->storedLocales[spl_object_id($event->getRequest())] = $locales;
        }

        $this->setLocale($event->getRequest()->getLocale(), $event->getRequest()->getDefaultLocale());
    }

    public function onKernelFinishRequest(FinishRequestEvent $event): void
    {
        $storedLocales = $this->storedLocales[$id = spl_object_id($event->getRequest())] ?? [];
        unset($this->storedLocales[$id]);

        if (null === $parentRequest = $this->requestStack->getParentRequest()) {
            foreach ($this->localeAwareServices as $service) {
                $service->setLocale($event->getRequest()->getDefaultLocale());
            }

            return;
        }

        $this->setLocale($parentRequest->getLocale(), $parentRequest->getDefaultLocale(), $storedLocales);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // must be registered after the Locale listener
            KernelEvents::REQUEST => [['onKernelRequest', 15]],
            KernelEvents::FINISH_REQUEST => [['onKernelFinishRequest', -15]],
        ];
    }

    private function setLocale(string $locale, string $defaultLocale, array $storedLocales = []): void
    {
        foreach ($this->localeAwareServices as $key => $service) {
            try {
                $service->setLocale($storedLocales[$key] ?? $locale);
            } catch (\InvalidArgumentException) {
                $service->setLocale($defaultLocale);
            }
        }
    }
}
