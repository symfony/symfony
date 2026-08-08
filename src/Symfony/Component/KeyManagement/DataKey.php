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

use Symfony\Component\KeyManagement\Exception\LogicException;

/**
 * Result of a `generateDataKey()` call: a freshly generated symmetric data key
 * (DEK) returned both in plaintext form (for local use) and in KMS-wrapped
 * form (for persistence).
 *
 * The plaintext is not a field at all, so that no dump of this object can print
 * it, see {@see KeyMaterial}. Access is mediated by {@see use()}, which passes
 * it to a caller-provided closure and releases the reference as soon as the
 * closure returns (or throws). A destructor releases it as a safety net in case
 * `use()` is never called.
 *
 * Notes on memory wiping:
 *   - When `ext-sodium` is available, {@see sodium_memzero()} clears the
 *     underlying buffer before the reference is dropped, but only while
 *     nothing else holds it: PHP declines to zero a string another variable
 *     shares rather than blank it under that other holder. Without sodium
 *     there is no in-place zeroing primitive at all, so the holder is only
 *     dropped and the engine releases the buffer when it gets to it.
 *   - PHP strings are reference-counted with copy-on-write, so a consumer
 *     that returns the plaintext, or keeps it anywhere, leaves the wipe with
 *     nothing to wipe. Pass the parameter straight to the crypto primitives
 *     and let it go. A consumer that genuinely has to outlive the DataKey
 *     must take a buffer of its own first, by writing to an offset, which is
 *     what {@see DataKeyHandle} does.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class DataKey
{
    use KeyMaterial;

    public function __construct(
        #[\SensitiveParameter] string $plaintext,
        public readonly Ciphertext $wrapped,
    ) {
        $this->keepMaterial($plaintext);
    }

    /**
     * Passes the plaintext data key to `$consumer`, then drops the reference.
     * The plaintext is released on both success and exception paths.
     * Subsequent calls throw {@see LogicException}.
     *
     * @template T
     *
     * @param \Closure(string): T $consumer
     *
     * @return T
     */
    public function use(\Closure $consumer): mixed
    {
        if (!$this->hasMaterial()) {
            throw new LogicException('The data key plaintext has already been consumed.');
        }

        try {
            return $consumer($this->material());
        } finally {
            $this->wipeMaterial();
        }
    }

    public function isConsumed(): bool
    {
        return !$this->hasMaterial();
    }

    public function __destruct()
    {
        $this->wipeMaterial();
    }
}
