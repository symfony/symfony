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
class TurboDriveCspListener implements EventSubscriberInterface
{
    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $responseContent = $response->getContent();

        if (!str_contains($responseContent, '<div id="sfwdt')) {
            return;
        }

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
        $hashString = "'sha256-" . $hash . "'";

        if (preg_match('/script-src\s+([^;]+)/', $csp, $matches)) {
            // If script-src exists, update it if the hash is not present.
            $scriptSrc = $matches[1];

            if (!str_contains($scriptSrc, $hashString)) {
                $newScriptSrc = $scriptSrc . ' ' . $hashString;
                $csp = str_replace($matches[0], 'script-src ' . $newScriptSrc, $csp);
            }
        } else {
            // If no script-src directive exists, check for default-src to preserve its sources.
            if (preg_match('/default-src\s+([^;]+)/', $csp, $defaultMatches)) {
                $defaultSrc = $defaultMatches[1];
                $newScriptSrc = $defaultSrc . ' ' . $hashString;
                $csp .= "; script-src " . $newScriptSrc;
            } else {
                // Fallback case if neither script-src nor default-src are present.
                $csp .= "; script-src 'self' " . $hashString;
            }
        }
        $response->headers->set('Content-Security-Policy', $csp);

        $modifiedContent = str_replace('</head>', $scriptTag.'</head>', $responseContent);
        $response->setContent($modifiedContent);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -128],
        ];
    }
}
