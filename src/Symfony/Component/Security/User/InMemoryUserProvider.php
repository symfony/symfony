<?php

namespace Symfony\Component\Security\User;

use Symfony\Component\Security\Exception\UsernameNotFoundException;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * InMemoryUserProvider is a simple non persistent user provider.
 *
 * Useful for testing, demonstration, prototyping, and for simple needs
 * (a backend with a unique admin for instance)
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class InMemoryUserProvider implements UserProviderInterface
{
    protected $users;
    protected $name;

    /**
     * Constructor.
     *
     * The user array is a hash where the keys are usernames and the values are
     * an array of attributes: 'password', 'enabled', and 'roles'.
     *
     * @param array $users An array of users
     * @param string $name
     */
    public function __construct($name, array $users = array())
    {
        foreach ($users as $username => $attributes) {
            $password = isset($attributes['password']) ? $attributes['password'] : null;
            $enabled = isset($attributes['enabled']) ? $attributes['enabled'] : true;
            $roles = isset($attributes['roles']) ? $attributes['roles'] : array();
            $user = new User($username, $password, $roles, $enabled, true, true, true);

            $this->createUser($user);
        }

        $this->name = $name;
    }

    /**
     * Adds a new User to the provider.
     *
     * @param AccountInterface $user A AccountInterface instance
     */
    public function createUser(AccountInterface $user)
    {
        if (isset($this->users[strtolower($user->getUsername())])) {
            throw new \LogicException('Another user with the same username already exist.');
        }

        $this->users[strtolower($user->getUsername())] = $user;
    }

    /**
     * {@inheritDoc}
     */
    public function isAggregate()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByUsername($username)
    {
        if (!isset($this->users[strtolower($username)])) {
            throw new UsernameNotFoundException(sprintf('Username "%s" does not exist.', $username));
        }

        $user = $this->users[strtolower($username)];

        return array(new User($user->getUsername(), $user->getPassword(), $user->getRoles(), $user->isEnabled(), $user->isAccountNonExpired(),
                $user->isCredentialsNonExpired(), $user->isAccountNonLocked()), $this->name);
    }

    /**
     * {@inheritDoc}
     */
    public function supports($providerName)
    {
        return $this->name === $providerName;
    }
}
