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

use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\AuthenticationExpiredException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AuthenticatorInterface;
use Symfony\Component\Security\Guard\Token\GuardTokenInterface;
use Symfony\Component\Security\Guard\Token\PreAuthenticationGuardToken;

/**
 * Responsible for accepting the PreAuthenticationGuardToken and calling
 * the correct authenticator to retrieve the authenticated token.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
class GuardAuthenticationProvider implements AuthenticationProviderInterface
{
    use GuardAuthenticationProviderTrait;

    /**
     * @var AuthenticatorInterface[]
     */
    private $guardAuthenticators;
    private $userProvider;
    private $providerKey;
    private $userChecker;
    private $passwordEncoder;

    /**
     * @param iterable|AuthenticatorInterface[] $guardAuthenticators The authenticators, with keys that match what's passed to GuardAuthenticationListener
     * @param string                            $providerKey         The provider (i.e. firewall) key
     */
    public function __construct(iterable $guardAuthenticators, UserProviderInterface $userProvider, string $providerKey, UserCheckerInterface $userChecker, UserPasswordEncoderInterface $passwordEncoder = null)
    {
        $this->guardAuthenticators = $guardAuthenticators;
        $this->userProvider = $userProvider;
        $this->providerKey = $providerKey;
        $this->userChecker = $userChecker;
        $this->passwordEncoder = $passwordEncoder;
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
            if ($token->isAuthenticated()) {
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

    public function supports(TokenInterface $token)
    {
        if ($token instanceof PreAuthenticationGuardToken) {
            return null !== $this->findOriginatingAuthenticator($token);
        }

        return $token instanceof GuardTokenInterface;
    }

    protected function getGuardKey(string $key): string
    {
        return $this->providerKey.'_'.$key;
    }
}
