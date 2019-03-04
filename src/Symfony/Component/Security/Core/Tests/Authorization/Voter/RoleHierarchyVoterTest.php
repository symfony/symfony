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
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

class RoleHierarchyVoterTest extends RoleVoterTest
{
    /**
     * @group legacy
     * @dataProvider getVoteTests
     */
    public function testVote($roles, $attributes, $expected)
    {
        $voter = new RoleHierarchyVoter(new RoleHierarchy(['ROLE_FOO' => ['ROLE_FOOBAR']]));

        $this->assertSame($expected, $voter->vote($this->getToken($roles), null, $attributes));
    }

    /**
     * @dataProvider getVoteTests
     */
    public function testVoteUsingTokenThatReturnsRoleNames($roles, $attributes, $expected)
    {
        $voter = new RoleHierarchyVoter(new RoleHierarchy(['ROLE_FOO' => ['ROLE_FOOBAR']]));

        $this->assertSame($expected, $voter->vote($this->getTokenWithRoleNames($roles), null, $attributes));
    }

    public function getVoteTests()
    {
        return array_merge(parent::getVoteTests(), [
            [['ROLE_FOO'], ['ROLE_FOOBAR'], VoterInterface::ACCESS_GRANTED],
        ]);
    }

    /**
     * @group legacy
     * @dataProvider getLegacyVoteOnRoleObjectsTests
     */
    public function testVoteOnRoleObjects($roles, $attributes, $expected)
    {
        $voter = new RoleHierarchyVoter(new RoleHierarchy(['ROLE_FOO' => ['ROLE_FOOBAR']]));

        $this->assertSame($expected, $voter->vote($this->getToken($roles), null, $attributes));
    }

    /**
     * @group legacy
     * @dataProvider getVoteWithEmptyHierarchyTests
     */
    public function testVoteWithEmptyHierarchy($roles, $attributes, $expected)
    {
        $voter = new RoleHierarchyVoter(new RoleHierarchy([]));

        $this->assertSame($expected, $voter->vote($this->getToken($roles), null, $attributes));
    }

    /**
     * @dataProvider getVoteWithEmptyHierarchyTests
     */
    public function testVoteWithEmptyHierarchyUsingTokenThatReturnsRoleNames($roles, $attributes, $expected)
    {
        $voter = new RoleHierarchyVoter(new RoleHierarchy([]));

        $this->assertSame($expected, $voter->vote($this->getTokenWithRoleNames($roles), null, $attributes));
    }

    public function getVoteWithEmptyHierarchyTests()
    {
        return parent::getVoteTests();
    }
}
