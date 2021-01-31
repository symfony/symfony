<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\User;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;

/**
 * InMemoryUserProvider is a simple non persistent user provider.
 *
 * Useful for testing, demonstration, prototyping, and for simple needs
 * (a backend with a unique admin for instance)
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class InMemoryUserProvider implements UserProviderInterface
{
    private $users;

    /**
     * The user array is a hash where the keys are usernames and the values are
     * an array of attributes: 'password', 'enabled', and 'roles'.
     *
     * @param array $users An array of users
     */
    public function __construct(array $users = [])
    {
        foreach ($users as $username => $attributes) {
            if (isset($attributes['enabled'])) {
                trigger_deprecation('symfony/security-core', '5.3', 'The "enabled" attribute is deprecated and will be removed in version 6.0. Remove it or implement a custom InMemoryUserProvider.');
            }

            $password = $attributes['password'] ?? null;
            $enabled = $attributes['enabled'] ?? true;
            $roles = $attributes['roles'] ?? [];
            $user = new User($username, $password, $roles, $enabled, true, true, true);

            $this->createUser($user);
        }
    }

    /**
     * Adds a new User to the provider.
     *
     * @throws \LogicException
     */
    public function createUser(UserInterface $user)
    {
        if (isset($this->users[strtolower($user->getUsername())])) {
            throw new \LogicException('Another user with the same username already exists.');
        }

        if ($user instanceof User) {
            trigger_deprecation('symfony/security-core', '5.3', 'The "%s" class is deprecated and will be removed in version 6.0. Use "%s" instead or implement a custom User class.', User::class, InMemoryUser::class);
        }

        $this->users[strtolower($user->getUsername())] = $user;
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByUsername(string $username)
    {
        $user = $this->getUser($username);

        return new User($user->getUsername(), $user->getPassword(), $user->getRoles(), $user->isEnabled(), $user->isAccountNonExpired(), $user->isCredentialsNonExpired(), $user->isAccountNonLocked());
    }

    /**
     * {@inheritdoc}
     */
    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof User && !$user instanceof InMemoryUser) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_debug_type($user)));
        }

        $storedUser = $this->getUser($user->getUsername());

        if ($user instanceof User) {
            trigger_deprecation('symfony/security-core', '5.3', 'The "%s" class is deprecated and will be removed in version 6.0. Use "%s" instead or implement a custom User class.', User::class, InMemoryUser::class);

            return new User($storedUser->getUsername(), $storedUser->getPassword(), $storedUser->getRoles(), $storedUser->isEnabled(), $storedUser->isAccountNonExpired(), $storedUser->isCredentialsNonExpired() && $storedUser->getPassword() === $user->getPassword(), $storedUser->isAccountNonLocked());
        }

        return new InMemoryUser($storedUser->getUsername(), $storedUser->getPassword(), $storedUser->getRoles(), $storedUser->getExtraFields());
    }

    /**
     * {@inheritdoc}
     */
    public function supportsClass(string $class)
    {
        return 'Symfony\Component\Security\Core\User\User' === $class || 'Symfony\Component\Security\Core\User\InMemoryUser' === $class;
    }

    /**
     * Returns the user by given username.
     *
     * @throws UsernameNotFoundException if user whose given username does not exist
     */
    private function getUser(string $username): User
    {
        if (!isset($this->users[strtolower($username)])) {
            $ex = new UsernameNotFoundException(sprintf('Username "%s" does not exist.', $username));
            $ex->setUsername($username);

            throw $ex;
        }

        $user = $this->users[strtolower($username)];

        if ($user instanceof InMemoryUser) {
            return new User($user->getUsername(), $user->getPassword(), $user->getRoles());
        }

        return $user;
    }
}
