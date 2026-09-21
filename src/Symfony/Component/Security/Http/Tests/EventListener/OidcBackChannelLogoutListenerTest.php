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
use Symfony\Component\Security\Http\Oidc\OidcEndedSessions;

class OidcBackChannelLogoutListenerTest extends TestCase
{
    public function testATokenWhoseProviderSessionEndedIsDeauthenticated()
    {
        $endedSessions = new OidcEndedSessions(new ArrayAdapter(), 'main');
        $endedSessions->record('session-42');

        $event = $this->createEvent($this->createToken('session-42'));

        (new OidcBackChannelLogoutListener($endedSessions))($event);

        $this->assertTrue($event->isUserChanged());
        $this->assertSame('The OIDC provider ended the session this login belongs to.', $event->getException()?->getMessage());
    }

    public function testATokenWhoseProviderSessionIsStillOpenIsLeftAlone()
    {
        $endedSessions = new OidcEndedSessions(new ArrayAdapter(), 'main');
        $endedSessions->record('session-43');

        $event = $this->createEvent($this->createToken('session-42'));

        (new OidcBackChannelLogoutListener($endedSessions))($event);

        $this->assertFalse($event->isUserChanged());
        $this->assertNull($event->getException());
    }

    /**
     * A login made through another authenticator, or by a provider issuing no "sid", names no
     * provider session: this runs on every request of a stateful firewall, so it costs nothing
     * rather than a lookup on an identifier that can match nothing.
     */
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

            (new OidcBackChannelLogoutListener(new OidcEndedSessions($cache, 'main')))($event);

            $this->assertFalse($event->isUserChanged(), $case);
        }
    }

    /**
     * A previous listener, or the built-in comparison, may already have decided: what this one
     * does not recognise, it does not undo.
     */
    public function testAVerdictThisListenerDoesNotShareIsLeftUntouched()
    {
        $endedSessions = new OidcEndedSessions(new ArrayAdapter(), 'main');

        $event = $this->createEvent($this->createToken('session-42'), true);

        (new OidcBackChannelLogoutListener($endedSessions))($event);

        $this->assertTrue($event->isUserChanged());
    }

    private function createToken(?string $sid): TokenInterface
    {
        $token = new UsernamePasswordToken(new InMemoryUser('bob', null), 'main');
        $token->setAttribute('oidc_sid', $sid);

        return $token;
    }

    private function createEvent(TokenInterface $token, bool $userChanged = false): CheckRefreshedUserEvent
    {
        $user = new InMemoryUser('bob', null);

        return new CheckRefreshedUserEvent($token, $user, $user, $userChanged);
    }
}
