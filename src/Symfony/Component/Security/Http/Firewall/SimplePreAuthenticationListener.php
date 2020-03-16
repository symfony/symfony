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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\LegacyEventDispatcherProxy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authentication\SimplePreAuthenticatorInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface;

@trigger_error(sprintf('The "%s" class is deprecated since Symfony 4.2, use Guard instead.', SimplePreAuthenticationListener::class), E_USER_DEPRECATED);

/**
 * SimplePreAuthenticationListener implements simple proxying to an authenticator.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 *
 * @deprecated since Symfony 4.2, use Guard instead.
 */
class SimplePreAuthenticationListener extends AbstractListener implements ListenerInterface
{
    use LegacyListenerTrait;

    private $tokenStorage;
    private $authenticationManager;
    private $providerKey;
    private $simpleAuthenticator;
    private $logger;
    private $dispatcher;
    private $sessionStrategy;
    private $trustResolver;

    public function __construct(TokenStorageInterface $tokenStorage, AuthenticationManagerInterface $authenticationManager, string $providerKey, SimplePreAuthenticatorInterface $simpleAuthenticator, LoggerInterface $logger = null, EventDispatcherInterface $dispatcher = null, AuthenticationTrustResolverInterface $trustResolver = null)
    {
        if (empty($providerKey)) {
            throw new \InvalidArgumentException('$providerKey must not be empty.');
        }

        $this->tokenStorage = $tokenStorage;
        $this->authenticationManager = $authenticationManager;
        $this->providerKey = $providerKey;
        $this->simpleAuthenticator = $simpleAuthenticator;
        $this->logger = $logger;

        if (null !== $dispatcher && class_exists(LegacyEventDispatcherProxy::class)) {
            $this->dispatcher = LegacyEventDispatcherProxy::decorate($dispatcher);
        } else {
            $this->dispatcher = $dispatcher;
        }

        $this->trustResolver = $trustResolver ?: new AuthenticationTrustResolver(AnonymousToken::class, RememberMeToken::class);
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

    public function supports(Request $request): ?bool
    {
        if ((null !== $token = $this->tokenStorage->getToken()) && !$this->trustResolver->isAnonymous($token)) {
            return false;
        }

        $token = $this->simpleAuthenticator->createToken($request, $this->providerKey);

        // allow null to be returned to skip authentication
        if (null === $token) {
            return false;
        }

        $request->attributes->set('_simple_pre_authenticator_token', $token);

        return true;
    }

    /**
     * Handles basic authentication.
     */
    public function authenticate(RequestEvent $event)
    {
        $request = $event->getRequest();

        if (null !== $this->logger) {
            $this->logger->info('Attempting SimplePreAuthentication.', ['key' => $this->providerKey, 'authenticator' => \get_class($this->simpleAuthenticator)]);
        }

        if ((null !== $token = $this->tokenStorage->getToken()) && !$this->trustResolver->isAnonymous($token)) {
            $request->attributes->remove('_simple_pre_authenticator_token');

            return;
        }

        try {
            $token = $request->attributes->get('_simple_pre_authenticator_token');
            $request->attributes->remove('_simple_pre_authenticator_token');

            $token = $this->authenticationManager->authenticate($token);

            $this->migrateSession($request, $token);

            $this->tokenStorage->setToken($token);

            if (null !== $this->dispatcher) {
                $loginEvent = new InteractiveLoginEvent($request, $token);
                $this->dispatcher->dispatch($loginEvent, SecurityEvents::INTERACTIVE_LOGIN);
            }
        } catch (AuthenticationException $e) {
            $this->tokenStorage->setToken(null);

            if (null !== $this->logger) {
                $this->logger->info('SimplePreAuthentication request failed.', ['exception' => $e, 'authenticator' => \get_class($this->simpleAuthenticator)]);
            }

            if ($this->simpleAuthenticator instanceof AuthenticationFailureHandlerInterface) {
                $response = $this->simpleAuthenticator->onAuthenticationFailure($request, $e);
                if ($response instanceof Response) {
                    $event->setResponse($response);
                } elseif (null !== $response) {
                    throw new \UnexpectedValueException(sprintf('The "%s::onAuthenticationFailure()" method must return null or a Response object.', \get_class($this->simpleAuthenticator)));
                }
            }

            return;
        }

        if ($this->simpleAuthenticator instanceof AuthenticationSuccessHandlerInterface) {
            $response = $this->simpleAuthenticator->onAuthenticationSuccess($request, $token);
            if ($response instanceof Response) {
                $event->setResponse($response);
            } elseif (null !== $response) {
                throw new \UnexpectedValueException(sprintf('The "%s::onAuthenticationSuccess()" method must return null or a Response object.', \get_class($this->simpleAuthenticator)));
            }
        }
    }

    private function migrateSession(Request $request, TokenInterface $token)
    {
        if (!$this->sessionStrategy || !$request->hasSession() || !$request->hasPreviousSession()) {
            return;
        }

        $this->sessionStrategy->onAuthentication($request, $token);
    }
}
