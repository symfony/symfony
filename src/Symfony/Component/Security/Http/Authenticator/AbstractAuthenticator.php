<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\LogicException;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\UserPassportInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

/**
 * An optional base class that creates the necessary tokens for you.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
abstract class AbstractAuthenticator implements AuthenticatorInterface
{
    /**
     * Shortcut to create a PostAuthenticationToken for you, if you don't really
     * care about which authenticated token you're using.
     */
    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        if (self::class !== (new \ReflectionMethod($this, 'createAuthenticatedToken'))->getDeclaringClass()->getName() && self::class === (new \ReflectionMethod($this, 'createToken'))->getDeclaringClass()->getName()) {
            return $this->createAuthenticatedToken($passport, $firewallName);
        }

        return new PostAuthenticationToken($passport->getUser(), $firewallName, $passport->getUser()->getRoles());
    }

    /**
     * @deprecated since Symfony 5.4, use {@link createToken()} instead
     */
    public function createAuthenticatedToken(PassportInterface $passport, string $firewallName): TokenInterface
    {
        // @deprecated since Symfony 5.4
        if (!$passport instanceof UserPassportInterface) {
            throw new LogicException(sprintf('Passport does not contain a user, overwrite "createToken()" in "%s" to create a custom authentication token.', static::class));
        }

        trigger_deprecation('symfony/security-http', '5.4', 'Method "%s()" is deprecated, use "%s::createToken()" instead.', __METHOD__, __CLASS__);

        return new PostAuthenticationToken($passport->getUser(), $firewallName, $passport->getUser()->getRoles());
    }
}
