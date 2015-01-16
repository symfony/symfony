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

use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Role\SwitchUserRole;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Event\SwitchUserEvent;
use Symfony\Component\Security\Http\SecurityEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * SwitchUserListener allows a user to impersonate another one temporarily
 * (like the Unix su command).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SwitchUserListener implements ListenerInterface
{
    const ROLE_PREVOIUS_ADMIN = 'ROLE_PREVIOUS_ADMIN';

    private $tokenStorage;
    private $provider;
    private $userChecker;
    private $providerKey;
    private $accessDecisionManager;
    private $usernameParameter;
    private $role;
    private $logger;
    private $dispatcher;

    public function __construct(TokenStorageInterface $tokenStorage, UserProviderInterface $provider, UserCheckerInterface $userChecker, $providerKey, AccessDecisionManagerInterface $accessDecisionManager, LoggerInterface $logger = null, $usernameParameter = '_switch_user', $role = 'ROLE_ALLOWED_TO_SWITCH', EventDispatcherInterface $dispatcher = null)
    {
        if (empty($providerKey)) {
            throw new \InvalidArgumentException('$providerKey must not be empty.');
        }

        $this->tokenStorage = $tokenStorage;
        $this->provider = $provider;
        $this->userChecker = $userChecker;
        $this->providerKey = $providerKey;
        $this->accessDecisionManager = $accessDecisionManager;
        $this->usernameParameter = $usernameParameter;
        $this->role = $role;
        $this->logger = $logger;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Handles the switch to another user.
     *
     * @param GetResponseEvent $event A GetResponseEvent instance
     *
     * @throws \LogicException if switching to a user or exiting fails
     */
    public function handle(GetResponseEvent $event)
    {
        $request = $event->getRequest();
        $usernameParameter = $request->get($this->usernameParameter);

        // listener stops if _switch_user request parameters is not defined
        if (!$usernameParameter) {
            return;
        }

        try {

            if ('_exit' === $usernameParameter) {
                $token = $this->exitSwitchUser($request);
            } else {
                $token = $this->switchUser($request);
            }

        } catch (AuthenticationException $e) {
            throw new \LogicException(sprintf('Switch User failed: "%s"', $e->getMessage()));
        }

        $this->tokenStorage->setToken($token);

        $request->query->remove($this->usernameParameter);
        $request->server->set('QUERY_STRING', http_build_query($request->query->all()));

        $response = new RedirectResponse($request->getUri(), 302);

        $event->setResponse($response);
    }

    /**
     * Attempts to switch to another user.
     *
     * @param Request $request A Request instance
     *
     * @return TokenInterface
     *
     * @throws \LogicException
     * @throws AccessDeniedException
     * @throws UsernameNotFoundException
     */
    private function switchUser(Request $request)
    {
        if (null !== $this->logger) {
            $this->logger->info('Attempting to switch to user.', array('username' => $request->get($this->usernameParameter));
        }

        // token of the currently authenticated user
        $sourceToken = $this->tokenStorage->getToken();

        // check if the authenticated user has the a role to switch user
        if (true !== $this->accessDecisionManager->decide($sourceToken, array($this->role)) && true !== $this->accessDecisionManager->decide($sourceToken, array(self::ROLE_PREVOIUS_ADMIN))) {
            throw new AccessDeniedException(sprintf("You must have the \"%s\" or the \"%s\" role to be able to switch user.", $this->role, self::ROLE_PREVOIUS_ADMIN));
        }

        // user is attempting to switch to his own username
        if ($sourceToken->getUsername() === $request->get($this->usernameParameter)) {
            return $sourceToken;
        }

        // authenticate the user with username passed by request
        $user = $this->provider->loadUserByUsername($request->get($this->usernameParameter));
        $this->userChecker->checkPostAuth($user);

        /*
         * if user is attempting to switch from an already switched token,
         * fetch the original token instead of the currently authenticated user's one
         */
        $originalToken = $this->getOriginalToken($sourceToken);
        if ($originalToken !== null) {
            $sourceToken = $originalToken;
        }

        $roles = $user->getRoles();

        /*
         * add role ROLE_PREVIOUS_ADMIN to switched user lo let him exit switching,
         * only if he's not the original one
         */
        if (!$originalToken || $originalToken->getUsername() !== $request->get($this->usernameParameter)) {
            $roles[] = new SwitchUserRole(self::ROLE_PREVOIUS_ADMIN, $sourceToken);
        }

        // create token for switched user
        $token = new UsernamePasswordToken($user, $user->getPassword(), $this->providerKey, $roles);

        // dispatch event on user switching
        if (null !== $this->dispatcher) {
            $switchEvent = new SwitchUserEvent($request, $token->getUser());
            $this->dispatcher->dispatch(SecurityEvents::SWITCH_USER, $switchEvent);
        }

        return $token;
    }

    /**
     * Attempts to exit from an already switched user.
     *
     * @param Request $request A Request instance
     *
     * @return TokenInterface The original TokenInterface instance
     *
     * @throws AuthenticationCredentialsNotFoundException
     */
    private function exitSwitchUser(Request $request)
    {
        $token = $this->tokenStorage->getToken();

        // check if the authenticated user has the right role to exit switching
        if (true !== $this->accessDecisionManager->decide($token, array(self::ROLE_PREVOIUS_ADMIN))) {
            throw new AccessDeniedException(sprintf("You must have the \"%s\" role to exit user switching.", self::ROLE_PREVOIUS_ADMIN));
        }

        $originalToken = $this->getOriginalToken($token);

        if (null === $originalToken) {
            throw new AuthenticationCredentialsNotFoundException('Could not find original Token object.');
        }

        if (null !== $this->dispatcher) {
            $switchEvent = new SwitchUserEvent($request, $originalToken->getUser());
            $this->dispatcher->dispatch(SecurityEvents::SWITCH_USER, $switchEvent);
        }

        return $originalToken;
    }

    /**
     * Gets the original Token from a switched one.
     *
     * @param TokenInterface $token A switched TokenInterface instance
     *
     * @return TokenInterface|null The original TokenInterface instance, false if the current TokenInterface is not switched
     */
    private function getOriginalToken(TokenInterface $token)
    {
        foreach ($token->getRoles() as $role) {
            if ($role instanceof SwitchUserRole) {
                return $role->getSource();
            }
        }

        return;
    }
}
