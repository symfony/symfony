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
     * @dataProvider getVoteTests
     */
    public function testVoteUsingTokenThatReturnsRoleNames($roles, $attributes, $expected)
    {
        $voter = new RoleHierarchyVoter(new RoleHierarchy(['ROLE_FOO' => ['ROLE_FOOBAR']]));

        $this->assertSame($expected, $voter->vote($this->getTokenWithRoleNames($roles), null, $attributes));
    }

    /**
     * @dataProvider getVoteTests
     */
    public function testGetVoteUsingTokenThatReturnsRoleNames($roles, $attributes, $expected)
    {
        $voter = new RoleHierarchyVoter(new RoleHierarchy(['ROLE_FOO' => ['ROLE_FOOBAR']]));

        $this->assertSame($expected, $voter->getVote($this->getTokenWithRoleNames($roles), null, $attributes)->getAccess());
    }

    public static function getVoteTests()
    {
        return array_merge(parent::getVoteTests(), [
            [['ROLE_FOO'], ['ROLE_FOOBAR'], VoterInterface::ACCESS_GRANTED],
        ]);
    }

    /**
     * @dataProvider getVoteWithEmptyHierarchyTests
     */
    public function testVoteWithEmptyHierarchyUsingTokenThatReturnsRoleNames($roles, $attributes, $expected)
    {
        $voter = new RoleHierarchyVoter(new RoleHierarchy([]));

        $this->assertSame($expected, $voter->vote($this->getTokenWithRoleNames($roles), null, $attributes));
    }

    /**
     * @dataProvider getVoteWithEmptyHierarchyTests
     */
    public function testGetVoteWithEmptyHierarchyUsingTokenThatReturnsRoleNames($roles, $attributes, $expected)
    {
        $voter = new RoleHierarchyVoter(new RoleHierarchy([]));

        $this->assertSame($expected, $voter->getVote($this->getTokenWithRoleNames($roles), null, $attributes)->getAccess());
    }

    public static function getVoteWithEmptyHierarchyTests()
    {
        return parent::getVoteTests();
    }
}
