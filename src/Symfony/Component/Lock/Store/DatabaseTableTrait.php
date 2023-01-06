<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Store;

use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\InvalidTtlException;
use Symfony\Component\Lock\Key;

/**
 * @internal
 */
trait DatabaseTableTrait
{
    private string $table = 'lock_keys';
    private string $idCol = 'key_id';
    private string $tokenCol = 'key_token';
    private string $expirationCol = 'key_expiration';
    private float $gcProbability;
    private int $initialTtl;

    private function init(array $options, float $gcProbability, int $initialTtl): void
    {
        if ($gcProbability < 0 || $gcProbability > 1) {
            throw new InvalidArgumentException(sprintf('"%s" requires gcProbability between 0 and 1, "%f" given.', __METHOD__, $gcProbability));
        }
        if ($initialTtl < 1) {
            throw new InvalidTtlException(sprintf('"%s()" expects a strictly positive TTL, "%d" given.', __METHOD__, $initialTtl));
        }

        $this->table = $options['db_table'] ?? $this->table;
        $this->idCol = $options['db_id_col'] ?? $this->idCol;
        $this->tokenCol = $options['db_token_col'] ?? $this->tokenCol;
        $this->expirationCol = $options['db_expiration_col'] ?? $this->expirationCol;

        $this->gcProbability = $gcProbability;
        $this->initialTtl = $initialTtl;
    }

    /**
     * Returns a hashed version of the key.
     */
    private function getHashedKey(Key $key): string
    {
        return hash('sha256', (string) $key);
    }

    private function getUniqueToken(Key $key): string
    {
        if (!$key->hasState(__CLASS__)) {
            $token = base64_encode(random_bytes(32));
            $key->setState(__CLASS__, $token);
        }

        return $key->getState(__CLASS__);
    }

    /**
     * Prune the table randomly, based on GC probability.
     */
    private function randomlyPrune(): void
    {
        if ($this->gcProbability > 0 && (1.0 === $this->gcProbability || (random_int(0, \PHP_INT_MAX) / \PHP_INT_MAX) <= $this->gcProbability)) {
            $this->prune();
        }
    }
}
