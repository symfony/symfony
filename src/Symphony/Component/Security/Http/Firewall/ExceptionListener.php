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

use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Symphony\Component\Security\Core\Security;
use Symphony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symphony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symphony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symphony\Component\Security\Core\Exception\AccountStatusException;
use Symphony\Component\Security\Core\Exception\AuthenticationException;
use Symphony\Component\Security\Core\Exception\AccessDeniedException;
use Symphony\Component\Security\Core\Exception\InsufficientAuthenticationException;
use Symphony\Component\Security\Core\Exception\LogoutException;
use Symphony\Component\Security\Http\Util\TargetPathTrait;
use Symphony\Component\Security\Http\HttpUtils;
use Symphony\Component\HttpFoundation\Request;
use Psr\Log\LoggerInterface;
use Symphony\Component\HttpKernel\HttpKernelInterface;
use Symphony\Component\HttpKernel\KernelEvents;
use Symphony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symphony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symphony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * ExceptionListener catches authentication exception and converts them to
 * Response instances.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class ExceptionListener
{
    use TargetPathTrait;

    private $tokenStorage;
    private $providerKey;
    private $accessDeniedHandler;
    private $authenticationEntryPoint;
    private $authenticationTrustResolver;
    private $errorPage;
    private $logger;
    private $httpUtils;
    private $stateless;

    public function __construct(TokenStorageInterface $tokenStorage, AuthenticationTrustResolverInterface $trustResolver, HttpUtils $httpUtils, string $providerKey, AuthenticationEntryPointInterface $authenticationEntryPoint = null, string $errorPage = null, AccessDeniedHandlerInterface $accessDeniedHandler = null, LoggerInterface $logger = null, bool $stateless = false)
    {
        $this->tokenStorage = $tokenStorage;
        $this->accessDeniedHandler = $accessDeniedHandler;
        $this->httpUtils = $httpUtils;
        $this->providerKey = $providerKey;
        $this->authenticationEntryPoint = $authenticationEntryPoint;
        $this->authenticationTrustResolver = $trustResolver;
        $this->errorPage = $errorPage;
        $this->logger = $logger;
        $this->stateless = $stateless;
    }

    /**
     * Registers a onKernelException listener to take care of security exceptions.
     */
    public function register(EventDispatcherInterface $dispatcher)
    {
        $dispatcher->addListener(KernelEvents::EXCEPTION, array($this, 'onKernelException'), 1);
    }

    /**
     * Unregisters the dispatcher.
     */
    public function unregister(EventDispatcherInterface $dispatcher)
    {
        $dispatcher->removeListener(KernelEvents::EXCEPTION, array($this, 'onKernelException'));
    }

    /**
     * Handles security related exceptions.
     */
    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();
        do {
            if ($exception instanceof AuthenticationException) {
                return $this->handleAuthenticationException($event, $exception);
            } elseif ($exception instanceof AccessDeniedException) {
                return $this->handleAccessDeniedException($event, $exception);
            } elseif ($exception instanceof LogoutException) {
                return $this->handleLogoutException($exception);
            }
        } while (null !== $exception = $exception->getPrevious());
    }

    private function handleAuthenticationException(GetResponseForExceptionEvent $event, AuthenticationException $exception): void
    {
        if (null !== $this->logger) {
            $this->logger->info('An AuthenticationException was thrown; redirecting to authentication entry point.', array('exception' => $exception));
        }

        try {
            $event->setResponse($this->startAuthentication($event->getRequest(), $exception));
            $event->allowCustomResponseCode();
        } catch (\Exception $e) {
            $event->setException($e);
        }
    }

    private function handleAccessDeniedException(GetResponseForExceptionEvent $event, AccessDeniedException $exception)
    {
        $event->setException(new AccessDeniedHttpException($exception->getMessage(), $exception));

        $token = $this->tokenStorage->getToken();
        if (!$this->authenticationTrustResolver->isFullFledged($token)) {
            if (null !== $this->logger) {
                $this->logger->debug('Access denied, the user is not fully authenticated; redirecting to authentication entry point.', array('exception' => $exception));
            }

            try {
                $insufficientAuthenticationException = new InsufficientAuthenticationException('Full authentication is required to access this resource.', 0, $exception);
                $insufficientAuthenticationException->setToken($token);

                $event->setResponse($this->startAuthentication($event->getRequest(), $insufficientAuthenticationException));
            } catch (\Exception $e) {
                $event->setException($e);
            }

            return;
        }

        if (null !== $this->logger) {
            $this->logger->debug('Access denied, the user is neither anonymous, nor remember-me.', array('exception' => $exception));
        }

        try {
            if (null !== $this->accessDeniedHandler) {
                $response = $this->accessDeniedHandler->handle($event->getRequest(), $exception);

                if ($response instanceof Response) {
                    $event->setResponse($response);
                }
            } elseif (null !== $this->errorPage) {
                $subRequest = $this->httpUtils->createRequest($event->getRequest(), $this->errorPage);
                $subRequest->attributes->set(Security::ACCESS_DENIED_ERROR, $exception);

                $event->setResponse($event->getKernel()->handle($subRequest, HttpKernelInterface::SUB_REQUEST, true));
                $event->allowCustomResponseCode();
            }
        } catch (\Exception $e) {
            if (null !== $this->logger) {
                $this->logger->error('An exception was thrown when handling an AccessDeniedException.', array('exception' => $e));
            }

            $event->setException(new \RuntimeException('Exception thrown when handling an exception.', 0, $e));
        }
    }

    private function handleLogoutException(LogoutException $exception): void
    {
        if (null !== $this->logger) {
            $this->logger->info('A LogoutException was thrown.', array('exception' => $exception));
        }
    }

    private function startAuthentication(Request $request, AuthenticationException $authException): Response
    {
        if (null === $this->authenticationEntryPoint) {
            throw $authException;
        }

        if (null !== $this->logger) {
            $this->logger->debug('Calling Authentication entry point.');
        }

        if (!$this->stateless) {
            $this->setTargetPath($request);
        }

        if ($authException instanceof AccountStatusException) {
            // remove the security token to prevent infinite redirect loops
            $this->tokenStorage->setToken(null);

            if (null !== $this->logger) {
                $this->logger->info('The security token was removed due to an AccountStatusException.', array('exception' => $authException));
            }
        }

        $response = $this->authenticationEntryPoint->start($request, $authException);

        if (!$response instanceof Response) {
            $given = is_object($response) ? get_class($response) : gettype($response);

            throw new \LogicException(sprintf('The %s::start() method must return a Response object (%s returned)', get_class($this->authenticationEntryPoint), $given));
        }

        return $response;
    }

    protected function setTargetPath(Request $request)
    {
        // session isn't required when using HTTP basic authentication mechanism for example
        if ($request->hasSession() && $request->isMethodSafe(false) && !$request->isXmlHttpRequest()) {
            $this->saveTargetPath($request->getSession(), $this->providerKey, $request->getUri());
        }
    }
}
