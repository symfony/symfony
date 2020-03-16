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
        foreach ($this->providers as $provider) {
            try {
                return $provider->loadUserByUsername($username);
            } catch (UsernameNotFoundException $e) {
                // try next one
            }
        }

        $ex = new UsernameNotFoundException(sprintf('There is no user with name "%s".', $username));
        $ex->setUsername($username);
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
                if (!$provider->supportsClass(\get_class($user))) {
                    continue;
                }

                return $provider->refreshUser($user);
            } catch (UnsupportedUserException $e) {
                // try next one
            } catch (UsernameNotFoundException $e) {
                $supportedUserFound = true;
                // try next one
            }
        }

        if ($supportedUserFound) {
            $e = new UsernameNotFoundException(sprintf('There is no user with name "%s".', $user->getUsername()));
            $e->setUsername($user->getUsername());
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
     * {@inheritdoc}
     */
    public function upgradePassword(UserInterface $user, string $newEncodedPassword): void
    {
        foreach ($this->providers as $provider) {
            if ($provider instanceof PasswordUpgraderInterface) {
                try {
                    $provider->upgradePassword($user, $newEncodedPassword);
                } catch (UnsupportedUserException $e) {
                    // ignore: password upgrades are opportunistic
                }
            }
        }
    }
}
