<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Guard\Token;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;

trigger_deprecation('symfony/security-guard', '5.3', 'The "%s" class is deprecated, use the new authenticator system instead.', PreAuthenticationGuardToken::class);

/**
 * The token used by the guard auth system before authentication.
 *
 * The GuardAuthenticationListener creates this, which is then consumed
 * immediately by the GuardAuthenticationProvider. If authentication is
 * successful, a different authenticated token is returned
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 *
 * @deprecated since Symfony 5.3, use the new authenticator system instead
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

        parent::__construct([]);

        // @deprecated since Symfony 5.4
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
     * @return mixed
     */
    public function getCredentials()
    {
        return $this->credentials;
    }

    /**
     * @deprecated since Symfony 5.4
     */
    public function setAuthenticated(bool $authenticated)
    {
        throw new \LogicException('The PreAuthenticationGuardToken is *never* authenticated.');
    }
}
