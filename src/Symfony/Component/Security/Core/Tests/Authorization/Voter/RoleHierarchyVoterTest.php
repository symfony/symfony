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
        $voter = new RoleHierarchyVoter(new RoleHierarchy([
            'ROLE_FOO' => ['ROLE_FOOBAR'],
            'ROLE_FOO_*' => ['ROLE_BAR_A', 'ROLE_FOO'],
            'ROLE_BAR_*' => ['ROLE_BAZ'],
        ]));

        $this->assertSame($expected, $voter->vote($this->getTokenWithRoleNames($roles), null, $attributes));
    }

    public static function getVoteTests()
    {
        return array_merge(parent::getVoteTests(), [
            [['ROLE_FOO'], ['ROLE_FOOBAR'], VoterInterface::ACCESS_GRANTED],
            [['ROLE_FOO_A'], ['ROLE_BAR_A'], VoterInterface::ACCESS_GRANTED], // ROLE_FOO_A => ROLE_FOO_* => ROLE_BAR_A
            [['ROLE_FOO_A'], ['ROLE_FOOBAR'], VoterInterface::ACCESS_GRANTED], // ROLE_FOO_A => ROLE_FOO_* => ROLE_FOO => ROLE_FOOBAR
            [['ROLE_FOO_A'], ['ROLE_BAZ'], VoterInterface::ACCESS_GRANTED], // ROLE_FOO_A => ROLE_FOO_* => ROLE_BAR_A => ROLE_BAR_* => ROLE_BAZ
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

    public static function getVoteWithEmptyHierarchyTests()
    {
        return parent::getVoteTests();
    }
}
