<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication\RememberMe;

use Psr\Cache\CacheItemPoolInterface;

/**
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class CacheTokenVerifier implements TokenVerifierInterface
{
    private $cache;
    private $outdatedTokenTtl;
    private $cacheKeyPrefix;

    /**
     * @param int $outdatedTokenTtl How long the outdated token should still be considered valid. Defaults
     *                              to 60, which matches how often the PersistentRememberMeHandler will at
     *                              most refresh tokens. Increasing to more than that is not recommended,
     *                              but you may use a lower value.
     */
    public function __construct(CacheItemPoolInterface $cache, int $outdatedTokenTtl = 60, string $cacheKeyPrefix = 'rememberme-stale-')
    {
        $this->cache = $cache;
        $this->outdatedTokenTtl = $outdatedTokenTtl;
    }

    /**
     * {@inheritdoc}
     */
    public function verifyToken(PersistentTokenInterface $token, string $tokenValue): bool
    {
        if (hash_equals($token->getTokenValue(), $tokenValue)) {
            return true;
        }

        if (!$this->cache->hasItem($this->cacheKeyPrefix.$token->getSeries())) {
            return false;
        }

        $item = $this->cache->getItem($this->cacheKeyPrefix.$token->getSeries());
        $outdatedToken = $item->get();

        return hash_equals($outdatedToken, $tokenValue);
    }

    /**
     * {@inheritdoc}
     */
    public function updateExistingToken(PersistentTokenInterface $token, string $tokenValue, \DateTimeInterface $lastUsed): void
    {
        // When a token gets updated, persist the outdated token for $outdatedTokenTtl seconds so we can
        // still accept it as valid in verifyToken
        $item = $this->cache->getItem($this->cacheKeyPrefix.$token->getSeries());
        $item->set($token->getTokenValue());
        $item->expiresAfter($this->outdatedTokenTtl);
        $this->cache->save($item);
    }
}
