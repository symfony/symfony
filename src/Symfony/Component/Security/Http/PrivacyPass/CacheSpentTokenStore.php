<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\PrivacyPass;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Keeps the spent tokens in a cache pool.
 *
 * Two requests redeeming the same token at the same time race for the same entry, which a pool cannot settle on its own: an origin that cannot afford a replay at all has to back the store with a lock or with a unique index instead.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class CacheSpentTokenStore implements SpentTokenStoreInterface
{
    /**
     * @param int $lifetime How long a spent token is remembered, in seconds, which must cover the lifetime of the challenges the origin accepts
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly int $lifetime = 7200,
    ) {
        if (1 > $lifetime) {
            throw new \InvalidArgumentException(\sprintf('The lifetime of a spent token must be at least 1 second, "%d" given.', $lifetime));
        }
    }

    public function spend(PrivateToken $token): bool
    {
        $firstTime = false;
        $lifetime = $this->lifetime;

        $this->cache->get('privacy-pass.'.hash('sha256', $token->getAuthenticatorInput()), static function (ItemInterface $item) use (&$firstTime, $lifetime): bool {
            $item->expiresAfter($lifetime);
            $firstTime = true;

            return true;
        });

        return $firstTime;
    }
}
