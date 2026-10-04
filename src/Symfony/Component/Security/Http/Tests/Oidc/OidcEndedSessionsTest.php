<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Oidc;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Security\Http\Oidc\OidcEndedSessions;

class OidcEndedSessionsTest extends TestCase
{
    public function testASessionIsOnlyEndedOnceItHasBeenRecorded()
    {
        $endedSessions = new OidcEndedSessions(new ArrayAdapter(), 'main', 86400);

        $this->assertFalse($endedSessions->has('session-42'));

        $endedSessions->record('session-42');

        $this->assertTrue($endedSessions->has('session-42'));
        $this->assertFalse($endedSessions->has('session-43'));
    }

    public function testRecordingTheSameEndTwiceSaysWhatItAlreadySaid()
    {
        $endedSessions = new OidcEndedSessions(new ArrayAdapter(), 'main', 86400);

        $endedSessions->record('session-42');
        $endedSessions->record('session-42');

        $this->assertTrue($endedSessions->has('session-42'));
    }

    public function testTwoFirewallsDoNotReadEachOthersEntries()
    {
        $cache = new ArrayAdapter();

        (new OidcEndedSessions($cache, 'main', 86400))->record('session-42');

        $this->assertFalse((new OidcEndedSessions($cache, 'admin', 86400))->has('session-42'));
    }

    public function testAnEntryIsKeptForTheLifetimeItWasGiven()
    {
        // never the value of "session.gc_maxlifetime", so that reading it instead cannot pass
        $lifetime = ((int) \ini_get('session.gc_maxlifetime') ?: 1440) + 3600;

        $item = $this->createMock(CacheItemInterface::class);
        $item->expects($this->once())->method('expiresAfter')->with($lifetime);

        $cache = $this->createStub(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($item);

        (new OidcEndedSessions($cache, 'main', $lifetime))->record('session-42');
    }

    public function testASidIsNeverHandedToTheCacheBackendAsItIs()
    {
        $cache = new ArrayAdapter();

        (new OidcEndedSessions($cache, 'main', 86400))->record('a sid {with} reserved/characters');

        $this->assertTrue((new OidcEndedSessions($cache, 'main', 86400))->has('a sid {with} reserved/characters'));
        $this->assertStringNotContainsString('reserved/characters', implode(' ', array_keys($cache->getValues())));
    }
}
