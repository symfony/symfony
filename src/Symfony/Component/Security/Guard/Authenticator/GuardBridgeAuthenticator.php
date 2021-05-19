<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Guard\Authenticator;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AuthenticatorInterface as GuardAuthenticatorInterface;
use Symfony\Component\Security\Guard\PasswordAuthenticatedInterface;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PasswordUpgradeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\UserPassportInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

trigger_deprecation('symfony/security-guard', '5.3', 'The "%s" class is deprecated, use the new authenticator system instead.', GuardBridgeAuthenticator::class);

/**
 * This authenticator is used to bridge Guard authenticators with
 * the Symfony Authenticator system.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @internal
 *
 * @deprecated since Symfony 5.3
 */
class GuardBridgeAuthenticator implements InteractiveAuthenticatorInterface, AuthenticationEntryPointInterface
{
    private $guard;
    private $userProvider;

    public function __construct(GuardAuthenticatorInterface $guard, UserProviderInterface $userProvider)
    {
        $this->guard = $guard;
        $this->userProvider = $userProvider;
    }

    public function start(Request $request, AuthenticationException $authException = null)
    {
        return $this->guard->start($request, $authException);
    }

    public function supports(Request $request): ?bool
    {
        return $this->guard->supports($request);
    }

    public function authenticate(Request $request): PassportInterface
    {
        $credentials = $this->guard->getCredentials($request);

        if (null === $credentials) {
            throw new \UnexpectedValueException(sprintf('The return value of "%1$s::getCredentials()" must not be null. Return false from "%1$s::supports()" instead.', get_debug_type($this->guard)));
        }

        // get the user from the GuardAuthenticator
        if (class_exists(UserBadge::class)) {
            $user = new UserBadge('guard_authenticator_'.md5(serialize($credentials)), function () use ($credentials) { return $this->getUser($credentials); });
        } else {
            // BC with symfony/security-http:5.1
            $user = $this->getUser($credentials);
        }

        if ($this->guard instanceof PasswordAuthenticatedInterface && !$user instanceof PasswordAuthenticatedUserInterface) {
            trigger_deprecation('symfony/security-guard', '5.3', 'Not implementing the "%s" interface in class "%s" while using password-based guard authenticators is deprecated.', PasswordAuthenticatedUserInterface::class, get_debug_type($user));
        }

        $passport = new Passport($user, new CustomCredentials([$this->guard, 'checkCredentials'], $credentials));
        if ($this->userProvider instanceof PasswordUpgraderInterface && $this->guard instanceof PasswordAuthenticatedInterface && (null !== $password = $this->guard->getPassword($credentials))) {
            $passport->addBadge(new PasswordUpgradeBadge($password, $this->userProvider));
        }

        if ($this->guard->supportsRememberMe()) {
            $passport->addBadge(new RememberMeBadge());
        }

        return $passport;
    }

    private function getUser($credentials): UserInterface
    {
        $user = $this->guard->getUser($credentials, $this->userProvider);

        if (null === $user) {
            throw new UserNotFoundException(sprintf('Null returned from "%s::getUser()".', get_debug_type($this->guard)));
        }

        if (!$user instanceof UserInterface) {
            throw new \UnexpectedValueException(sprintf('The "%s::getUser()" method must return a UserInterface. You returned "%s".', get_debug_type($this->guard), get_debug_type($user)));
        }

        return $user;
    }

    public function createAuthenticatedToken(PassportInterface $passport, string $firewallName): TokenInterface
    {
        if (!$passport instanceof UserPassportInterface) {
            throw new \LogicException(sprintf('"%s" does not support non-user passports.', __CLASS__));
        }

        return $this->guard->createAuthenticatedToken($passport->getUser(), $firewallName);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return $this->guard->onAuthenticationSuccess($request, $token, $firewallName);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return $this->guard->onAuthenticationFailure($request, $exception);
    }

    public function isInteractive(): bool
    {
        // the GuardAuthenticationHandler always dispatches the InteractiveLoginEvent
        return true;
    }
}
