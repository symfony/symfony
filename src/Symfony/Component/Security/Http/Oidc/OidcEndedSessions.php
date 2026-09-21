<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Oidc;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Remembers the OIDC provider sessions a back-channel logout ended.
 *
 * A logout token names the provider session by its "sid" claim, and arrives outside of any
 * request of the end-user: nothing of that browser is at hand, not even its session cookie,
 * so the end is written down here and acted on when that browser comes back, by
 * {@see \Symfony\Component\Security\Http\EventListener\OidcBackChannelLogoutListener}.
 *
 * The pool has to be shared by every server of the application, as the logout token reaches
 * any one of them, and the session it names was opened by another: an adapter local to one
 * process, such as APCu or the array one, loses what the others recorded.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class OidcEndedSessions
{
    /**
     * @param string $namespace The firewall the sessions belong to, so that two firewalls of one
     *                          application, with two providers, never read each other's entries
     */
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly string $namespace,
    ) {
    }

    /**
     * Records that the given provider session has ended.
     *
     * The entry is kept for the lifetime PHP gives a session ("session.gc_maxlifetime"): a
     * session that can no longer be restored can no longer be presented either, so nothing
     * is gained by remembering its end any longer.
     *
     * Recording the same end twice is what it already says, which is what makes replaying a
     * logout token harmless.
     *
     * @param string $sid The "sid" claim of the logout token, naming the session at the provider
     */
    public function record(string $sid): void
    {
        $item = $this->cache->getItem($this->key($sid));
        $item->set(true);
        $item->expiresAfter((int) \ini_get('session.gc_maxlifetime') ?: 1440);

        $this->cache->save($item);
    }

    /**
     * Whether the given provider session is one a back-channel logout ended.
     */
    public function has(string $sid): bool
    {
        return $this->cache->hasItem($this->key($sid));
    }

    private function key(string $sid): string
    {
        // a "sid" is opaque and PSR-6 reserves characters: hashed rather than escaped,
        // which also keeps it out of the cache backend
        return 'oidc_ended_session.'.$this->namespace.'.'.hash('xxh128', $sid);
    }
}
