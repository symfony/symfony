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
    private $authenticationEntryPoint;
    private $logger;

    public function __construct(AccessMapInterface $map, AuthenticationEntryPointInterface $authenticationEntryPoint, LoggerInterface $logger = null)
    {
        $this->map = $map;
        $this->authenticationEntryPoint = $authenticationEntryPoint;
        $this->logger = $logger;
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
                } elseif (false !== strpos($request->headers->get('Forwarded'), 'proto=https')) {
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

        $response = $this->authenticationEntryPoint->start($request);

        $event->setResponse($response);
    }
}
