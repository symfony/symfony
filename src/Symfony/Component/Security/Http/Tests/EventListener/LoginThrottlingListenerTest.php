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
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\HttpFoundation\RateLimiter\PeekableRequestRateLimiterInterface;
use Symfony\Component\HttpFoundation\RateLimiter\RequestRateLimiterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\LimiterStateInterface;
use Symfony\Component\RateLimiter\Policy\TokenBucketLimiter;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\RateLimiter\Storage\StorageInterface;
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
    private DefaultLoginRateLimiter $limiter;
    private LoginThrottlingListener $listener;

    protected function setUp(): void
    {
        ClockMock::register(LoginThrottlingListener::class);
        ClockMock::register(TokenBucketLimiter::class);
        ClockMock::register(InMemoryStorage::class);
        ClockMock::register(RateLimit::class);

        $this->requestStack = new RequestStack();

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
        $this->limiter = new DefaultLoginRateLimiter($globalLimiter, $localLimiter, '$3cre7');

        $this->listener = new LoginThrottlingListener($this->requestStack, $this->limiter, false);
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

    /**
     * @group time-sensitive
     */
    public function testThresholdDoesNotCountTheRoundingOfTheRetryInstant()
    {
        ClockMock::withClockMock(1729990780.75);

        $this->requestStack->push($this->createRequest());

        $limiter = $this->createMock(RequestRateLimiterInterface::class);
        $limiter->method('consume')->willReturn(new RateLimit(0, \DateTimeImmutable::createFromFormat('U', '1729990841'), false, 3));

        $listener = new LoginThrottlingListener($this->requestStack, $limiter);

        try {
            $listener->checkPassport($this->createCheckPassportEvent($this->createPassport('wouter')));
            $this->fail(TooManyLoginAttemptsAuthenticationException::class.' was not thrown.');
        } catch (TooManyLoginAttemptsAuthenticationException $e) {
            $this->assertSame(1, $e->getMessageData()['%minutes%']);
        }
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

    public function testPreventsLoginWithInvisibleOrControlCharactersCombiningMarksOrSpaces()
    {
        $request = $this->createRequest();
        $passports = [$this->createPassport("wou\u{200B}ter"), $this->createPassport("wo\u{301}u\u{20DD}ter"), $this->createPassport(" wou\x01ter ")];

        $this->requestStack->push($request);

        for ($i = 0; $i < 3; ++$i) {
            $this->listener->checkPassport($this->createCheckPassportEvent($passports[$i % 3]));
        }

        $this->expectException(TooManyLoginAttemptsAuthenticationException::class);
        $this->listener->checkPassport($this->createCheckPassportEvent($this->createPassport('wouter')));
    }

    public function testPreventsLoginWithAccentsOrCompatibilityForms()
    {
        if (!class_exists(\Normalizer::class)) {
            $this->markTestSkipped('The "intl" extension or the "symfony/polyfill-intl-normalizer" package is required.');
        }

        $request = $this->createRequest();
        $passports = [$this->createPassport('wóuter'), $this->createPassport('ⓦouter'), $this->createPassport('ｗｏｕｔｅｒ')];

        $this->requestStack->push($request);

        for ($i = 0; $i < 3; ++$i) {
            $this->listener->checkPassport($this->createCheckPassportEvent($passports[$i % 3]));
        }

        $this->expectException(TooManyLoginAttemptsAuthenticationException::class);
        $this->listener->checkPassport($this->createCheckPassportEvent($this->createPassport('wouter')));
    }

    public function testPreventsLoginWithCaseFoldedVariants()
    {
        if (!\defined('MB_CASE_FOLD')) {
            $this->markTestSkipped('The "mbstring" extension on PHP 7.3 or higher is required.');
        }

        $request = $this->createRequest();
        $passports = [$this->createPassport('straße'), $this->createPassport('STRAẞE'), $this->createPassport('Strasse')];

        $this->requestStack->push($request);

        for ($i = 0; $i < 3; ++$i) {
            $this->listener->checkPassport($this->createCheckPassportEvent($passports[$i % 3]));
        }

        $this->expectException(TooManyLoginAttemptsAuthenticationException::class);
        $this->listener->checkPassport($this->createCheckPassportEvent($this->createPassport('strasse')));
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

    /**
     * @dataProvider provideStateless
     */
    public function testSuccessfulLoginResetsThrottling(bool $stateless)
    {
        $this->listener = new LoginThrottlingListener($this->requestStack, $this->limiter, $stateless);
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

    public function testAttemptsAreCountedBeforeTheirCredentialsAreChecked()
    {
        $this->requestStack->push($this->createRequest());
        $passport = $this->createPassport('wouter');

        // The local limit is 3, and none of these concurrent attempts has failed yet
        for ($i = 0; $i < 3; ++$i) {
            $this->listener->checkPassport($this->createCheckPassportEvent($passport));
        }

        $this->expectException(TooManyLoginAttemptsAuthenticationException::class);
        $this->listener->checkPassport($this->createCheckPassportEvent($passport));
    }

    public function testSuccessfulLoginsDoNotUseTheGlobalLimit()
    {
        $this->requestStack->push($this->createRequest());

        // The global limit is 6
        for ($i = 0; $i < 10; ++$i) {
            $passport = $this->createPassport('user'.$i);
            $this->listener->checkPassport($this->createCheckPassportEvent($passport));
            $this->listener->onSuccessfulLogin($this->createLoginSuccessEvent($passport));
        }

        $this->addToAssertionCount(1);
    }

    public function testFailuresBeforeThePassportCheckAreCounted()
    {
        $this->requestStack->push($this->createRequest());

        for ($i = 0; $i < 6; ++$i) {
            $this->listener->onFailedLogin(new LoginFailureEvent(new AuthenticationException(), new DummyAuthenticator(), $this->requestStack->getCurrentRequest(), null, 'main'));
        }

        $this->expectException(TooManyLoginAttemptsAuthenticationException::class);
        $this->listener->checkPassport($this->createCheckPassportEvent($this->createPassport('wouter')));
    }

    /**
     * @dataProvider provideStateless
     */
    public function testSuccessfulLoginKeepsTheGlobalLimit(bool $stateless)
    {
        $this->listener = new LoginThrottlingListener($this->requestStack, $this->limiter, $stateless);
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

    public static function provideStateless(): iterable
    {
        yield 'stateful' => [false];
        yield 'stateless' => [true];
    }

    public function testSuccessfulLoginDoesNotResetUntouchedPeekableLimiter()
    {
        $request = $this->createRequest();
        $passport = $this->createPassport('wouter');

        $this->requestStack->push($request);

        $limiter = $this->createMock(PeekableRequestRateLimiterInterface::class);
        $limiter->expects($this->once())->method('peek')->willReturn(new RateLimit(3, new \DateTimeImmutable(), true, 3));
        $limiter->expects($this->never())->method('consume');
        $limiter->expects($this->never())->method('reset');

        $listener = new LoginThrottlingListener($this->requestStack, $limiter);
        $listener->checkPassport($this->createCheckPassportEvent($passport));
        $listener->onSuccessfulLogin($this->createLoginSuccessEvent($passport));
    }

    public function testStatelessSuccessfulLoginDoesNotWriteToTheStorageInAnUntouchedWindow()
    {
        $storage = new class implements StorageInterface {
            public int $writes = 0;
            private InMemoryStorage $storage;

            public function __construct()
            {
                $this->storage = new InMemoryStorage();
            }

            public function save(LimiterStateInterface $limiterState): void
            {
                ++$this->writes;
                $this->storage->save($limiterState);
            }

            public function fetch(string $limiterStateId): ?LimiterStateInterface
            {
                return $this->storage->fetch($limiterStateId);
            }

            public function delete(string $limiterStateId): void
            {
                ++$this->writes;
                $this->storage->delete($limiterStateId);
            }
        };
        $config = ['id' => 'login', 'policy' => 'fixed_window', 'interval' => '1 minute'];
        $limiter = new DefaultLoginRateLimiter(new RateLimiterFactory($config + ['limit' => 6], $storage), new RateLimiterFactory($config + ['limit' => 3], $storage), '$3cre7');

        $this->requestStack->push($this->createRequest());
        $passport = $this->createPassport('wouter');

        $listener = new LoginThrottlingListener($this->requestStack, $limiter, true);
        $listener->checkPassport($this->createCheckPassportEvent($passport));
        $listener->onSuccessfulLogin($this->createLoginSuccessEvent($passport));

        $this->assertSame(0, $storage->writes);
    }

    public function testStatelessAttemptsAreCountedBeforeTheirCredentialsAreCheckedAfterAFailure()
    {
        $this->requestStack->push($this->createRequest());
        $passport = $this->createPassport('wouter');

        $listener = new LoginThrottlingListener($this->requestStack, $this->limiter, true);
        $listener->checkPassport($this->createCheckPassportEvent($passport));
        $listener->onFailedLogin($this->createLoginFailedEvent($passport));

        // The local limit is 3, and none of these concurrent attempts has failed yet
        for ($i = 0; $i < 2; ++$i) {
            $listener->checkPassport($this->createCheckPassportEvent($passport));
        }

        $this->expectException(TooManyLoginAttemptsAuthenticationException::class);
        $listener->checkPassport($this->createCheckPassportEvent($passport));
    }

    /**
     * @group time-sensitive
     */
    public function testStatelessBurstGetsOneAttemptWhenOneTokenIsFreed()
    {
        $localLimiter = new RateLimiterFactory([
            'id' => 'login',
            'policy' => 'token_bucket',
            'limit' => 3,
            'rate' => ['interval' => '1 minute'],
        ], new InMemoryStorage());
        $globalLimiter = new RateLimiterFactory([
            'id' => 'login',
            'policy' => 'token_bucket',
            'limit' => 6,
            'rate' => ['interval' => '1 minute'],
        ], new InMemoryStorage());
        $listener = new LoginThrottlingListener($this->requestStack, new DefaultLoginRateLimiter($globalLimiter, $localLimiter, '$3cre7'), true);

        $this->requestStack->push($this->createRequest());
        $passport = $this->createPassport('wouter');

        for ($i = 0; $i < 3; ++$i) {
            $listener->checkPassport($this->createCheckPassportEvent($passport));
            $listener->onFailedLogin($this->createLoginFailedEvent($passport));
        }

        ClockMock::sleep(60);

        // A single token is back, and none of these concurrent attempts has failed yet
        $listener->checkPassport($this->createCheckPassportEvent($passport));

        $this->expectException(TooManyLoginAttemptsAuthenticationException::class);
        $listener->checkPassport($this->createCheckPassportEvent($passport));
    }

    public function testPreventsLoginFromTheSameIpv6Network()
    {
        $passport = $this->createPassport('wouter');

        foreach (['2001:db8:1:2::1', '2001:db8:1:2::2', '2001:db8:1:2:a:b:c:d'] as $ip) {
            $this->requestStack->push($this->createRequest($ip));
            $this->listener->checkPassport($this->createCheckPassportEvent($passport));
            $this->requestStack->pop();
        }

        $this->requestStack->push($this->createRequest('2001:db8:1:2:ffff:ffff:ffff:ffff'));

        $this->expectException(TooManyLoginAttemptsAuthenticationException::class);
        $this->listener->checkPassport($this->createCheckPassportEvent($passport));
    }

    public function testDoesNotShareTheLimitWithOtherIpv6NetworksOrIpv4MappedAddresses()
    {
        $passport = $this->createPassport('wouter');

        foreach (['2001:db8:1:2::1', '2001:db8:1:3::1', '::ffff:192.0.2.1', '::ffff:192.0.2.2'] as $ip) {
            $this->requestStack->push($this->createRequest($ip));
            for ($i = 0; $i < 3; ++$i) {
                $this->listener->checkPassport($this->createCheckPassportEvent($passport));
            }
            $this->requestStack->pop();
        }

        $this->addToAssertionCount(1);
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
}
