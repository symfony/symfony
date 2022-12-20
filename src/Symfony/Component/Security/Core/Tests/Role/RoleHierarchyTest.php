<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Role;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

class RoleHierarchyTest extends TestCase
{
    public function testGetReachableRoleNames()
    {
        $role = new RoleHierarchy([
            'ROLE_ADMIN' => ['ROLE_USER'],
            'ROLE_SUPER_ADMIN' => ['ROLE_ADMIN', 'ROLE_FOO'],
        ]);

        self::assertEquals(['ROLE_USER'], $role->getReachableRoleNames(['ROLE_USER']));
        self::assertEquals(['ROLE_FOO'], $role->getReachableRoleNames(['ROLE_FOO']));
        self::assertEquals(['ROLE_ADMIN', 'ROLE_USER'], $role->getReachableRoleNames(['ROLE_ADMIN']));
        self::assertEquals(['ROLE_FOO', 'ROLE_ADMIN', 'ROLE_USER'], $role->getReachableRoleNames(['ROLE_FOO', 'ROLE_ADMIN']));
        self::assertEquals(['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_FOO', 'ROLE_USER'], $role->getReachableRoleNames(['ROLE_SUPER_ADMIN']));
        self::assertEquals(['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_FOO', 'ROLE_USER'], $role->getReachableRoleNames(['ROLE_SUPER_ADMIN', 'ROLE_SUPER_ADMIN']));
    }
}
