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
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Exception\SessionExpiredException;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * SessionExpirationListener controls idle sessions
 *
 * @author Antonio J. García Lagar <aj@garcialagar.es>
 */
class SessionExpirationListener implements ListenerInterface
{
    private $tokenStorage;
    private $httpUtils;
    private $maxIdleTime;
    private $targetUrl;
    private $logger;

    public function __construct(TokenStorageInterface $tokenStorage, HttpUtils $httpUtils, $maxIdleTime, $targetUrl = null, LoggerInterface $logger = null)
    {
        $this->tokenStorage = $tokenStorage;
        $this->httpUtils = $httpUtils;
        $this->setMaxIdleTime($maxIdleTime);
        $this->targetUrl = $targetUrl;
        $this->logger = $logger;
    }

    /**
     * Handles expired sessions.
     *
     * @param  GetResponseEvent  $event A GetResponseEvent instance
     *
     * @throws SessionExpiredException If the session has expired
     */
    public function handle(GetResponseEvent $event)
    {
        $request = $event->getRequest();
        $session = $request->getSession();

        if (null === $session || null === $token = $this->tokenStorage->getToken()) {
            return;
        }

        if (!$this->hasSessionExpired($session)) {
            return;
        }

        if (null !== $this->logger) {
            $this->logger->info(sprintf("Expired session detected for user named '%s'", $token->getUsername()));
        }

        $this->tokenStorage->setToken(null);
        $session->invalidate();

        if (null === $this->targetUrl) {
            throw new SessionExpiredException();
        }

        $response = $this->httpUtils->createRedirectResponse($request, $this->targetUrl);
        $event->setResponse($response);
    }

    /**
     * @param int $maxIdleTime
     */
    private function setMaxIdleTime($maxIdleTime)
    {
        if ($maxIdleTime > ini_get('session.gc_maxlifetime') && null !== $this->logger) {
            $this->logger->warning("Max idle time should not be greater than 'session.gc_maxlifetime'");
        }
        $this->maxIdleTime = (int) $maxIdleTime;
    }

    /**
     * Checks if the given session has expired.
     *
     * @param SessionInterface $session
     * @return bool
     */
    private function hasSessionExpired(SessionInterface $session)
    {
        return time() - $session->getMetadataBag()->getLastUsed() >= $this->maxIdleTime;
    }
}
