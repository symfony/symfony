<?php

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
class ChainUserProvider implements UserProviderInterface
{
    private $providers;

    public function __construct(array $providers)
    {
        $this->providers = $providers;
    }

    /**
     * {@inheritDoc}
     */
    public function loadUserByUsername($username)
    {
        foreach ($this->providers as $provider) {
            try {
                return $provider->loadUserByUsername($username);
            } catch (UsernameNotFoundException $notFound) {
                // try next one
            }
        }

        throw new UsernameNotFoundException(sprintf('There is no user with name "%s".', $username));
    }

    /**
     * {@inheritDoc}
     */
    public function loadUser(UserInterface $user)
    {
        foreach ($this->providers as $provider) {
            try {
                return $provider->loadUser($user);
            } catch (UnsupportedUserException $unsupported) {
                // try next one
            }
        }

        throw new UnsupportedUserException(sprintf('The account "%s" is not supported.', get_class($user)));
    }

    /**
     * {@inheritDoc}
     */
    public function supportsClass($class)
    {
        foreach ($this->providers as $provider) {
            if ($provider->supportsClass($class)) {
                return true;
            }
        }

        return false;
    }
}