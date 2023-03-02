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
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver;
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

class AuthenticatedVoterTest extends TestCase
{
    /**
     * @dataProvider getVoteTests
     */
    public function testVote($authenticated, $attributes, $expected)
    {
        $voter = new AuthenticatedVoter(new AuthenticationTrustResolver());

        $this->assertSame($expected, $voter->vote($this->getToken($authenticated), null, $attributes));
    }

    public static function getVoteTests()
    {
        return [
            ['fully', [], VoterInterface::ACCESS_ABSTAIN],
            ['fully', ['FOO'], VoterInterface::ACCESS_ABSTAIN],
            ['remembered', [], VoterInterface::ACCESS_ABSTAIN],
            ['remembered', ['FOO'], VoterInterface::ACCESS_ABSTAIN],

            ['fully', ['IS_AUTHENTICATED_REMEMBERED'], VoterInterface::ACCESS_GRANTED],
            ['remembered', ['IS_AUTHENTICATED_REMEMBERED'], VoterInterface::ACCESS_GRANTED],

            ['fully', ['IS_AUTHENTICATED_FULLY'], VoterInterface::ACCESS_GRANTED],
            ['remembered', ['IS_AUTHENTICATED_FULLY'], VoterInterface::ACCESS_DENIED],

            ['fully', ['IS_IMPERSONATOR'], VoterInterface::ACCESS_DENIED],
            ['remembered', ['IS_IMPERSONATOR'], VoterInterface::ACCESS_DENIED],
            ['impersonated', ['IS_IMPERSONATOR'], VoterInterface::ACCESS_GRANTED],
        ];
    }

    /**
     * @dataProvider provideAttributes
     */
    public function testSupportsAttribute(string $attribute, bool $expected)
    {
        $voter = new AuthenticatedVoter(new AuthenticationTrustResolver());

        $this->assertSame($expected, $voter->supportsAttribute($attribute));
    }

    public static function provideAttributes()
    {
        yield [AuthenticatedVoter::IS_AUTHENTICATED_FULLY, true];
        yield [AuthenticatedVoter::IS_AUTHENTICATED_REMEMBERED, true];
        yield [AuthenticatedVoter::IS_AUTHENTICATED, true];
        yield [AuthenticatedVoter::IS_IMPERSONATOR, true];
        yield [AuthenticatedVoter::IS_REMEMBERED, true];
        yield [AuthenticatedVoter::PUBLIC_ACCESS, true];

        yield ['', false];
        yield ['foo', false];
    }

    public function testSupportsType()
    {
        $voter = new AuthenticatedVoter(new AuthenticationTrustResolver());

        $this->assertTrue($voter->supportsType(get_debug_type('foo')));
        $this->assertTrue($voter->supportsType(get_debug_type(null)));
        $this->assertTrue($voter->supportsType(get_debug_type(new \stdClass())));
    }

    protected function getToken($authenticated)
    {
        $user = new InMemoryUser('wouter', '', ['ROLE_USER']);

        if ('fully' === $authenticated) {
            $token = new class() extends AbstractToken {
                public function getCredentials()
                {
                }
            };
            $token->setUser($user);

            return $token;
        }

        if ('remembered' === $authenticated) {
            return new RememberMeToken($user, 'foo', 'bar');
        }

        if ('impersonated' === $authenticated) {
            return $this->getMockBuilder(SwitchUserToken::class)->disableOriginalConstructor()->getMock();
        }

        return new NullToken();
    }
}
