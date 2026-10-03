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
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Event\CheckRefreshedUserEvent;
use Symfony\Component\Security\Http\EventListener\OidcBackChannelLogoutListener;
use Symfony\Component\Security\Http\Exception\OidcSessionEndedException;
use Symfony\Component\Security\Http\Oidc\OidcEndedSessions;

class OidcBackChannelLogoutListenerTest extends TestCase
{
    public function testATokenWhoseProviderSessionEndedIsDeauthenticated()
    {
        $endedSessions = new OidcEndedSessions(new ArrayAdapter(), 'main', 86400);
        $endedSessions->record('session-42');

        $event = $this->createEvent($this->createToken('session-42'));

        (new OidcBackChannelLogoutListener($endedSessions, 'main'))($event);

        $this->assertTrue($event->isUserChanged());
        $this->assertInstanceOf(OidcSessionEndedException::class, $event->getException(), 'an application tells this deauthentication from any other by its class');
        $this->assertSame('The OIDC provider ended the session this login belongs to.', $event->getException()->getMessageKey());
    }

    public function testATokenWhoseProviderSessionIsStillOpenIsLeftAlone()
    {
        $endedSessions = new OidcEndedSessions(new ArrayAdapter(), 'main', 86400);
        $endedSessions->record('session-43');

        $event = $this->createEvent($this->createToken('session-42'));

        (new OidcBackChannelLogoutListener($endedSessions, 'main'))($event);

        $this->assertFalse($event->isUserChanged());
        $this->assertNull($event->getException());
    }

    public function testATokenNamingNoProviderSessionCostsNoLookup()
    {
        $tokens = [
            'no attribute at all' => new UsernamePasswordToken(new InMemoryUser('bob', null), 'main'),
            'a provider issuing no "sid"' => $this->createToken(null),
            'an empty "sid"' => $this->createToken(''),
        ];

        foreach ($tokens as $case => $token) {
            $cache = $this->createMock(CacheItemPoolInterface::class);
            $cache->expects($this->never())->method('hasItem');

            $event = $this->createEvent($token);

            (new OidcBackChannelLogoutListener(new OidcEndedSessions($cache, 'main', 86400), 'main'))($event);

            $this->assertFalse($event->isUserChanged(), $case);
        }
    }

    /**
     * The listener runs on every firewall, so the one of a firewall only answers for its own.
     *
     * Two providers are free to mint the same opaque "sid", which without this would have the
     * logout of one end the logins of the other.
     */
    public function testATokenMintedOnAnotherFirewallIsLeftAloneAndCostsNoLookup()
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->never())->method('hasItem');

        $event = $this->createEvent($this->createToken('session-42', 'admin'));

        (new OidcBackChannelLogoutListener(new OidcEndedSessions($cache, 'main', 86400), 'main'))($event);

        $this->assertFalse($event->isUserChanged());
        $this->assertNull($event->getException());
    }

    public function testTheSameSessionEndedOnItsOwnFirewallIsRefused()
    {
        $endedSessions = new OidcEndedSessions(new ArrayAdapter(), 'main', 86400);
        $endedSessions->record('session-42');

        $event = $this->createEvent($this->createToken('session-42', 'main'));

        (new OidcBackChannelLogoutListener($endedSessions, 'main'))($event);

        $this->assertTrue($event->isUserChanged(), 'the very same sid, on the firewall that recorded its end');
    }

    public function testAVerdictThisListenerDoesNotShareIsLeftUntouched()
    {
        $endedSessions = new OidcEndedSessions(new ArrayAdapter(), 'main', 86400);

        $event = $this->createEvent($this->createToken('session-42'), true);

        (new OidcBackChannelLogoutListener($endedSessions, 'main'))($event);

        $this->assertTrue($event->isUserChanged());
    }

    private function createToken(?string $sid, string $firewallName = 'main'): TokenInterface
    {
        $token = new UsernamePasswordToken(new InMemoryUser('bob', null), $firewallName);
        $token->setAttribute('oidc_sid', $sid);
        $token->setAttribute('oidc_firewall', $firewallName);

        return $token;
    }

    private function createEvent(TokenInterface $token, bool $userChanged = false): CheckRefreshedUserEvent
    {
        $user = new InMemoryUser('bob', null);

        return new CheckRefreshedUserEvent($token, $user, $user, $userChanged);
    }
}
