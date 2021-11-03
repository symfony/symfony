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
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

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
    /**
     * @var array<string, UserInterface>
     */
    private $users;

    /**
     * The user array is a hash where the keys are usernames and the values are
     * an array of attributes: 'password', 'enabled', and 'roles'.
     *
     * @param array<string, array{password?: string, enabled?: bool, roles?: list<string>}> $users An array of users
     */
    public function __construct(array $users = [])
    {
        foreach ($users as $username => $attributes) {
            $password = $attributes['password'] ?? null;
            $enabled = $attributes['enabled'] ?? true;
            $roles = $attributes['roles'] ?? [];
            $user = new InMemoryUser($username, $password, $roles, $enabled);

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
        // @deprecated since Symfony 5.3, change to $user->getUserIdentifier() in 6.0
        $userIdentifier = strtolower(method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : $user->getUsername());
        if (isset($this->users[$userIdentifier])) {
            throw new \LogicException('Another user with the same username already exists.');
        }

        $this->users[$userIdentifier] = $user;
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByUsername(string $username)
    {
        trigger_deprecation('symfony/security-core', '5.3', 'Method "%s()" is deprecated, use loadUserByIdentifier() instead.', __METHOD__);

        return $this->loadUserByIdentifier($username);
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->getUser($identifier);

        // @deprecated since Symfony 5.3, change to $user->getUserIdentifier() in 6.0
        return new InMemoryUser(method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : $user->getUsername(), $user->getPassword(), $user->getRoles(), $user->isEnabled());
    }

    /**
     * {@inheritdoc}
     */
    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof InMemoryUser && !$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_debug_type($user)));
        }

        // @deprecated since Symfony 5.3, change to $user->getUserIdentifier() in 6.0
        $storedUser = $this->getUser(method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : $user->getUsername());
        $userIdentifier = method_exists($storedUser, 'getUserIdentifier') ? $storedUser->getUserIdentifier() : $storedUser->getUsername();

        // @deprecated since Symfony 5.3
        if (User::class === \get_class($user)) {
            if (User::class !== \get_class($storedUser)) {
                $accountNonExpired = true;
                $credentialsNonExpired = $storedUser->getPassword() === $user->getPassword();
                $accountNonLocked = true;
            } else {
                $accountNonExpired = $storedUser->isAccountNonExpired();
                $credentialsNonExpired = $storedUser->isCredentialsNonExpired() && $storedUser->getPassword() === $user->getPassword();
                $accountNonLocked = $storedUser->isAccountNonLocked();
            }

            return new User($userIdentifier, $storedUser->getPassword(), $storedUser->getRoles(), $storedUser->isEnabled(), $accountNonExpired, $credentialsNonExpired, $accountNonLocked);
        }

        return new InMemoryUser($userIdentifier, $storedUser->getPassword(), $storedUser->getRoles(), $storedUser->isEnabled());
    }

    /**
     * {@inheritdoc}
     */
    public function supportsClass(string $class)
    {
        // @deprecated since Symfony 5.3
        if (User::class === $class) {
            return true;
        }

        return InMemoryUser::class == $class;
    }

    /**
     * Returns the user by given username.
     *
     * @throws UserNotFoundException if user whose given username does not exist
     */
    private function getUser(string $username)/*: InMemoryUser */
    {
        if (!isset($this->users[strtolower($username)])) {
            $ex = new UserNotFoundException(sprintf('Username "%s" does not exist.', $username));
            $ex->setUserIdentifier($username);

            throw $ex;
        }

        return $this->users[strtolower($username)];
    }
}
