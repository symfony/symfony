<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\EventListener;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\AuthenticationMethod;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\AuthenticationMethodBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\EventListener\AuthenticationProofsListener;

class AuthenticationProofsListenerTest extends TestCase
{
    public function testAnInteractiveLoginIsRecordedAtTheClockTime()
    {
        $clock = new MockClock('2026-09-11 12:00:00');
        $token = $this->createToken();

        (new AuthenticationProofsListener($clock))->onLoginSuccess($this->createLoginSuccessEvent($token));

        $this->assertSame([AuthenticationMethod::UNSPECIFIED => $clock->now()->getTimestamp()], $token->getAuthenticationProofs());
    }

    public function testItFallsBackToTheSystemTimeWithoutAClock()
    {
        $token = $this->createToken();

        $before = time();
        (new AuthenticationProofsListener())->onLoginSuccess($this->createLoginSuccessEvent($token));
        $after = time();

        $authTime = $token->getAuthenticationProofs()[AuthenticationMethod::UNSPECIFIED];
        $this->assertGreaterThanOrEqual($before, $authTime);
        $this->assertLessThanOrEqual($after, $authTime);
    }

    public function testTheMethodsTheAuthenticatorStatesAreRecorded()
    {
        $clock = new MockClock('2026-09-11 12:00:00');
        $token = $this->createToken();

        (new AuthenticationProofsListener($clock))->onLoginSuccess($this->createLoginSuccessEvent($token, badge: new AuthenticationMethodBadge(AuthenticationMethod::PASSWORD, AuthenticationMethod::ONE_TIME_PASSWORD)));

        $this->assertSame([
            AuthenticationMethod::PASSWORD => $clock->now()->getTimestamp(),
            AuthenticationMethod::ONE_TIME_PASSWORD => $clock->now()->getTimestamp(),
        ], $token->getAuthenticationProofs());
    }

    public function testTheMethodsOfTheBadgeAreAddedToTheProofsTheTokenAlreadyHolds()
    {
        // the second step of a 2FA flow authenticates the token of the first step again,
        // so its proof has to be added to the first factor rather than skipped over it
        $clock = new MockClock('@200');
        $token = $this->createToken();
        $token->setAuthenticationProofs([AuthenticationMethod::PASSWORD => 100]);

        (new AuthenticationProofsListener($clock))->onLoginSuccess($this->createLoginSuccessEvent($token, badge: new AuthenticationMethodBadge(AuthenticationMethod::ONE_TIME_PASSWORD)));

        $this->assertSame([AuthenticationMethod::ONE_TIME_PASSWORD => 200, AuthenticationMethod::PASSWORD => 100], $token->getAuthenticationProofs());
    }

    public function testANonInteractiveLoginRecordsNothing()
    {
        $token = $this->createToken();

        (new AuthenticationProofsListener())->onLoginSuccess($this->createLoginSuccessEvent($token, interactive: false, badge: new AuthenticationMethodBadge(AuthenticationMethod::PASSWORD)));

        $this->assertSame([], $token->getAuthenticationProofs());
    }

    public function testARememberMeLoginIsNotAProof()
    {
        // the authenticator is interactive, but presenting a cookie proves nothing about
        // the credentials, and the entry would outlive the token through the carry-over
        $token = new RememberMeToken(new InMemoryUser('wouter', 'password', ['ROLE_USER']), 'main');

        (new AuthenticationProofsListener())->onLoginSuccess($this->createLoginSuccessEvent($token));

        $this->assertSame([], $token->getAuthenticationProofs());
    }

    public function testTheRecordedTimeSurvivesSerialization()
    {
        $token = $this->createToken();
        (new AuthenticationProofsListener())->onLoginSuccess($this->createLoginSuccessEvent($token));

        $this->assertSame($token->getAuthenticationProofs(), unserialize(serialize($token))->getAuthenticationProofs());
    }

    public function testItDoesNotOverwriteWhatTheAuthenticatorAlreadyRecorded()
    {
        // AuthenticatorManager calls createToken() before it dispatches the event, so an
        // authenticator that knows which methods were proven and when, such as an OIDC
        // client reading the "amr" and "auth_time" claims, would otherwise be lost
        $token = $this->createToken();
        $token->setAuthenticationProofs([AuthenticationMethod::ONE_TIME_PASSWORD => 1234567890]);

        (new AuthenticationProofsListener(new MockClock('2026-09-11 12:00:00')))->onLoginSuccess($this->createLoginSuccessEvent($token));

        $this->assertSame([AuthenticationMethod::ONE_TIME_PASSWORD => 1234567890], $token->getAuthenticationProofs());
    }

    public function testTheProofsOfThePreviousTokenOfTheSameUserAreCarriedOver()
    {
        // a re-authentication replaces the token, and a second factor proven at login
        // must not be lost with it; the newer entry wins where both tokens have one
        $clock = new MockClock('@200');
        $previousToken = $this->createToken();
        $previousToken->setAuthenticationProofs([AuthenticationMethod::ONE_TIME_PASSWORD => 100, AuthenticationMethod::UNSPECIFIED => 100]);
        $token = $this->createToken();

        (new AuthenticationProofsListener($clock))->onLoginSuccess($this->createLoginSuccessEvent($token, $previousToken));

        $this->assertSame([AuthenticationMethod::UNSPECIFIED => 200, AuthenticationMethod::ONE_TIME_PASSWORD => 100], $token->getAuthenticationProofs());
    }

    public function testANonInteractiveLoginOfTheSameUserInheritsTheProofs()
    {
        $previousToken = $this->createToken();
        $previousToken->setAuthenticationProofs([AuthenticationMethod::ONE_TIME_PASSWORD => 100]);
        $token = $this->createToken();

        (new AuthenticationProofsListener())->onLoginSuccess($this->createLoginSuccessEvent($token, $previousToken, interactive: false));

        $this->assertSame([AuthenticationMethod::ONE_TIME_PASSWORD => 100], $token->getAuthenticationProofs());
    }

    public function testTheProofsOfAnotherUserAreNotCarriedOver()
    {
        $clock = new MockClock('@200');
        $previousToken = $this->createToken('someone-else');
        $previousToken->setAuthenticationProofs([AuthenticationMethod::ONE_TIME_PASSWORD => 100]);
        $token = $this->createToken();

        (new AuthenticationProofsListener($clock))->onLoginSuccess($this->createLoginSuccessEvent($token, $previousToken));

        $this->assertSame([AuthenticationMethod::UNSPECIFIED => 200], $token->getAuthenticationProofs());
    }

    public function testNothingIsCarriedOverWithoutAPreviousTokenOrWithoutProofsOnIt()
    {
        $listener = new AuthenticationProofsListener();

        $token = $this->createToken();
        $listener->onLoginSuccess($this->createLoginSuccessEvent($token, null, interactive: false));
        $this->assertSame([], $token->getAuthenticationProofs());

        $token = $this->createToken();
        $listener->onLoginSuccess($this->createLoginSuccessEvent($token, $this->createToken(), interactive: false));
        $this->assertSame([], $token->getAuthenticationProofs());
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testATokenWithoutTheProofsMethodsIsDeprecatedAndLeftAlone()
    {
        $token = $this->createStub(TokenInterface::class);

        $this->expectUserDeprecationMessage(\sprintf('Since symfony/security-http 8.2: Not implementing "%s::setAuthenticationProofs()" is deprecated, the method will be added to "%s" in 9.0; no authentication proof is recorded until then.', get_debug_type($token), TokenInterface::class));

        (new AuthenticationProofsListener())->onLoginSuccess($this->createLoginSuccessEvent($token));
    }

    public function testItSubscribesToLoginSuccess()
    {
        $this->assertSame([LoginSuccessEvent::class => ['onLoginSuccess', 256]], AuthenticationProofsListener::getSubscribedEvents());
    }

    private function createToken(string $userIdentifier = 'wouter'): UsernamePasswordToken
    {
        return new UsernamePasswordToken(new InMemoryUser($userIdentifier, 'password', ['ROLE_USER']), 'main', ['ROLE_USER']);
    }

    private function createLoginSuccessEvent(TokenInterface $token, ?TokenInterface $previousToken = null, bool $interactive = true, ?BadgeInterface $badge = null): LoginSuccessEvent
    {
        if ($interactive) {
            $authenticator = $this->createStub(InteractiveAuthenticatorInterface::class);
            $authenticator->method('isInteractive')->willReturn(true);
        } else {
            $authenticator = $this->createStub(AuthenticatorInterface::class);
        }

        $passport = new SelfValidatingPassport(new UserBadge('wouter'), $badge ? [$badge] : []);

        return new LoginSuccessEvent($authenticator, $passport, $token, new Request(), null, 'main', $previousToken);
    }
}
