<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Guard\Token;

use Symphony\Component\Security\Core\Authentication\Token\AbstractToken;

/**
 * The token used by the guard auth system before authentication.
 *
 * The GuardAuthenticationListener creates this, which is then consumed
 * immediately by the GuardAuthenticationProvider. If authentication is
 * successful, a different authenticated token is returned
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
class PreAuthenticationGuardToken extends AbstractToken implements GuardTokenInterface
{
    private $credentials;
    private $guardProviderKey;

    /**
     * @param mixed  $credentials
     * @param string $guardProviderKey Unique key that bind this token to a specific AuthenticatorInterface
     */
    public function __construct($credentials, string $guardProviderKey)
    {
        $this->credentials = $credentials;
        $this->guardProviderKey = $guardProviderKey;

        parent::__construct(array());

        // never authenticated
        parent::setAuthenticated(false);
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

    public function setAuthenticated($authenticated)
    {
        throw new \LogicException('The PreAuthenticationGuardToken is *never* authenticated.');
    }
}
