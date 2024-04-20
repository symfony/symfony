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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Event\SwitchUserEvent;
use Symfony\Component\Security\Http\SecurityEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * SwitchUserListener allows a user to impersonate another one temporarily
 * (like the Unix su command).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class SwitchUserListener extends AbstractListener
{
    public const EXIT_VALUE = '_exit';

    private TokenStorageInterface $tokenStorage;
    private UserProviderInterface $provider;
    private UserCheckerInterface $userChecker;
    private string $firewallName;
    private AccessDecisionManagerInterface $accessDecisionManager;
    private string $usernameParameter;
    private string $role;
    private ?LoggerInterface $logger;
    private ?EventDispatcherInterface $dispatcher;
    private bool $stateless;
    private ?UrlGeneratorInterface $urlGenerator;
    private ?string $targetRoute;

    public function __construct(TokenStorageInterface $tokenStorage, UserProviderInterface $provider, UserCheckerInterface $userChecker, string $firewallName, AccessDecisionManagerInterface $accessDecisionManager, ?LoggerInterface $logger = null, string $usernameParameter = '_switch_user', string $role = 'ROLE_ALLOWED_TO_SWITCH', ?EventDispatcherInterface $dispatcher = null, bool $stateless = false, ?UrlGeneratorInterface $urlGenerator = null, ?string $targetRoute = null)
    {
        if ('' === $firewallName) {
            throw new \InvalidArgumentException('$firewallName must not be empty.');
        }

        $this->tokenStorage = $tokenStorage;
        $this->provider = $provider;
        $this->userChecker = $userChecker;
        $this->firewallName = $firewallName;
        $this->accessDecisionManager = $accessDecisionManager;
        $this->usernameParameter = $usernameParameter;
        $this->role = $role;
        $this->logger = $logger;
        $this->dispatcher = $dispatcher;
        $this->stateless = $stateless;
        $this->urlGenerator = $urlGenerator;
        $this->targetRoute = $targetRoute;
    }

    public function supports(Request $request): ?bool
    {
        // usernames can be falsy
        $username = $request->get($this->usernameParameter);

        if (null === $username || '' === $username) {
            $username = $request->headers->get($this->usernameParameter);
        }

        // if it's still "empty", nothing to do.
        if (null === $username || '' === $username) {
            return false;
        }

        $request->attributes->set('_switch_user_username', $username);

        return true;
    }

    /**
     * Handles the switch to another user.
     *
     * @throws \LogicException if switching to a user failed
     */
    public function authenticate(RequestEvent $event): void
    {
        $request = $event->getRequest();

        $username = $request->attributes->get('_switch_user_username');
        $request->attributes->remove('_switch_user_username');

        if (null === $this->tokenStorage->getToken()) {
            throw new AuthenticationCredentialsNotFoundException('Could not find original Token object.');
        }

        if (self::EXIT_VALUE === $username) {
            $this->tokenStorage->setToken($this->attemptExitUser($request));
        } else {
            try {
                $this->tokenStorage->setToken($this->attemptSwitchUser($request, $username));
            } catch (AuthenticationException $e) {
                // Generate 403 in any conditions to prevent user enumeration vulnerabilities
                throw new AccessDeniedException('Switch User failed: '.$e->getMessage(), $e);
            }
        }

        if (!$this->stateless) {
            $request->query->remove($this->usernameParameter);
            $request->server->set('QUERY_STRING', http_build_query($request->query->all(), '', '&'));
            $response = new RedirectResponse($this->urlGenerator && $this->targetRoute ? $this->urlGenerator->generate($this->targetRoute) : $request->getUri(), 302);

            $event->setResponse($response);
        }
    }

    /**
     * Attempts to switch to another user and returns the new token if successfully switched.
     *
     * @throws \LogicException
     * @throws AccessDeniedException
     */
    private function attemptSwitchUser(Request $request, string $username): ?TokenInterface
    {
        $token = $this->tokenStorage->getToken();
        $originalToken = $this->getOriginalToken($token);

        if (null !== $originalToken) {
            if ($token->getUserIdentifier() === $username) {
                return $token;
            }

            // User already switched, exit before seamlessly switching to another user
            $token = $this->attemptExitUser($request);
        }

        $currentUsername = $token->getUserIdentifier();
        $nonExistentUsername = '_'.hash('xxh128', random_bytes(8).$username);

        // To protect against user enumeration via timing measurements
        // we always load both successfully and unsuccessfully
        try {
            $user = $this->provider->loadUserByIdentifier($username);

            try {
                $this->provider->loadUserByIdentifier($nonExistentUsername);
            } catch (\Exception) {
            }
        } catch (AuthenticationException $e) {
            $this->provider->loadUserByIdentifier($currentUsername);

            throw $e;
        }

        if (false === $this->accessDecisionManager->decide($token, [$this->role], $user)) {
            $exception = new AccessDeniedException();
            $exception->setAttributes($this->role);

            throw $exception;
        }

        $this->logger?->info('Attempting to switch to user.', ['username' => $username]);

        $this->userChecker->checkPostAuth($user);

        $roles = $user->getRoles();
        $originatedFromUri = str_replace('/&', '/?', preg_replace('#[&?]'.$this->usernameParameter.'=[^&]*#', '', $request->getRequestUri()));
        $token = new SwitchUserToken($user, $this->firewallName, $roles, $token, $originatedFromUri);

        if (null !== $this->dispatcher) {
            $switchEvent = new SwitchUserEvent($request, $token->getUser(), $token);
            $this->dispatcher->dispatch($switchEvent, SecurityEvents::SWITCH_USER);
            // use the token from the event in case any listeners have replaced it.
            $token = $switchEvent->getToken();
        }

        return $token;
    }

    /**
     * Attempts to exit from an already switched user and returns the original token.
     *
     * @throws AuthenticationCredentialsNotFoundException
     */
    private function attemptExitUser(Request $request): TokenInterface
    {
        if (null === ($currentToken = $this->tokenStorage->getToken()) || null === $original = $this->getOriginalToken($currentToken)) {
            throw new AuthenticationCredentialsNotFoundException('Could not find original Token object.');
        }

        if (null !== $this->dispatcher && $original->getUser() instanceof UserInterface) {
            $user = $this->provider->refreshUser($original->getUser());
            $original->setUser($user);
            $switchEvent = new SwitchUserEvent($request, $user, $original);
            $this->dispatcher->dispatch($switchEvent, SecurityEvents::SWITCH_USER);
            $original = $switchEvent->getToken();
        }

        return $original;
    }

    private function getOriginalToken(TokenInterface $token): ?TokenInterface
    {
        if ($token instanceof SwitchUserToken) {
            return $token->getOriginalToken();
        }

        return null;
    }
}
