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

use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Security\LdapUserProvider as BaseLdapUserProvider;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;

@trigger_error(sprintf('The "%s" class is deprecated since Symfony 4.4, use "%s" instead.', LdapUserProvider::class, BaseLdapUserProvider::class), E_USER_DEPRECATED);

/**
 * LdapUserProvider is a simple user provider on top of ldap.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Charles Sarrazin <charles@sarraz.in>
 *
 * @deprecated since Symfony 4.4, use "Symfony\Component\Ldap\Security\LdapUserProvider" instead
 */
class LdapUserProvider extends BaseLdapUserProvider
{
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

    /**
     * Loads a user from an LDAP entry.
     *
     * @return User
     */
    protected function loadUser($username, Entry $entry)
    {
        $ldapUser = parent::loadUser($username, $entry);

        return new User($ldapUser->getUsername(), $ldapUser->getPassword(), $ldapUser->getRoles(), true, true, true, true, $ldapUser->getExtraFields());
    }
}
