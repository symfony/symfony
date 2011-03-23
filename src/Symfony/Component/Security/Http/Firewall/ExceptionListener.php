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
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Exception\InsufficientAuthenticationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Events;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * ExceptionListener catches authentication exception and converts them to
 * Response instances.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ExceptionListener
{
    private $context;
    private $authenticationEntryPoint;
    private $authenticationTrustResolver;
    private $logger;

    public function __construct(SecurityContextInterface $context, AuthenticationTrustResolverInterface $trustResolver, AuthenticationEntryPointInterface $authenticationEntryPoint = null, LoggerInterface $logger = null)
    {
        $this->context = $context;
        $this->authenticationEntryPoint = $authenticationEntryPoint;
        $this->authenticationTrustResolver = $trustResolver;
        $this->logger = $logger;
    }

    /**
     * Registers a onCoreException listener to take care of security exceptions.
     *
     * @param EventDispatcherInterface $dispatcher An EventDispatcherInterface instance
     */
    public function register(EventDispatcherInterface $dispatcher)
    {
        $dispatcher->addListener(Events::onCoreException, $this);
    }

    /**
     * Handles security related exceptions.
     *
     * @param GetResponseForExceptionEvent $event An GetResponseForExceptionEvent instance
     */
    public function onCoreException(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();
        $request = $event->getRequest();

        if ($exception instanceof AuthenticationException) {
            if (null !== $this->logger) {
                $this->logger->info(sprintf('Authentication exception occurred; redirecting to authentication entry point (%s)', $exception->getMessage()));
            }

            try {
                $event->setResponse($this->startAuthentication($request, $exception));
            } catch (\Exception $e) {
                $event->setException($e);
            }
        } elseif ($exception instanceof AccessDeniedException) {
            $token = $this->context->getToken();
            if (!$this->authenticationTrustResolver->isFullFledged($token)) {
                if (null !== $this->logger) {
                    $this->logger->info('Access denied (user is not fully authenticated); redirecting to authentication entry point');
                }

                try {
                    $event->setResponse($this->startAuthentication($request, new InsufficientAuthenticationException('Full authentication is required to access this resource.', $token, 0, $exception)));
                } catch (\Exception $e) {
                    $event->setException($e);
                }
            } else {
                if (null !== $this->logger) {
                    $this->logger->info('Access is denied (and user is neither anonymous, nor remember-me)');
                }

                $event->setException(new AccessDeniedHttpException('Forbidden', null, 0, $e));
            }
        }
    }

    private function startAuthentication(Request $request, AuthenticationException $authException)
    {
        $this->context->setToken(null);

        if (null === $this->authenticationEntryPoint) {
            throw $authException;
        }

        if (null !== $this->logger) {
            $this->logger->debug('Calling Authentication entry point');
        }

        // session isn't required when using http basic authentification mechanism for example
        if ($request->hasSession()) {
            $request->getSession()->set('_security.target_path', $request->getUri());
        }

        return $this->authenticationEntryPoint->start($request, $authException);
    }
}
