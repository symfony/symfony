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
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\EventListener\AuthenticationTimeListener;
use Symfony\Component\Security\Http\SecurityEvents;

class AuthenticationTimeListenerTest extends TestCase
{
    public function testItUsesTheInjectedClock()
    {
        $clock = new MockClock('2026-09-11 12:00:00');
        $token = $this->createToken();

        (new AuthenticationTimeListener($clock))->onInteractiveLogin(new InteractiveLoginEvent(new Request(), $token));

        $this->assertSame($clock->now()->getTimestamp(), $token->getAttribute(AuthenticatedVoter::AUTH_TIME_ATTRIBUTE));
    }

    public function testItFallsBackToTheSystemTimeWithoutAClock()
    {
        $token = $this->createToken();
        $this->assertFalse($token->hasAttribute(AuthenticatedVoter::AUTH_TIME_ATTRIBUTE));

        $before = time();
        (new AuthenticationTimeListener())->onInteractiveLogin(new InteractiveLoginEvent(new Request(), $token));
        $after = time();

        $authTime = $token->getAttribute(AuthenticatedVoter::AUTH_TIME_ATTRIBUTE);
        $this->assertGreaterThanOrEqual($before, $authTime);
        $this->assertLessThanOrEqual($after, $authTime);
    }

    public function testTheRecordedTimeSurvivesSerialization()
    {
        $token = $this->createToken();
        (new AuthenticationTimeListener())->onInteractiveLogin(new InteractiveLoginEvent(new Request(), $token));

        $restored = unserialize(serialize($token));

        $this->assertSame(
            $token->getAttribute(AuthenticatedVoter::AUTH_TIME_ATTRIBUTE),
            $restored->getAttribute(AuthenticatedVoter::AUTH_TIME_ATTRIBUTE)
        );
    }

    public function testItSubscribesToInteractiveLogin()
    {
        $this->assertSame([SecurityEvents::INTERACTIVE_LOGIN => ['onInteractiveLogin', 256]], AuthenticationTimeListener::getSubscribedEvents());
    }

    private function createToken(): UsernamePasswordToken
    {
        return new UsernamePasswordToken(new InMemoryUser('wouter', 'password', ['ROLE_USER']), 'main', ['ROLE_USER']);
    }
}
