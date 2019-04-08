<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Encoder;

/**
 * @internal
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
trait Argon2Trait
{
    private $memoryCost;
    private $timeCost;
    private $threads;

    public function __construct(int $memoryCost = null, int $timeCost = null, int $threads = null)
    {
        $this->memoryCost = $memoryCost;
        $this->timeCost = $timeCost;
        $this->threads = $threads;
    }

    private function encodePasswordNative(string $raw, int $algorithm)
    {
        return password_hash($raw, $algorithm, [
            'memory_cost' => $this->memoryCost ?? \PASSWORD_ARGON2_DEFAULT_MEMORY_COST,
            'time_cost' => $this->timeCost ?? \PASSWORD_ARGON2_DEFAULT_TIME_COST,
            'threads' => $this->threads ?? \PASSWORD_ARGON2_DEFAULT_THREADS,
        ]);
    }

    private function encodePasswordSodiumFunction(string $raw)
    {
        $hash = \sodium_crypto_pwhash_str(
            $raw,
            \SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            \SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE
        );
        \sodium_memzero($raw);

        return $hash;
    }
}
