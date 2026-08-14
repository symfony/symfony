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

        $this->assertEqualsCanonicalizing(['ROLE_USER'], $role->getReachableRoleNames(['ROLE_USER']));
        $this->assertEqualsCanonicalizing(['ROLE_FOO'], $role->getReachableRoleNames(['ROLE_FOO']));
        $this->assertEqualsCanonicalizing(['ROLE_ADMIN', 'ROLE_USER'], $role->getReachableRoleNames(['ROLE_ADMIN']));
        $this->assertEqualsCanonicalizing(['ROLE_FOO', 'ROLE_ADMIN', 'ROLE_USER'], $role->getReachableRoleNames(['ROLE_FOO', 'ROLE_ADMIN']));
        $this->assertEqualsCanonicalizing(['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_FOO', 'ROLE_USER'], $role->getReachableRoleNames(['ROLE_SUPER_ADMIN']));
        $this->assertEqualsCanonicalizing(['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_FOO', 'ROLE_USER'], $role->getReachableRoleNames(['ROLE_SUPER_ADMIN', 'ROLE_SUPER_ADMIN']));

        $this->assertTrue(array_is_list($role->getReachableRoleNames(['ROLE_SUPER_ADMIN'])));
    }

    public function testGetParentRoleNames()
    {
        $role = new RoleHierarchy([
            'ROLE_ADMIN' => ['ROLE_USER'],
            'ROLE_SUPER_ADMIN' => ['ROLE_ADMIN', 'ROLE_FOO'],
            'ROLE_USER' => ['ROLE_BAR'],
        ]);

        $this->assertEqualsCanonicalizing(['ROLE_SUPER_ADMIN'], $role->getParentRoleNames(['ROLE_SUPER_ADMIN']));
        $this->assertEqualsCanonicalizing(['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'], $role->getParentRoleNames(['ROLE_ADMIN']));
        $this->assertEqualsCanonicalizing(['ROLE_USER', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'], $role->getParentRoleNames(['ROLE_USER']));
        $this->assertEqualsCanonicalizing(['ROLE_BAR', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN', 'ROLE_USER'], $role->getParentRoleNames(['ROLE_BAR']));
        $this->assertEqualsCanonicalizing(['ROLE_SUPER_ADMIN'], $role->getParentRoleNames(['ROLE_SUPER_ADMIN', 'ROLE_SUPER_ADMIN']));
        $this->assertEqualsCanonicalizing(['ROLE_SUPER_ADMIN', 'ROLE_USER', 'ROLE_ADMIN'], $role->getParentRoleNames(['ROLE_SUPER_ADMIN', 'ROLE_USER']));
        $this->assertEqualsCanonicalizing(['ROLE_BAR', 'ROLE_FOO', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN', 'ROLE_USER'], $role->getParentRoleNames(['ROLE_BAR', 'ROLE_FOO']));

        $this->assertTrue(array_is_list($role->getParentRoleNames(['ROLE_BAR', 'ROLE_FOO'])));
    }

    public function testGetReachableRoleNamesWithPlaceholders()
    {
        $role = new RoleHierarchy([
            'ROLE_BAZ_*' => ['ROLE_USER'],
            'ROLE_FOO_*' => ['ROLE_BAZ_FOO'],
            'ROLE_BAR_*' => ['ROLE_BAZ_BAR'],
            'ROLE_QUX_*_BAR' => ['ROLE_FOOBAR'],
        ]);

        $this->assertEquals(['ROLE_BAZ_A', 'ROLE_USER'], $role->getReachableRoleNames(['ROLE_BAZ_A']));
        $this->assertEquals(['ROLE_FOO_A', 'ROLE_BAZ_FOO', 'ROLE_USER'], $role->getReachableRoleNames(['ROLE_FOO_A']));

        // Multiple roles matching the same placeholder
        $this->assertEquals(['ROLE_FOO_A', 'ROLE_FOO_B', 'ROLE_BAZ_FOO', 'ROLE_USER'], $role->getReachableRoleNames(['ROLE_FOO_A', 'ROLE_FOO_B']));

        // Multiple roles matching multiple placeholders
        $this->assertEquals(['ROLE_FOO_A', 'ROLE_BAR_A', 'ROLE_BAZ_FOO', 'ROLE_BAZ_BAR', 'ROLE_USER'], $role->getReachableRoleNames(['ROLE_FOO_A', 'ROLE_BAR_A']));

        // Test placeholders don't match more than the pattern
        $this->assertEquals(['FOO_ROLE_FOO_A'], $role->getReachableRoleNames(['FOO_ROLE_FOO_A'])); // Doesn't start with ROLE_FOO_
        $this->assertEquals(['ROLE_QUX_A_BARA'], $role->getReachableRoleNames(['ROLE_QUX_A_BARA'])); // Doesn't end with _BAR
    }

    public function testGetReachableRoleNamesWithRecursivePlaceholders()
    {
        $role = new RoleHierarchy([
            'ROLE_FOO_*' => ['ROLE_BAR_BAZ'],
            'ROLE_BAR_*' => ['ROLE_FOO_BAZ'],
            'ROLE_QUX_*' => ['ROLE_QUX_BAZ'],
        ]);

        // ROLE_FOO_* expanded once
        $this->assertEquals(['ROLE_FOO_A', 'ROLE_BAR_BAZ', 'ROLE_FOO_BAZ'], $role->getReachableRoleNames(['ROLE_FOO_A']));

        // ROLE_FOO_* expanded once even with multiple ROLE_FOO_* input roles
        $this->assertEquals(['ROLE_FOO_A', 'ROLE_FOO_B', 'ROLE_BAR_BAZ', 'ROLE_FOO_BAZ'], $role->getReachableRoleNames(['ROLE_FOO_A', 'ROLE_FOO_B']));

        // ROLE_BAR_* expanded once with ROLE_FOO_A => ROLE_FOO_* => ROLE_BAR_BAZ => ROLE_BAR_* => ROLE_FOO_BAZ
        $this->assertEquals(['ROLE_FOO_A', 'ROLE_BAR_A', 'ROLE_BAR_BAZ', 'ROLE_FOO_BAZ'], $role->getReachableRoleNames(['ROLE_FOO_A', 'ROLE_BAR_A']));

        // Self matching placeholder
        $this->assertEquals(['ROLE_QUX_A', 'ROLE_QUX_BAZ'], $role->getReachableRoleNames(['ROLE_QUX_A']));
        $this->assertEquals(['ROLE_QUX_BAZ'], $role->getReachableRoleNames(['ROLE_QUX_BAZ']));
    }

    public function testInvalidPlaceholderSyntaxAreNotResolved()
    {
        $role = new RoleHierarchy([
            'ROLE_FOO*' => ['ROLE_FOOBAR'],
            'ROLE_*FOO' => ['ROLE_FOOBAR'],
            'ROLE_FOO_*BAR' => ['ROLE_FOOBAR'],
            'ROLE_FOO*_BAR' => ['ROLE_FOOBAR'],
        ]);

        $this->assertEquals(['ROLE_FOOA'], $role->getReachableRoleNames(['ROLE_FOOA']));
        $this->assertEquals(['ROLE_AFOO'], $role->getReachableRoleNames(['ROLE_AFOO']));
        $this->assertEquals(['ROLE_FOO_ABAR'], $role->getReachableRoleNames(['ROLE_FOO_ABAR']));
        $this->assertEquals(['ROLE_FOOA_BAR'], $role->getReachableRoleNames(['ROLE_FOOA_BAR']));
    }

    public function testPlaceholderMatchingARoleHoldingARegexDelimiter()
    {
        $role = new RoleHierarchy(['ROLE_A/B_*' => ['ROLE_USER']]);

        $this->assertSame(['ROLE_A/B_READ', 'ROLE_USER'], $role->getReachableRoleNames(['ROLE_A/B_READ']));
    }

    public function testGetParentRoleNamesDoesNotReturnPlaceholders()
    {
        $role = new RoleHierarchy([
            'ROLE_FOO_*' => ['ROLE_USER'],
            'ROLE_ADMIN' => ['ROLE_USER'],
        ]);

        $this->assertSame(['ROLE_USER', 'ROLE_ADMIN'], $role->getParentRoleNames(['ROLE_USER']));
    }
}
