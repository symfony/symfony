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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\Authorization\Voter\RoleVoter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class RoleVoterTest extends TestCase
{
    use ExpectDeprecationTrait;

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
     */
    public function testDeprecatedRolePreviousAdmin()
    {
        $this->expectDeprecation('Since symfony/security-core 5.1: The ROLE_PREVIOUS_ADMIN role is deprecated and will be removed in version 6.0, use the IS_IMPERSONATOR attribute instead.');
        $voter = new RoleVoter();

        $voter->vote($this->getTokenWithRoleNames(['ROLE_USER', 'ROLE_PREVIOUS_ADMIN']), null, ['ROLE_PREVIOUS_ADMIN']);
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
