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

use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Events as KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Events;

/**
 * The AbstractAuthenticationListener is the preferred base class for all
 * browser-/HTTP-based authentication requests.
 *
 * Subclasses likely have to implement the following:
 * - an TokenInterface to hold authentication related data
 * - an AuthenticationProvider to perform the actual authentication of the
 *   token, retrieve the UserInterface implementation from a database, and
 *   perform the specific account checks using the UserChecker
 *
 * By default, this listener only is active for a specific path, e.g.
 * /login_check. If you want to change this behavior, you can overwrite the
 * requiresAuthentication() method.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
abstract class AbstractAuthenticationListener implements ListenerInterface
{
    protected $options;
    protected $logger;
    protected $authenticationManager;
    protected $providerKey;
    private $securityContext;
    private $sessionStrategy;
    private $dispatcher;
    private $successHandler;
    private $failureHandler;
    private $rememberMeServices;

    /**
     * Constructor.
     *
     * @param SecurityContextInterface       $securityContext       A SecurityContext instance
     * @param AuthenticationManagerInterface $authenticationManager An AuthenticationManagerInterface instance
     * @param array                          $options               An array of options for the processing of a successful, or failed authentication attempt
     * @param LoggerInterface                $logger                A LoggerInterface instance
     * @param EventDispatcherInterface       $dispatcher            An EventDispatcherInterface instance
     */
    public function __construct(SecurityContextInterface $securityContext, AuthenticationManagerInterface $authenticationManager, SessionAuthenticationStrategyInterface $sessionStrategy, $providerKey, array $options = array(), AuthenticationSuccessHandlerInterface $successHandler = null, AuthenticationFailureHandlerInterface $failureHandler = null, LoggerInterface $logger = null, EventDispatcherInterface $dispatcher = null)
    {
        if (empty($providerKey)) {
            throw new \InvalidArgumentException('$providerKey must not be empty.');
        }

        $this->securityContext = $securityContext;
        $this->authenticationManager = $authenticationManager;
        $this->sessionStrategy = $sessionStrategy;
        $this->providerKey = $providerKey;
        $this->successHandler = $successHandler;
        $this->failureHandler = $failureHandler;
        $this->options = array_merge(array(
            'check_path'                     => '/login_check',
            'login_path'                     => '/login',
            'always_use_default_target_path' => false,
            'default_target_path'            => '/',
            'target_path_parameter'          => '_target_path',
            'use_referer'                    => false,
            'failure_path'                   => null,
            'failure_forward'                => false,
        ), $options);
        $this->logger = $logger;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Sets the RememberMeServices implementation to use
     *
     * @param RememberMeServicesInterface $rememberMeServices
     */
    public function setRememberMeServices(RememberMeServicesInterface $rememberMeServices)
    {
        $this->rememberMeServices = $rememberMeServices;
    }

    /**
     * Handles form based authentication.
     *
     * @param GetResponseEvent $event A GetResponseEvent instance
     */
    public final function handle(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        if (!$this->requiresAuthentication($request)) {
            return;
        }

        try {
            if (null === $returnValue = $this->attemptAuthentication($request)) {
                return;
            }

            if ($returnValue instanceof TokenInterface) {
                $this->sessionStrategy->onAuthentication($request, $returnValue);

                $response = $this->onSuccess($event, $request, $returnValue);
            } else if ($returnValue instanceof Response) {
                $response = $returnValue;
            } else {
                throw new \RuntimeException('attemptAuthentication() must either return a Response, an implementation of TokenInterface, or null.');
            }
        } catch (AuthenticationException $e) {
            $response = $this->onFailure($event, $request, $e);
        }

        $event->setResponse($response);
    }

    /**
     * Whether this request requires authentication.
     *
     * The default implementation only processed requests to a specific path,
     * but a subclass could change this to only authenticate requests where a
     * certain parameters is present.
     *
     * @param Request $request
     *
     * @return Boolean
     */
    protected function requiresAuthentication(Request $request)
    {
        return $this->options['check_path'] === $request->getPathInfo();
    }

    /**
     * Performs authentication.
     *
     * @param  Request $request A Request instance
     *
     * @return TokenInterface The authenticated token, or null if full authentication is not possible
     *
     * @throws AuthenticationException if the authentication fails
     */
    abstract protected function attemptAuthentication(Request $request);

    private function onFailure(GetResponseEvent $event, Request $request, AuthenticationException $failed)
    {
        if (null !== $this->logger) {
            $this->logger->debug(sprintf('Authentication request failed: %s', $failed->getMessage()));
        }

        $this->securityContext->setToken(null);

        if (null !== $this->failureHandler) {
            return $this->failureHandler->onAuthenticationFailure($request, $failed);
        }

        if (null === $this->options['failure_path']) {
            $this->options['failure_path'] = $this->options['login_path'];
        }

        if ($this->options['failure_forward']) {
            if (null !== $this->logger) {
                $this->logger->debug(sprintf('Forwarding to %s', $this->options['failure_path']));
            }

            $subRequest = Request::create($this->options['failure_path']);
            $subRequest->attributes->set(SecurityContextInterface::AUTHENTICATION_ERROR, $failed);

            return $event->getKernel()->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
        }

        if (null !== $this->logger) {
            $this->logger->debug(sprintf('Redirecting to %s', $this->options['failure_path']));
        }

        $request->getSession()->set(SecurityContextInterface::AUTHENTICATION_ERROR, $failed);

        return new RedirectResponse(0 !== strpos($this->options['failure_path'], 'http') ? $request->getUriForPath($this->options['failure_path']) : $this->options['failure_path'], 302);
    }

    private function onSuccess(GetResponseEvent $event, Request $request, TokenInterface $token)
    {
        if (null !== $this->logger) {
            $this->logger->debug('User has been authenticated successfully');
        }

        $this->securityContext->setToken($token);

        $session = $request->getSession();
        $session->remove(SecurityContextInterface::AUTHENTICATION_ERROR);
        $session->remove(SecurityContextInterface::LAST_USERNAME);

        if (null !== $this->dispatcher) {
            $loginEvent = new InteractiveLoginEvent($request, $token);
            $this->dispatcher->dispatch(Events::onSecurityInteractiveLogin, $loginEvent);
        }

        if (null !== $this->successHandler) {
            $response = $this->successHandler->onAuthenticationSuccess($request, $token);
        } else {
            $path = $this->determineTargetUrl($request);
            $response = new RedirectResponse(0 !== strpos($path, 'http') ? $request->getUriForPath($path) : $path, 302);
        }

        if (null !== $this->rememberMeServices) {
            $this->rememberMeServices->loginSuccess($request, $response, $token);
        }

        return $response;
    }

    /**
     * Builds the target URL according to the defined options.
     *
     * @param Request $request
     *
     * @return string
     */
    private function determineTargetUrl(Request $request)
    {
        if ($this->options['always_use_default_target_path']) {
            return $this->options['default_target_path'];
        }

        if ($targetUrl = $request->get($this->options['target_path_parameter'])) {
            return $targetUrl;
        }

        $session = $request->getSession();
        if ($targetUrl = $session->get('_security.target_path')) {
            $session->remove('_security.target_path');

            return $targetUrl;
        }

        if ($this->options['use_referer'] && $targetUrl = $request->headers->get('Referer')) {
            return $targetUrl;
        }

        return $this->options['default_target_path'];
    }
}
