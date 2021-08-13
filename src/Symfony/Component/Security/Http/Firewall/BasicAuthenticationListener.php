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
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface;

trigger_deprecation('symfony/security-http', '5.3', 'The "%s" class is deprecated, use the new authenticator system instead.', AnonymousAuthenticationListener::class);

/**
 * BasicAuthenticationListener implements Basic HTTP authentication.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 *
 * @deprecated since Symfony 5.3, use the new authenticator system instead
 */
class BasicAuthenticationListener extends AbstractListener
{
    private $tokenStorage;
    private $authenticationManager;
    private $providerKey;
    private $authenticationEntryPoint;
    private $logger;
    private $ignoreFailure;
    private $sessionStrategy;

    public function __construct(TokenStorageInterface $tokenStorage, AuthenticationManagerInterface $authenticationManager, string $providerKey, AuthenticationEntryPointInterface $authenticationEntryPoint, LoggerInterface $logger = null)
    {
        if (empty($providerKey)) {
            throw new \InvalidArgumentException('$providerKey must not be empty.');
        }

        $this->tokenStorage = $tokenStorage;
        $this->authenticationManager = $authenticationManager;
        $this->providerKey = $providerKey;
        $this->authenticationEntryPoint = $authenticationEntryPoint;
        $this->logger = $logger;
        $this->ignoreFailure = false;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(Request $request): ?bool
    {
        return null !== $request->headers->get('PHP_AUTH_USER');
    }

    /**
     * Handles basic authentication.
     */
    public function authenticate(RequestEvent $event)
    {
        $request = $event->getRequest();

        if (null === $username = $request->headers->get('PHP_AUTH_USER')) {
            return;
        }

        if (null !== $token = $this->tokenStorage->getToken()) {
            // @deprecated since Symfony 5.3, change to $token->getUserIdentifier() in 6.0
            if ($token instanceof UsernamePasswordToken && $token->isAuthenticated() && (method_exists($token, 'getUserIdentifier') ? $token->getUserIdentifier() : $token->getUsername()) === $username) {
                return;
            }
        }

        if (null !== $this->logger) {
            $this->logger->info('Basic authentication Authorization header found for user.', ['username' => $username]);
        }

        try {
            $token = $this->authenticationManager->authenticate(new UsernamePasswordToken($username, $request->headers->get('PHP_AUTH_PW'), $this->providerKey));

            $this->migrateSession($request, $token);

            $this->tokenStorage->setToken($token);
        } catch (AuthenticationException $e) {
            $token = $this->tokenStorage->getToken();
            if ($token instanceof UsernamePasswordToken && $this->providerKey === $token->getFirewallName()) {
                $this->tokenStorage->setToken(null);
            }

            if (null !== $this->logger) {
                $this->logger->info('Basic authentication failed for user.', ['username' => $username, 'exception' => $e]);
            }

            if ($this->ignoreFailure) {
                return;
            }

            $event->setResponse($this->authenticationEntryPoint->start($request, $e));
        }
    }

    /**
     * Call this method if your authentication token is stored to a session.
     *
     * @final
     */
    public function setSessionAuthenticationStrategy(SessionAuthenticationStrategyInterface $sessionStrategy)
    {
        $this->sessionStrategy = $sessionStrategy;
    }

    private function migrateSession(Request $request, TokenInterface $token)
    {
        if (!$this->sessionStrategy || !$request->hasSession() || !$request->hasPreviousSession()) {
            return;
        }

        $this->sessionStrategy->onAuthentication($request, $token);
    }
}
