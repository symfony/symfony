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

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RateLimiter\PeekableRequestRateLimiterInterface;
use Symfony\Component\HttpFoundation\RateLimiter\RequestRateLimiterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\Event\RateLimitExceededEvent;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\EventListener\LoginThrottlingListener;
use Symfony\Component\Security\Http\RateLimiter\DefaultLoginRateLimiter;
use Symfony\Component\Security\Http\Tests\Fixtures\DummyAuthenticator;

class LoginThrottlingListenerTest extends TestCase
{
    private RequestStack $requestStack;
    private LoginThrottlingListener $listener;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
        $this->listener = new LoginThrottlingListener($this->requestStack, $this->createLimiter());
    }

    public function testPreventsLoginWhenOverLocalThreshold()
    {
        $request = $this->createRequest();
        $passport = $this->createPassport('wouter');

        $this->requestStack->push($request);

        for ($i = 0; $i < 3; ++$i) {
            $this->listener->checkPassport($this->createCheckPassportEvent($passport));
            $this->listener->onFailedLogin($this->createLoginFailedEvent($passport));
        }

        $this->expectException(TooManyLoginAttemptsAuthenticationException::class);
        $this->listener->checkPassport($this->createCheckPassportEvent($passport));
    }

    public function testPreventsLoginWithMultipleCase()
    {
        $request = $this->createRequest();
        $passports = [$this->createPassport('wouter'), $this->createPassport('Wouter'), $this->createPassport('wOuter')];

        $this->requestStack->push($request);

        for ($i = 0; $i < 3; ++$i) {
            $this->listener->checkPassport($this->createCheckPassportEvent($passports[$i % 3]));
            $this->listener->onFailedLogin($this->createLoginFailedEvent($passports[$i % 3]));
        }

        $this->expectException(TooManyLoginAttemptsAuthenticationException::class);
        $this->listener->checkPassport($this->createCheckPassportEvent($passports[0]));
    }

    public function testPreventsLoginWhenOverGlobalThreshold()
    {
        $request = $this->createRequest();
        $passports = [$this->createPassport('wouter'), $this->createPassport('ryan')];

        $this->requestStack->push($request);

        for ($i = 0; $i < 6; ++$i) {
            $this->listener->checkPassport($this->createCheckPassportEvent($passports[$i % 2]));
            $this->listener->onFailedLogin($this->createLoginFailedEvent($passports[$i % 2]));
        }

        $this->expectException(TooManyLoginAttemptsAuthenticationException::class);
        $this->listener->checkPassport($this->createCheckPassportEvent($passports[0]));
    }

    public function testSuccessfulLoginResetsThrottling()
    {
        $request = $this->createRequest();
        $passport = $this->createPassport('wouter');

        $this->requestStack->push($request);

        for ($i = 0; $i < 2; ++$i) {
            $this->listener->checkPassport($this->createCheckPassportEvent($passport));
            $this->listener->onFailedLogin($this->createLoginFailedEvent($passport));
        }

        $this->listener->checkPassport($this->createCheckPassportEvent($passport));
        $this->listener->onSuccessfulLogin($this->createLoginSuccessEvent($passport));

        for ($i = 0; $i < 3; ++$i) {
            $this->listener->checkPassport($this->createCheckPassportEvent($passport));
            $this->listener->onFailedLogin($this->createLoginFailedEvent($passport));
        }

        $this->expectException(TooManyLoginAttemptsAuthenticationException::class);
        $this->listener->checkPassport($this->createCheckPassportEvent($passport));
    }

    public function testSuccessfulLoginDoesNotResetUntouchedPeekableLimiter()
    {
        $request = $this->createRequest();
        $passport = $this->createPassport('wouter');

        $this->requestStack->push($request);

        $limiter = $this->createMock(PeekableRequestRateLimiterInterface::class);
        $limiter->expects($this->once())->method('peek')->willReturn(new RateLimit(3, new \DateTimeImmutable(), true, 3));
        $limiter->expects($this->never())->method('reset');

        $listener = new LoginThrottlingListener($this->requestStack, $limiter);
        $listener->checkPassport($this->createCheckPassportEvent($passport));
        $listener->onSuccessfulLogin($this->createLoginSuccessEvent($passport));
    }

    public function testSuccessfulLoginKeepsTheGlobalLimit()
    {
        $this->requestStack->push($this->createRequest());

        // The global limit is 6 and the local one 3, so failing on four
        // different usernames only brings the global limit close to its
        // threshold, leaving two attempts.
        foreach (['a', 'b', 'c', 'd'] as $username) {
            $passport = $this->createPassport($username);
            $this->listener->checkPassport($this->createCheckPassportEvent($passport));
            $this->listener->onFailedLogin($this->createLoginFailedEvent($passport));
        }

        $passport = $this->createPassport('e');
        $this->listener->checkPassport($this->createCheckPassportEvent($passport));
        $this->listener->onSuccessfulLogin($this->createLoginSuccessEvent($passport));

        foreach (['f', 'g'] as $username) {
            $passport = $this->createPassport($username);
            $this->listener->checkPassport($this->createCheckPassportEvent($passport));
            $this->listener->onFailedLogin($this->createLoginFailedEvent($passport));
        }

        $this->expectException(TooManyLoginAttemptsAuthenticationException::class);
        $this->listener->checkPassport($this->createCheckPassportEvent($this->createPassport('h')));
    }

    public function testDispatchesRateLimitExceededEventWhenThrottled()
    {
        if (!class_exists(RateLimitExceededEvent::class)) {
            $this->markTestSkipped('The installed "symfony/rate-limiter" does not provide RateLimitExceededEvent.');
        }

        $passport = $this->createPassport('wouter');

        $this->requestStack->push($this->createRequest());

        $dispatcher = new EventDispatcher();

        $dispatchedEvent = null;
        $dispatcher->addListener(RateLimitExceededEvent::class, static function (RateLimitExceededEvent $event) use (&$dispatchedEvent) {
            $dispatchedEvent = $event;
        });

        $listener = new LoginThrottlingListener($this->requestStack, $this->createLimiter(), 'security.login_throttling.main.limiter');

        for ($i = 0; $i < 3; ++$i) {
            $listener->checkPassport($this->createCheckPassportEvent($passport), CheckPassportEvent::class, $dispatcher);
            $listener->onFailedLogin($this->createLoginFailedEvent($passport));
        }

        try {
            $listener->checkPassport($this->createCheckPassportEvent($passport), CheckPassportEvent::class, $dispatcher);
            $this->fail('Expected TooManyLoginAttemptsAuthenticationException');
        } catch (TooManyLoginAttemptsAuthenticationException) {
        }

        $this->assertInstanceOf(RateLimitExceededEvent::class, $dispatchedEvent);
        $this->assertSame('security.login_throttling.main.limiter', $dispatchedEvent->getLimiterName());
        $this->assertNull($dispatchedEvent->getKey());
        $this->assertFalse($dispatchedEvent->getRateLimit()->isAccepted());
        $this->assertSame(0, $dispatchedEvent->getRateLimit()->getRemainingTokens());
        $this->assertSame(3, $dispatchedEvent->getRateLimit()->getLimit());
    }

    public function testDispatchesTheRateLimitOfANonPeekableLimiter()
    {
        if (!class_exists(RateLimitExceededEvent::class)) {
            $this->markTestSkipped('The installed "symfony/rate-limiter" does not provide RateLimitExceededEvent.');
        }

        $this->requestStack->push($this->createRequest());

        $rateLimit = new RateLimit(0, new \DateTimeImmutable('+1 minute'), false, 3);
        $limiter = $this->createMock(RequestRateLimiterInterface::class);
        $limiter->expects($this->once())->method('consume')->willReturn($rateLimit);

        $dispatched = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(RateLimitExceededEvent::class, static function (RateLimitExceededEvent $event) use (&$dispatched) {
            $dispatched[] = $event;
        });

        $listener = new LoginThrottlingListener($this->requestStack, $limiter, 'app.rate_limiter');

        try {
            $listener->checkPassport($this->createCheckPassportEvent($this->createPassport('wouter')), CheckPassportEvent::class, $dispatcher);
            $this->fail('Expected TooManyLoginAttemptsAuthenticationException');
        } catch (TooManyLoginAttemptsAuthenticationException) {
        }

        $this->assertCount(1, $dispatched);
        $this->assertSame($rateLimit, $dispatched[0]->getRateLimit());
        $this->assertSame('app.rate_limiter', $dispatched[0]->getLimiterName());
        $this->assertNull($dispatched[0]->getKey());
    }

    public function testAcceptedDoesNotDispatchRateLimitExceededEvent()
    {
        $this->requestStack->push($this->createRequest());

        $dispatched = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(RateLimitExceededEvent::class, static function ($event) use (&$dispatched) {
            $dispatched[] = $event;
        });

        $listener = new LoginThrottlingListener($this->requestStack, $this->createLimiter());

        $listener->checkPassport($this->createCheckPassportEvent($this->createPassport('wouter')), CheckPassportEvent::class, $dispatcher);

        $this->assertSame([], $dispatched);
    }

    private function createPassport($username)
    {
        return new SelfValidatingPassport(new UserBadge($username));
    }

    private function createLoginFailedEvent($passport)
    {
        return new LoginFailureEvent(new AuthenticationException(), new DummyAuthenticator(), $this->requestStack->getCurrentRequest(), null, 'main', $passport);
    }

    private function createLoginSuccessEvent($passport)
    {
        return new LoginSuccessEvent(new DummyAuthenticator(), $passport, new NullToken(), $this->requestStack->getCurrentRequest(), null, 'main');
    }

    private function createCheckPassportEvent($passport)
    {
        return new CheckPassportEvent(new DummyAuthenticator(), $passport);
    }

    private function createRequest($ip = '192.168.1.0')
    {
        $request = new Request();
        $request->server->set('REMOTE_ADDR', $ip);

        return $request;
    }

    private function createLimiter(): DefaultLoginRateLimiter
    {
        $localLimiter = new RateLimiterFactory([
            'id' => 'login',
            'policy' => 'fixed_window',
            'limit' => 3,
            'interval' => '1 minute',
        ], new InMemoryStorage());
        $globalLimiter = new RateLimiterFactory([
            'id' => 'login',
            'policy' => 'fixed_window',
            'limit' => 6,
            'interval' => '1 minute',
        ], new InMemoryStorage());

        return new DefaultLoginRateLimiter($globalLimiter, $localLimiter, '$3cre7');
    }
}
