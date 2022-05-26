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
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver;
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Authorization\Voter\RoleVoter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class RoleVoterTest extends TestCase
{
    use ExpectDeprecationTrait;

    /**
     * @dataProvider getGetVoteTests
     */
    public function testGetVoteUsingTokenThatReturnsRoleNames($roles, $attributes, $expected)
    {
        $voter = new RoleVoter();

        $this->assertEquals($expected, $voter->getVote($this->getTokenWithRoleNames($roles), null, $attributes));
    }

    public function getGetVoteTests()
    {
        return [
            [[], [], Vote::createAbstain()],
            [[], ['FOO'], Vote::createAbstain()],
            [[], ['ROLE_FOO'], Vote::createDenied()],
            [['ROLE_FOO'], ['ROLE_FOO'], Vote::createGranted()],
            [['ROLE_FOO'], ['FOO', 'ROLE_FOO'], Vote::createGranted()],
            [['ROLE_BAR', 'ROLE_FOO'], ['ROLE_FOO'], Vote::createGranted()],

            // Test mixed Types
            [[], [[]], Vote::createAbstain()],
            [[], [new \stdClass()], Vote::createAbstain()],
        ];
    }

    /**
     * @group legacy
     * @dataProvider getVoteTests
     */
    public function testVoteUsingTokenThatReturnsRoleNamesLegacy($roles, $attributes, $expected)
    {
        $voter = new RoleVoter();

        $this->expectDeprecation('Since symfony/security-core 6.2: Method "%s::vote()" has been deprecated, use "%s::getVote()" instead.');
        $this->assertSame($expected, $voter->vote($this->getTokenWithRoleNames($roles), null, $attributes));
    }

    public static function getVoteTests()
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
     * @dataProvider provideAttributes
     */
    public function testSupportsAttribute(string $prefix, string $attribute, bool $expected)
    {
        $voter = new RoleVoter($prefix);

        $this->assertSame($expected, $voter->supportsAttribute($attribute));
    }

    public static function provideAttributes()
    {
        yield ['ROLE_', 'ROLE_foo', true];
        yield ['ROLE_', 'ROLE_', true];
        yield ['FOO_', 'FOO_bar', true];

        yield ['ROLE_', '', false];
        yield ['ROLE_', 'foo', false];
    }

    public function testSupportsType()
    {
        $voter = new AuthenticatedVoter(new AuthenticationTrustResolver());

        $this->assertTrue($voter->supportsType(get_debug_type('foo')));
        $this->assertTrue($voter->supportsType(get_debug_type(null)));
        $this->assertTrue($voter->supportsType(get_debug_type(new \stdClass())));
    }

    protected function getTokenWithRoleNames(array $roles)
    {
        $token = $this->createMock(AbstractToken::class);
        $token->expects($this->once())
              ->method('getRoleNames')
              ->willReturn($roles);

        return $token;
    }
}
