<?php

namespace Symfony\Component\HttpKernel\Security\Firewall;

use Symfony\Component\Security\SecurityContext;
use Symfony\Component\Security\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Authentication\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Security\Exception\AuthenticationException;
use Symfony\Component\Security\Exception\AccessDeniedException;
use Symfony\Component\Security\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Exception\InsufficientAuthenticationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * ExceptionListener catches authentication exception and converts them to
 * Response instances.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ExceptionListener implements ListenerInterface
{
    protected $context;
    protected $authenticationEntryPoint;
    protected $authenticationTrustResolver;
    protected $errorPage;
    protected $logger;

    public function __construct(SecurityContext $context, AuthenticationTrustResolverInterface $trustResolver, AuthenticationEntryPointInterface $authenticationEntryPoint = null, $errorPage = null, LoggerInterface $logger = null)
    {
        $this->context = $context;
        $this->authenticationEntryPoint = $authenticationEntryPoint;
        $this->authenticationTrustResolver = $trustResolver;
        $this->errorPage = $errorPage;
        $this->logger = $logger;
    }

    /**
     * Registers a core.exception listener to take care of security exceptions.
     *
     * @param EventDispatcher $dispatcher An EventDispatcher instance
     * @param integer         $priority   The priority
     */
    public function register(EventDispatcher $dispatcher)
    {
        $dispatcher->connect('core.exception', array($this, 'handleException'), 0);
    }
    
    /**
     * {@inheritDoc}
     */
    public function unregister(EventDispatcher $dispatcher)
    {
        $dispatcher->disconnect('core.exception', array($this, 'handleException'));
    }

    /**
     * Handles security related exceptions.
     *
     * @param Event $event An Event instance
     */
    public function handleException(Event $event)
    {
        $exception = $event->get('exception');
        $request = $event->get('request');

        if ($exception instanceof AuthenticationException) {
            if (null !== $this->logger) {
                $this->logger->info(sprintf('Authentication exception occurred; redirecting to authentication entry point (%s)', $exception->getMessage()));
            }

            try {
                $response = $this->startAuthentication($request, $exception);
            } catch (\Exception $e) {
                $event->set('exception', $e);

                return;
            }
        } elseif ($exception instanceof AccessDeniedException) {
            $token = $this->context->getToken();
            if (!$this->authenticationTrustResolver->isFullFledged($token)) {
                if (null !== $this->logger) {
                    $this->logger->info('Access denied (user is not fully authenticated); redirecting to authentication entry point');
                }

                try {
                    $response = $this->startAuthentication($request, new InsufficientAuthenticationException('Full authentication is required to access this resource.', $token, 0, $exception));
                } catch (\Exception $e) {
                    $event->set('exception', $e);

                    return;
                }
            } else {
                if (null !== $this->logger) {
                    $this->logger->info('Access is denied (and user is neither anonymous, nor remember-me)');
                }

                if (null === $this->errorPage) {
                    return;
                }

                $subRequest = Request::create($this->errorPage);
                $subRequest->attributes->set(SecurityContext::ACCESS_DENIED_ERROR, $exception->getMessage());

                try {
                    $response = $event->getSubject()->handle($subRequest, HttpKernelInterface::SUB_REQUEST, true);
                } catch (\Exception $e) {
                    if (null !== $this->logger) {
                        $this->logger->err(sprintf('Exception thrown when handling an exception (%s: %s)', get_class($e), $e->getMessage()));
                    }

                    $event->set('exception', new \RuntimeException('Exception thrown when handling an exception.', 0, $e));

                    return;
                }
                $response->setStatusCode(403);
            }
        } else {
            return;
        }

        $event->setReturnValue($response);

        return true;
    }

    protected function startAuthentication(Request $request, AuthenticationException $reason)
    {
        $this->context->setToken(null);

        if (null === $this->authenticationEntryPoint) {
            throw $reason;
        }

        if (null !== $this->logger) {
            $this->logger->debug('Calling Authentication entry point');
        }

        $request->getSession()->set('_security.target_path', $request->getUri());

        return $this->authenticationEntryPoint->start($request, $reason);
    }
}
