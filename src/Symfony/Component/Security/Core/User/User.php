<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\User;

/**
 * User is the user implementation used by the in-memory user provider.
 *
 * This should not be used for anything else.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class User implements AdvancedAccountInterface
{
    protected $username;
    protected $password;
    protected $accountNonExpired;
    protected $credentialsNonExpired;
    protected $accountNonLocked;
    protected $roles;

    public function __construct($username, $password, array $roles = array(), $enabled = true, $accountNonExpired = true, $credentialsNonExpired = true, $accountNonLocked = true)
    {
        if (empty($username)) {
            throw new \InvalidArgumentException('The username cannot be empty.');
        }

        $this->username = $username;
        $this->password = $password;
        $this->enabled = $enabled;
        $this->accountNonExpired = $accountNonExpired;
        $this->credentialsNonExpired = $credentialsNonExpired;
        $this->accountNonLocked = $accountNonLocked;
        $this->roles = $roles;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return $this->username;
    }

    /**
     * {@inheritdoc}
     */
    public function getRoles()
    {
        return $this->roles;
    }

    /**
     * {@inheritdoc}
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * {@inheritdoc}
     */
    public function getSalt()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * {@inheritdoc}
     */
    public function isAccountNonExpired()
    {
        return $this->accountNonExpired;
    }

    /**
     * {@inheritdoc}
     */
    public function isAccountNonLocked()
    {
        return $this->accountNonLocked;
    }

    /**
     * {@inheritdoc}
     */
    public function isCredentialsNonExpired()
    {
        return $this->credentialsNonExpired;
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * {@inheritdoc}
     */
    public function eraseCredentials()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function equals(AccountInterface $account)
    {
        if (!$account instanceof User) {
            return false;
        }

        return $this->toArray() === $account->toArray();
    }

    /**
     * Returns all account properties as a single array.
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            'username'              => $this->username,
            'password'              => $this->password,
            'salt'                  => $this->getSalt(),
            'accountNonExpired'     => $this->accountNonExpired,
            'accountNonLocked'      => $this->accountNonLocked,
            'credentialsNonExpired' => $this->credentialsNonExpired,
            'enabled'               => $this->enabled
        );
    }
}
