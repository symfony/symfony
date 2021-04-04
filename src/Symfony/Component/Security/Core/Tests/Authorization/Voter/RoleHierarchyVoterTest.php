<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authorization\Voter;

use Symfony\Component\Security\Core\Authorization\Voter\RoleHierarchyVoter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

class RoleHierarchyVoterTest extends RoleVoterTest
{
    /**
     * @dataProvider getVoteTests
     */
    public function testVoteUsingTokenThatReturnsRoleNames($roles, $attributes, $expected)
    {
        $voter = new RoleHierarchyVoter(new RoleHierarchy(['ROLE_FOO' => ['ROLE_FOOBAR']]));

        $this->assertEquals($expected->getAccess(), $voter->vote($this->getTokenWithRoleNames($roles), null, $attributes)->getAccess());
    }

    public function getVoteTests()
    {
        return array_merge(parent::getVoteTests(), [
            [['ROLE_FOO'], ['ROLE_FOOBAR'], Vote::createGranted()],
        ]);
    }

    /**
     * @dataProvider getVoteWithEmptyHierarchyTests
     */
    public function testVoteWithEmptyHierarchyUsingTokenThatReturnsRoleNames($roles, $attributes, $expected)
    {
        $voter = new RoleHierarchyVoter(new RoleHierarchy([]));

        $this->assertSame($expected->getAccess(), $voter->vote($this->getTokenWithRoleNames($roles), null, $attributes)->getAccess());
    }

    public function getVoteWithEmptyHierarchyTests()
    {
        return parent::getVoteTests();
    }
}
