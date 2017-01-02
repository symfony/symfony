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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\PropertyAccess\Exception\AccessException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

/**
 * UsernamePasswordJsonAuthenticationListener is a stateless implementation of
 * an authentication via a JSON document composed of a username and a password.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class UsernamePasswordJsonAuthenticationListener implements ListenerInterface
{
    private $tokenStorage;
    private $authenticationManager;
    private $providerKey;
    private $successHandler;
    private $failureHandler;
    private $options;
    private $logger;
    private $eventDispatcher;
    private $propertyAccessor;

    public function __construct(TokenStorageInterface $tokenStorage, AuthenticationManagerInterface $authenticationManager, $providerKey, AuthenticationSuccessHandlerInterface $successHandler, AuthenticationFailureHandlerInterface $failureHandler, array $options = array(), LoggerInterface $logger = null, EventDispatcherInterface $eventDispatcher = null, PropertyAccessorInterface $propertyAccessor = null)
    {
        $this->tokenStorage = $tokenStorage;
        $this->authenticationManager = $authenticationManager;
        $this->providerKey = $providerKey;
        $this->successHandler = $successHandler;
        $this->failureHandler = $failureHandler;
        $this->logger = $logger;
        $this->eventDispatcher = $eventDispatcher;
        $this->options = array_merge(array('username_path' => 'username', 'password_path' => 'password'), $options);
        $this->propertyAccessor = $propertyAccessor ?: PropertyAccess::createPropertyAccessor();
    }

    /**
     * {@inheritdoc}
     */
    public function handle(GetResponseEvent $event)
    {
        $request = $event->getRequest();
        $data = json_decode($request->getContent());

        if (!$data instanceof \stdClass) {
            throw new BadCredentialsException('Invalid JSON.');
        }

        try {
            $username = $this->propertyAccessor->getValue($data, $this->options['username_path']);
        } catch (AccessException $e) {
            throw new BadCredentialsException(sprintf('The key "%s" must be provided.', $this->options['username_path']));
        }

        try {
            $password = $this->propertyAccessor->getValue($data, $this->options['password_path']);
        } catch (AccessException $e) {
            throw new BadCredentialsException(sprintf('The key "%s" must be provided.', $this->options['password_path']));
        }

        if (!is_string($username)) {
            throw new BadCredentialsException(sprintf('The key "%s" must be a string.', $this->options['username_path']));
        }

        if (strlen($username) > Security::MAX_USERNAME_LENGTH) {
            throw new BadCredentialsException('Invalid username.');
        }

        if (!is_string($password)) {
            throw new BadCredentialsException(sprintf('The key "%s" must be a string.', $this->options['password_path']));
        }

        try {
            $token = new UsernamePasswordToken($username, $password, $this->providerKey);

            $authenticatedToken = $this->authenticationManager->authenticate($token);
            $response = $this->onSuccess($request, $authenticatedToken);
        } catch (AuthenticationException $e) {
            $response = $this->onFailure($request, $e);
        }

        $event->setResponse($response);
    }

    private function onSuccess(Request $request, TokenInterface $token)
    {
        if (null !== $this->logger) {
            $this->logger->info('User has been authenticated successfully.', array('username' => $token->getUsername()));
        }

        $this->tokenStorage->setToken($token);

        if (null !== $this->eventDispatcher) {
            $loginEvent = new InteractiveLoginEvent($request, $token);
            $this->eventDispatcher->dispatch(SecurityEvents::INTERACTIVE_LOGIN, $loginEvent);
        }

        $response = $this->successHandler->onAuthenticationSuccess($request, $token);

        if (!$response instanceof Response) {
            throw new \RuntimeException('Authentication Success Handler did not return a Response.');
        }

        return $response;
    }

    private function onFailure(Request $request, AuthenticationException $failed)
    {
        if (null !== $this->logger) {
            $this->logger->info('Authentication request failed.', array('exception' => $failed));
        }

        $token = $this->tokenStorage->getToken();
        if ($token instanceof UsernamePasswordToken && $this->providerKey === $token->getProviderKey()) {
            $this->tokenStorage->setToken(null);
        }

        $response = $this->failureHandler->onAuthenticationFailure($request, $failed);

        if (!$response instanceof Response) {
            throw new \RuntimeException('Authentication Failure Handler did not return a Response.');
        }

        return $response;
    }
}
