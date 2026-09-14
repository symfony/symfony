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
use Symfony\Component\KeyManagement\Test\InMemoryKms;

/**
 * Backend that ignores the data key length it is asked for and hands out keys of its own length,
 * both when generating and when unwrapping, the way a misconfigured or buggy backend would.
 */
final class WrongLengthDataKeyKms implements DataKeyGeneratorInterface
{
    private readonly InMemoryKms $kms;

    public function __construct(private readonly int $length)
    {
        $this->kms = new InMemoryKms();
    }

    public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
    {
        return $this->kms->generateDataKey($keyId, $this->length, $aad);
    }

    public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
    {
        $key = $this->kms->unwrapDataKey($wrapped, $aad)->use(static fn (string $key): string => $key);

        return new DataKey(str_pad(substr($key, 0, $this->length), $this->length, "\0"), $wrapped);
    }
}
