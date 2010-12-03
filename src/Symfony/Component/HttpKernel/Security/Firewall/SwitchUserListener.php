<?php

namespace Symfony\Component\HttpKernel\Security\Firewall;

use Symfony\Component\Security\SecurityContext;
use Symfony\Component\Security\User\UserProviderInterface;
use Symfony\Component\Security\User\AccountCheckerInterface;
use Symfony\Component\Security\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Security\Exception\AuthenticationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Role\SwitchUserRole;
use Symfony\Component\Security\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Authentication\Token\TokenInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * SwitchUserListener allows a user to impersonate another one temporarly
 * (like the Unix su command).
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class SwitchUserListener implements ListenerInterface
{
    protected $securityContext;
    protected $provider;
    protected $accountChecker;
    protected $accessDecisionManager;
    protected $usernameParameter;
    protected $role;
    protected $logger;

    /**
     * Constructor.
     */
    public function __construct(SecurityContext $securityContext, UserProviderInterface $provider, AccountCheckerInterface $accountChecker, AccessDecisionManagerInterface $accessDecisionManager, LoggerInterface $logger = null, $usernameParameter = '_switch_user', $role = 'ROLE_ALLOWED_TO_SWITCH')
    {
        $this->securityContext = $securityContext;
        $this->provider = $provider;
        $this->accountChecker = $accountChecker;
        $this->accessDecisionManager = $accessDecisionManager;
        $this->usernameParameter = $usernameParameter;
        $this->role = $role;
        $this->logger = $logger;
    }

    /**
     * 
     *
     * @param EventDispatcher $dispatcher An EventDispatcher instance
     * @param integer         $priority   The priority
     */
    public function register(EventDispatcher $dispatcher)
    {
        $dispatcher->connect('core.security', array($this, 'handle'), 0);
    }
    
    /**
     * {@inheritDoc}
     */
    public function unregister(EventDispatcher $dispatcher)
    {
    }
    
    /**
     * Handles digest authentication.
     *
     * @param Event $event An Event instance
     */
    public function handle(Event $event)
    {
        $request = $event->get('request');

        if (!$request->get($this->usernameParameter)) {
            return;
        }

        if ('_exit' === $request->get($this->usernameParameter)) {
            $this->securityContext->setToken($this->attemptExitUser($request));
        } else {
            try {
                $this->securityContext->setToken($this->attemptSwitchUser($request));
            } catch (AuthenticationException $e) {
                if (null !== $this->logger) {
                    $this->logger->debug(sprintf('Switch User failed: "%s"', $e->getMessage()));
                }
            }
        }

        $response = new Response();
        $request->server->set('QUERY_STRING', '');
        $response->setRedirect($request->getUri(), 302);

        $event->setReturnValue($response);

        return true;
    }

    /**
     * Attempts to switch to another user.
     *
     * @param Request $request A Request instance
     *
     * @return TokenInterface|null The new TokenInterface if successfully switched, null otherwise
     */
    protected function attemptSwitchUser(Request $request)
    {
        $token = $this->securityContext->getToken();
        if (false !== $this->getOriginalToken($token)) {
            throw new \LogicException(sprintf('You are already switched to "%s" user.', (string) $token));
        }

        $this->accessDecisionManager->decide($token, null, array($this->role));

        $username = $request->get($this->usernameParameter);

        if (null !== $this->logger) {
            $this->logger->debug(sprintf('Attempt to switch to user "%s"', $username));
        }

        $user = $this->provider->loadUserByUsername($username);
        $this->accountChecker->checkPostAuth($user);

        $roles = $user->getRoles();
        $roles[] = new SwitchUserRole('ROLE_PREVIOUS_ADMIN', $this->securityContext->getToken());

        $token = new UsernamePasswordToken($user, $user->getPassword(), $roles);
        $token->setImmutable(true);

        return $token;
    }

    /**
     * Attempts to exit from an already switched user.
     *
     * @param Request $request A Request instance
     *
     * @return TokenInterface The original TokenInterface instance
     */
    protected function attemptExitUser(Request $request)
    {
        if (false === $original = $this->getOriginalToken($this->securityContext->getToken())) {
            throw new AuthenticationCredentialsNotFoundException(sprintf('Could not find original Token object.'));
        }

        return $original;
    }

    /**
     * Gets the original Token from a switched one.
     *
     * @param TokenInterface $token A switched TokenInterface instance
     *
     * @return TokenInterface|false The original TokenInterface instance, false if the current TokenInterface is not switched
     */
    protected function getOriginalToken(TokenInterface $token)
    {
        foreach ($token->getRoles() as $role) {
            if ($role instanceof SwitchUserRole) {
                return $role->getSource();
            }
        }

        return false;
    }
}
