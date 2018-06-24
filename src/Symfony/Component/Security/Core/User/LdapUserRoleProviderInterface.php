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

interface LdapUserRoleProviderInterface
{
    /**
     * @return string[] The user roles
     */
    public function getRoles(Entry $userEntry);
}
