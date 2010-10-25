<?php

namespace Symfony\Component\Security\Authentication\Token;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * AnonymousToken represents an anonymous token.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class AnonymousToken extends Token
{
    protected $user;
    protected $key;

    /**
     * Constructor.
     *
     * @param string $key   The key shared with the authentication provider
     * @param string $user  The user
     * @param Role[] $roles An array of roles
     */
    public function __construct($key, $user, array $roles = array())
    {
        parent::__construct($roles);

        $this->key = $key;
        $this->user = $user;

        parent::setAuthenticated(true);
    }

    /**
     * {@inheritdoc}
     */
    public function getCredentials()
    {
        return '';
    }

    /**
     * Returns the key.
     *
     * @return string The Key
     */
    public function getKey()
    {
        return $this->key;
    }
}
