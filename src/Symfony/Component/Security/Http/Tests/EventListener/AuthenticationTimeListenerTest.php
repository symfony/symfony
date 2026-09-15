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
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\EventListener\AuthenticationTimeListener;
use Symfony\Component\Security\Http\SecurityEvents;

class AuthenticationTimeListenerTest extends TestCase
{
    public function testItUsesTheInjectedClock()
    {
        $clock = new MockClock('2026-09-11 12:00:00');
        $token = $this->createToken();

        (new AuthenticationTimeListener($clock))->onInteractiveLogin(new InteractiveLoginEvent(new Request(), $token));

        $this->assertSame(
            [AuthenticationMethod::UNSPECIFIED => $clock->now()->getTimestamp()],
            $token->getAuthenticationProofs()
        );
    }

    public function testItFallsBackToTheSystemTimeWithoutAClock()
    {
        $token = $this->createToken();
        $this->assertSame([], $token->getAuthenticationProofs());

        $before = time();
        (new AuthenticationTimeListener())->onInteractiveLogin(new InteractiveLoginEvent(new Request(), $token));
        $after = time();

        $authTime = $token->getAuthenticationProofs()[AuthenticationMethod::UNSPECIFIED];
        $this->assertGreaterThanOrEqual($before, $authTime);
        $this->assertLessThanOrEqual($after, $authTime);
    }

    public function testTheRecordedTimeSurvivesSerialization()
    {
        $token = $this->createToken();
        (new AuthenticationTimeListener())->onInteractiveLogin(new InteractiveLoginEvent(new Request(), $token));

        $restored = unserialize(serialize($token));

        $this->assertSame(
            $token->getAuthenticationProofs(),
            $restored->getAuthenticationProofs()
        );
    }

    public function testItDoesNotOverwriteWhatTheAuthenticatorAlreadyRecorded()
    {
        // AuthenticatorManager calls createToken() before it dispatches INTERACTIVE_LOGIN,
        // so an authenticator that knows which methods were proven and when, such as an
        // OIDC client reading the "amr" and "auth_time" claims, would otherwise be lost
        $token = $this->createToken();
        $token->setAuthenticationProofs([AuthenticationMethod::ONE_TIME_PASSWORD => 1234567890]);

        (new AuthenticationTimeListener(new MockClock('2026-09-11 12:00:00')))->onInteractiveLogin(new InteractiveLoginEvent(new Request(), $token));

        $this->assertSame([AuthenticationMethod::ONE_TIME_PASSWORD => 1234567890], $token->getAuthenticationProofs());
    }

    public function testTheProofsOfThePreviousTokenOfTheSameUserAreCarriedOver()
    {
        // a re-authentication replaces the token, and a second factor proven at login
        // must not be lost with it; the newer entry wins where both tokens have one
        $previousToken = $this->createToken();
        $previousToken->setAuthenticationProofs([AuthenticationMethod::ONE_TIME_PASSWORD => 100, AuthenticationMethod::UNSPECIFIED => 100]);
        $token = $this->createToken();
        $token->setAuthenticationProofs([AuthenticationMethod::UNSPECIFIED => 200]);

        (new AuthenticationTimeListener())->onLoginSuccess($this->createLoginSuccessEvent($token, $previousToken));

        $this->assertSame([AuthenticationMethod::UNSPECIFIED => 200, AuthenticationMethod::ONE_TIME_PASSWORD => 100], $token->getAuthenticationProofs());
    }

    public function testANonInteractiveLoginOfTheSameUserInheritsTheProofs()
    {
        $previousToken = $this->createToken();
        $previousToken->setAuthenticationProofs([AuthenticationMethod::ONE_TIME_PASSWORD => 100]);
        $token = $this->createToken();

        (new AuthenticationTimeListener())->onLoginSuccess($this->createLoginSuccessEvent($token, $previousToken));

        $this->assertSame([AuthenticationMethod::ONE_TIME_PASSWORD => 100], $token->getAuthenticationProofs());
    }

    public function testTheProofsOfAnotherUserAreNotCarriedOver()
    {
        $previousToken = $this->createToken('someone-else');
        $previousToken->setAuthenticationProofs([AuthenticationMethod::ONE_TIME_PASSWORD => 100]);
        $token = $this->createToken();
        $token->setAuthenticationProofs([AuthenticationMethod::UNSPECIFIED => 200]);

        (new AuthenticationTimeListener())->onLoginSuccess($this->createLoginSuccessEvent($token, $previousToken));

        $this->assertSame([AuthenticationMethod::UNSPECIFIED => 200], $token->getAuthenticationProofs());
    }

    public function testNothingIsCarriedOverWithoutAPreviousTokenOrWithoutProofsOnIt()
    {
        $listener = new AuthenticationTimeListener();

        $token = $this->createToken();
        $listener->onLoginSuccess($this->createLoginSuccessEvent($token, null));
        $this->assertSame([], $token->getAuthenticationProofs());

        $token = $this->createToken();
        $listener->onLoginSuccess($this->createLoginSuccessEvent($token, $this->createToken()));
        $this->assertSame([], $token->getAuthenticationProofs());
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testATokenWithoutTheProofsMethodsIsDeprecatedAndLeftAlone()
    {
        $token = $this->createStub(TokenInterface::class);

        $this->expectUserDeprecationMessage(\sprintf('Since symfony/security-http 8.2: Not implementing "%s::setAuthenticationProofs()" is deprecated, the method will be added to "%s" in 9.0; no authentication proof is recorded until then.', get_debug_type($token), TokenInterface::class));

        (new AuthenticationTimeListener())->onInteractiveLogin(new InteractiveLoginEvent(new Request(), $token));
    }

    public function testItSubscribesToBothLoginEvents()
    {
        $this->assertSame([
            SecurityEvents::INTERACTIVE_LOGIN => ['onInteractiveLogin', 256],
            LoginSuccessEvent::class => ['onLoginSuccess', 256],
        ], AuthenticationTimeListener::getSubscribedEvents());
    }

    private function createToken(string $userIdentifier = 'wouter'): UsernamePasswordToken
    {
        return new UsernamePasswordToken(new InMemoryUser($userIdentifier, 'password', ['ROLE_USER']), 'main', ['ROLE_USER']);
    }

    private function createLoginSuccessEvent(UsernamePasswordToken $token, ?UsernamePasswordToken $previousToken): LoginSuccessEvent
    {
        return new LoginSuccessEvent($this->createStub(AuthenticatorInterface::class), new SelfValidatingPassport(new UserBadge($token->getUserIdentifier())), $token, new Request(), null, 'main', $previousToken);
    }
}
