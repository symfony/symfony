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
 * Chain User Provider.
 *
 * This provider calls several leaf providers in a chain until one is able to
 * handle the request.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ChainUserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    private iterable $providers;

    /**
     * @param iterable<array-key, UserProviderInterface> $providers
     */
    public function __construct(iterable $providers)
    {
        $this->providers = $providers;
    }

    /**
     * @return UserProviderInterface[]
     */
    public function getProviders(): array
    {
        if ($this->providers instanceof \Traversable) {
            return iterator_to_array($this->providers);
        }

        return $this->providers;
    }

    /**
     * @internal for compatibility with Symfony 5.4
     */
    public function loadUserByUsername(string $username): UserInterface
    {
        return $this->loadUserByIdentifier($username);
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        foreach ($this->providers as $provider) {
            try {
                return $provider->loadUserByIdentifier($identifier);
            } catch (UserNotFoundException) {
                // try next one
            }
        }

        $ex = new UserNotFoundException(sprintf('There is no user with identifier "%s".', $identifier));
        $ex->setUserIdentifier($identifier);
        throw $ex;
    }

    /**
     * {@inheritdoc}
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        $supportedUserFound = false;

        foreach ($this->providers as $provider) {
            try {
                if (!$provider->supportsClass(get_debug_type($user))) {
                    continue;
                }

                return $provider->refreshUser($user);
            } catch (UnsupportedUserException) {
                // try next one
            } catch (UserNotFoundException) {
                $supportedUserFound = true;
                // try next one
            }
        }

        if ($supportedUserFound) {
            $username = $user->getUserIdentifier();
            $e = new UserNotFoundException(sprintf('There is no user with name "%s".', $username));
            $e->setUserIdentifier($username);
            throw $e;
        } else {
            throw new UnsupportedUserException(sprintf('There is no user provider for user "%s". Shouldn\'t the "supportsClass()" method of your user provider return true for this classname?', get_debug_type($user)));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supportsClass(string $class): bool
    {
        foreach ($this->providers as $provider) {
            if ($provider->supportsClass($class)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        foreach ($this->providers as $provider) {
            if ($provider instanceof PasswordUpgraderInterface) {
                try {
                    $provider->upgradePassword($user, $newHashedPassword);
                } catch (UnsupportedUserException) {
                    // ignore: password upgrades are opportunistic
                }
            }
        }
    }
}
