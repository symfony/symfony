<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\JsonLdapLoginBundle\Security\Ldap;

use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Security\RoleFetcherInterface;

class DummyRoleFetcher implements RoleFetcherInterface
{
    public function fetchRoles(Entry $entry): array
    {
        if ($entry->getAttribute('uid') === ['spomky']) {
            return ['ROLE_SUPER_ADMIN', 'ROLE_USER'];
        }

        return ['ROLE_LDAP_USER_42', 'ROLE_USER'];
    }
}
