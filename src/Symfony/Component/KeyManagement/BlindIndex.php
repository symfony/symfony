<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement;

use Symfony\Component\KeyManagement\BlindIndex\AbstractBlindIndex;
use Symfony\Component\KeyManagement\BlindIndex\AlgorithmInterface;
use Symfony\Component\KeyManagement\BlindIndex\ProjectionInterface;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;

/**
 * A {@see BlindIndexInterface} over a wrapped data key the KMS unwraps.
 *
 * Mint the key once and keep the wrapped form in the configuration:
 *
 *     bin/console key-management:generate-data-key alias/app-key
 *
 * It is unwrapped once, so the plaintext never leaves memory and the KMS is not reached again
 * whatever the number of values indexed. Once per index, though: two indexes over one key each open
 * it, which is what {@see StoredKeyBlindIndex} is for.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class BlindIndex extends AbstractBlindIndex
{
    /**
     * @param Ciphertext $wrappedKey Wrapped data key the tags are derived under, dedicated to this index and never rotated
     */
    public function __construct(
        private readonly DataKeyGeneratorInterface $kms,
        private readonly Ciphertext $wrappedKey,
        ProjectionInterface $projection,
        ?AlgorithmInterface $algorithm = null,
    ) {
        parent::__construct($projection, $algorithm);
    }

    /**
     * @throws DecryptionFailedException If the index key cannot be unwrapped
     */
    protected function open(): DataKeyHandle
    {
        return new DataKeyHandle('blind-index', $this->kms->unwrapDataKey($this->wrappedKey));
    }
}
