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
use Symfony\Component\KeyManagement\Exception\RuntimeException;
use Symfony\Component\KeyManagement\Test\InMemoryKms;

/**
 * Backend whose master key derives, so that it refuses to work without a context.
 *
 * This is what a HashiCorp Vault Transit key created with `derived=true` does: every operation on it
 * carries a `context`, and one that carries none is answered with `missing 'context' for key
 * derivation`. Such a key cannot back a data key store that wraps under no authenticated data at
 * all, which is the case this fixture is here to exercise.
 *
 * What it wraps under is {@see InMemoryKms}, so the context is authenticated the way a real backend
 * authenticates it: unwrapping under another one fails.
 */
final class DerivedKeyKms implements DataKeyGeneratorInterface
{
    private readonly InMemoryKms $kms;

    public function __construct()
    {
        $this->kms = new InMemoryKms('derived');
    }

    public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
    {
        return $this->kms->generateDataKey($keyId, $length, self::derive($aad));
    }

    public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
    {
        return $this->kms->unwrapDataKey($wrapped, self::derive($aad));
    }

    private static function derive(string $aad): string
    {
        if ('' === $aad) {
            throw new RuntimeException("missing 'context' for key derivation");
        }

        return $aad;
    }
}
