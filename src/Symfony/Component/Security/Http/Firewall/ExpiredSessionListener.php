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

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Security\Http\Logout\LogoutHandlerInterface;
use Symfony\Component\Security\Http\Logout\LogoutSuccessHandlerInterface;
use Symfony\Component\Security\Http\Session\SessionRegistry;
use Symfony\Component\Security\Http\HttpUtils;

/**
 * @author Stefan Paschke <stefan.paschke@gmail.com>
 * @author Antonio J. García Lagar <aj@garcialagar.es>
 */
class ExpiredSessionListener implements ListenerInterface
{
    private $securityContext;
    private $httpUtils;
    private $sessionRegistry;
    private $targetUrl;
    private $successHandler;
    private $logger;
    private $handlers;

    public function __construct(SecurityContextInterface $securityContext, HttpUtils $httpUtils, SessionRegistry $sessionRegistry, $targetUrl = '/', LogoutSuccessHandlerInterface $successHandler = null, LoggerInterface $logger = null)
    {
        $this->securityContext = $securityContext;
        $this->httpUtils = $httpUtils;
        $this->sessionRegistry = $sessionRegistry;
        $this->targetUrl = $targetUrl;
        $this->successHandler = $successHandler;
        $this->logger = $logger;
        $this->handlers = array();
    }

    /**
     * Adds a logout handler
     *
     * @param LogoutHandlerInterface $handler
     */
    public function addHandler(LogoutHandlerInterface $handler)
    {
        $this->handlers[] = $handler;
    }

    /**
     * Handles the number of simultaneous sessions for a single user.
     *
     * @param GetResponseEvent $event A GetResponseEvent instance
     * @throws \RuntimeException if the successHandler exists and do not return a response
     */
    public function handle(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        $session = $request->getSession();

        if (null === $session || null === $token = $this->securityContext->getToken()) {
            return;
        }

        if ($sessionInformation = $this->sessionRegistry->getSessionInformation($session->getId())) {
            if ($sessionInformation->isExpired()) {

                if (null !== $this->logger) {
                    $this->logger->info(sprintf("Logging out expired session for username '%s'", $token->getUsername()));
                }

                if (null !== $this->successHandler) {
                    $response = $this->successHandler->onLogoutSuccess($request);
                    if (!$response instanceof Response) {
                        throw new \RuntimeException('Logout Success Handler did not return a Response.');
                    }
                } else {
                    $response = $this->httpUtils->createRedirectResponse($request, $this->targetUrl);
                }

                foreach ($this->handlers as $handler) {
                    $handler->logout($request, $response, $token);
                }

                $this->securityContext->setToken(null);

                $event->setResponse($response);
            } else {
                $this->sessionRegistry->refreshLastRequest($session->getId());
            }
        } else {
            // sessionInformation was lost, try to recover by recreating it
            $this->sessionRegistry->registerNewSession($session->getId(), $token->getUsername());
        }
    }
}
