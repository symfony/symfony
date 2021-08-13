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
    private $providers;

    /**
     * @param iterable|UserProviderInterface[] $providers
     */
    public function __construct(iterable $providers)
    {
        $this->providers = $providers;
    }

    /**
     * @return array
     */
    public function getProviders()
    {
        if ($this->providers instanceof \Traversable) {
            return iterator_to_array($this->providers);
        }

        return $this->providers;
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByUsername(string $username)
    {
        trigger_deprecation('symfony/security-core', '5.3', 'Method "%s()" is deprecated, use loadUserByIdentifier() instead.', __METHOD__);

        return $this->loadUserByIdentifier($username);
    }

    public function loadUserByIdentifier(string $userIdentifier): UserInterface
    {
        foreach ($this->providers as $provider) {
            try {
                // @deprecated since Symfony 5.3, change to $provider->loadUserByIdentifier() in 6.0
                if (!method_exists($provider, 'loadUserByIdentifier')) {
                    trigger_deprecation('symfony/security-core', '5.3', 'Not implementing method "loadUserByIdentifier()" in user provider "%s" is deprecated. This method will replace "loadUserByUsername()" in Symfony 6.0.', get_debug_type($provider));

                    return $provider->loadUserByUsername($userIdentifier);
                }

                return $provider->loadUserByIdentifier($userIdentifier);
            } catch (UserNotFoundException $e) {
                // try next one
            }
        }

        $ex = new UserNotFoundException(sprintf('There is no user with identifier "%s".', $userIdentifier));
        $ex->setUserIdentifier($userIdentifier);
        throw $ex;
    }

    /**
     * {@inheritdoc}
     */
    public function refreshUser(UserInterface $user)
    {
        $supportedUserFound = false;

        foreach ($this->providers as $provider) {
            try {
                if (!$provider->supportsClass(get_debug_type($user))) {
                    continue;
                }

                return $provider->refreshUser($user);
            } catch (UnsupportedUserException $e) {
                // try next one
            } catch (UserNotFoundException $e) {
                $supportedUserFound = true;
                // try next one
            }
        }

        if ($supportedUserFound) {
            // @deprecated since Symfony 5.3, change to $user->getUserIdentifier() in 6.0
            $username = method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : $user->getUsername();
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
    public function supportsClass(string $class)
    {
        foreach ($this->providers as $provider) {
            if ($provider->supportsClass($class)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param PasswordAuthenticatedUserInterface $user
     *
     * {@inheritdoc}
     */
    public function upgradePassword($user, string $newHashedPassword): void
    {
        if (!$user instanceof PasswordAuthenticatedUserInterface) {
            trigger_deprecation('symfony/security-core', '5.3', 'The "%s::upgradePassword()" method expects an instance of "%s" as first argument, the "%s" class should implement it.', PasswordUpgraderInterface::class, PasswordAuthenticatedUserInterface::class, get_debug_type($user));

            if (!$user instanceof UserInterface) {
                throw new \TypeError(sprintf('The "%s::upgradePassword()" method expects an instance of "%s" as first argument, "%s" given.', PasswordAuthenticatedUserInterface::class, get_debug_type($user)));
            }
        }

        foreach ($this->providers as $provider) {
            if ($provider instanceof PasswordUpgraderInterface) {
                try {
                    $provider->upgradePassword($user, $newHashedPassword);
                } catch (UnsupportedUserException $e) {
                    // ignore: password upgrades are opportunistic
                }
            }
        }
    }
}
