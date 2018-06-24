<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\LdapBundle;

use Symfony\Component\Ldap\Entry;
use Symfony\Component\Security\Core\User\LdapUserRoleProviderInterface;

class LdapUserRoleProvider implements LdapUserRoleProviderInterface
{
    public function getRoles(Entry $userEntry)
    {
        return array('ROLE_GROUP');
    }
}
