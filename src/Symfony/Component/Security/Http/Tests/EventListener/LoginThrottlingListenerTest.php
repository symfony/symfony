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
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\SessionLockedException;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\LoginThrottlingBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\EventListener\LoginThrottlingListener;

class LoginThrottlingListenerTest extends TestCase
{
    private $requestStack;
    private $listener;
    private $cache;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
        $this->cache = new ArrayAdapter();
        $this->listener = new LoginThrottlingListener($this->requestStack, $this->cache);
    }

    public function testCountsFailedAttempts()
    {
        $request = $this->createRequest();
        $passport = $this->createPassport('wouter');

        $this->requestStack->push($request);

        $this->listener->onLoginFailure($this->createLoginFailureEvent($passport));
        $this->listener->onLoginFailure($this->createLoginFailureEvent($passport));
        $this->listener->onLoginFailure($this->createLoginFailureEvent($passport));

        $cacheItem = $this->cache->getItem('wouter192.168.1.0');
        $this->assertTrue($cacheItem->isHit());
        $this->assertEquals(3, $cacheItem->get());
    }

    public function testSuccessfulLoginResetsCount()
    {
        $request = $this->createRequest();
        $passport = $this->createPassport('wouter');

        $this->requestStack->push($request);

        $this->listener->onLoginFailure($this->createLoginFailureEvent($passport));
        $this->assertEquals(1, $this->cache->getItem('wouter192.168.1.0')->get());

        $this->listener->onLoginSuccess($this->createLoginSuccessfulEvent($passport));
        $this->assertFalse($this->cache->getItem('wouter192.168.1.0')->isHit());
    }

    /**
     * @dataProvider provideTooManyAttemptsData
     */
    public function testPreventsLoginWhenOverThreshold($time, $attempts, $expectError)
    {
        if ($expectError) {
            $this->expectException(SessionLockedException::class);
        } else {
            $this->expectNotToPerformAssertions();
        }

        $request = $this->createRequest();
        $passport = $this->createPassport('wouter');

        $cacheItem = $this->cache->getItem('wouter192.168.1.0');
        $cacheItem->expiresAt(new \DateTime(($time >= 0 ? '+' : '').$time.' minutes'));
        $cacheItem->set($attempts);
        $this->cache->save($cacheItem);

        $this->requestStack->push($request);

        $this->listener->checkPassport($this->createCheckPassportEvent($passport));
    }

    public function provideTooManyAttemptsData()
    {
        yield [time() + 100, 3, true];
        yield [time() + 100, 4, true];
        yield [time() + 100, 0, false]; // below threshold
        yield [time() + 100, 1, false]; // below threshold
    }

    private function createPassport($username)
    {
        return new SelfValidatingPassport(new User($username, null), [new LoginThrottlingBadge($username)]);
    }

    private function createLoginSuccessfulEvent($passport)
    {
        return new LoginSuccessEvent($this->createMock(AuthenticatorInterface::class), $passport, $this->createMock(TokenInterface::class), $this->requestStack->getCurrentRequest(), null, 'main');
    }

    private function createLoginFailureEvent($passport)
    {
        return new LoginFailureEvent(new AuthenticationException(), $this->createMock(AuthenticatorInterface::class), $passport, $this->requestStack->getCurrentRequest(), null, 'main');
    }

    private function createCheckPassportEvent($passport)
    {
        return new CheckPassportEvent($this->createMock(AuthenticatorInterface::class), $passport);
    }

    private function createRequest($ip = '192.168.1.0')
    {
        $request = new Request();
        $request->server->set('REMOTE_ADDR', $ip);

        return $request;
    }
}
