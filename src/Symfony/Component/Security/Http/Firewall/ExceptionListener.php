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
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\InsufficientAuthenticationException;
use Symfony\Component\Security\Core\Exception\LazyResponseException;
use Symfony\Component\Security\Core\Exception\LogoutException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * ExceptionListener catches authentication exception and converts them to
 * Response instances.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
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
        $dispatcher->addListener(KernelEvents::EXCEPTION, [$this, 'onKernelException'], 1);
    }

    /**
     * Unregisters the dispatcher.
     */
    public function unregister(EventDispatcherInterface $dispatcher)
    {
        $dispatcher->removeListener(KernelEvents::EXCEPTION, [$this, 'onKernelException']);
    }

    /**
     * Handles security related exceptions.
     */
    public function onKernelException(ExceptionEvent $event)
    {
        $exception = $event->getException();
        do {
            if ($exception instanceof AuthenticationException) {
                $this->handleAuthenticationException($event, $exception);

                return;
            }

            if ($exception instanceof AccessDeniedException) {
                $this->handleAccessDeniedException($event, $exception);

                return;
            }

            if ($exception instanceof LazyResponseException) {
                $event->setResponse($exception->getResponse());

                return;
            }

            if ($exception instanceof LogoutException) {
                $this->handleLogoutException($exception);

                return;
            }
        } while (null !== $exception = $exception->getPrevious());
    }

    private function handleAuthenticationException(ExceptionEvent $event, AuthenticationException $exception): void
    {
        if (null !== $this->logger) {
            $this->logger->info('An AuthenticationException was thrown; redirecting to authentication entry point.', ['exception' => $exception]);
        }

        try {
            $event->setResponse($this->startAuthentication($event->getRequest(), $exception));
            $event->allowCustomResponseCode();
        } catch (\Exception $e) {
            $event->setException($e);
        }
    }

    private function handleAccessDeniedException(ExceptionEvent $event, AccessDeniedException $exception)
    {
        $event->setException(new AccessDeniedHttpException($exception->getMessage(), $exception));

        $token = $this->tokenStorage->getToken();
        if (!$this->authenticationTrustResolver->isFullFledged($token)) {
            if (null !== $this->logger) {
                $this->logger->debug('Access denied, the user is not fully authenticated; redirecting to authentication entry point.', ['exception' => $exception]);
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
            $this->logger->debug('Access denied, the user is neither anonymous, nor remember-me.', ['exception' => $exception]);
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
                $this->logger->error('An exception was thrown when handling an AccessDeniedException.', ['exception' => $e]);
            }

            $event->setException(new \RuntimeException('Exception thrown when handling an exception.', 0, $e));
        }
    }

    private function handleLogoutException(LogoutException $exception): void
    {
        if (null !== $this->logger) {
            $this->logger->info('A LogoutException was thrown.', ['exception' => $exception]);
        }
    }

    private function startAuthentication(Request $request, AuthenticationException $authException): Response
    {
        if (null === $this->authenticationEntryPoint) {
            throw new HttpException(Response::HTTP_UNAUTHORIZED, $authException->getMessage(), $authException, [], $authException->getCode());
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
                $this->logger->info('The security token was removed due to an AccountStatusException.', ['exception' => $authException]);
            }
        }

        $response = $this->authenticationEntryPoint->start($request, $authException);

        if (!$response instanceof Response) {
            $given = \is_object($response) ? \get_class($response) : \gettype($response);

            throw new \LogicException(sprintf('The %s::start() method must return a Response object (%s returned)', \get_class($this->authenticationEntryPoint), $given));
        }

        return $response;
    }

    protected function setTargetPath(Request $request)
    {
        // session isn't required when using HTTP basic authentication mechanism for example
        if ($request->hasSession() && $request->isMethodSafe() && !$request->isXmlHttpRequest()) {
            $this->saveTargetPath($request->getSession(), $this->providerKey, $request->getUri());
        }
    }
}
