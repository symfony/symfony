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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\Authorization\Voter\RoleVoter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Role\Role;

class RoleVoterTest extends TestCase
{
    /**
     * @group legacy
     * @dataProvider getVoteTests
     */
    public function testVote($roles, $attributes, $expected)
    {
        $voter = new RoleVoter();

        $this->assertSame($expected, $voter->vote($this->getToken($roles), null, $attributes));
    }

    /**
     * @dataProvider getVoteTests
     */
    public function testVoteUsingTokenThatReturnsRoleNames($roles, $attributes, $expected)
    {
        $voter = new RoleVoter();

        $this->assertSame($expected, $voter->vote($this->getTokenWithRoleNames($roles), null, $attributes));
    }

    public function getVoteTests()
    {
        return [
            [[], [], VoterInterface::ACCESS_ABSTAIN],
            [[], ['FOO'], VoterInterface::ACCESS_ABSTAIN],
            [[], ['ROLE_FOO'], VoterInterface::ACCESS_DENIED],
            [['ROLE_FOO'], ['ROLE_FOO'], VoterInterface::ACCESS_GRANTED],
            [['ROLE_FOO'], ['FOO', 'ROLE_FOO'], VoterInterface::ACCESS_GRANTED],
            [['ROLE_BAR', 'ROLE_FOO'], ['ROLE_FOO'], VoterInterface::ACCESS_GRANTED],

            // Test mixed Types
            [[], [[]], VoterInterface::ACCESS_ABSTAIN],
            [[], [new \stdClass()], VoterInterface::ACCESS_ABSTAIN],
        ];
    }

    /**
     * @group legacy
     * @dataProvider getLegacyVoteOnRoleObjectsTests
     */
    public function testVoteOnRoleObjects($roles, $attributes, $expected)
    {
        $voter = new RoleVoter();

        $this->assertSame($expected, $voter->vote($this->getToken($roles), null, $attributes));
    }

    public function getLegacyVoteOnRoleObjectsTests()
    {
        return [
            [['ROLE_BAR'], [new Role('ROLE_BAR')], VoterInterface::ACCESS_GRANTED],
            [['ROLE_BAR'], [new Role('ROLE_FOO')], VoterInterface::ACCESS_DENIED],
        ];
    }

    protected function getToken(array $roles)
    {
        foreach ($roles as $i => $role) {
            $roles[$i] = new Role($role);
        }
        $token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();
        $token->expects($this->once())
              ->method('getRoles')
              ->willReturn($roles);

        return $token;
    }

    protected function getTokenWithRoleNames(array $roles)
    {
        $token = $this->getMockBuilder(AbstractToken::class)->getMock();
        $token->expects($this->once())
              ->method('getRoleNames')
              ->willReturn($roles);

        return $token;
    }
}
