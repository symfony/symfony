<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Tests\Fixtures;

use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\DataKey;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;

/**
 * Counts the round trips a backend is actually asked for.
 *
 * A data key is unwrapped in order to be used many times, so what says whether an opening was
 * shared or repeated is the number of calls, and nothing a tag or a ciphertext looks like.
 */
final class CountingKms implements DataKeyGeneratorInterface
{
    public int $generated = 0;

    public int $unwrapped = 0;

    public function __construct(
        private readonly DataKeyGeneratorInterface $inner,
    ) {
    }

    public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
    {
        ++$this->generated;

        return $this->inner->generateDataKey($keyId, $length, $aad);
    }

    public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
    {
        ++$this->unwrapped;

        return $this->inner->unwrapDataKey($wrapped, $aad);
    }
}
