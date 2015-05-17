<?php

namespace Symfony\Component\Security\Guard\Token;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;

/**
 * The token used by the guard auth system before authentication
 *
 * The GuardAuthenticationListener creates this, which is then consumed
 * immediately by the GuardAuthenticationProvider. If authentication is
 * successful, a different authenticated token is returned
 *
 * @author Ryan Weaver <weaverryan@gmail.com>
 */
class PreAuthenticationGuardToken extends AbstractToken implements GuardTokenInterface
{
    private $credentials;
    private $guardProviderKey;

    /**
     * @param mixed  $credentials
     * @param string $guardProviderKey Unique key that bind this token to a specific GuardAuthenticatorInterface
     */
    public function __construct($credentials, $guardProviderKey)
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
        throw new \Exception('The PreAuthenticationGuardToken is *always* not authenticated');
    }
}
