<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication\Token;

/**
 * The token used by the guard auth system before authentication.
 *
 * The GuardAuthenticationListener creates this, which is then consumed
 * immediately by the GuardAuthenticationProvider. If authentication is
 * successful, a different authenticated token is returned
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
class PreAuthenticationGuardToken extends AbstractToken
{
    private $credentials;
    private $guardProviderKey;
    private $providerKey;

    /**
     * @param mixed       $credentials
     * @param string      $guardProviderKey Unique key that bind this token to a specific AuthenticatorInterface
     * @param string|null $providerKey      The general provider key (when using with HTTP, this is the firewall name)
     */
    public function __construct($credentials, string $guardProviderKey, ?string $providerKey = null)
    {
        $this->credentials = $credentials;
        $this->guardProviderKey = $guardProviderKey;
        $this->providerKey = $providerKey;

        parent::__construct([]);

        // never authenticated
        parent::setAuthenticated(false);
    }

    public function getProviderKey(): ?string
    {
        return $this->providerKey;
    }

    public function getGuardProviderKey()
    {
        return $this->guardProviderKey;
    }

    /**
     * Returns the user credentials, which might be an array of anything you
     * wanted to put in there (e.g. username, password, favoriteColor).
     *
     * @return mixed The user credentials
     */
    public function getCredentials()
    {
        return $this->credentials;
    }

    public function setAuthenticated(bool $authenticated)
    {
        throw new \LogicException('The PreAuthenticationGuardToken is *never* authenticated.');
    }
}
