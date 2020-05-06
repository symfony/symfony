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
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AuthenticatorInterface as GuardAuthenticatorInterface;
use Symfony\Component\Security\Guard\PasswordAuthenticatedInterface;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PasswordUpgradeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\UserPassportInterface;

/**
 * This authenticator is used to bridge Guard authenticators with
 * the Symfony Authenticator system.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @internal
 */
class GuardBridgeAuthenticator implements InteractiveAuthenticatorInterface
{
    private $guard;
    private $userProvider;

    public function __construct(GuardAuthenticatorInterface $guard, UserProviderInterface $userProvider)
    {
        $this->guard = $guard;
        $this->userProvider = $userProvider;
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
        $user = $this->guard->getUser($credentials, $this->userProvider);

        if (null === $user) {
            throw new UsernameNotFoundException(sprintf('Null returned from "%s::getUser()".', get_debug_type($this->guard)));
        }

        if (!$user instanceof UserInterface) {
            throw new \UnexpectedValueException(sprintf('The "%s::getUser()" method must return a UserInterface. You returned "%s".', get_debug_type($this->guard), get_debug_type($user)));
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
