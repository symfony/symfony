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
use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

class RoleHierarchyTest extends TestCase
{
    /**
     * @group legacy
     */
    public function testGetReachableRoles()
    {
        $role = new RoleHierarchy([
            'ROLE_ADMIN' => ['ROLE_USER'],
            'ROLE_SUPER_ADMIN' => ['ROLE_ADMIN', 'ROLE_FOO'],
        ]);

        $this->assertEquals([new Role('ROLE_USER')], $role->getReachableRoles([new Role('ROLE_USER')]));
        $this->assertEquals([new Role('ROLE_FOO')], $role->getReachableRoles([new Role('ROLE_FOO')]));
        $this->assertEquals([new Role('ROLE_ADMIN'), new Role('ROLE_USER')], $role->getReachableRoles([new Role('ROLE_ADMIN')]));
        $this->assertEquals([new Role('ROLE_FOO'), new Role('ROLE_ADMIN'), new Role('ROLE_USER')], $role->getReachableRoles([new Role('ROLE_FOO'), new Role('ROLE_ADMIN')]));
        $this->assertEquals([new Role('ROLE_SUPER_ADMIN'), new Role('ROLE_ADMIN'), new Role('ROLE_FOO'), new Role('ROLE_USER')], $role->getReachableRoles([new Role('ROLE_SUPER_ADMIN')]));
    }

    public function testGetReachableRoleNames()
    {
        $role = new RoleHierarchy([
            'ROLE_ADMIN' => ['ROLE_USER'],
            'ROLE_SUPER_ADMIN' => ['ROLE_ADMIN', 'ROLE_FOO'],
        ]);

        $this->assertEquals(['ROLE_USER'], $role->getReachableRoleNames(['ROLE_USER']));
        $this->assertEquals(['ROLE_FOO'], $role->getReachableRoleNames(['ROLE_FOO']));
        $this->assertEquals(['ROLE_ADMIN', 'ROLE_USER'], $role->getReachableRoleNames(['ROLE_ADMIN']));
        $this->assertEquals(['ROLE_FOO', 'ROLE_ADMIN', 'ROLE_USER'], $role->getReachableRoleNames(['ROLE_FOO', 'ROLE_ADMIN']));
        $this->assertEquals(['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_FOO', 'ROLE_USER'], $role->getReachableRoleNames(['ROLE_SUPER_ADMIN']));
    }
}
