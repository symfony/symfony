<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authentication;

use Symfony\Component\Security\Http\Authentication\Authenticator\AuthenticatorInterface as CoreAuthenticatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticationGuardToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\LogicException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Guard\AuthenticatorInterface;
use Symfony\Component\Security\Guard\PasswordAuthenticatedInterface;

/**
 * @author Ryan Weaver <ryan@knpuniversity.com>
 *
 * @internal
 */
trait GuardAuthenticationManagerTrait
{
    /**
     * @param CoreAuthenticatorInterface|AuthenticatorInterface $guardAuthenticator
     */
    private function authenticateViaGuard($guardAuthenticator, PreAuthenticationGuardToken $token, string $providerKey): TokenInterface
    {
        // get the user from the GuardAuthenticator
        if ($guardAuthenticator instanceof AuthenticatorInterface) {
            if (!isset($this->userProvider)) {
                throw new LogicException(sprintf('%s only supports authenticators implementing "%s", update "%s" or use the legacy guard integration instead.', __CLASS__, CoreAuthenticatorInterface::class, \get_class($guardAuthenticator)));
            }
            $user = $guardAuthenticator->getUser($token->getCredentials(), $this->userProvider);
        } elseif ($guardAuthenticator instanceof CoreAuthenticatorInterface) {
            $user = $guardAuthenticator->getUser($token->getCredentials());
        } else {
            throw new \UnexpectedValueException('Invalid guard authenticator passed to '.__METHOD__.'. Expected AuthenticatorInterface of either Security Core or Security Guard.');
        }

        if (null === $user) {
            throw new UsernameNotFoundException(sprintf('Null returned from "%s::getUser()".', get_debug_type($guardAuthenticator)));
        }

        if (!$user instanceof UserInterface) {
            throw new \UnexpectedValueException(sprintf('The "%s::getUser()" method must return a UserInterface. You returned "%s".', get_debug_type($guardAuthenticator), get_debug_type($user)));
        }

        $this->userChecker->checkPreAuth($user);
        if (true !== $checkCredentialsResult = $guardAuthenticator->checkCredentials($token->getCredentials(), $user)) {
            if (false !== $checkCredentialsResult) {
                throw new \TypeError(sprintf('"%s::checkCredentials()" must return a boolean value.', get_debug_type($guardAuthenticator)));
            }

            throw new BadCredentialsException(sprintf('Authentication failed because "%s::checkCredentials()" did not return true.', get_debug_type($guardAuthenticator)));
        }

        if ($guardAuthenticator instanceof PasswordAuthenticatedInterface
            && null !== $password = $guardAuthenticator->getPassword($token->getCredentials())
            && null !== $passwordEncoder = $this->passwordEncoder ?? (method_exists($guardAuthenticator, 'getPasswordEncoder') ? $guardAuthenticator->getPasswordEncoder() : null)
        ) {
            if (method_exists($passwordEncoder, 'needsRehash') && $passwordEncoder->needsRehash($user)) {
                if (!isset($this->userProvider)) {
                    if ($guardAuthenticator instanceof PasswordUpgraderInterface) {
                        $guardAuthenticator->upgradePassword($user, $guardAuthenticator->getPasswordEncoder()->encodePassword($user, $password));
                    }
                } elseif ($this->userProvider instanceof PasswordUpgraderInterface) {
                    $this->userProvider->upgradePassword($user, $passwordEncoder->encodePassword($user, $password));
                }
            }
        }
        $this->userChecker->checkPostAuth($user);

        // turn the UserInterface into a TokenInterface
        $authenticatedToken = $guardAuthenticator->createAuthenticatedToken($user, $providerKey);
        if (!$authenticatedToken instanceof TokenInterface) {
            throw new \UnexpectedValueException(sprintf('The "%s::createAuthenticatedToken()" method must return a TokenInterface. You returned "%s".', get_debug_type($guardAuthenticator), get_debug_type($authenticatedToken)));
        }

        return $authenticatedToken;
    }

    /**
     * @return CoreAuthenticatorInterface|\Symfony\Component\Security\Guard\AuthenticatorInterface|null
     */
    private function findOriginatingAuthenticator(PreAuthenticationGuardToken $token)
    {
        // find the *one* GuardAuthenticator that this token originated from
        foreach ($this->guardAuthenticators as $key => $guardAuthenticator) {
            // get a key that's unique to *this* guard authenticator
            // this MUST be the same as GuardAuthenticationListener
            $uniqueGuardKey = $this->getGuardKey($key);

            if ($uniqueGuardKey === $token->getGuardProviderKey()) {
                return $guardAuthenticator;
            }
        }

        // no matching authenticator found - but there will be multiple GuardAuthenticationProvider
        // instances that will be checked if you have multiple firewalls.

        return null;
    }

    abstract protected function getGuardKey(string $key): string;
}
