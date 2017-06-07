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

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\SessionExpiredException;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Session\SessionRegistry;
use Symfony\Component\Security\Http\Session\SessionInformation;

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
    private $sessionRegistry;
    private $logger;

    public function __construct(TokenStorageInterface $tokenStorage, HttpUtils $httpUtils, $maxIdleTime, $targetUrl = null, SessionRegistry $sessionRegistry = null, LoggerInterface $logger = null)
    {
        $this->tokenStorage = $tokenStorage;
        $this->httpUtils = $httpUtils;
        $this->maxIdleTime = $maxIdleTime;
        $this->targetUrl = $targetUrl;
        $this->sessionRegistry = $sessionRegistry;
        $this->logger = $logger;
    }

    /**
     * Handles expired sessions.
     *
     * @param  GetResponseEvent        $event A GetResponseEvent instance
     * @throws SessionExpiredException If the session has expired
     */
    public function handle(GetResponseEvent $event)
    {
        $request = $event->getRequest();
        $session = $request->getSession();

        if (null === $session || null === $token = $this->tokenStorage->getToken()) {
            return;
        }

        $sessionInformation = null !== $this->sessionRegistry ? $this->sessionRegistry->getSessionInformation($session->getId()) : null;

        if (!$this->hasSessionExpired($session, $sessionInformation)) {
            return;
        }

        if (null !== $this->logger) {
            $this->logger->info(sprintf("Expired session detected for user named '%s'", $token->getUsername()));
        }

        $this->tokenStorage->setToken(null);
        $session->invalidate();

        if (null !== $this->sessionRegistry && null !== $sessionInformation) {
            $this->sessionRegistry->removeSessionInformation($sessionInformation->getSessionId());
        }

        if (null === $this->targetUrl) {
            throw new SessionExpiredException();
        }

        $response = $this->httpUtils->createRedirectResponse($request, $this->targetUrl);
        $event->setResponse($response);
    }

    /**
     * Checks if the given session has expired.
     *
     * @param  SessionInterface   $session
     * @param  SessionInformation $sessionInformation
     * @return bool
     */
    private function hasSessionExpired(SessionInterface $session, SessionInformation $sessionInformation = null)
    {
        if (time() - $session->getMetadataBag()->getLastUsed() > $this->maxIdleTime) {
            return true;
        }

        if (null !== $sessionInformation) {
            return $sessionInformation->isExpired();
        }

        return false;
    }
}
