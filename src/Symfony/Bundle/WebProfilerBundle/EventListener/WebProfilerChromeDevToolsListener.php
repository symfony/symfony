<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Profiler\Profiler;

/**
 * Disables the profiler on Chrome DevTools "automatic workspace folders" probes
 * to prevent polluting the profile list with 404 entries.
 *
 * @see https://chromium.googlesource.com/devtools/devtools-frontend/+/main/docs/ecosystem/automatic_workspace_folders.md
 *
 * @author Michael Thieulin <michael.thieulin@gmail.com>
 *
 * @internal
 */
final class WebProfilerChromeDevToolsListener implements EventSubscriberInterface
{
    private const DEVTOOLS_PROBE_PATH = '/.well-known/appspecific/com.chrome.devtools.json';

    public function __construct(
        private readonly ?Profiler $profiler = null,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (self::DEVTOOLS_PROBE_PATH !== $event->getRequest()->getPathInfo()) {
            return;
        }

        $this->profiler?->disable();
    }

    public static function getSubscribedEvents(): array
    {
        // priority higher than RouterListener (32), which throws NotFoundHttpException
        // on unknown paths and prevents later request listeners from running
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 35],
        ];
    }
}
