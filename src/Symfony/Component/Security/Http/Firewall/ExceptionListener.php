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
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\InsufficientAuthenticationException;
use Symfony\Component\Security\Core\Exception\LazyResponseException;
use Symfony\Component\Security\Core\Exception\LogoutException;
use Symfony\Component\Security\Core\Exception\ReAuthenticationRequiredException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\EntryPoint\Exception\NotAnEntryPointException;
use Symfony\Component\Security\Http\EntryPoint\FallbackAuthenticationEntryPointInterface;
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
        private ?AuthenticationEntryPointInterface $reAuthenticationEntryPoint = null,
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
        if (!$this->authenticationTrustResolver->isFullFledged($token)) {
            $this->logger?->debug('Access denied, the user is not fully authenticated; redirecting to authentication entry point.', ['exception' => $exception]);

            try {
                $insufficientAuthenticationException = new InsufficientAuthenticationException('Full authentication is required to access this resource.', 0, $exception);
                if (null !== $token) {
                    $insufficientAuthenticationException->setToken($token);
                }

                $event->setResponse($this->startAuthentication($event->getRequest(), $insufficientAuthenticationException));
            } catch (\Exception $e) {
                $event->setThrowable($e);
            }

            return;
        }

        // Matching the whole attribute list rather than searching it is deliberate: an
        // access_control rule is decided on all of its roles at once, so a denial of
        // [ROLE_ADMIN, IS_AUTHENTICATED_RECENTLY] does not say which one failed, and
        // re-authenticating would not help a user who simply lacks the role.
        if (null !== $this->reAuthenticationEntryPoint
            && [AuthenticatedVoter::IS_AUTHENTICATED_RECENTLY] === $exception->getAttributes()
        ) {
            $this->logger?->debug('The authentication is not recent enough, starting re-authentication.', ['entry_point' => $this->reAuthenticationEntryPoint]);

            if (!$this->stateless && !$this->reAuthenticationEntryPoint instanceof FallbackAuthenticationEntryPointInterface) {
                $this->setTargetPath($event->getRequest());
            }

            try {
                $event->setResponse($this->reAuthenticationEntryPoint->start($event->getRequest(), new ReAuthenticationRequiredException('Re-authentication is required to access this resource.', 0, $exception)));

                return;
            } catch (NotAnEntryPointException) {
                // an entry point that cannot start re-authentication leaves the denial
                // standing: 403 describes a stale authentication better than the 401
                // an unusable entry point would otherwise produce
                $this->logger?->debug('The re-authentication entry point declined to start, falling back to access denied.');
            }
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

        // an entry point that only stands in has no authentication to start, so there is
        // nothing for the user to come back to and no reason to start a session for it
        if (!$this->stateless && !$this->authenticationEntryPoint instanceof FallbackAuthenticationEntryPointInterface) {
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
