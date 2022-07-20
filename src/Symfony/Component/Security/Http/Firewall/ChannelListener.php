<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Firewall;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Http\AccessMapInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * ChannelListener switches the HTTP protocol based on the access control
 * configuration.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class ChannelListener extends AbstractListener
{
    private $map;
    private $authenticationEntryPoint = null;
    private $logger;
    private $httpPort;
    private $httpsPort;

    public function __construct(AccessMapInterface $map, /* LoggerInterface */ $logger = null, /* int */ $httpPort = 80, /* int */ $httpsPort = 443)
    {
        if ($logger instanceof AuthenticationEntryPointInterface) {
            trigger_deprecation('symfony/security-http', '5.4', 'The "$authenticationEntryPoint" argument of "%s()" is deprecated.', __METHOD__);

            $this->authenticationEntryPoint = $logger;
            $nrOfArgs = \func_num_args();
            $logger = $nrOfArgs > 2 ? func_get_arg(2) : null;
            $httpPort = $nrOfArgs > 3 ? func_get_arg(3) : 80;
            $httpsPort = $nrOfArgs > 4 ? func_get_arg(4) : 443;
        }

        if (null !== $logger && !$logger instanceof LoggerInterface) {
            throw new \TypeError(sprintf('Argument "$logger" of "%s()" must be instance of "%s", "%s" given.', __METHOD__, LoggerInterface::class, get_debug_type($logger)));
        }

        $this->map = $map;
        $this->logger = $logger;
        $this->httpPort = $httpPort;
        $this->httpsPort = $httpsPort;
    }

    /**
     * Handles channel management.
     */
    public function supports(Request $request): ?bool
    {
        [, $channel] = $this->map->getPatterns($request);

        if ('https' === $channel && !$request->isSecure()) {
            if (null !== $this->logger) {
                if ('https' === $request->headers->get('X-Forwarded-Proto')) {
                    $this->logger->info('Redirecting to HTTPS. ("X-Forwarded-Proto" header is set to "https" - did you set "trusted_proxies" correctly?)');
                } elseif (str_contains($request->headers->get('Forwarded', ''), 'proto=https')) {
                    $this->logger->info('Redirecting to HTTPS. ("Forwarded" header is set to "proto=https" - did you set "trusted_proxies" correctly?)');
                } else {
                    $this->logger->info('Redirecting to HTTPS.');
                }
            }

            return true;
        }

        if ('http' === $channel && $request->isSecure()) {
            if (null !== $this->logger) {
                $this->logger->info('Redirecting to HTTP.');
            }

            return true;
        }

        return false;
    }

    public function authenticate(RequestEvent $event)
    {
        $request = $event->getRequest();

        $event->setResponse($this->createRedirectResponse($request));
    }

    private function createRedirectResponse(Request $request): RedirectResponse
    {
        if (null !== $this->authenticationEntryPoint) {
            return $this->authenticationEntryPoint->start($request);
        }

        $scheme = $request->isSecure() ? 'http' : 'https';
        if ('http' === $scheme && 80 != $this->httpPort) {
            $port = ':'.$this->httpPort;
        } elseif ('https' === $scheme && 443 != $this->httpsPort) {
            $port = ':'.$this->httpsPort;
        } else {
            $port = '';
        }

        $qs = $request->getQueryString();
        if (null !== $qs) {
            $qs = '?'.$qs;
        }

        $url = $scheme.'://'.$request->getHost().$port.$request->getBaseUrl().$request->getPathInfo().$qs;

        return new RedirectResponse($url, 301);
    }
}
