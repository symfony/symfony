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

use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Show the Web Debug Toolbar (WDT) when navigating via Turbo Drive and when a strict Content-Security-Policy is set.
 * This is done by reusing WDT's nonces.
 * This service is registered only when TurboBundle is installed.
 *
 * @author David Petrásek <davidpetrasek@hotmail.cz>
 */
#[When(env: 'dev')]
class TurboDriveCspListener implements EventSubscriberInterface
{
    public function __construct(
        readonly private KernelInterface $kernel,
    ) {
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$this->kernel->isDebug()) {
            return;
        }
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $routeName = $request->get('_route');

        if ('_wdt' === $routeName) {
            return;
        }
        if ($request->headers->has('X-Turbo-Request-Id')) {
            return;
        }
        if ($request->headers->has('Turbo-Frame')) {
            return;
        }
        if ('turbo_stream' === $request->getPreferredFormat()) {
            return;
        }

        $response = $event->getResponse();

        $csp = $response->headers->get('Content-Security-Policy');
        if (!$csp) {
            return;
        }

        $scriptContent = <<<'EOD'
            document.addEventListener('turbo:before-fetch-request', (event) =>
            {
                var wdt = document.querySelector('.sf-toolbar');
                if (wdt)
                {
                    let wdtStyle = wdt.nextElementSibling;
                    let wdtScript = wdtStyle.nextElementSibling;

                    if (wdtStyle.nonce) {event.detail.fetchOptions.headers['X-SymfonyProfiler-Style-Nonce'] = wdtStyle.nonce;}
                    if (wdtScript.nonce) {event.detail.fetchOptions.headers['X-SymfonyProfiler-Script-Nonce'] = wdtScript.nonce;}
                }
            });
        EOD;
        $scriptTag = '<script>'.$scriptContent.'</script>';

        $hash = base64_encode(hash('sha256', $scriptContent, true));
        $hashString = "'sha256-".$hash."'";

        if (preg_match('/script-src\s+([^;]+)/', $csp, $matches)) {
            $scriptSrc = $matches[1];

            if (!str_contains($scriptSrc, $hashString)) {
                $newScriptSrc = $scriptSrc.' '.$hashString;
                $csp = str_replace($matches[0], 'script-src '.$newScriptSrc, $csp);
            }
        } else {
            $csp .= "; script-src 'self' ".$hashString;
        }
        $response->headers->set('Content-Security-Policy', $csp);

        $modifiedContent = str_replace('</head>', $scriptTag.'</head>', $response->getContent());
        $response->setContent($modifiedContent);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -256],
        ];
    }
}
