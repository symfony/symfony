<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\PrivacyPass;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Http\PrivacyPass\PrivateTokenManagerInterface;
use Symfony\Component\Security\Http\PrivacyPass\SessionPrivateTokenManager;

class SessionPrivateTokenManagerTest extends TestCase
{
    public function testASessionThatPresentedATokenIsAcceptedWithoutOne()
    {
        $manager = new SessionPrivateTokenManager($this->manager(true, false), 1800);
        $session = new Session(new MockArraySessionStorage());

        $this->assertTrue($manager->isTokenValid(self::request($session)));
        $this->assertTrue($manager->isTokenValid(self::request($session, withCookie: true)));
    }

    public function testASessionIsNoLongerAcceptedAfterTheLifetime()
    {
        $clock = new MockClock('2026-10-23 12:00:00');
        $manager = new SessionPrivateTokenManager($this->manager(true, false), 1800, '_security.private_token', $clock);
        $session = new Session(new MockArraySessionStorage());

        $this->assertTrue($manager->isTokenValid(self::request($session)));
        $clock->sleep(1799);
        $this->assertTrue($manager->isTokenValid(self::request($session, withCookie: true)));
        $clock->sleep(2);
        $this->assertFalse($manager->isTokenValid(self::request($session, withCookie: true)));
    }

    public function testARequestThatPresentsNoTokenIsRejectedUntilOneIsAccepted()
    {
        $manager = new SessionPrivateTokenManager($this->manager(false));
        $session = new Session(new MockArraySessionStorage());

        $this->assertFalse($manager->isTokenValid(self::request($session, withCookie: true)));
    }

    public function testNothingIsRememberedWithoutASession()
    {
        $inner = $this->createMock(PrivateTokenManagerInterface::class);
        $inner->expects($this->exactly(2))->method('isTokenValid')->willReturn(true);
        $manager = new SessionPrivateTokenManager($inner);

        $this->assertTrue($manager->isTokenValid(new Request()));
        $this->assertTrue($manager->isTokenValid(new Request()));
    }

    public function testTheSessionOfARequestThatBroughtNoCookieIsNotRead()
    {
        $manager = new SessionPrivateTokenManager($this->manager(false));
        $session = new Session(new MockArraySessionStorage());
        $session->set('_security.private_token', time() + 1800);

        $this->assertFalse($manager->isTokenValid(self::request($session)));
    }

    public function testTheChallengeIsTheOneOfTheManagerItDecorates()
    {
        $inner = $this->createStub(PrivateTokenManagerInterface::class);
        $inner->method('getChallengeHeader')->willReturn('PrivateToken challenge="abc"');

        $this->assertSame('PrivateToken challenge="abc"', (new SessionPrivateTokenManager($inner))->getChallengeHeader(new Request()));
    }

    public function testItRejectsALifetimeOfNoDuration()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The lifetime of an accepted session must be at least 1 second, "0" given.');

        new SessionPrivateTokenManager($this->createStub(PrivateTokenManagerInterface::class), 0);
    }

    private function manager(bool ...$verdicts): PrivateTokenManagerInterface
    {
        $manager = $this->createStub(PrivateTokenManagerInterface::class);
        $manager->method('isTokenValid')->willReturn(...$verdicts);

        return $manager;
    }

    private static function request(Session $session, bool $withCookie = false): Request
    {
        $request = new Request();
        $request->setSession($session);

        if ($withCookie) {
            $request->cookies->set($session->getName(), 'the-session-of-the-client');
        }

        return $request;
    }
}
