<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Http\Firewall;

use Psr\Log\LoggerInterface;
use Symphony\Component\EventDispatcher\EventDispatcherInterface;
use Symphony\Component\HttpFoundation\JsonResponse;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\HttpKernel\Event\GetResponseEvent;
use Symphony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symphony\Component\PropertyAccess\Exception\AccessException;
use Symphony\Component\PropertyAccess\PropertyAccess;
use Symphony\Component\PropertyAccess\PropertyAccessorInterface;
use Symphony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symphony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symphony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symphony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symphony\Component\Security\Core\Exception\AuthenticationException;
use Symphony\Component\Security\Core\Exception\BadCredentialsException;
use Symphony\Component\Security\Core\Security;
use Symphony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symphony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symphony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symphony\Component\Security\Http\HttpUtils;
use Symphony\Component\Security\Http\SecurityEvents;

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
    private $httpUtils;
    private $providerKey;
    private $successHandler;
    private $failureHandler;
    private $options;
    private $logger;
    private $eventDispatcher;
    private $propertyAccessor;

    public function __construct(TokenStorageInterface $tokenStorage, AuthenticationManagerInterface $authenticationManager, HttpUtils $httpUtils, string $providerKey, AuthenticationSuccessHandlerInterface $successHandler = null, AuthenticationFailureHandlerInterface $failureHandler = null, array $options = array(), LoggerInterface $logger = null, EventDispatcherInterface $eventDispatcher = null, PropertyAccessorInterface $propertyAccessor = null)
    {
        $this->tokenStorage = $tokenStorage;
        $this->authenticationManager = $authenticationManager;
        $this->httpUtils = $httpUtils;
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
        if (false === strpos($request->getRequestFormat(), 'json')
            && false === strpos($request->getContentType(), 'json')
        ) {
            return;
        }

        if (isset($this->options['check_path']) && !$this->httpUtils->checkRequestPath($request, $this->options['check_path'])) {
            return;
        }

        $data = json_decode($request->getContent());

        try {
            if (!$data instanceof \stdClass) {
                throw new BadRequestHttpException('Invalid JSON.');
            }

            try {
                $username = $this->propertyAccessor->getValue($data, $this->options['username_path']);
            } catch (AccessException $e) {
                throw new BadRequestHttpException(sprintf('The key "%s" must be provided.', $this->options['username_path']), $e);
            }

            try {
                $password = $this->propertyAccessor->getValue($data, $this->options['password_path']);
            } catch (AccessException $e) {
                throw new BadRequestHttpException(sprintf('The key "%s" must be provided.', $this->options['password_path']), $e);
            }

            if (!is_string($username)) {
                throw new BadRequestHttpException(sprintf('The key "%s" must be a string.', $this->options['username_path']));
            }

            if (strlen($username) > Security::MAX_USERNAME_LENGTH) {
                throw new BadCredentialsException('Invalid username.');
            }

            if (!is_string($password)) {
                throw new BadRequestHttpException(sprintf('The key "%s" must be a string.', $this->options['password_path']));
            }

            $token = new UsernamePasswordToken($username, $password, $this->providerKey);

            $authenticatedToken = $this->authenticationManager->authenticate($token);
            $response = $this->onSuccess($request, $authenticatedToken);
        } catch (AuthenticationException $e) {
            $response = $this->onFailure($request, $e);
        } catch (BadRequestHttpException $e) {
            $request->setRequestFormat('json');

            throw $e;
        }

        if (null === $response) {
            return;
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

        if (!$this->successHandler) {
            return; // let the original request succeeds
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

        if (!$this->failureHandler) {
            return new JsonResponse(array('error' => $failed->getMessageKey()), 401);
        }

        $response = $this->failureHandler->onAuthenticationFailure($request, $failed);

        if (!$response instanceof Response) {
            throw new \RuntimeException('Authentication Failure Handler did not return a Response.');
        }

        return $response;
    }
}
