<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Guard\Provider;

use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\AuthenticationExpiredException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AuthenticatorInterface;
use Symfony\Component\Security\Guard\PasswordAuthenticatedInterface;
use Symfony\Component\Security\Guard\Token\GuardTokenInterface;
use Symfony\Component\Security\Guard\Token\PreAuthenticationGuardToken;

trigger_deprecation('symfony/security-guard', '5.3', 'The "%s" class is deprecated, use the new authenticator system instead.', GuardAuthenticationProvider::class);

/**
 * Responsible for accepting the PreAuthenticationGuardToken and calling
 * the correct authenticator to retrieve the authenticated token.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 *
 * @deprecated since Symfony 5.3, use the new authenticator system instead
 */
class GuardAuthenticationProvider implements AuthenticationProviderInterface
{
    private $guardAuthenticators;
    private $userProvider;
    private $providerKey;
    private $userChecker;
    private $passwordHasher;

    /**
     * @param iterable<array-key, AuthenticatorInterface> $guardAuthenticators The authenticators, with keys that match what's passed to GuardAuthenticationListener
     * @param string                                      $providerKey         The provider (i.e. firewall) key
     * @param UserPasswordHasherInterface                 $passwordHasher
     */
    public function __construct(iterable $guardAuthenticators, UserProviderInterface $userProvider, string $providerKey, UserCheckerInterface $userChecker, $passwordHasher = null)
    {
        $this->guardAuthenticators = $guardAuthenticators;
        $this->userProvider = $userProvider;
        $this->providerKey = $providerKey;
        $this->userChecker = $userChecker;
        $this->passwordHasher = $passwordHasher;

        if ($passwordHasher instanceof UserPasswordEncoderInterface) {
            trigger_deprecation('symfony/security-core', '5.3', sprintf('Passing a "%s" instance to the "%s" constructor is deprecated, use "%s" instead.', UserPasswordEncoderInterface::class, __CLASS__, UserPasswordHasherInterface::class));
        }
    }

    /**
     * Finds the correct authenticator for the token and calls it.
     *
     * @param GuardTokenInterface $token
     *
     * @return TokenInterface
     */
    public function authenticate(TokenInterface $token)
    {
        if (!$token instanceof GuardTokenInterface) {
            throw new \InvalidArgumentException('GuardAuthenticationProvider only supports GuardTokenInterface.');
        }

        if (!$token instanceof PreAuthenticationGuardToken) {
            /*
             * The listener *only* passes PreAuthenticationGuardToken instances.
             * This means that an authenticated token (e.g. PostAuthenticationGuardToken)
             * is being passed here, which happens if that token becomes
             * "not authenticated" (e.g. happens if the user changes between
             * requests). In this case, the user should be logged out, so
             * we will return an AnonymousToken to accomplish that.
             */

            // this should never happen - but technically, the token is
            // authenticated... so it could just be returned
            if ($token->isAuthenticated(false)) {
                return $token;
            }

            // this causes the user to be logged out
            throw new AuthenticationExpiredException();
        }

        $guardAuthenticator = $this->findOriginatingAuthenticator($token);

        if (null === $guardAuthenticator) {
            throw new AuthenticationException(sprintf('Token with provider key "%s" did not originate from any of the guard authenticators of provider "%s".', $token->getGuardProviderKey(), $this->providerKey));
        }

        return $this->authenticateViaGuard($guardAuthenticator, $token);
    }

    private function authenticateViaGuard(AuthenticatorInterface $guardAuthenticator, PreAuthenticationGuardToken $token): GuardTokenInterface
    {
        // get the user from the GuardAuthenticator
        $user = $guardAuthenticator->getUser($token->getCredentials(), $this->userProvider);

        if (null === $user) {
            $e = new UserNotFoundException(sprintf('Null returned from "%s::getUser()".', get_debug_type($guardAuthenticator)));
            // @deprecated since Symfony 5.3, change to $token->getUserIdentifier() in 6.0
            $e->setUserIdentifier(method_exists($token, 'getUserIdentifier') ? $token->getUserIdentifier() : $token->getUsername());

            throw $e;
        }

        if (!$user instanceof UserInterface) {
            throw new \UnexpectedValueException(sprintf('The "%s::getUser()" method must return a UserInterface. You returned "%s".', get_debug_type($guardAuthenticator), get_debug_type($user)));
        }

        if ($guardAuthenticator instanceof PasswordAuthenticatedInterface && !$user instanceof PasswordAuthenticatedUserInterface) {
            trigger_deprecation('symfony/security-guard', '5.3', 'Not implementing the "%s" interface in class "%s" while using password-based guard authenticators is deprecated.', PasswordAuthenticatedUserInterface::class, get_debug_type($user));
        }

        $this->userChecker->checkPreAuth($user);
        if (true !== $checkCredentialsResult = $guardAuthenticator->checkCredentials($token->getCredentials(), $user)) {
            if (false !== $checkCredentialsResult) {
                throw new \TypeError(sprintf('"%s::checkCredentials()" must return a boolean value.', get_debug_type($guardAuthenticator)));
            }

            throw new BadCredentialsException(sprintf('Authentication failed because "%s::checkCredentials()" did not return true.', get_debug_type($guardAuthenticator)));
        }
        if ($this->userProvider instanceof PasswordUpgraderInterface && $guardAuthenticator instanceof PasswordAuthenticatedInterface && null !== $this->passwordHasher && (null !== $password = $guardAuthenticator->getPassword($token->getCredentials())) && $this->passwordHasher->needsRehash($user)) {
            if ($this->passwordHasher instanceof UserPasswordEncoderInterface) {
                // @deprecated since Symfony 5.3
                $this->userProvider->upgradePassword($user, $this->passwordHasher->encodePassword($user, $password));
            } else {
                $this->userProvider->upgradePassword($user, $this->passwordHasher->hashPassword($user, $password));
            }
        }
        $this->userChecker->checkPostAuth($user);

        // turn the UserInterface into a TokenInterface
        $authenticatedToken = $guardAuthenticator->createAuthenticatedToken($user, $this->providerKey);
        if (!$authenticatedToken instanceof TokenInterface) {
            throw new \UnexpectedValueException(sprintf('The "%s::createAuthenticatedToken()" method must return a TokenInterface. You returned "%s".', get_debug_type($guardAuthenticator), get_debug_type($authenticatedToken)));
        }

        return $authenticatedToken;
    }

    private function findOriginatingAuthenticator(PreAuthenticationGuardToken $token): ?AuthenticatorInterface
    {
        // find the *one* GuardAuthenticator that this token originated from
        foreach ($this->guardAuthenticators as $key => $guardAuthenticator) {
            // get a key that's unique to *this* guard authenticator
            // this MUST be the same as GuardAuthenticationListener
            $uniqueGuardKey = $this->providerKey.'_'.$key;

            if ($uniqueGuardKey === $token->getGuardProviderKey()) {
                return $guardAuthenticator;
            }
        }

        // no matching authenticator found - but there will be multiple GuardAuthenticationProvider
        // instances that will be checked if you have multiple firewalls.

        return null;
    }

    public function supports(TokenInterface $token)
    {
        if ($token instanceof PreAuthenticationGuardToken) {
            return null !== $this->findOriginatingAuthenticator($token);
        }

        return $token instanceof GuardTokenInterface;
    }
}
