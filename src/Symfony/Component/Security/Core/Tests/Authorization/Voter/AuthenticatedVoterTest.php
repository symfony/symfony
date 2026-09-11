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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver;
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\OfflineTokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Exception\InvalidArgumentException;
use Symfony\Component\Security\Core\User\InMemoryUser;

class AuthenticatedVoterTest extends TestCase
{
    #[DataProvider('getVoteTests')]
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

    #[DataProvider('provideAttributes')]
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
        yield [AuthenticatedVoter::IS_AUTHENTICATED_RECENTLY, true];

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

    #[DataProvider('provideOfflineAttributes')]
    public function testOfflineToken($attributes, $expected)
    {
        $voter = new AuthenticatedVoter(new AuthenticationTrustResolver());

        $this->assertSame($expected, $voter->vote($this->getToken('offline'), null, $attributes));
    }

    public static function provideOfflineAttributes()
    {
        yield [[AuthenticatedVoter::PUBLIC_ACCESS], VoterInterface::ACCESS_GRANTED];
        yield [['ROLE_FOO'], VoterInterface::ACCESS_ABSTAIN];
    }

    #[DataProvider('provideUnsupportedOfflineAttributes')]
    public function testUnsupportedOfflineToken(string $attribute)
    {
        $voter = new AuthenticatedVoter(new AuthenticationTrustResolver());

        $this->expectException(InvalidArgumentException::class);

        $voter->vote($this->getToken('offline'), null, [$attribute]);
    }

    public static function provideUnsupportedOfflineAttributes()
    {
        yield [AuthenticatedVoter::IS_AUTHENTICATED_FULLY];
        yield [AuthenticatedVoter::IS_AUTHENTICATED_REMEMBERED];
        yield [AuthenticatedVoter::IS_AUTHENTICATED];
        yield [AuthenticatedVoter::IS_IMPERSONATOR];
        yield [AuthenticatedVoter::IS_REMEMBERED];
        yield [AuthenticatedVoter::IS_AUTHENTICATED_RECENTLY];
    }

    public function testRecentlyAuthenticatedIsGrantedWithinTheLifetime()
    {
        $token = $this->getToken('fully');
        $token->setAttribute(AuthenticatedVoter::AUTH_TIME_ATTRIBUTE, time() - 60);

        $voter = new AuthenticatedVoter(new AuthenticationTrustResolver(), 900);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, null, ['IS_AUTHENTICATED_RECENTLY']));
    }

    public function testRecentlyAuthenticatedIsDeniedOnceTheLifetimeElapsed()
    {
        $token = $this->getToken('fully');
        $token->setAttribute(AuthenticatedVoter::AUTH_TIME_ATTRIBUTE, time() - 901);

        $voter = new AuthenticatedVoter(new AuthenticationTrustResolver(), 900);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, null, ['IS_AUTHENTICATED_RECENTLY']));
    }

    public function testFullyAuthenticatedDoesNotImplyRecentlyAuthenticated()
    {
        $voter = new AuthenticatedVoter(new AuthenticationTrustResolver(), 900);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($this->getToken('fully'), null, ['IS_AUTHENTICATED_FULLY']));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($this->getToken('fully'), null, ['IS_AUTHENTICATED_RECENTLY']));
    }

    public function testRecentlyAuthenticatedIsDeniedForARememberedToken()
    {
        $token = $this->getToken('remembered');
        $token->setAttribute(AuthenticatedVoter::AUTH_TIME_ATTRIBUTE, time());

        $voter = new AuthenticatedVoter(new AuthenticationTrustResolver(), 900);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, null, ['IS_AUTHENTICATED_RECENTLY']));
    }

    public function testRecentlyAuthenticatedUsesTheInjectedClock()
    {
        $clock = new MockClock('2026-09-11 12:00:00');
        $token = $this->getToken('fully');
        $token->setAttribute(AuthenticatedVoter::AUTH_TIME_ATTRIBUTE, $clock->now()->getTimestamp());

        $voter = new AuthenticatedVoter(new AuthenticationTrustResolver(), 900, $clock);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, null, ['IS_AUTHENTICATED_RECENTLY']));

        $clock->sleep(900);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, null, ['IS_AUTHENTICATED_RECENTLY']));

        $clock->sleep(1);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, null, ['IS_AUTHENTICATED_RECENTLY']));
    }

    public function testRecentlyAuthenticatedLifetimeIsConfigurable()
    {
        $token = $this->getToken('fully');
        $token->setAttribute(AuthenticatedVoter::AUTH_TIME_ATTRIBUTE, time() - 120);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, (new AuthenticatedVoter(new AuthenticationTrustResolver(), 300))->vote($token, null, ['IS_AUTHENTICATED_RECENTLY']));
        $this->assertSame(VoterInterface::ACCESS_DENIED, (new AuthenticatedVoter(new AuthenticationTrustResolver(), 60))->vote($token, null, ['IS_AUTHENTICATED_RECENTLY']));
    }

    public function testRecentlyAuthenticatedVoteReasons()
    {
        $voter = new AuthenticatedVoter(new AuthenticationTrustResolver(), 900);

        $token = $this->getToken('fully');
        $token->setAttribute(AuthenticatedVoter::AUTH_TIME_ATTRIBUTE, time());
        $voter->vote($token, null, ['IS_AUTHENTICATED_RECENTLY'], $granted = new Vote());
        $this->assertSame(['The user authenticated recently.'], $granted->reasons);

        $voter->vote($this->getToken('fully'), null, ['IS_AUTHENTICATED_RECENTLY'], $denied = new Vote());
        $this->assertSame(['The user is not authenticated recently enough.'], $denied->reasons);

        $voter->vote($this->getToken('remembered'), null, ['IS_AUTHENTICATED_RECENTLY'], $remembered = new Vote());
        $this->assertSame(['The user is not authenticated recently enough.'], $remembered->reasons);
    }

    #[DataProvider('provideDenialReasons')]
    public function testTheDenialReasonNamesTheStrictestAttributeThatFailed(string $authenticated, array $attributes, array $expectedReasons)
    {
        $voter = new AuthenticatedVoter(new AuthenticationTrustResolver(), 900);

        $voter->vote($this->getToken($authenticated), null, $attributes, $vote = new Vote());

        $this->assertSame($expectedReasons, $vote->reasons);
    }

    public static function provideDenialReasons()
    {
        yield 'remembered is not fully authenticated' => ['remembered', ['IS_AUTHENTICATED_FULLY'], ['The user is not fully authenticated.']];
        yield 'null token is neither' => ['none', ['IS_AUTHENTICATED_REMEMBERED'], ['The user is neither fully authenticated nor remembered.']];
        yield 'null token is not authenticated' => ['none', ['IS_AUTHENTICATED'], ['The user is not authenticated.']];
        yield 'fully is not remembered' => ['fully', ['IS_REMEMBERED'], ['The user is not remembered.']];
        yield 'fully is not impersonating' => ['fully', ['IS_IMPERSONATOR'], ['The user is not impersonating another user.']];
        // only the strictest failing attribute is reported, whatever order they came in
        yield 'fully beats impersonator' => ['remembered', ['IS_IMPERSONATOR', 'IS_AUTHENTICATED_FULLY'], ['The user is not fully authenticated.']];
        yield 'recently beats fully' => ['remembered', ['IS_AUTHENTICATED_FULLY', 'IS_AUTHENTICATED_RECENTLY'], ['The user is not authenticated recently enough.']];
        yield 'unrelated attributes are ignored' => ['remembered', ['ROLE_ADMIN', 'IS_AUTHENTICATED_FULLY'], ['The user is not fully authenticated.']];
    }

    protected function getToken($authenticated)
    {
        $user = new InMemoryUser('wouter', '', ['ROLE_USER']);

        if ('fully' === $authenticated) {
            $token = new class extends AbstractToken {
                public function getCredentials()
                {
                }
            };
            $token->setUser($user);

            return $token;
        }

        if ('remembered' === $authenticated) {
            return new RememberMeToken($user, 'foo');
        }

        if ('impersonated' === $authenticated) {
            return new SwitchUserToken(new InMemoryUser('John', 'password'), 'main', ['ROLE_USER'], new NullToken());
        }

        if ('offline' === $authenticated) {
            return new class($user->getRoles()) extends AbstractToken implements OfflineTokenInterface {};
        }

        return new NullToken();
    }
}
