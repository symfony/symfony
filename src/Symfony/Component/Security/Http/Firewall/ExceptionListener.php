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
use Symfony\Component\Security\Core\Exception\LazyResponseException;
use Symfony\Component\Security\Core\Exception\LogoutException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Symfony\Component\Security\Http\Authorization\NotFullFledgedHandlerInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\EntryPoint\Exception\NotAnEntryPointException;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
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

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private AuthenticationTrustResolverInterface $authenticationTrustResolver,
        private HttpUtils $httpUtils,
        private string $firewallName,
        private ?AuthenticationEntryPointInterface $authenticationEntryPoint = null,
        private ?string $errorPage = null,
        private ?AccessDeniedHandlerInterface $accessDeniedHandler = null,
        private ?LoggerInterface $logger = null,
        private bool $stateless = false,
        private ?NotFullFledgedHandlerInterface $notFullFledgedHandler = null,
    ) {
    }

    /**
     * Registers a onKernelException listener to take care of security exceptions.
     */
    public function register(EventDispatcherInterface $dispatcher): void
    {
        $dispatcher->addListener(KernelEvents::EXCEPTION, $this->onKernelException(...), 1);
    }

    /**
     * Unregisters the dispatcher.
     */
    public function unregister(EventDispatcherInterface $dispatcher): void
    {
        $dispatcher->removeListener(KernelEvents::EXCEPTION, $this->onKernelException(...));
    }

    /**
     * Handles security related exceptions.
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
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
                $this->handleLogoutException($event, $exception);

                return;
            }
        } while (null !== $exception = $exception->getPrevious());
    }

    private function handleAuthenticationException(ExceptionEvent $event, AuthenticationException $exception): void
    {
        $this->logger?->info('An AuthenticationException was thrown; redirecting to authentication entry point.', ['exception' => $exception]);

        try {
            $event->setResponse($this->startAuthentication($event->getRequest(), $exception));
            $event->allowCustomResponseCode();
        } catch (\Exception $e) {
            $event->setThrowable($e);
        }
    }

    private function handleAccessDeniedException(ExceptionEvent $event, AccessDeniedException $exception): void
    {
        $event->setThrowable(new AccessDeniedHttpException($exception->getMessage(), $exception));
        $token = $this->tokenStorage->getToken();

        if ($this->notFullFledgedHandler?->handle($event, $exception, $this->authenticationTrustResolver, $token, $this->logger, function ($request, $exception) {return $this->startAuthentication($request, $exception); })) {
            return;
        }

        $this->logger?->debug('Access denied, the user is neither anonymous, nor remember-me.', ['exception' => $exception]);

        try {
            if (null !== $this->accessDeniedHandler) {
                $response = $this->accessDeniedHandler->handle($event->getRequest(), $exception);

                if ($response instanceof Response) {
                    $event->setResponse($response);
                }
            } elseif (null !== $this->errorPage) {
                $subRequest = $this->httpUtils->createRequest($event->getRequest(), $this->errorPage);
                $subRequest->attributes->set(SecurityRequestAttributes::ACCESS_DENIED_ERROR, $exception);

                $event->setResponse($event->getKernel()->handle($subRequest, HttpKernelInterface::SUB_REQUEST, true));
                $event->allowCustomResponseCode();
            }
        } catch (\Exception $e) {
            $this->logger?->error('An exception was thrown when handling an AccessDeniedException.', ['exception' => $e]);

            $event->setThrowable(new \RuntimeException('Exception thrown when handling an exception.', 0, $e));
        }
    }

    private function handleLogoutException(ExceptionEvent $event, LogoutException $exception): void
    {
        $event->setThrowable(new AccessDeniedHttpException($exception->getMessage(), $exception));

        $this->logger?->info('A LogoutException was thrown; wrapping with AccessDeniedHttpException', ['exception' => $exception]);
    }

    private function startAuthentication(Request $request, AuthenticationException $authException): Response
    {
        if (null === $this->authenticationEntryPoint) {
            $this->throwUnauthorizedException($authException);
        }

        $this->logger?->debug('Calling Authentication entry point.', ['entry_point' => $this->authenticationEntryPoint]);

        if (!$this->stateless) {
            $this->setTargetPath($request);
        }

        if ($authException instanceof AccountStatusException) {
            // remove the security token to prevent infinite redirect loops
            $this->tokenStorage->setToken(null);

            $this->logger?->info('The security token was removed due to an AccountStatusException.', ['exception' => $authException]);
        }

        try {
            $response = $this->authenticationEntryPoint->start($request, $authException);
        } catch (NotAnEntryPointException) {
            $this->throwUnauthorizedException($authException);
        }

        return $response;
    }

    protected function setTargetPath(Request $request): void
    {
        // session isn't required when using HTTP basic authentication mechanism for example
        if ($request->hasSession() && $request->isMethodSafe() && !$request->isXmlHttpRequest()) {
            $this->saveTargetPath($request->getSession(), $this->firewallName, $request->getUri());
        }
    }

    private function throwUnauthorizedException(AuthenticationException $authException): never
    {
        $this->logger?->notice(\sprintf('No Authentication entry point configured, returning a %s HTTP response. Configure "entry_point" on the firewall "%s" if you want to modify the response.', Response::HTTP_UNAUTHORIZED, $this->firewallName));

        throw new HttpException(Response::HTTP_UNAUTHORIZED, $authException->getMessage(), $authException, [], $authException->getCode());
    }
}
