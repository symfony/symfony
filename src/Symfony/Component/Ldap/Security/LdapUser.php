<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap\Security;

use Symfony\Component\Ldap\Entry;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @author Robin Chalas <robin.chalas@gmail.com>
 *
 * @final
 */
class LdapUser implements UserInterface
{
    private $entry;
    private $username;
    private $password;
    private $roles;
    private $extraFields;

    public function __construct(Entry $entry, string $username, ?string $password, array $roles = [], array $extraFields = [])
    {
        if (!$username) {
            throw new \InvalidArgumentException('The username cannot be empty.');
        }

        $this->entry = $entry;
        $this->username = $username;
        $this->password = $password;
        $this->roles = $roles;
        $this->extraFields = $extraFields;
    }

    public function getEntry(): Entry
    {
        return $this->entry;
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
    public function eraseCredentials()
    {
        $this->password = null;
    }

    public function getExtraFields(): array
    {
        return $this->extraFields;
    }
}
