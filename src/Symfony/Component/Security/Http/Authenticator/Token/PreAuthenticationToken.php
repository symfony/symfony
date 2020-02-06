<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator\Token;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;

/**
 * The token used by the authenticator system before authentication.
 *
 * The AuthenticatorManagerListener creates this, which is then consumed
 * immediately by the AuthenticatorManager. If authentication is
 * successful, a different authenticated token is returned
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
class PreAuthenticationToken extends AbstractToken
{
    private $credentials;
    private $authenticatorProviderKey;
    private $providerKey;

    /**
     * @param mixed       $credentials
     * @param string      $authenticatorProviderKey Unique key that bind this token to a specific AuthenticatorInterface
     * @param string|null $providerKey              The general provider key (when using with HTTP, this is the firewall name)
     */
    public function __construct($credentials, string $authenticatorProviderKey, ?string $providerKey = null)
    {
        $this->credentials = $credentials;
        $this->authenticatorProviderKey = $authenticatorProviderKey;
        $this->providerKey = $providerKey;

        parent::__construct([]);

        // never authenticated
        parent::setAuthenticated(false);
    }

    public function getProviderKey(): ?string
    {
        return $this->providerKey;
    }

    public function getAuthenticatorKey()
    {
        return $this->authenticatorProviderKey;
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
        throw new \LogicException('The PreAuthenticationToken is *never* authenticated.');
    }
}
