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
 * AnonymousToken represents an anonymous token.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @since v2.0.0
 */
class AnonymousToken extends AbstractToken
{
    private $key;

    /**
     * Constructor.
     *
     * @param string          $key   The key shared with the authentication provider
     * @param string          $user  The user
     * @param RoleInterface[] $roles An array of roles
     *
     * @since v2.0.0
     */
    public function __construct($key, $user, array $roles = array())
    {
        parent::__construct($roles);

        $this->key = $key;
        $this->setUser($user);
        $this->setAuthenticated(true);
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.0.0
     */
    public function getCredentials()
    {
        return '';
    }

    /**
     * Returns the key.
     *
     * @return string The Key
     *
     * @since v2.0.0
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.0.0
     */
    public function serialize()
    {
        return serialize(array($this->key, parent::serialize()));
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.2.0
     */
    public function unserialize($serialized)
    {
        list($this->key, $parentStr) = unserialize($serialized);
        parent::unserialize($parentStr);
    }
}
