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

/**
 * OauthUserProvider is a provider built on top of the Oauth component.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class OauthUserProvider implements UserProviderInterface
{
    private const USER_ROLES = ['ROLE_USER', 'ROLE_OAUTH_USER'];

    public function loadUserByUsername($username)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
        }

        return new User($user->getUsername(), null, $user->getRoles());
    }

    /**
     * {@inheritdoc}
     */
    public function supportsClass($class)
    {
        return 'Symfony\Component\Security\Core\User\User' === $class;
    }
}
