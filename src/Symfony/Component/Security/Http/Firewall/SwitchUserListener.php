<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Firewall;

use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\AccountCheckerInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\RequestEventArgs;
use Symfony\Component\HttpKernel\Events;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Role\SwitchUserRole;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Event\SwitchUserEventArgs;
use Symfony\Component\Security\Http\Events;
use Doctrine\Common\EventManager;

/**
 * SwitchUserListener allows a user to impersonate another one temporarily
 * (like the Unix su command).
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class SwitchUserListener implements ListenerInterface
{
    protected $securityContext;
    protected $provider;
    protected $accountChecker;
    protected $providerKey;
    protected $accessDecisionManager;
    protected $usernameParameter;
    protected $role;
    protected $logger;
    protected $evm;

    /**
     * Constructor.
     */
    public function __construct(SecurityContextInterface $securityContext, UserProviderInterface $provider, AccountCheckerInterface $accountChecker, $providerKey, AccessDecisionManagerInterface $accessDecisionManager, LoggerInterface $logger = null, $usernameParameter = '_switch_user', $role = 'ROLE_ALLOWED_TO_SWITCH')
    {
        if (empty($providerKey)) {
            throw new \InvalidArgumentException('$providerKey must not be empty.');
        }

        $this->securityContext = $securityContext;
        $this->provider = $provider;
        $this->accountChecker = $accountChecker;
        $this->providerKey = $providerKey;
        $this->accessDecisionManager = $accessDecisionManager;
        $this->usernameParameter = $usernameParameter;
        $this->role = $role;
        $this->logger = $logger;
    }

    /**
     *
     *
     * @param EventManager $evm An EventManager instance
     */
    public function register(EventManager $evm)
    {
        $evm->addEventListener(Events::onCoreSecurity, $this);

        $this->evm = $evm;
    }

    /**
     * {@inheritDoc}
     */
    public function unregister(EventManager $evm)
    {
    }

    /**
     * Handles digest authentication.
     *
     * @param RequestEventArgs $eventArgs A RequestEventArgs instance
     */
    public function onCoreSecurity(RequestEventArgs $eventArgs)
    {
        $request = $eventArgs->getRequest();

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

        $request->server->set('QUERY_STRING', '');
        $response = new RedirectResponse($request->getUri(), 302);

        $event->setResponse($response);
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

        $this->accessDecisionManager->decide($token, array($this->role));

        $username = $request->get($this->usernameParameter);

        if (null !== $this->logger) {
            $this->logger->debug(sprintf('Attempt to switch to user "%s"', $username));
        }

        $user = $this->provider->loadUserByUsername($username);
        $this->accountChecker->checkPostAuth($user);

        $roles = $user->getRoles();
        $roles[] = new SwitchUserRole('ROLE_PREVIOUS_ADMIN', $this->securityContext->getToken());

        $token = new UsernamePasswordToken($user, $user->getPassword(), $this->providerKey, $roles);
        $token->setImmutable(true);

        if (null !== $this->evm) {
            $switchEventArgs = new SwitchUserEventArgs($request, $token->getUser());
            $this->evm->dispatchEvent(Events::onSecuritySwitchUser, $switchEventArgs);
        }

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

        if (null !== $this->evm) {
            $switchEventArgs = new SwitchUserEventArgs($request, $original->getUser());
            $this->evm->notify(Events::onSecuritySwitchUser, $switchEventArgs);
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
