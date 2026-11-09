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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\EventListener\LoginThrottlingListener;
use Symfony\Component\Security\Http\RateLimiter\DefaultLoginRateLimiter;

class LoginThrottlingListenerTest extends TestCase
{
    private $requestStack;
    private $listener;

    protected function setUp(): void
    {
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
        $limiter = new DefaultLoginRateLimiter($globalLimiter, $localLimiter);

        $this->listener = new LoginThrottlingListener($this->requestStack, $limiter);
    }

    public function testPreventsLoginWhenOverLocalThreshold()
    {
        $request = $this->createRequest();
        $passport = $this->createPassport('wouter');

        $this->requestStack->push($request);

        for ($i = 0; $i < 3; ++$i) {
            $this->listener->checkPassport($this->createCheckPassportEvent($passport));
        }

        $this->listener->onSuccessfulLogin($this->createLoginSuccessfulEvent($passport));

        for ($i = 0; $i < 3; ++$i) {
            $this->listener->checkPassport($this->createCheckPassportEvent($passport));
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
        }

        $this->expectException(TooManyLoginAttemptsAuthenticationException::class);
        $this->listener->checkPassport($this->createCheckPassportEvent($passports[0]));
    }

    public function testSuccessfulLoginKeepsTheGlobalLimit()
    {
        $this->requestStack->push($this->createRequest());

        // The global limit is 6 and the local one 3, so trying five different
        // usernames, the last one successfully, leaves a single attempt.
        foreach (['a', 'b', 'c', 'd', 'e'] as $username) {
            $passport = $this->createPassport($username);
            $this->listener->checkPassport($this->createCheckPassportEvent($passport));
        }
        $this->listener->onSuccessfulLogin($this->createLoginSuccessfulEvent($passport));

        $this->listener->checkPassport($this->createCheckPassportEvent($this->createPassport('f')));

        $this->expectException(TooManyLoginAttemptsAuthenticationException::class);
        $this->listener->checkPassport($this->createCheckPassportEvent($this->createPassport('g')));
    }

    private function createPassport($username)
    {
        return new SelfValidatingPassport(new UserBadge($username));
    }

    private function createLoginSuccessfulEvent($passport)
    {
        return new LoginSuccessEvent($this->createMock(AuthenticatorInterface::class), $passport, $this->createMock(TokenInterface::class), $this->requestStack->getCurrentRequest(), null, 'main');
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
