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

use Symfony\Component\Security\Core\Role\Role;

/**
 * AnonymousToken represents an anonymous token.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated Since version 3.1, to be removed in 4.0. Use AnonymousRequestToken or AuthenticatedAnonymousToken instead.
 */
class AnonymousToken extends AbstractToken
{
    private $secret;

    /**
     * Constructor.
     *
     * @param string        $secret A secret used to make sure the token is created by the app and not by a malicious client
     * @param string|object $user   The user can be a UserInterface instance, or an object implementing a __toString method or the username as a regular string
     * @param Role[]        $roles  An array of roles
     */
    public function __construct($secret, $user, array $roles = array(), $deprecation = true)
    {
        if ($deprecation) {
            @trigger_error(__CLASS__.' is deprecated since version 3.1 and will be removed in 4.0. Use AnonymousRequestToken or AuthenticatedAnonymousToken instead.', E_USER_DEPRECATED);
        }

        parent::__construct($roles);

        $this->secret = $secret;
        $this->setUser($user);
        $this->setAuthenticated(true);
    }

    /**
     * {@inheritdoc}
     */
    public function getCredentials()
    {
        return '';
    }

    /**
     * Returns the secret.
     *
     * @return string
     */
    public function getSecret()
    {
        return $this->secret;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize(array($this->secret, parent::serialize()));
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        list($this->secret, $parentStr) = unserialize($serialized);
        parent::unserialize($parentStr);
    }
}
