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
 */
class AnonymousToken extends AbstractToken
{
    private $secret;

    /**
     * @param string        $secret A secret used to make sure the token is created by the app and not by a malicious client
     * @param string|object $user   The user can be a UserInterface instance, or an object implementing a __toString method or the username as a regular string
     * @param Role[]        $roles  An array of roles
     */
    public function __construct(string $secret, $user, array $roles = [])
    {
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
        $serialized = [$this->secret, parent::serialize(true)];

        return $this->doSerialize($serialized, \func_num_args() ? \func_get_arg(0) : null);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        list($this->secret, $parentStr) = \is_array($serialized) ? $serialized : unserialize($serialized);
        parent::unserialize($parentStr);
    }
}
